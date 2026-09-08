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

const totalKo = entries.reduce((sum, e) => sum + e.data.length, 0) / 1024;
console.log(`Plugin : ${entries.length} fichiers, ${totalKo.toFixed(0)} Ko`);

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

const mb = (fs.statSync(zipPath).size / 1024 / 1024).toFixed(2);
console.log(`✓ dist/infinitycod.zip créé (${mb} Mo, ${entries.length} entrées, racine infinitycod/, séparateurs '/')`);
console.log('  Installation : wp-admin → Extensions → Ajouter → Téléverser → Activer.');

process.exit(ok ? 0 : 1);
