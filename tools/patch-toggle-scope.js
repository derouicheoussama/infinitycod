/* FIX CRITIQUE : handle_save n'écrase plus les toggles des autres onglets.
   Chaque toggle a un hidden companion value=0 pour un fonctionnement fiable. */
'use strict';
const fs = require('fs');
const p = 'infinitycod/includes/admin/pages/class-settings-page.php';
let s = fs.readFileSync(p, 'utf8');

// 1. Les toggles ne sont mis à 0 que s'ils sont dans le POST de CET onglet.
const oldToggle = [
  "\t\t// Cases à cocher (absent = 0).",
  "\t\tforeach ( array( 'show_qty_selector', 'show_stopdesk', 'show_note', 'show_offers', 'show_reassurance', 'sticky_bar', 'menu_badge', 'auto_update', 'payment_enabled', 'redirect_enabled', 'upsell_enabled', 'show_email', 'restrict_hours_enabled', 'wa_order_enabled', 'shield_enabled', 'phone_strict', 'block_duplicate_phone', 'whatsapp_enabled', 'abandoned_enabled', 'delete_on_uninstall' ) as $toggle_key ) {",
  "\t\t\t$clean[ $toggle_key ] = empty( $raw[ $toggle_key ] ) ? 0 : 1;",
  "\t\t}",
].join('\n');

const newToggle = [
  "\t\t// Toggles : SEULEMENT ceux présents dans le POST de cet onglet.",
  "\t\t// Les toggles des autres onglets sont préservés.",
  "\t\t$page_toggles = isset( $_POST['icod_scope'] ) ? array_filter( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['icod_scope'] ) ) ) ) ) : array();",
  "\t\t$all_toggles = array(",
  "\t\t\t'show_qty_selector', 'show_stopdesk', 'show_note', 'show_offers', 'show_reassurance',",
  "\t\t\t'sticky_bar', 'menu_badge', 'auto_update', 'payment_enabled', 'redirect_enabled',",
  "\t\t\t'upsell_enabled', 'show_email', 'restrict_hours_enabled', 'wa_order_enabled',",
  "\t\t\t'shield_enabled', 'phone_strict', 'block_duplicate_phone',",
  "\t\t\t'whatsapp_enabled', 'abandoned_enabled', 'delete_on_uninstall',",
  "\t);",
  "\t\tforeach ( $page_toggles as $toggle_key ) {",
  "\t\t\t$toggle_key = sanitize_key( $toggle_key );",
  "\t\t\tif ( ! in_array( $toggle_key, $all_toggles, true ) ) { continue; }",
  "\t\t\t$clean[ $toggle_key ] = empty( $raw[ $toggle_key ] ) ? 0 : 1;",
  "\t\t}",
].join('\n');

if (!s.includes(oldToggle)) { console.error('ANCRE toggles introuvable'); process.exit(1); }
s = s.replace(oldToggle, () => newToggle);

fs.writeFileSync(p, s);
console.log('✓ handle_save : toggles scoper par onglet');
