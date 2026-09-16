# -*- coding: utf-8 -*-
"""Insère la section harnais 5.31.0 avant le BILAN."""
import io

p = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\tools\..\infinitycod\..\tools\test-form-engine.php"
p = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\tools\test-form-engine.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()

anchor = 'echo "\\n=== BILAN : {$pass} OK, {$fail} échec(s) ===\\n";'
assert d.count(anchor) == 1, "ancre bilan : %d" % d.count(anchor)

section = r'''
/* ---------- 5.31.0 : Logistique, croissance & confirmation ---------- */

echo "\n47) 5.31.0 — Paliers de poids & zones régionales\n";
$rmanager = file_get_contents( $plugin_dir . 'includes/shipping/class-rates-manager.php' );
check( 'paliers de poids : price_weighted avec repli base', false !== strpos( $rmanager, 'public function price_weighted' ) && false !== strpos( $rmanager, 'w_over' ) );
check( 'zones régionales : classe + repli dans price()', file_exists( $plugin_dir . 'includes/shipping/class-zones.php' ) && false !== strpos( $rmanager, 'Zones::price' ) );
check( 'zones : preset national + sauvegarde nettoyée', false !== strpos( file_get_contents( $plugin_dir . 'includes/shipping/class-zones.php' ), 'public static function preset' ) );
check( 'quote REST : poids appliqué aux deux modes', false !== strpos( $routes_src, 'price_weighted' ) );
check( 'create_from_form : wilaya suspendue bloquée', false !== strpos( $orders_src, 'wilaya_closed' ) );
check( 'géo : colonnes poids/retour éditables + duplication', false !== strpos( file_get_contents( $plugin_dir . 'includes/admin/pages/class-geo-page.php' ), 'icod-dup' ) && false !== strpos( file_get_contents( $plugin_dir . 'includes/admin/pages/class-geo-page.php' ), 'icod-zone-preset' ) );
check( 'tarifs : CSV export/import étendus (paliers + retour)', false !== strpos( $am_src, 'poids_5_10' ) && false !== strpos( $am_src, 'return_fee' ) );

echo "\n48) 5.31.0 — A/B test du formulaire\n";
$abtest_src = file_exists( $plugin_dir . 'includes/orders/class-ab-test.php' ) ? file_get_contents( $plugin_dir . 'includes/orders/class-ab-test.php' ) : '';
check( 'abtest : attribution cookie stable + compteur de vues', '' !== $abtest_src && false !== strpos( $abtest_src, 'icod_ab' ) && false !== strpos( $abtest_src, 'track_view' ) );
check( 'formulaire : variante appliquée (titre/bouton/couleur B)', false !== strpos( $fm_now, 'AbTest::assign()' ) && false !== strpos( $fm_now, 'ab_b_accent' ) );
check( 'commandes : colonne ab_variant alimentée', false !== strpos( $orders_src, 'ab_variant' ) );
check( 'stats : résumé A/B disponible', false !== strpos( $abtest_src, 'public static function stats' ) );

echo "\n49) 5.31.0 — Confirmation WhatsApp 1 clic\n";
check( 'whatsapp : lien signé {lien_confirmation}', false !== strpos( file_get_contents( $plugin_dir . 'includes/whatsapp/class-whatsapp-manager.php' ), 'lien_confirmation' ) );
check( 'endpoint public icod_confirm + vérification HMAC', false !== strpos( file_get_contents( $plugin_dir . 'includes/core/class-plugin.php' ), 'handle_order_confirm' ) && false !== strpos( $orders_src, 'confirm_from_link' ) );

echo "\n50) 5.31.0 — Webhooks, Telegram, blacklist communautaire\n";
check( 'webhooks : classe signée HMAC + hooks commande', file_exists( $plugin_dir . 'includes/core/class-webhooks.php' ) && false !== strpos( file_get_contents( $plugin_dir . 'includes/core/class-webhooks.php' ), 'X-InfinityCod-Signature' ) );
check( 'webhooks : module enregistré', false !== strpos( file_get_contents( $plugin_dir . 'includes/core/class-plugin.php' ), "'webhooks'" ) );
check( 'rapport hebdo : synthèse Telegram', false !== strpos( file_get_contents( $plugin_dir . 'includes/admin/class-admin-extras.php' ), 'sendMessage' ) );
$shield_src = file_get_contents( $plugin_dir . 'includes/anti-fraud/class-shield.php' );
check( 'antifraude : historique de retours du numéro', false !== strpos( $shield_src, 'count_phone_returns' ) );
check( 'antifraude : blacklist communautaire opt-in (hashés)', false !== strpos( $shield_src, 'community_blacklisted' ) );

echo "\n51) 5.31.0 — Retours, bordereaux, export/import\n";
check( 'P&L : provision des frais de retour', false !== strpos( file_get_contents( $plugin_dir . 'includes/stats/class-pnl.php' ), 'return_fees' ) );
check( 'bordereaux en lot : handler + bouton imprimable', false !== strpos( $am_src, 'handle_bordereaux' ) && false !== strpos( file_get_contents( $plugin_dir . 'includes/admin/pages/class-orders-page.php' ), 'icod-bordereaux' ) );
$set_page = file_get_contents( $plugin_dir . 'includes/admin/pages/class-settings-page.php' );
check( 'réglages : A/B + webhook + blacklist dans le schéma', false !== strpos( $set_page, "'abtest_enabled'" ) && false !== strpos( $set_page, "'webhook_url'" ) && false !== strpos( $set_page, "'community_blacklist'" ) );
check( 'réglages : export/import JSON de la config', false !== strpos( $am_src, 'handle_settings_export' ) && false !== strpos( $am_src, 'handle_settings_import' ) );
'''

d = d.replace(anchor, section + "\n" + anchor, 1)
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
print("OK section harnais 5.31.0 insérée")
