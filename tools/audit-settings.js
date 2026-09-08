/* Audit croisé : chaque clé Settings::get() doit exister dans les défauts. */
'use strict';
const fs = require('fs');
const path = require('path');

const st = fs.readFileSync('infinitycod/includes/core/class-settings.php', 'utf8');
const defaults = new Set([...st.matchAll(/'([a-z_0-9]+)'\s+=>/g)].map(m => m[1]));

let missing = [];
(function walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const f = path.join(dir, e.name);
    if (e.isDirectory()) { walk(f); continue; }
    if (!e.name.endsWith('.php')) continue;
    if (f.replace(/\\/g, '/') === 'infinitycod/includes/core/class-settings.php') continue; // le fichier des défauts lui-même (exemples PHPDoc).
    const src = fs.readFileSync(f, 'utf8');
    for (const m of src.matchAll(/Settings::get\(\s*'([a-z_0-9]+)'/g)) {
      if (!defaults.has(m[1])) missing.push(f.replace(/\\/g, '/') + ' → ' + m[1]);
    }
  }
})('infinitycod/includes');

if (missing.length) {
  console.error('✗ clés sans défaut :');
  missing.forEach(x => console.error('  ' + x));
  process.exit(1);
}
console.log('✓ toutes les clés Settings::get() ont des défauts (' + defaults.size + ' clés)');
