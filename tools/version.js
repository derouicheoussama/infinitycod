/**
 * Source unique de version — InfinityCod.
 *
 *   node tools/version.js            → affiche la version courante
 *   node tools/version.js 2.1.0      → met à jour header + constante + package.json
 *   node tools/version.js --check v2.1.0 → vérifie la cohérence avec un tag
 *
 * Source de vérité : l'en-tête "Version:" de infinitycod/infinitycod.php.
 */
'use strict';

const fs = require('fs');
const path = require('path');

const mainFile = path.join(__dirname, '..', 'infinitycod', 'infinitycod.php');
const pkgFile = path.join(__dirname, '..', 'package.json');

function readVersion() {
  const src = fs.readFileSync(mainFile, 'utf8');
  return (src.match(/define\(\s*'INFINITYCOD_VERSION',\s*'([0-9.]+)'/) || [])[1] || null;
}

function writeVersion(version) {
  if (!/^\d+\.\d+\.\d+$/.test(version)) {
    console.error('✗ Version invalide (attendu MAJOR.MINOR.PATCH) :', version);
    process.exit(1);
  }

  let src = fs.readFileSync(mainFile, 'utf8');
  if (!/Version:\s+[0-9.]+/.test(src) || !/define\(\s*'INFINITYCOD_VERSION'/.test(src)) {
    console.error('✗ En-tête ou constante introuvable.');
    process.exit(1);
  }
  src = src.replace(/(\* Version:\s+)[0-9.]+/, '$1' + version);
  src = src.replace(/(define\(\s*'INFINITYCOD_VERSION',\s*')[0-9.]+(')/, '$1' + version + '$2');
  fs.writeFileSync(mainFile, src);

  if (fs.existsSync(pkgFile)) {
    const pkg = JSON.parse(fs.readFileSync(pkgFile, 'utf8'));
    pkg.version = version;
    fs.writeFileSync(pkgFile, JSON.stringify(pkg, null, 2) + '\n');
  }

  console.log('✓ Version ' + version + ' appliquée (header + constante + package.json).');
}

const arg = process.argv[2];

if (!arg) {
  const v = readVersion();
  if (!v) { console.error('✗ Version introuvable.'); process.exit(1); }
  console.log(v);
  process.exit(0);
}

if (arg === '--check') {
  const tag = (process.argv[3] || '').replace(/^v/, '');
  const current = readVersion();
  if (!tag || !current) { console.error('✗ Usage : version.js --check v2.1.0'); process.exit(1); }
  if (tag !== current) {
    console.error(`✗ INCOHÉRENCE DE VERSION : tag v${tag} ≠ plugin ${current}. BUILD FAILED.`);
    process.exit(1);
  }
  console.log(`✓ Version cohérente : tag v${tag} = plugin ${current}`);
  process.exit(0);
}

writeVersion(arg);
