'use strict';
const fs = require('fs');
const f = 'infinitycod/includes/admin/pages/class-geo-page.php';
let s = fs.readFileSync(f, 'utf8');
if (s.includes('icod_carrier_autosync')) { console.log('déjà présent'); process.exit(0); }

const T = '\t';
const anchor = [
  T + '\t\t\t\t\t</label>',
  T + '\t\t\t\t</div>',
  '',
  T + '\t\t\t<div class="icod-card">',
].join('\n');

const insert = [
  T + '\t\t\t\t\t</label>',
  T + '\t\t\t\t\t<label class="icod-toggle">',
  T + '\t\t\t\t\t\t<input type="checkbox" name="icod_carrier_autosync" value="1" <?php checked( \'1\', (string) Settings::get( \'carrier_autosync\', \'1\' ) ); ?> />',
  T + '\t\t\t\t\t\t<span><?php esc_html_e( \'Synchronisation automatique du suivi transporteur (chaque heure)\', \'infinitycod\' ); ?></span>',
  T + '\t\t\t\t\t</label>',
  T + '\t\t\t\t</div>',
  '',
  T + '\t\t\t<div class="icod-card">',
].join('\n');

if (!s.includes(anchor)) { console.error('FAIL ancre introuvable'); process.exit(1); }
s = s.replace(anchor, insert);
fs.writeFileSync(f, s);
console.log('OK autosync toggle inséré');
