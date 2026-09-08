/**
 * Configure le secret RELEASES_TOKEN du dépôt privé (une seule fois).
 *
 * Ce token permet à la CI de publier le zip sur le dépôt PUBLIC des
 * releases — c'est ce qui rend les mises à jour 100 % automatiques
 * (un simple `git push --tags` publie partout).
 *
 * La valeur est lue dans la variable d'environnement INFINITYCOD_SECRET_VALUE
 * (jamais écrite dans un fichier ni affichée).
 *
 * Usage : INFINITYCOD_SECRET_VALUE=<token> node tools/set-releases-secret.js
 */
'use strict';

const { execFileSync } = require('child_process');

const PRIVATE_REPO = 'derouicheoussama/infinitycod';
const SECRET_NAME = 'RELEASES_TOKEN';

const value = process.env.INFINITYCOD_SECRET_VALUE;
if (!value) {
  console.error('✗ Définissez INFINITYCOD_SECRET_VALUE (token GitHub avec scope repo).');
  process.exit(1);
}

// Token depuis Git Credential Manager (pour l'API d'administration).
const cred = execFileSync('git', ['credential', 'fill'], {
  input: 'protocol=https\nhost=github.com\n\n',
}).toString();
const token = (cred.match(/^password=(.*)$/m) || [])[1];
if (!token) {
  console.error('✗ Identifiants GitHub introuvables.');
  process.exit(1);
}

const gh = (opts) => JSON.parse(execFileSync('curl', ['-s', ...opts]).toString());

// 1. Clé publique de chiffrement du dépôt.
const keyInfo = gh([
  '-H', `Authorization: Bearer ${token}`,
  `https://api.github.com/repos/${PRIVATE_REPO}/actions/secrets/public-key`,
]);
if (!keyInfo.key) {
  console.error('✗ Clé publique : ' + JSON.stringify(keyInfo).slice(0, 200));
  process.exit(1);
}

// 2. Chiffrement sealed box (libsodium).
(async () => {
  const sodium = require('libsodium-wrappers');
  await sodium.ready;

  const binkey = sodium.from_base64(keyInfo.key, sodium.base64_variants.ORIGINAL);
  const binsecret = sodium.from_string(value);
  const encrypted = sodium.crypto_box_seal(binsecret, binkey);
  const b64 = sodium.to_base64(encrypted, sodium.base64_variants.ORIGINAL);

  // 3. Envoi du secret.
  const bodyPath = require('path').join(__dirname, '..', 'dist', 'secret-body.json');
  require('fs').writeFileSync(bodyPath, JSON.stringify({
    encrypted_value: b64,
    key_id: keyInfo.key_id,
  }));

  const out = execFileSync('curl', [
    '-s',
    '-X', 'PUT',
    '-H', `Authorization: Bearer ${token}`,
    '-H', 'Accept: application/vnd.github+json',
    `-H`, 'Content-Type: application/json',
    `https://api.github.com/repos/${PRIVATE_REPO}/actions/secrets/${SECRET_NAME}`,
    '--data-binary', '@' + bodyPath,
    '-o', require('path').join(__dirname, '..', 'dist', 'secret-out.txt'),
    '-w', '%{http_code}',
  ]).toString();

  require('fs').unlinkSync(bodyPath);
  require('fs').unlinkSync(require('path').join(__dirname, '..', 'dist', 'secret-out.txt'));

  if (out === '201' || out === '204') {
    console.log(`✓ Secret ${SECRET_NAME} configuré sur ${PRIVATE_REPO} (HTTP ${out}).`);
    console.log('  La CI publiera désormais le zip sur le dépôt public à chaque tag.');
  } else {
    console.error('✗ HTTP ' + out);
    process.exit(1);
  }
})();
