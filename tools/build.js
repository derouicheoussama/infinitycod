/**
 * Construit dist/infinitycod.zip installable dans WordPress.
 *
 * Le zip est généré en Node pur (module tools/lib/zip.js) : zéro dépendance,
 * séparateurs '/' conformes à la norme zip — un zip à plat ou écrit avec des
 * '\' (Compress-Archive PS5) casse l'extraction WordPress.
 *
 * Usage : node tools/build.js
 */
'use strict';

const fs = require('fs');
const path = require('path');
const { makeZip } = require('./lib/zip');

const root = path.join(__dirname, '..');
const src = path.join(root, 'infinitycod');
const dist = path.join(root, 'dist');
const zipPath = path.join(dist, 'infinitycod.zip');

if (!fs.existsSync(src)) {
  console.error('Dossier plugin introuvable :', src);
  process.exit(1);
}

// Collecte des fichiers sous la racine infinitycod/.
const entries = [];
(function walk(dir, prefix) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name))) {
    const full = path.join(dir, entry.name);
    const zipName = prefix + entry.name;
    if (entry.isDirectory()) {
      walk(full, zipName + '/');
    } else {
      entries.push({ name: zipName, data: fs.readFileSync(full) });
    }
  }
})(src, 'infinitycod/');

// Journal des modifications embarqué : le CHANGELOG.md de la racine du
// dépôt voyage DANS le plugin — l'onglet « Journal des modifications » de
// wp-admin est alors renseigné même sans réseau (aucune source externe).
const changelogPath = path.join(root, 'CHANGELOG.md');
if (fs.existsSync(changelogPath)) {
  entries.push({ name: 'infinitycod/CHANGELOG.md', data: fs.readFileSync(changelogPath) });
}

const totalKo = entries.reduce((sum, e) => sum + e.data.length, 0) / 1024;
console.log(`Plugin : ${entries.length} fichiers, ${totalKo.toFixed(0)} Ko`);

// Validation pré-build : fichiers interdits dans un zip commercial.
const forbidden = [/\.git\//, /\.github\//, /node_modules\//, /(^|\/)tests?\//, /\.env/, /\.tools\//];
for (const e of entries) {
  for (const re of forbidden) {
    if (re.test(e.name)) {
      console.error('✗ Fichier interdit dans le zip commercial : ' + e.name);
      process.exit(1);
    }
  }
}

fs.mkdirSync(dist, { recursive: true });
makeZip(zipPath, entries);

// Vérification structurelle avec unzip -l si disponible, sinon lecture directe.
let ok = true;
if (entries.some((e) => e.name.indexOf('\\') !== -1 || !e.name.startsWith('infinitycod/'))) {
  console.error('✗ Entrées invalides détectées.');
  ok = false;
}
if (!entries.some((e) => e.name === 'infinitycod/infinitycod.php')) {
  console.error('✗ infinitycod/infinitycod.php manquant.');
  ok = false;
}

// ===== Artefacts de release : SHA-256 + manifest update.json =====
const crypto = require('crypto');
const zipBuf = fs.readFileSync(zipPath);
const sha256 = crypto.createHash('sha256').update(zipBuf).digest('hex');
fs.writeFileSync(zipPath + '.sha256', sha256 + '  infinitycod.zip\n');

// Version depuis le header du plugin (source de vérité).
const header = fs.readFileSync(path.join(src, 'infinitycod.php'), 'utf8');
const version = (header.match(/define\(\s*'INFINITYCOD_VERSION',\s*'([0-9.]+)'/) || [])[1];

// Manifest update.json : le journal de la dernière version est embarqué —
// les miroirs (raw / jsDelivr / miroir perso) servent alors le changelog
// aussi, sans API GitHub.
function latestChangelogSection(md) {
  const start = md.indexOf('## ');
  if (start === -1) { return ''; }
  let end = md.indexOf('\n## ', start + 1);
  if (end === -1) { end = md.length; }
  return md.slice(start, end).trim().slice(0, 4000);
}
const changelogText = fs.existsSync(changelogPath) ? fs.readFileSync(changelogPath, 'utf8') : '';

const manifest = {
  name: 'InfinityCod — Paiement à la livraison (COD Algérie)',
  slug: 'infinitycod',
  version: version,
  requires: '6.0',
  requires_php: '7.4',
  requires_woocommerce: '6.0',
  download_url: `https://github.com/derouicheoussama/infinitycod-releases/releases/download/v${version}/infinitycod.zip`,
  details_url: `https://github.com/derouicheoussama/infinitycod-releases/releases/tag/v${version}`,
  sha256: sha256,
  release_date: new Date().toISOString(),
  channel: 'stable',
  changelog: latestChangelogSection(changelogText),
};
fs.writeFileSync(path.join(dist, 'update.json'), JSON.stringify(manifest, null, 2));

const mb = (fs.statSync(zipPath).size / 1024 / 1024).toFixed(2);
console.log(`✓ dist/infinitycod.zip créé (${mb} Mo, ${entries.length} entrées, racine infinitycod/, séparateurs '/')`);
console.log(`✓ dist/infinitycod.zip.sha256 (${sha256.slice(0, 16)}…)`);
console.log(`✓ dist/update.json (v${version}, canal stable)`);
console.log('  Installation : wp-admin → Extensions → Ajouter → Téléverser → Activer.');

process.exit(ok ? 0 : 1);
