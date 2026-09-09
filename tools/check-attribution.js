/**
 * Attribution Check — InfinityCod (§126-127).
 *
 * Vérifie que les sources InfinityCod portent l'attribution du développeur.
 * Les fichiers tiers (chart.umd.min.js…) sont exclus de l'exigence.
 *
 *   node tools/check-attribution.js
 */
'use strict';

const fs = require('fs');
const path = require('path');

const AUTHOR = 'Derouiche Oussama';
const results = [];
let failed = false;

function walk(dir, filter, cb) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const f = path.join(dir, e.name);
    if (e.isDirectory()) walk(f, filter, cb);
    else if (filter(e.name)) cb(f, fs.readFileSync(f, 'utf8'));
  }
}

/* PHP : @author + @copyright dans le docblock. */
let phpTotal = 0;
let phpOk = 0;
const phpBad = [];
walk('infinitycod', (n) => n.endsWith('.php'), (f, s) => {
  if (path.basename(f) === 'index.php') return; // Gardes « Silence is golden » : hors exigence d'attribution.
  phpTotal++;
  const isMain = path.basename(f) === 'infinitycod.php';
  const signed = isMain
    ? (s.includes('Author:            ' + AUTHOR) && s.includes('Copyright:         © ' + AUTHOR))
    : (s.includes('@author ' + AUTHOR) && s.includes('© ' + AUTHOR));
  if (signed) phpOk++;
  else phpBad.push(path.basename(f));
});
results.push(['PHP source', phpOk === phpTotal ? 'PASS' : 'FAIL', phpOk + '/' + phpTotal + ' fichiers signés']);
if (phpOk !== phpTotal) failed = true;

/* JS : attribution sauf fichiers tiers minifiés. */
const jsFiles = [];
walk('infinitycod/assets', (n) => n.endsWith('.js') && !n.includes('.min.'), (f, s) => jsFiles.push([f, s]));
let jsOk = 0;
const jsBad = [];
for (const [f, s] of jsFiles) {
  if (s.includes('@author ' + AUTHOR)) jsOk++;
  else jsBad.push(path.basename(f));
}
results.push(['JavaScript source', jsFiles.length && jsOk === jsFiles.length ? 'PASS' : 'FAIL', jsOk + '/' + jsFiles.length + ' fichiers signés']);
if (jsOk !== jsFiles.length) failed = true;

/* CSS. */
const cssFiles = [];
walk('infinitycod/assets', (n) => n.endsWith('.css') && !n.includes('.min.'), (f, s) => cssFiles.push([f, s]));
let cssOk = 0;
const cssBad = [];
for (const [f, s] of cssFiles) {
  if (s.includes('Developer: ' + AUTHOR)) cssOk++;
  else cssBad.push(path.basename(f));
}
results.push(['CSS source', cssFiles.length && cssOk === cssFiles.length ? 'PASS' : 'FAIL', cssOk + '/' + cssFiles.length + ' fichiers signés']);
if (cssOk !== cssFiles.length) failed = true;

/* Header plugin : Author + Copyright. */
const main = fs.readFileSync('infinitycod/infinitycod.php', 'utf8');
const headerOk = main.includes('Author:            ' + AUTHOR) && main.includes('Copyright:         © ' + AUTHOR);
results.push(['Plugin header', headerOk ? 'PASS' : 'FAIL', headerOk ? 'Author + Copyright corrects' : 'Author/Copyright incorrects']);
if (!headerOk) failed = true;

/* README : section Developer. */
const readme = fs.readFileSync('README.md', 'utf8');
const readmeOk = readme.includes(AUTHOR) && readme.includes('derouicheoussama.com');
results.push(['README', readmeOk ? 'PASS' : 'FAIL', readmeOk ? 'Developer section présente' : 'Developer section absente']);
if (!readmeOk) failed = true;

/* Documentation. */
let docOk = false;
for (const d of fs.readdirSync('docs')) {
  const s = fs.readFileSync(path.join('docs', d), 'utf8');
  if (s.includes(AUTHOR)) { docOk = true; break; }
}
results.push(['Documentation', docOk ? 'PASS' : 'FAIL', docOk ? 'attribution présente' : 'attribution absente']);
if (!docOk) failed = true;

/* Rapport. */
console.log('InfinityCod Attribution Check\n');
for (const [name, status, detail] of results) {
  console.log(`${status === 'PASS' ? '✓' : '✗'} ${name}: ${status} (${detail})`);
}
console.log('\nDeveloper:\n' + AUTHOR);
console.log('\nCopyright:\n© ' + AUTHOR);

process.exit(failed ? 1 : 0);
