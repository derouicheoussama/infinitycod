# -*- coding: utf-8 -*-
"""Patch 5.36.0 bis : schéma paiement + UI passerelles + libellés formulaire + logos."""
import io

ROOT = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod"
BS = chr(92)
ok = []


def patch(rel, pairs):
    p = ROOT + BS + rel.replace("/", BS)
    with io.open(p, "r", encoding="utf-8", newline="") as f:
        d = f.read()
    for old, new in pairs:
        n = d.count(old)
        if n == 0:
            continue  # déjà appliqué (idempotent)
        assert n == 1, rel + " :: " + old[:70].replace("\n", "⏎") + " -> " + str(n)
        d = d.replace(old, new, 1)
    with io.open(p, "w", encoding="utf-8", newline="") as f:
        f.write(d)
    ok.append(rel + " (" + str(len(pairs)) + ")")


# ---------- 1) SCHÉMA PAIEMENT ----------
patch("includes/admin/pages/class-settings-page.php", [
    (
        "\t\t\t'chargily_secret'    => array( 'tab' => 'payment', 'type' => 'secret' ),",
        "\t\t\t'chargily_secret'    => array( 'tab' => 'payment', 'type' => 'secret' ),\n"
        "\t\t\t'payment_mode'       => array( 'tab' => 'payment', 'type' => 'enum', 'choices' => array( 'chargily', 'stripe', 'paypal' ) ),\n"
        "\t\t\t'stripe_secret'      => array( 'tab' => 'payment', 'type' => 'secret' ),\n"
        "\t\t\t'stripe_currency'    => array( 'tab' => 'payment', 'type' => 'text' ),\n"
        "\t\t\t'stripe_rate'        => array( 'tab' => 'payment', 'type' => 'price' ),\n"
        "\t\t\t'paypal_client_id'   => array( 'tab' => 'payment', 'type' => 'text' ),\n"
        "\t\t\t'paypal_secret'      => array( 'tab' => 'payment', 'type' => 'secret' ),\n"
        "\t\t\t'paypal_mode'        => array( 'tab' => 'payment', 'type' => 'enum', 'choices' => array( 'live', 'sandbox' ) ),\n"
        "\t\t\t'paypal_currency'    => array( 'tab' => 'payment', 'type' => 'text' ),\n"
        "\t\t\t'paypal_rate'        => array( 'tab' => 'payment', 'type' => 'price' ),",
    ),
])

# ---------- 2) UI PAIEMENT : sélecteur de passerelle + cartes Stripe/PayPal ----------
patch("includes/admin/pages/class-settings-page.php", [
    (
        "\t\t\t<h2><?php esc_html_e( 'Paiement en ligne — Chargily Pay (CIB / Edahabia)', 'infinitycod' ); ?></h2>",
        "\t\t\t<h2>💳 <?php esc_html_e( 'Paiement en ligne — passerelle active', 'infinitycod' ); ?></h2>\n"
        "\t\t\t<p class=\"description\"><?php esc_html_e( 'Choisissez comment vos clients paient en ligne : CIB / Edahabia (Chargily Pay, Algérie), carte bancaire Visa / Mastercard (Stripe) ou PayPal. Le formulaire affiche automatiquement la bonne option.', 'infinitycod' ); ?></p>\n"
        "\t\t\t<div class=\"icod-grid\">\n"
        "\t\t\t\t<label>\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'Passerelle active', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t\t<select name=\"icod[payment_mode]\">\n"
        "\t\t\t\t\t\t<option value=\"chargily\" <?php selected( Settings::get( 'payment_mode', 'chargily' ), 'chargily' ); ?>><?php esc_html_e( 'CIB / Edahabia — Chargily Pay (Algérie)', 'infinitycod' ); ?></option>\n"
        "\t\t\t\t\t\t<option value=\"stripe\" <?php selected( Settings::get( 'payment_mode' ), 'stripe' ); ?>><?php esc_html_e( 'Carte bancaire Visa / Mastercard — Stripe', 'infinitycod' ); ?></option>\n"
        "\t\t\t\t\t\t<option value=\"paypal\" <?php selected( Settings::get( 'payment_mode' ), 'paypal' ); ?>><?php esc_html_e( 'PayPal', 'infinitycod' ); ?></option>\n"
        "\t\t\t\t\t</select>\n"
        "\t\t\t\t</label>\n"
        "\t\t\t</div>\n"
        "\t\t\t<div class=\"icod-card\" style=\"margin-top:14px\">\n"
        "\t\t\t\t<h2><?php esc_html_e( 'Passerelle CIB / Edahabia', 'infinitycod' ); ?></h2>",
    ),
    (
        "\t\t\t<p class=\"description\"><?php esc_html_e( 'Téléversez les logos officiels CIB et Edahabia depuis votre médiathèque (Médiathèque → image → ID dans l’URL d’édition). Format conseillé : 260×164.', 'infinitycod' ); ?></p>",
        "\t\t\t<p class=\"description\"><?php esc_html_e( 'Téléversez les logos officiels CIB et Edahabia depuis votre médiathèque (Médiathèque → image → ID dans l’URL d’édition). Format conseillé : 260×164. Ces logos s’affichent quand la passerelle Chargily est active.', 'infinitycod' ); ?></p>",
    ),
])

