# -*- coding: utf-8 -*-
"""Harnais : section 5.39.0 (portail suivi, facture, stock, WA quotidien, fidélité, PWA)."""
import io

p = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\tools\test-form-engine.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
anchor = 'echo "\\n=== BILAN : {$pass} OK, {$fail} échec(s) ===\\n";'
assert d.count(anchor) == 1

section = r'''
/* ---------- 5.39.0 : Suivi client, facture, stock, WA quotidien, fidélité, PWA ---------- */

echo "\n56) 5.39.0 — Portail de suivi + facture PDF\n";
check( 'portail : page /suivi-commande/ + recherche téléphone', file_exists( $plugin_dir . 'includes/track/class-track-portal.php' ) && false !== strpos( file_get_contents( $plugin_dir . 'includes/track/class-track-portal.php' ), 'suivi-commande' ) );
check( 'portail : limite anti-abus par IP', false !== strpos( file_get_contents( $plugin_dir . 'includes/track/class-track-portal.php' ), 'icod_track_rl_' ) );
check( 'facture : générateur PDF sans dépendance', file_exists( $plugin_dir . 'includes/orders/class-invoice.php' ) && false !== strpos( file_get_contents( $plugin_dir . 'includes/orders/class-invoice.php' ), 'assemble' ) );
check( 'facture : e-mail automatique à la confirmation', false !== strpos( file_get_contents( $plugin_dir . 'includes/orders/class-invoice.php' ), 'maybe_email_invoice' ) );
check( 'facture : bouton dans la modale commande', false !== strpos( file_get_contents( $plugin_dir . 'assets/admin/js/admin.js' ), 'Facture</a>' ) );

echo "\n57) 5.39.0 — Stock, WhatsApp quotidien, fidélité, PWA\n";
check( 'stock : badge « Bientôt épuisé » avec seuil réglable', false !== strpos( $fm_now, 'stock_alert_threshold' ) && false !== strpos( $fm_now, 'Bientôt épuisé' ) );
check( 'stock : vérification quotidienne + e-mail admin', false !== strpos( file_get_contents( $plugin_dir . 'includes/admin/class-admin-extras.php' ), 'run_stock_alert_check' ) );
check( 'whatsapp : rapport quotidien au marchand', false !== strpos( file_get_contents( $plugin_dir . 'includes/admin/class-admin-extras.php' ), 'send_daily_wa_report' ) && false !== strpos( file_get_contents( $plugin_dir . 'includes/admin/class-admin-extras.php' ), 'wa_owner_phone' ) );
$loy_src = file_exists( $plugin_dir . 'includes/loyalty/class-loyalty.php' ) ? file_get_contents( $plugin_dir . 'includes/loyalty/class-loyalty.php' ) : '';
check( 'fidélité : points calculés sur commandes livrées', false !== strpos( $loy_src, 'loyalty_point_da' ) && false !== strpos( $loy_src, 'delivered' ) );
check( 'fidélité : remise appliquée côté serveur + plafond 30 %', false !== strpos( file_get_contents( $plugin_dir . 'includes/orders/class-order-store.php' ), 'loyalty_discount' ) && false !== strpos( $loy_src, '0.3' ) );
check( 'quote : aperçu fidélité dans la réponse', false !== strpos( $routes_src, 'Loyalty::preview' ) );
check( 'pwa : manifest + service worker pour le marchand', file_exists( $plugin_dir . 'includes/core/class-pwa.php' ) && false !== strpos( file_get_contents( $plugin_dir . 'includes/core/class-pwa.php' ), 'icod-manifest.json' ) );
$set_page = file_get_contents( $plugin_dir . 'includes/admin/pages/class-settings-page.php' );
check( 'réglages : cartes suivi/facture/stock/WA/fidélité présentes', false !== strpos( $set_page, "'track_portal_enabled'" ) && false !== strpos( $set_page, "'invoice_enabled'" ) && false !== strpos( $set_page, "'stock_alert_threshold'" ) && false !== strpos( $set_page, "'wa_daily_report'" ) && false !== strpos( $set_page, "'loyalty_enabled'" ) );
'''

d = d.replace(anchor, section + "\n" + anchor, 1)
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
print("OK section harnais 5.39.0")
