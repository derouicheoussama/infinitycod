/**
 * Publie la release courante sur le dépôt PUBLIC des releases
 * (derouicheoussama/infinitycod-releases) : c'est ce dépôt que les
 * boutiques clientes interrogent — sans aucun token.
 *
 * Rituel de publication complet :
 *   1. Version à jour dans infinitycod/infinitycod.php (2 endroits)
 *   2. git add -A && git commit -m "1.4.0 — …"
 *   3. git tag v1.4.0 && git push origin main --tags
 *   4. node tools/publish-releases.js      ← ce script
 *
 * Utilise les identifiants Git de la machine (Git Credential Manager).
 */
'use strict';

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const root = path.join(__dirname, '..');
const zipPath = path.join(root, 'dist', 'infinitycod.zip');
const RELEASES_REPO = 'derouicheoussama/infinitycod-releases';

// Version courante depuis l'en-tête du plugin.
const header = fs.readFileSync(path.join(root, 'infinitycod', 'infinitycod.php'), 'utf8');
const version = (header.match(/Version:\s*([\d.]+)/) || [])[1];
if (!version) {
  console.error('✗ Version introuvable dans infinitycod/infinitycod.php');
  process.exit(1);
}

// 1. Rebuild pour garantir un zip à jour.
execFileSync('node', [path.join(__dirname, 'build.js')], { stdio: 'inherit' });
if (!fs.existsSync(zipPath)) {
  console.error('✗ dist/infinitycod.zip absent après le build.');
  process.exit(1);
}

// 2. Token depuis Git Credential Manager (jamais affiché).
let token;
try {
  const cred = execFileSync('git', ['credential', 'fill'], {
    input: 'protocol=https\nhost=github.com\n\n',
  }).toString();
  token = (cred.match(/^password=(.*)$/m) || [])[1];
} catch (e) { /* géré plus bas */ }

if (!token) {
  console.error('✗ Identifiants GitHub introuvables (git credential fill).');
  process.exit(1);
}

const api = (opts) => JSON.parse(
  execFileSync('curl', ['-s', ...opts]).toString()
);

const tag = 'v' + version;

// 3. Release dans le dépôt public (crée le tag sur la branche par défaut).
console.log(`Publication de ${tag} sur ${RELEASES_REPO}…`);
const body = JSON.stringify({
  tag_name: tag,
  name: 'InfinityCod ' + version,
  body: `Mise à jour automatique WordPress : installez via Extensions → Mettre à jour.\n\nZip : \`infinitycod.zip\` (source des mises à jour des boutiques clientes).`,
  draft: false,
  prerelease: false,
});
const release = api([
  '-X', 'POST',
  '-H', `Authorization: Bearer ${token}`,
  '-H', 'Accept: application/vnd.github+json',
  'https://api.github.com/repos/' + RELEASES_REPO + '/releases',
  '--data-binary', '@-',
  body,
]);

if (!release.id) {
  if (release.errors || (release.message || '').includes('already_exists')) {
    console.log('ℹ La release ' + tag + ' existe déjà — upload du zip par-dessus.');
  } else {
    console.error('✗ Création release : ' + (release.message || JSON.stringify(release).slice(0, 200)));
    process.exit(1);
  }
}

const uploadUrl = (release.upload_url || `https://uploads.github.com/repos/${RELEASES_REPO}/releases/${release.id}/assets{?name,label}`).replace(/\{.*\}/, '');

// 4. Upload du zip.
const upload = execFileSync('curl', [
  '-s',
  '-X', 'POST',
  '-H', `Authorization: Bearer ${token}`,
  '-H', 'Content-Type: application/zip',
  '--data-binary', '@' + zipPath,
  uploadUrl + '?name=infinitycod.zip',
]).toString();

const asset = JSON.parse(upload);
if (asset.name === 'infinitycod.zip') {
  console.log(`✓ ${tag} publié : https://github.com/${RELEASES_REPO}/releases/tag/${tag}`);
  console.log('  Les boutiques clientes recevront la mise à jour (vérification horaire).');
} else {
  console.error('✗ Upload : ' + upload.slice(0, 200));
  process.exit(1);
}