# Cartes Stripe + PayPal : insérées juste après la fermeture du bloc logos CIB.
p = ROOT + BS + r"includes\admin\pages\class-settings-page.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
SKIP_INSERT = "Carte bancaire Visa / Mastercard — Stripe" in d
anchor = "Téléversez les logos officiels CIB et Edahabia"
if "Carte bancaire Visa / Mastercard — Stripe" in d:
    ok.append("cartes Stripe/PayPal déjà insérées")
    SKIP_INSERT = True
elif anchor not in d:
    raise SystemExit("ancre logos CIB introuvable")
else:
    i = d.index(anchor)
    i = d.index("\n", i) + 1  # fin de la ligne du paragraphe logos
    end = d.index("\t\t\t</div>\n", i)
    end = d.index("\n", end + len("\t\t\t</div>\n")) + 1
cards = (
    "\n"
    "\t\t<div class=\"icod-card\">\n"
    "\t\t\t<h2>💳 <?php esc_html_e( 'Carte bancaire Visa / Mastercard — Stripe', 'infinitycod' ); ?></h2>\n"
    "\t\t\t<p class=\"description\"><?php esc_html_e( 'Active le paiement par carte via Stripe Checkout (page de paiement hébergée par Stripe, 3-D Secure inclus). Nécessite un compte Stripe (pays supporté par Stripe) ; saisissez le taux de conversion si votre boutique est en DZD.', 'infinitycod' ); ?></p>\n"
    "\t\t\t<div class=\"icod-grid\">\n"
    "\t\t\t\t<label>\n"
    "\t\t\t\t\t<span><?php esc_html_e( 'Clé secrète Stripe (sk_live_… / sk_test_…)', 'infinitycod' ); ?></span>\n"
    "\t\t\t\t\t<input type=\"password\" name=\"icod[stripe_secret]\" value=\"\" dir=\"ltr\" autocomplete=\"new-password\" placeholder=\"<?php esc_attr_e( 'Laisser vide pour conserver la clé actuelle', 'infinitycod' ); ?>\" class=\"regular-text\" />\n"
    "\t\t\t\t</label>\n"
    "\t\t\t\t<label>\n"
    "\t\t\t\t\t<span><?php esc_html_e( 'Devise de facturation Stripe', 'infinitycod' ); ?></span>\n"
    "\t\t\t\t\t<input type=\"text\" name=\"icod[stripe_currency]\" dir=\"ltr\" value=\"<?php echo esc_attr( Settings::get( 'stripe_currency', 'usd' ) ); ?>\" placeholder=\"usd\" />\n"
    "\t\t\t\t</label>\n"
    "\t\t\t\t<label>\n"
    "\t\t\t\t\t<span><?php esc_html_e( 'Taux de conversion (1 DZD = X devise)', 'infinitycod' ); ?></span>\n"
    "\t\t\t\t\t<input type=\"number\" step=\"0.0001\" min=\"0\" name=\"icod[stripe_rate]\" value=\"<?php echo esc_attr( Settings::get( 'stripe_rate', 1 ) ); ?>\" />\n"
    "\t\t\t\t</label>\n"
    "\t\t\t</div>\n"
    "\t\t</div>\n\n"
    "\t\t<div class=\"icod-card\">\n"
    "\t\t\t<h2>🅿️ <?php esc_html_e( 'PayPal', 'infinitycod' ); ?></h2>\n"
    "\t\t\t<p class=\"description\"><?php esc_html_e( 'Active le paiement PayPal (compte PayPal du client). Nécessite un compte PayPal Business et un taux de conversion si votre boutique est en DZD.', 'infinitycod' ); ?></p>\n"
    "\t\t\t<div class=\"icod-grid\">\n"
    "\t\t\t\t<label>\n"
    "\t\t\t\t\t<span><?php esc_html_e( 'Client ID PayPal', 'infinitycod' ); ?></span>\n"
    "\t\t\t\t\t<input type=\"text\" name=\"icod[paypal_client_id]\" dir=\"ltr\" class=\"regular-text\" value=\"<?php echo esc_attr( Settings::get( 'paypal_client_id', '' ) ); ?>\" />\n"
    "\t\t\t\t</label>\n"
    "\t\t\t\t<label>\n"
    "\t\t\t\t\t<span><?php esc_html_e( 'Secret PayPal', 'infinitycod' ); ?></span>\n"
    "\t\t\t\t\t<input type=\"password\" name=\"icod[paypal_secret]\" dir=\"ltr\" autocomplete=\"new-password\" value=\"\" class=\"regular-text\" placeholder=\"<?php esc_attr_e( 'Laisser vide pour conserver le secret actuel', 'infinitycod' ); ?>\" />\n"
    "\t\t\t\t</label>\n"
    "\t\t\t\t<label>\n"
    "\t\t\t\t\t<span><?php esc_html_e( 'Mode', 'infinitycod' ); ?></span>\n"
    "\t\t\t\t\t<select name=\"icod[paypal_mode]\">\n"
    "\t\t\t\t\t\t<option value=\"live\" <?php selected( Settings::get( 'paypal_mode', 'live' ), 'live' ); ?>><?php esc_html_e( 'Live', 'infinitycod' ); ?></option>\n"
    "\t\t\t\t\t\t<option value=\"sandbox\" <?php selected( Settings::get( 'paypal_mode', 'live' ), 'sandbox' ); ?>><?php esc_html_e( 'Sandbox (test)', 'infinitycod' ); ?></option>\n"
    "\t\t\t\t\t</select>\n"
    "\t\t\t\t</label>\n"
    "\t\t\t\t<label>\n"
    "\t\t\t\t\t<span><?php esc_html_e( 'Devise de facturation PayPal', 'infinitycod' ); ?></span>\n"
    "\t\t\t\t\t<input type=\"text\" name=\"icod[paypal_currency]\" dir=\"ltr\" value=\"<?php echo esc_attr( Settings::get( 'paypal_currency', 'USD' ) ); ?>\" placeholder=\"USD\" />\n"
    "\t\t\t\t</label>\n"
    "\t\t\t\t<label>\n"
    "\t\t\t\t\t<span><?php esc_html_e( 'Taux de conversion (1 DZD = X devise)', 'infinitycod' ); ?></span>\n"
    "\t\t\t\t\t<input type=\"number\" step=\"0.0001\" min=\"0\" name=\"icod[paypal_rate]\" value=\"<?php echo esc_attr( Settings::get( 'paypal_rate', 1 ) ); ?>\" />\n"
    "\t\t\t\t</label>\n"
    "\t\t\t</div>\n"
    "\t\t</div>\n"
)
if not SKIP_INSERT:
    d = d[:end] + cards + d[end:]
    with io.open(p, "w", encoding="utf-8", newline="") as f:
        f.write(d)
