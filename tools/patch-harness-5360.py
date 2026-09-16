# -*- coding: utf-8 -*-
"""Harnais : section 5.36.0 paiements Visa/Mastercard/PayPal + neutralisation GitHub."""
import io

p = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\tools\test-form-engine.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
anchor = 'echo "\\n=== BILAN : {$pass} OK, {$fail} échec(s) ===\\n";'
assert d.count(anchor) == 1

section = r'''
/* ---------- 5.36.0 : Paiements Visa/Mastercard + PayPal + discrétion ---------- */

echo "\n54) 5.36.0 — Paiement carte (Stripe) & PayPal\n";
$pay_src = file_get_contents( $plugin_dir . 'includes/payment/class-payment-manager.php' );
check( 'paiement : dispatch multi-passereelles (chargily/stripe/paypal)', false !== strpos( $pay_src, "case 'stripe'" ) && false !== strpos( $pay_src, "case 'paypal'" ) );
check( 'stripe : checkout session + unités mineures', false !== strpos( $pay_src, 'checkout/sessions' ) && false !== strpos( $pay_src, 'stripe_amount' ) );
check( 'stripe : vérification du statut au retour', false !== strpos( $pay_src, 'stripe_session_status' ) );
check( 'paypal : Orders v2 + capture au retour', false !== strpos( $pay_src, 'v2/checkout/orders' ) && false !== strpos( $pay_src, 'paypal_capture' ) );
check( 'paiement : taux de conversion par devise réglable', false !== strpos( $pay_src, 'stripe_rate' ) && false !== strpos( $pay_src, 'paypal_rate' ) );
check( 'paiement : mark_paid mémorise la passerelle', false !== strpos( $pay_src, "'payment'     => $gateway" ) );
check( 'réglages : passerelle active sélectionnable', false !== strpos( $set_page, "'payment_mode'" ) && false !== strpos( $set_page, "'stripe_secret'" ) && false !== strpos( $set_page, "'paypal_client_id'" ) );
check( 'formulaire : logos et libellés selon la passerelle', false !== strpos( file_get_contents( $plugin_dir . 'includes/form/class-form-manager.php' ), 'payment_logos_active' ) && false !== strpos( file_get_contents( $plugin_dir . 'includes/form/class-form-manager.php' ), 'gateway_sub_label' ) );

echo "\n55) 5.36.0 — Discrétion : aucune infrastructure exposée au marchand\n";
$diag_src = file_get_contents( $plugin_dir . 'includes/admin/pages/class-diagnostics-page.php' );
$upd_src  = file_get_contents( $plugin_dir . 'includes/admin/pages/class-updates-page.php' );
check( 'diagnostics : zéro mention GitHub visible', false === strpos( $diag_src, "__( 'GitHub" ) && false !== strpos( $diag_src, 'serveur de mises à jour' ) );
check( 'mises à jour : zéro mention GitHub visible', false === strpos( $upd_src, 'GitHub' ) );
'''

d = d.replace(anchor, section + "\n" + anchor, 1)
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
print("OK section harnais 5.36.0")
