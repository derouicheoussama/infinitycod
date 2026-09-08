/**
 * Configure le secret INFINITYCOD_SIGNING_KEY du dépôt privé (une seule fois).
 * Ce secret permet à la CI de signer le manifest update.json (Ed25519).
 * La clé privée est lue depuis .tools/signing-private.key — jamais affichée.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const PRIVATE_REPO = 'derouicheoussama/infinitycod';
const SECRET_NAME = 'INFINITYCOD_SIGNING_KEY';

const keyPath = path.join(__dirname, '..', '.tools', 'signing-private.key');
if (!fs.existsSync(keyPath)) {
  console.error('✗ ' + keyPath + ' introuvable.');
  process.exit(1);
}
const value = fs.readFileSync(keyPath, 'utf8').trim();

const cred = execFileSync('git', ['credential', 'fill'], {
  input: 'protocol=https\nhost=github.com\n\n',
}).toString();
const token = (cred.match(/^password=(.*)$/m) || [])[1];
if (!token) { console.error('✗ Identifiants GitHub introuvables.'); process.exit(1); }

const gh = (opts) => JSON.parse(execFileSync('curl', ['-s', ...opts]).toString());

const keyInfo = gh([
  '-H', `Authorization: Bearer ${token}`,
  `https://api.github.com/repos/${PRIVATE_REPO}/actions/secrets/public-key`,
]);
if (!keyInfo.key) { console.error('✗ Clé publique : ' + JSON.stringify(keyInfo).slice(0, 200)); process.exit(1); }

(async () => {
  const sodium = require('libsodium-wrappers');
  await sodium.ready;

  const encrypted = sodium.crypto_box_seal(
    sodium.from_string(value),
    sodium.from_base64(keyInfo.key, sodium.base64_variants.ORIGINAL)
  );
  const b64 = sodium.to_base64(encrypted, sodium.base64_variants.ORIGINAL);

  const bodyPath = path.join(__dirname, '..', '.tools', 'secret-body.json');
  fs.writeFileSync(bodyPath, JSON.stringify({ encrypted_value: b64, key_id: keyInfo.key_id }));

  const code = execFileSync('curl', [
    '-s', '-X', 'PUT',
    '-H', `Authorization: Bearer ${token}`,
    '-H', 'Content-Type: application/json',
    `https://api.github.com/repos/${PRIVATE_REPO}/actions/secrets/${SECRET_NAME}`,
    '--data-binary', '@' + bodyPath,
    '-o', path.join(__dirname, '..', '.tools', 'secret-out.txt'),
    '-w', '%{http_code}',
  ]).toString();

  fs.unlinkSync(bodyPath);
  fs.unlinkSync(path.join(__dirname, '..', '.tools', 'secret-out.txt'));

  if (code === '201' || code === '204') {
    console.log(`✓ Secret ${SECRET_NAME} configuré sur ${PRIVATE_REPO} (HTTP ${code}).`);
    console.log('  La CI signera désormais le manifest update.json (Ed25519).');
  } else {
    console.error('✗ HTTP ' + code);
    process.exit(1);
  }
})();