ok.append("cartes Stripe/PayPal")

# ---------- 3) FORMULAIRE : libellés + logos selon la passerelle ----------
patch("includes/form/class-form-manager.php", [
    (
        "\t\t\t\t\t\t\t\t\t\t<span class=\"icod-mode-title\"><?php echo esc_html( Settings::get( 'payment_label' ) ); ?></span>\n"
        "\t\t\t\t\t\t\t\t\t\t<span class=\"icod-mode-sub\"><?php esc_html_e( 'Paiement sécurisé CIB / Edahabia', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t\t\t\t\t\t\t<span class=\"icod-paylogos\"><?php echo self::payment_logo( 'cib' ) . self::payment_logo( 'edahabia' ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG internes. ?></span>",
        "\t\t\t\t\t\t\t\t\t\t<span class=\"icod-mode-title\"><?php echo esc_html( Settings::get( 'payment_label' ) ); ?></span>\n"
        "\t\t\t\t\t\t\t\t\t\t<span class=\"icod-mode-sub\"><?php echo esc_html( \\InfinityCod\\Payment\\PaymentManager::gateway_sub_label() ); ?></span>\n"
        "\t\t\t\t\t\t\t\t\t\t<span class=\"icod-paylogos\"><?php echo self::payment_logos_active(); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG internes. ?></span>",
    ),
])

