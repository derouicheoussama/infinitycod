/* Audit d'application : chaque clé du schéma settings_schema() doit être
   consommée quelque part (Settings::get) HORS la page de réglages elle-même.
   Une clé sans consommateur = option qui s'enregistre mais ne s'applique
   jamais au front/admin → bug silencieux.
   Sens inverse (UI → schéma, défauts) : voir audit.js / audit-settings.js. */
'use strict';
const fs = require('fs');
const path = require('path');

const sp = fs.readFileSync('infinitycod/includes/admin/pages/class-settings-page.php', 'utf8');

// 1. Clés du schéma déclaratif.
const schemaKeys = [...sp.matchAll(/'([a-z_0-9]+)'\s*=>\s*array\(\s*'tab'/g)].map(m => m[1]);

// 2. Consommateurs : Settings::get('clé' dans tout le plugin, HORS settings-page
//    (le schéma/l'UI/handlers y vivent mais n'appliquent rien).
//    Les clés "renseignées puis lues ailleurs via all()" sont rares : tout
//    consommateur légitime doit apparaitre en clair dans un module.
const consumers = new Map(); // clé → [fichier:ligne]
(function walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const f = path.join(dir, e.name);
    if (e.isDirectory()) { walk(f); continue; }
    if (!e.name.endsWith('.php')) continue;
    const norm = f.replace(/\\/g, '/');
    if (norm.includes('class-settings-page.php')) continue;
    const src = fs.readFileSync(f, 'utf8');
    const lines = src.split('\n');
    lines.forEach((line, i) => {
      for (const m of line.matchAll(/Settings::get\(\s*'([a-z_0-9]+)'/g)) {
        const k = m[1];
        if (!consumers.has(k)) consumers.set(k, []);
        consumers.get(k).push(norm.replace('infinitycod/', '') + ':' + (i + 1));
      }
    });
  }
})('infinitycod');
// Fichiers racine du plugin (uninstall.php lit la configuration directement).
for (const root of ['infinitycod/uninstall.php', 'infinitycod/infinitycod.php']) {
  const src = fs.readFileSync(root, 'utf8');
  src.split('\n').forEach((line, i) => {
    for (const m of line.matchAll(/([a-z_0-9]*settings)\s*\[\s*'([a-z_0-9]+)'\s*\]|Settings::get\(\s*'([a-z_0-9]+)'/g)) {
      const k = m[3] || m[2];
      if (!k || !schemaKeys.includes(k)) continue;
      if (!consumers.has(k)) consumers.set(k, []);
      consumers.get(k).push(root.replace('infinitycod/', '') + ':' + (i + 1));
    }
  });
}

// 3. Orphelines : dans le schéma mais jamais lues ailleurs (hors accès
//    dynamiques documentés ci-dessous).
const orphans = schemaKeys.filter(k => !consumers.has(k));
const WHITELIST = {
  // payment_logo() : mapping dynamique 'cib' => 'logo_cib_id' dans FormManager.
  logo_cib_id: 'lecture dynamique via le mapping payment_logo()',
  logo_edahabia_id: 'lecture dynamique via le mapping payment_logo()',
  // Accesseurs dédiés de Settings (default_country(), active_countries()).
  default_country: 'accesseur Settings::default_country()',
  countries: 'accesseur Settings::active_countries()',
  // send_template('msg_order_shipped') : premier argument dynamique.
  msg_order_shipped: 'send_template() — nom de réglage passé en argument',
  // Lues par migrate_legacy_field_toggles() DANS class-settings.php (alignement Builder).
  show_note: 'migration legacy → checkout_fields (class-settings.php)',
  show_email: 'migration legacy → checkout_fields (class-settings.php)',
  // Configurent les boutons d'achat PayPal rendus dans l'onglet Licence
  // (class-settings-page.php lui-même — lecture légitime pour son propre rendu).
  paypal_enabled: 'rendu des boutons PayPal, onglet Licence',
  paypal_email: 'rendu des boutons PayPal, onglet Licence',
  paypal_currency: 'rendu des boutons PayPal, onglet Licence',
  paypal_price: 'rendu des boutons PayPal, onglet Licence',
};

const real = orphans.filter(k => !WHITELIST[k]);
if (real.length) {
  console.error('✗ Options du schéma JAMAIS appliquées (aucun Settings::get hors réglages) :');
  real.forEach(k => {
    const hint = WHITELIST[k] ? ' (whitelist : ' + WHITELIST[k] + ')' : '';
    console.error('  ' + k + hint);
  });
  process.exit(1);
}
console.log('✓ Audit application : ' + schemaKeys.length + ' clés du schéma, toutes consommées (or ' +
  Object.keys(WHITELIST).length + ' documentée(s)).');
