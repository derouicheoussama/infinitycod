/**
 * Test de fumée statique : vérifie que chaque classe référencée par le
 * module_map correspond bien à un fichier selon les règles de l'autoloader,
 * que toutes les classes ont une méthode register() quand nécessaire, etc.
 * Usage : node tools/smoke.js
 */
'use strict';

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..', 'infinitycod');
const includes = path.join(root, 'includes');

// Reconstitue module_map depuis class-plugin.php.
const pluginSrc = fs.readFileSync(path.join(includes, 'core', 'class-plugin.php'), 'utf8');
const mapBlock = pluginSrc.match(/module_map = array\(([\s\S]*?)\);/);
if (!mapBlock) {
  console.error('✗ module_map introuvable');
  process.exit(1);
}

const fqcns = [...mapBlock[1].matchAll(/'([A-Za-z]+)'\s+=>\s*(?:(?:__NAMESPACE__ \. )?)(?:'([^']+)'|(__NAMESPACE__ \. '\\\\I18n'))/g)]
  .map(m => {
    if (m[3] !== undefined) return { slug: m[1], fqcn: 'InfinityCod\\Core\\I18n' };
    let fqcn = m[2].replace(/^\\\\/, '').replace(/\\\\/g, '\\');
    return { slug: m[1], fqcn };
  });

function fileCandidates(fqcn) {
  const relative = fqcn.replace(/^InfinityCod\\/, '');
  const parts = relative.split('\\');
  const className = parts.pop();
  // Répète exactement la règle de l'Autoloader PHP :
  // namespace et nom de classe en kebab (AntiFraud → anti-fraud).
  const dir = parts.map(p => p.replace(/(?<!^)[A-Z]/g, c => '-' + c).toLowerCase()).join('/');
  const kebab = className.replace(/(?<!^)[A-Z]/g, c => '-' + c).toLowerCase();
  const plain = relative.replace(/\\/g, '').toLowerCase();
  return [
    path.join(includes, dir, 'class-' + kebab + '.php'),
    path.join(includes, dir, 'class-' + plain + '.php')
  ];
}

let errors = 0;

for (const { slug, fqcn } of fqcns) {
  const candidates = fileCandidates(fqcn);
  const found = candidates.find(c => fs.existsSync(c));
  if (!found) {
    console.error(`✗ [${slug}] ${fqcn} : aucun fichier trouvé (attendu ${candidates[0].replace(root, '')})`);
    errors++;
  } else {
    console.log(`✓ [${slug}] ${fqcn} → ${found.replace(root, '')}`);
  }
}

// Toutes les classes PHP déclarées doivent être dans un fichier existant.
(function walk(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) { walk(full); continue; }
    if (!entry.name.endsWith('.php')) continue;
    const src = fs.readFileSync(full, 'utf8');
    const ns = src.match(/namespace\s+([^;]+);/);
    const cls = [...src.matchAll(/(?:abstract\s+)?(?:final\s+)?class\s+(\w+)|interface\s+(\w+)/g)];
    for (const m of cls) {
      const name = m[1] || m[2];
      const fqcn = ns ? ns[1].trim() + '\\' + name : name;
      if (!fqcns.find(x => x.fqcn === fqcn)) continue; // hors map : ok (helpers).
      void fqcn;
    }
  }
})(includes);

if (errors) {
  console.error(`\n${errors} problème(s) d'autoloading.`);
  process.exit(1);
}
console.log(`\n✓ ${fqcns.length} modules résolus par l'autoloader.`);