# payment_logos_active() + logos visa/mastercard/paypal.
p = ROOT + BS + r"includes\form\class-form-manager.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
anchor = "\tpublic static function payment_logo( $method ) {"
add = (
    "\t/**\n"
    "\t * Logos de la passerelle de paiement active (formulaire).\n"
    "\t *\n"
    "\t * @return string HTML.\n"
    "\t */\n"
    "\tpublic static function payment_logos_active() {\n"
    "\t\t$gateway = \\InfinityCod\\Payment\\PaymentManager::gateway();\n"
    "\t\tif ( 'stripe' === $gateway ) {\n"
    "\t\t\treturn self::payment_logo( 'visa' ) . self::payment_logo( 'mastercard' );\n"
    "\t\t}\n"
    "\t\tif ( 'paypal' === $gateway ) {\n"
    "\t\t\treturn self::payment_logo( 'paypal' );\n"
    "\t\t}\n"
    "\t\treturn self::payment_logo( 'cib' ) . self::payment_logo( 'edahabia' );\n"
    "\t}\n\n"
    "\t"
)
assert d.count(anchor) == 1
d = d.replace(anchor, add + anchor, 1)

# Logos par défaut visa / mastercard / paypal (SVG intégrés).
old_switch = "\t\t\tcase 'baridimob':"
new_switch = (
    "\t\t\tcase 'visa':\n"
    "\t\t\t\treturn '<svg ' . $common . '>'\n"
    "\t\t\t\t\t. '<rect x=\"1\" y=\"1\" width=\"62\" height=\"38\" rx=\"6\" fill=\"#ffffff\" stroke=\"#d8dde3\"/>'\n"
    "\t\t\t\t\t. '<text x=\"22\" y=\"27\" text-anchor=\"middle\" font-family=\"Arial, sans-serif\" font-weight=\"bold\" font-style=\"italic\" font-size=\"14\" fill=\"#1a1f71\">VISA</text>'\n"
    "\t\t\t\t\t. '</svg>';\n\n"
    "\t\t\tcase 'mastercard':\n"
    "\t\t\t\treturn '<svg ' . $common . '>'\n"
    "\t\t\t\t\t. '<rect x=\"1\" y=\"1\" width=\"62\" height=\"38\" rx=\"6\" fill=\"#ffffff\" stroke=\"#d8dde3\"/>'\n"
    "\t\t\t\t\t. '<circle cx=\"26\" cy=\"20\" r=\"11\" fill=\"#eb001b\"/>'\n"
    "\t\t\t\t\t. '<circle cx=\"38\" cy=\"20\" r=\"11\" fill=\"#f79e1b\" opacity=\".92\"/>'\n"
    "\t\t\t\t\t. '</svg>';\n\n"
    "\t\t\tcase 'paypal':\n"
    "\t\t\t\treturn '<svg ' . $common . '>'\n"
    "\t\t\t\t\t. '<rect x=\"1\" y=\"1\" width=\"62\" height=\"38\" rx=\"6\" fill=\"#ffffff\" stroke=\"#d8dde3\"/>'\n"
    "\t\t\t\t\t. '<text x=\"32\" y=\"21\" text-anchor=\"middle\" font-family=\"Arial, sans-serif\" font-weight=\"bold\" font-style=\"italic\" font-size=\"11\" fill=\"#003087\">Pay</text>'\n"
    "\t\t\t\t\t. '<text x=\"47\" y=\"21\" text-anchor=\"middle\" font-family=\"Arial, sans-serif\" font-weight=\"bold\" font-style=\"italic\" font-size=\"11\" fill=\"#009cde\">Pal</text>'\n"
    "\t\t\t\t\t. '</svg>';\n\n"
    "\t\t\tcase 'baridimob':"
)
assert d.count(old_switch) == 1
d = d.replace(old_switch, new_switch, 1)
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
ok.append("form-manager logos + libellés")

print("\n".join("OK  " + o for o in ok))
