/**
 * Construit dist/infinitycod.zip installable dans WordPress.
 * Usage : node tools/build.js
 */
'use strict';

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const root = path.join(__dirname, '..');
const src = path.join(root, 'infinitycod');
const dist = path.join(root, 'dist');
const zipPath = path.join(dist, 'infinitycod.zip');

if (!fs.existsSync(src)) {
  console.error('Dossier plugin introuvable :', src);
  process.exit(1);
}

fs.mkdirSync(dist, { recursive: true });
if (fs.existsSync(zipPath)) fs.unlinkSync(zipPath);

// Exclusions : rien à exclure dans le plugin lui-même (les sources du dépôt
// restent hors du plugin), mais on vérifie les fichiers présents.
const files = [];
(function walk(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(full);
    else files.push(path.relative(src, full));
  }
})(src);

console.log(`Plugin : ${files.length} fichiers, ${(function () {
  let size = 0;
  for (const f of files) size += fs.statSync(path.join(src, f)).size;
  return (size / 1024 / 1024).toFixed(2);
})()} Mo`);

let zipMade = false;

// PowerShell natif (Windows) : Compress-Archive.
try {
  execFileSync('powershell.exe', [
    '-NoProfile', '-Command',
    `Compress-Archive -Path '${src}\\*' -DestinationPath '${zipPath}' -Force`
  ], { stdio: 'inherit' });
  zipMade = fs.existsSync(zipPath);
} catch (e) {
  console.error('Compress-Archive a échoué :', e.message);
}

// Fallback : commande zip (Git Bash / Linux).
if (!zipMade) {
  try {
    execFileSync('zip', ['-r', '-q', zipPath, '.'], { cwd: src, stdio: 'inherit' });
    zipMade = fs.existsSync(zipPath);
  } catch (e) {
    console.error('zip indisponible aussi — compressez manuellement le dossier infinitycod/.');
    process.exit(1);
  }
}

const mb = (fs.statSync(zipPath).size / 1024 / 1024).toFixed(2);
console.log(`✓ dist/infinitycod.zip créé (${mb} Mo)`);
console.log('  Installation : wp-admin → Extensions → Ajouter → Téléverser → Activer.');
