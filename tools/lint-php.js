/**
 * Valide la syntaxe de tous les fichiers PHP du plugin (équivalent php -l).
 * Usage : node tools/lint-php.js
 */
'use strict';

const fs = require('fs');
const path = require('path');

let parser;
try {
  parser = require('php-parser');
} catch (e) {
  console.error('Dépendance manquante. Installez-la avec :  npm install php-parser');
  process.exit(2);
}

const engine = new parser.Engine({
  parser: { extractDoc: false, suppressErrors: false, version: 704 },
  ast: { withPositions: true },
});

const root = path.join(__dirname, '..', 'infinitycod');
let checked = 0;
let failed = 0;

function walk(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      walk(full);
    } else if (entry.name.endsWith('.php')) {
      checked++;
      try {
        engine.parseCode(fs.readFileSync(full, 'utf8'), full);
      } catch (e) {
        failed++;
        const line = e.lineNumber ? ` (ligne ${e.lineNumber})` : '';
        console.error(`✗ ${path.relative(root, full)}${line} : ${e.message}`);
      }
    }
  }
}

walk(root);

if (failed) {
  console.error(`\n${failed} fichier(s) en erreur sur ${checked}.`);
  process.exit(1);
}
console.log(`✓ ${checked} fichiers PHP valides.`);
