/* Audit : chaque champ icod[...] de SettingsPage doit être géré dans handle_save
   et exister dans les défauts. + scan anti-corruption. */
'use strict';
const fs = require('fs');
const path = require('path');

const spPath = 'infinitycod/includes/admin/pages/class-settings-page.php';
const sp = fs.readFileSync(spPath, 'utf8');
const setPath = 'infinitycod/includes/core/class-settings.php';
const st = fs.readFileSync(setPath, 'utf8');

// Champs utilisés dans les formulaires.
const used = new Set();
for (const m of sp.matchAll(/icod\[([a-z_0-9]+)\]/g)) used.add(m[1]);
for (const m of sp.matchAll(/icod\[([a-z_0-9]+)\]\[\]/g)) used.add(m[1]);
// icod_carrier[code][field] hors périmètre (transporteurs).

// Clés gérées dans handle_save : listes explicites.
const handledText = [...sp.matchAll(/array\(([^)]*)\) as \$(text_key|textarea_key)/g)].flatMap(m =>
  m[1].split(',').map(x => x.trim().replace(/^'|'$/g, '')).filter(x => x && !x.includes('('))
);
// Toggles : bloc $tab_toggles = array( 'onglet' => array( 'cle1', 'cle2', ... ), ... );
const tabTogglesBlock = sp.match(/\$tab_toggles\s*=\s*array\(([\s\S]*?)\);\s*\r?\n\s*\$scope_toggles/);
const handledToggleBlock = tabTogglesBlock
  ? [...tabTogglesBlock[1].matchAll(/'([a-z_0-9]+)'/g)].map(m => m[1])
  : [...sp.matchAll(/array\(([^)]*)\) as \$toggle_key/g)].flatMap(m =>
      m[1].split(',').map(x => x.trim().replace(/^'|'$/g, ''))
    );
const handledNum = [...sp.matchAll(/'([a-z_0-9]+)'\s+=> array\(/g)].map(m => m[1]);

// Schéma déclaratif settings_schema() : clés 'x' => array( 'tab' => ..., 'type' => ... ).
const schemaKeys = [...sp.matchAll(/'([a-z_0-9]+)'\s*=>\s*array\(\s*'tab'/g)].map(m => m[1]);

const handled = new Set([...handledText, ...handledToggleBlock, ...handledNum, ...schemaKeys,
  'accent_color', 'form_theme', 'whatsapp_gateway', 'chargily_mode', 'form_preset', 'form_position', 'success_style',
  'redirect_url', 'upsell_ids', 'whatsapp_number', 'whatsapp_phone_id', 'whatsapp_ultramsg_instance',
  'github_repo', 'releases_repo', 'license_server', 'whatsapp_cloud_token', 'whatsapp_ultramsg_key',
  'github_token', 'chargily_secret', 'checkout_fields_new', // champ d'ajout, fusionné dans checkout_fields
]);

const missingHandled = [...used].filter(k => !handled.has(k) && k !== 'checkout_fields_new' && k !== 'checkout_template');
const missingDefaults = [...used].filter(k => !st.includes(`'${k}'`) && k !== 'checkout_fields_new' && k !== 'checkout_template');

console.log('Champs utilisés :', used.size);
if (missingHandled.length) console.error('✗ NON gérés dans handle_save :', missingHandled);
if (missingDefaults.length) console.error('✗ Absents des défauts :', missingDefaults);

// Scan anti-corruption sur tout le PHP.
function walk(dir, cb) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, e.name);
    if (e.isDirectory()) walk(full, cb);
    else if (e.name.endsWith('.php')) cb(full, fs.readFileSync(full, 'utf8'));
  }
}
let bad = 0;
walk('infinitycod', (f, src) => {
  // Namespace corrompu : InfinityCod directement suivi d'une majuscule (sans antislash).
  if (/InfinityCod[A-Z]/.test(src)) { console.error('✗ namespace corrompu :', f); bad++; }
  // Dollar échappé littéral en code : \$
  const lines = src.split('\n');
  lines.forEach((line, i) => {
    if (line.includes('\\$') && !line.includes('%1\\$') && !line.includes('%2\\$')) {
      console.error('✗ \\' + '$' + ' littéral :', f + ':' + (i + 1));
      bad++;
    }
  });
});

if (missingHandled.length || missingDefaults.length || bad) process.exit(1);
console.log('✓ Audit : tous les champs couverts, aucune corruption détectée.');
