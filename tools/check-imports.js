/* Audit imports — v2 : compare les noms courts et tient compte des FQCN. */
'use strict';
const fs = require('fs');
const path = require('path');

/* 1. Carte nom court → FQN. */
const defined = new Map();
(function collect(dir, ns) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const f = path.join(dir, e.name);
    if (e.isDirectory()) { collect(f, ns + '\\' + e.name.replace(/-/g, '')); continue; }
    if (!e.name.endsWith('.php')) continue;
    const src = fs.readFileSync(f, 'utf8');
    const fileNs = (src.match(/namespace\s+([^;]+);/) || [])[1];
    if (!fileNs) continue;
    for (const m of src.matchAll(/\b(?:abstract\s+|final\s+)?(?:class|interface)\s+(\w+)/g)) {
      defined.set(m[1], fileNs.trim() + '\\' + m[1]);
    }
  }
})('infinitycod/includes', '');

const PHP_BUILTIN = new Set(['DateTime', 'Exception', 'RuntimeException', 'stdClass', 'Throwable']);

let errors = 0;
(function walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const f = path.join(dir, e.name);
    if (e.isDirectory()) { walk(f); continue; }
    if (!e.name.endsWith('.php')) continue;
    const src = fs.readFileSync(f, 'utf8');
    const fileNs = ((src.match(/namespace\s+([^;]+);/) || [])[1] || '').trim();

    const imports = new Set(
      [...src.matchAll(/^use\s+([^;]+);/gm)]
        .map(m => m[1].trim().split('\\').pop())
    );

    // Tokens Token:: — avec detection du backslash précédent (FQCN).
    const lines = src.split('\n');
    lines.forEach((rawLine, i) => {
      const line = rawLine.trim();
      if (line.startsWith('*') || line.startsWith('//') || line.startsWith('#')) return;
      const re = /(^|[^\\A-Za-z0-9_])([A-Z][A-Za-z0-9_]*)::/g;
      let m;
      while ((m = re.exec(line))) {
        const token = m[2];
        if (imports.has(token)) continue;                       // importé
        if (PHP_BUILTIN.has(token)) continue;                   // natif
        // Existe-t-il dans le plugin ?
        const target = [...defined.entries()].find(([short, fqn]) => short === token);
        if (!target) continue;                                  // externe (WP/WC) : toléré
        const [, fqn] = target;
        if (fqn === fileNs + '\\' + token) continue;            // même namespace
        console.error(`✗ ${path.relative('.', f)}:${i + 1} — "${token}::" résoudrait vers ${fileNs}\\${token} (import manquant)`);
        errors++;
      }
    });
  }
})('infinitycod/includes');

if (errors) { console.error(`\n${errors} référence(s) à corriger.`); process.exit(1); }
console.log('✓ Imports : toutes les références statiques résolues.');
