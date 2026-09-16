# -*- coding: utf-8 -*-
"""Patch 5.33.0 — Anti-leak : pixels, masquage téléphone, journal exports, intégrité, réglages."""
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
            continue
        assert n == 1, rel + " :: " + old[:70].replace("\n", "⏎") + " -> " + str(n)
        d = d.replace(old, new, 1)
    with io.open(p, "w", encoding="utf-8", newline="") as f:
        f.write(d)
    ok.append(rel)


def append(rel, text):
    p = ROOT + BS + rel.replace("/", BS)
    with io.open(p, "a", encoding="utf-8", newline="") as f:
        f.write(text)
    ok.append(rel + " (append)")


# ---------- 1) PIXELS JS : garde anti-leak sur l'achat navigateur ----------
patch("assets/front/js/pixels.js", [
    (
        "\twindow.icodFirePurchase = function (orderId, total, productName, phone) {\n"
        "\t\tvar eventId = 'icod-' + orderId;",
        "\twindow.icodFirePurchase = function (orderId, total, productName, phone) {\n"
        "\t\t/* Anti-leak : l'achat navigateur transmet l'identité du client au pixel\n"
        "\t\t   (Advanced Matching) et nourrit les audiences. Quand le garde est actif,\n"
        "\t\t   seul l'événement serveur (Conversions API, données hachées) part : les\n"
        "\t\t   campagnes continuent d'optimiser, mais aucun acheteur n'est exposé. */\n"
        "\t\tif (CFG.antileak) { return; }\n"
        "\t\tvar eventId = 'icod-' + orderId;",
    ),
])

# Localize : flag antileak.
patch("includes/tracking/class-pixel-manager.php", [
    (
        "\t\t\t'consent'   => (int) Settings::get( 'pixel_consent_required' ),",
        "\t\t\t'consent'   => (int) Settings::get( 'pixel_consent_required' ),\n"
        "\t\t\t'antileak'  => (bool) Settings::get( 'antileak_pixels' ),",
    ),
])

# ---------- 2) DEFAULTS ----------
patch("includes/core/class-settings.php", [
    (
        "\t\t\t// Blacklist communautaire (opt-in, numéros hashés).\n"
        "\t\t\t'community_blacklist'  => 0,",
        "\t\t\t// Blacklist communautaire (opt-in, numéros hashés).\n"
        "\t\t\t'community_blacklist'  => 0,\n\n"
        "\t\t\t// Anti-leak : confidentialité des clients et des exports.\n"
        "\t\t\t'antileak_pixels'      => 0,  // Achat navigateur désactivé, CAPI seule.\n"
        "\t\t\t'mask_phones'          => 0,  // Téléphones masqués dans la liste Commandes.\n"
        "\t\t\t'export_alert_min'     => 200, // Alerte admin dès N lignes exportées (0 = jamais).\n"
        "\t\t\t'integrity_check'      => 1,   // Vérification d'intégrité des fichiers cœur.",
    ),
])

# ---------- 3) SCHEMA RÉGLAGES + CARTE UI (avancé) ----------
patch("includes/admin/pages/class-settings-page.php", [
    (
        "\t\t\t'community_blacklist'  => array( 'tab' => 'advanced', 'type' => 'toggle' ),",
        "\t\t\t'community_blacklist'  => array( 'tab' => 'advanced', 'type' => 'toggle' ),\n"
        "\t\t\t'antileak_pixels'      => array( 'tab' => 'advanced', 'type' => 'toggle' ),\n"
        "\t\t\t'mask_phones'          => array( 'tab' => 'advanced', 'type' => 'toggle' ),\n"
        "\t\t\t'export_alert_min'     => array( 'tab' => 'advanced', 'type' => 'int', 'min' => 0, 'max' => 10000 ),\n"
        "\t\t\t'integrity_check'      => array( 'tab' => 'advanced', 'type' => 'toggle' ),",
    ),
    (
        "\t\t<div class=\"icod-card\">\n"
        "\t\t\t<h2><?php esc_html_e( 'Devise', 'infinitycod' ); ?></h2>",
        "\t\t<div class=\"icod-card\">\n"
        "\t\t\t<h2>🕵️ <?php esc_html_e( 'Anti-leak : confidentialité clients & exports', 'infinitycod' ); ?></h2>\n"
        "\t\t\t<p class=\"description\"><?php esc_html_e( 'Empêche la fuite de vos clients et de votre base. Garde pixel : l’achat part uniquement via la Conversions API (données hachées serveur) — les campagnes continuent d’optimiser sans exposer les acheteurs aux audiences navigateur. Masquage des téléphones dans la liste Commandes. Journal et alertes des exports ci-dessous.', 'infinitycod' ); ?></p>\n"
        "\t\t\t<div class=\"icod-toggles\">\n"
        "\t\t\t\t<label class=\"icod-toggle\">\n"
        "\t\t\t\t\t<input type=\"checkbox\" name=\"icod[antileak_pixels]\" value=\"1\" <?php checked( (int) Settings::get( 'antileak_pixels' ), 1 ); ?> />\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'Garde pixel : plus d’achat navigateur envoyé à Meta/TikTok (CAPI serveur uniquement)', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t</label>\n"
        "\t\t\t\t<label class=\"icod-toggle\">\n"
        "\t\t\t\t\t<input type=\"checkbox\" name=\"icod[mask_phones]\" value=\"1\" <?php checked( (int) Settings::get( 'mask_phones' ), 1 ); ?> />\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'Masquer les téléphones dans la liste Commandes (le numéro complet reste dans la fiche détaillée)', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t</label>\n"
        "\t\t\t\t<label class=\"icod-toggle\">\n"
        "\t\t\t\t\t<input type=\"checkbox\" name=\"icod[integrity_check]\" value=\"1\" <?php checked( (int) Settings::get( 'integrity_check', 1 ), 1 ); ?> />\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'Vérifier l’intégrité des fichiers du plugin (détection de copie piratée/modifiée)', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t</label>\n"
        "\t\t\t</div>\n"
        "\t\t\t<div class=\"icod-grid\">\n"
        "\t\t\t\t<label>\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'Alerte e-mail dès un export de (lignes)', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t\t<input type=\"number\" min=\"0\" max=\"10000\" step=\"50\" name=\"icod[export_alert_min]\" value=\"<?php echo esc_attr( Settings::get( 'export_alert_min', 200 ) ); ?>\" />\n"
        "\t\t\t\t</label>\n"
        "\t\t\t</div>\n"
        "\t\t\t<?php $icod_log = \\InfinityCod\\Core\\AntiLeak::get_log(); ?>\n"
        "\t\t\t<?php if ( $icod_log ) : ?>\n"
        "\t\t\t<h4 style=\"margin:14px 0 6px\"><?php esc_html_e( 'Journal des exports (50 derniers)', 'infinitycod' ); ?></h4>\n"
        "\t\t\t<table class=\"widefat striped\" style=\"max-width:760px\">\n"
        "\t\t\t\t<thead><tr><th><?php esc_html_e( 'Trace', 'infinitycod' ); ?></th><th><?php esc_html_e( 'Utilisateur', 'infinitycod' ); ?></th><th><?php esc_html_e( 'Type', 'infinitycod' ); ?></th><th><?php esc_html_e( 'Lignes', 'infinitycod' ); ?></th><th><?php esc_html_e( 'Date', 'infinitycod' ); ?></th></tr></thead>\n"
        "\t\t\t\t<tbody>\n"
        "\t\t\t\t<?php foreach ( array_slice( $icod_log, 0, 50 ) as $entry ) : ?>\n"
        "\t\t\t\t\t<tr><td><code><?php echo esc_html( $entry['trace'] ); ?></code></td><td><?php echo esc_html( $entry['user'] ); ?></td><td><?php echo esc_html( $entry['type'] ); ?></td><td><?php echo (int) $entry['count']; ?></td><td><?php echo esc_html( $entry['date'] ); ?></td></tr>\n"
        "\t\t\t\t<?php endforeach; ?>\n"
        "\t\t\t\t</tbody>\n"
        "\t\t\t</table>\n"
        "\t\t\t<?php endif; ?>\n"
        "\t\t</div>\n\n"
        "\t\t<div class=\"icod-card\">\n"
        "\t\t\t<h2><?php esc_html_e( 'Devise', 'infinitycod' ); ?></h2>",
    ),
])

# ---------- 4) MODULE ANITLEAK ----------
patch("includes/core/class-plugin.php", [
    (
        "\t\t'webhooks'  => '\\\\InfinityCod\\\\Core\\\\Webhooks',",
        "\t\t'webhooks'  => '\\\\InfinityCod\\\\Core\\\\Webhooks',\n"
        "\t\t'antileak'  => '\\\\InfinityCod\\\\Core\\\\AntiLeak',",
    ),
])

# ---------- 5) MASQUAGE TÉLÉPHONE DANS LA LISTE ----------
patch("includes/admin/pages/class-orders-page.php", [
    (
        "\t\t\t\t\t<span class=\"icod-sub\"><?php echo esc_html( $row['phone'] ); ?></span>\n"
        "\t\t\t\t</span>\n"
        "\t\t\t</div>\n"
        "\t\t</td>",
        "\t\t\t\t\t<span class=\"icod-sub\"><?php echo esc_html( \\InfinityCod\\Core\\AntiLeak::mask_phone( (string) $row['phone'] ) ); ?></span>\n"
        "\t\t\t\t</span>\n"
        "\t\t\t</div>\n"
        "\t\t</td>",
    ),
])

# Méthode mask_phone dans AntiLeak.
p = ROOT + BS + r"includes\core\class-antileak.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
anchor = "\t/**\n\t * Code de traçabilité court d'un export (journal + colonne du fichier)."
add = (
    "\t/**\n"
    "\t * Masque un téléphone (setting anti-leak) : 0770••••56.\n"
    "\t *\n"
    "\t * @param string $phone Téléphone brut.\n"
    "\t * @return string\n"
    "\t */\n"
    "\tpublic static function mask_phone( $phone ) {\n"
    "\t\tif ( ! Settings::get( 'mask_phones' ) ) {\n"
    "\t\t\treturn (string) $phone;\n"
    "\t\t}\n"
    "\t\t$digits = preg_replace( '/[^0-9]/', '', (string) $phone );\n"
    "\t\tif ( strlen( $digits ) < 6 ) {\n"
    "\t\t\treturn '••••••';\n"
    "\t\t}\n"
    "\t\treturn substr( $digits, 0, 4 ) . '••••' . substr( $digits, -2 );\n"
    "\t}\n\n"
    "\t"
)
assert d.count(anchor) == 1
d = d.replace(anchor, add + anchor, 1)
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
ok.append("antileak mask_phone")

print("\n".join("OK  " + o for o in ok))
