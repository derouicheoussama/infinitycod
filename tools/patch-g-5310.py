# -*- coding: utf-8 -*-
"""Patch 5.31.0 quinquies : schéma réglages + cartes UI (A/B, intégrations, export/import)."""
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
        assert n == 1, rel + " :: " + old[:70].replace("\n", "⏎") + " -> " + str(n)
        d = d.replace(old, new, 1)
    with io.open(p, "w", encoding="utf-8", newline="") as f:
        f.write(d)
    ok.append(rel + " (" + str(len(pairs)) + ")")


# ---------- SCHEMA : nouvelles clés ----------
patch("includes/admin/pages/class-settings-page.php", [
    (
        "\t\t\t'sticky_bar'         => array( 'tab' => 'form', 'type' => 'toggle' ),",
        "\t\t\t'sticky_bar'         => array( 'tab' => 'form', 'type' => 'toggle' ),\n"
        "\t\t\t'show_delivery_days' => array( 'tab' => 'form', 'type' => 'toggle' ),\n"
        "\t\t\t'abtest_enabled'     => array( 'tab' => 'form', 'type' => 'toggle' ),\n"
        "\t\t\t'ab_b_title'         => array( 'tab' => 'form', 'type' => 'text' ),\n"
        "\t\t\t'ab_b_button'        => array( 'tab' => 'form', 'type' => 'text' ),\n"
        "\t\t\t'ab_b_accent'        => array( 'tab' => 'form', 'type' => 'color' ),",
    ),
    (
        "\t\t\t'countries'            => array( 'tab' => 'advanced', 'type' => 'countries' ),",
        "\t\t\t'countries'            => array( 'tab' => 'advanced', 'type' => 'countries' ),\n"
        "\t\t\t'webhook_url'          => array( 'tab' => 'advanced', 'type' => 'url' ),\n"
        "\t\t\t'webhook_secret'       => array( 'tab' => 'advanced', 'type' => 'secret' ),\n"
        "\t\t\t'webhook_on_status'    => array( 'tab' => 'advanced', 'type' => 'toggle' ),\n"
        "\t\t\t'community_blacklist'  => array( 'tab' => 'advanced', 'type' => 'toggle' ),",
    ),
])

# ---------- UI FORM TAB : carte A/B (après le bloc sticky_bar) ----------
patch("includes/admin/pages/class-settings-page.php", [
    (
        "\t\t\t\t<label class=\"icod-toggle\">\n"
        "\t\t\t\t\t<input type=\"checkbox\" name=\"icod[sticky_bar]\" value=\"1\" <?php checked( (int) Settings::get( 'sticky_bar' ), 1 ); ?> />\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'Barre « Commander maintenant » collante sur mobile — pleine largeur, récapitulatif + total + bouton toujours visibles', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t</label>\n"
        "\t\t\t</div>",
        "\t\t\t\t<label class=\"icod-toggle\">\n"
        "\t\t\t\t\t<input type=\"checkbox\" name=\"icod[sticky_bar]\" value=\"1\" <?php checked( (int) Settings::get( 'sticky_bar' ), 1 ); ?> />\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'Barre « Commander maintenant » collante sur mobile — pleine largeur, récapitulatif + total + bouton toujours visibles', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t</label>\n"
        "\t\t\t\t<label class=\"icod-toggle\">\n"
        "\t\t\t\t\t<input type=\"checkbox\" name=\"icod[show_delivery_days]\" value=\"1\" <?php checked( (int) Settings::get( 'show_delivery_days', 1 ), 1 ); ?> />\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'Afficher le délai de livraison estimé (donnée saisie par wilaya dans Géo & Tarifs)', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t</label>\n"
        "\t\t\t</div>\n\n"
        "\t\t\t<div class=\"icod-card\" style=\"margin-top:16px\">\n"
        "\t\t\t\t<h2>🧪 <?php esc_html_e( 'A/B test du formulaire', 'infinitycod' ); ?></h2>\n"
        "\t\t\t\t<p class=\"description\"><?php esc_html_e( 'Les visiteurs reçoivent aléatoirement la variante A (vos réglages actuels) ou la variante B ci-dessous — le même visiteur garde toujours la même variante. Le taux de conversion par variante s’affiche dans Statistiques P&L.', 'infinitycod' ); ?></p>\n"
        "\t\t\t\t<div class=\"icod-toggles\">\n"
        "\t\t\t\t\t<label class=\"icod-toggle\">\n"
        "\t\t\t\t\t\t<input type=\"checkbox\" name=\"icod[abtest_enabled]\" value=\"1\" <?php checked( (int) Settings::get( 'abtest_enabled' ), 1 ); ?> />\n"
        "\t\t\t\t\t\t<span><?php esc_html_e( 'Activer le test A/B (50 / 50)', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t\t</label>\n"
        "\t\t\t\t</div>\n"
        "\t\t\t\t<div class=\"icod-grid\">\n"
        "\t\t\t\t\t<label>\n"
        "\t\t\t\t\t\t<span><?php esc_html_e( 'Variante B — titre', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t\t\t<input type=\"text\" name=\"icod[ab_b_title]\" value=\"<?php echo esc_attr( Settings::get( 'ab_b_title', '' ) ); ?>\" placeholder=\"<?php esc_attr_e( 'Vide = même titre que la variante A', 'infinitycod' ); ?>\" />\n"
        "\t\t\t\t\t</label>\n"
        "\t\t\t\t\t<label>\n"
        "\t\t\t\t\t\t<span><?php esc_html_e( 'Variante B — texte du bouton', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t\t\t<input type=\"text\" name=\"icod[ab_b_button]\" value=\"<?php echo esc_attr( Settings::get( 'ab_b_button', '' ) ); ?>\" placeholder=\"<?php esc_attr_e( 'Vide = même bouton que la variante A', 'infinitycod' ); ?>\" />\n"
        "\t\t\t\t\t</label>\n"
        "\t\t\t\t\t<label>\n"
        "\t\t\t\t\t\t<span><?php esc_html_e( 'Variante B — couleur d’accent', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t\t\t<input type=\"color\" name=\"icod[ab_b_accent]\" value=\"<?php echo esc_attr( Settings::get( 'ab_b_accent', '' ) ); ?>\" />\n"
        "\t\t\t\t\t</label>\n"
        "\t\t\t\t</div>\n"
        "\t\t\t</div>",
    ),
])

# ---------- UI ADVANCED TAB : carte intégrations + export/import ----------
patch("includes/admin/pages/class-settings-page.php", [
    (
        "\t\t<div class=\"icod-card\">\n"
        "\t\t\t<h2><?php esc_html_e( 'Devise', 'infinitycod' ); ?></h2>",
        "\t\t<div class=\"icod-card\">\n"
        "\t\t\t<h2>🔗 <?php esc_html_e( 'Intégrations & automatisation', 'infinitycod' ); ?></h2>\n"
        "\t\t\t<p class=\"description\"><?php esc_html_e( 'Webhook appelé à chaque commande (Google Sheets via Apps Script, Zapier, Make, CRM). Signature HMAC-SHA256 dans l’en-tête X-InfinityCod-Signature. Les webhooks Discord/Telegram de nouvelle commande se règlent dans l’onglet Formulaire.', 'infinitycod' ); ?></p>\n"
        "\t\t\t<div class=\"icod-grid\">\n"
        "\t\t\t\t<label>\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'URL du webhook', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t\t<input type=\"url\" name=\"icod[webhook_url]\" dir=\"ltr\" class=\"regular-text\" value=\"<?php echo esc_attr( Settings::get( 'webhook_url', '' ) ); ?>\" placeholder=\"https://script.google.com/…\" />\n"
        "\t\t\t\t</label>\n"
        "\t\t\t\t<label>\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'Secret de signature', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t\t<input type=\"password\" name=\"icod[webhook_secret]\" dir=\"ltr\" autocomplete=\"new-password\" value=\"\" class=\"regular-text\" placeholder=\"<?php esc_attr_e( 'Laisser vide pour conserver le secret actuel', 'infinitycod' ); ?>\" />\n"
        "\t\t\t\t</label>\n"
        "\t\t\t</div>\n"
        "\t\t\t<div class=\"icod-toggles\">\n"
        "\t\t\t\t<label class=\"icod-toggle\">\n"
        "\t\t\t\t\t<input type=\"checkbox\" name=\"icod[webhook_on_status]\" value=\"1\" <?php checked( (int) Settings::get( 'webhook_on_status' ), 1 ); ?> />\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'Notifier aussi les changements de statut (confirmée, expédiée, livrée…)', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t</label>\n"
        "\t\t\t\t<label class=\"icod-toggle\">\n"
        "\t\t\t\t\t<input type=\"checkbox\" name=\"icod[community_blacklist]\" value=\"1\" <?php checked( (int) Settings::get( 'community_blacklist' ), 1 ); ?> />\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'Blacklist communautaire : penaliser les numéros signalés par d’autres boutiques InfinityCod (numéros hachés, opt-in)', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t</label>\n"
        "\t\t\t</div>\n"
        "\t\t\t<div class=\"icod-grid\" style=\"margin-top:10px\">\n"
        "\t\t\t\t<label>\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'Exporter la configuration (JSON)', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t\t<a class=\"button\" href=\"<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=icod_settings_export' ), 'icod_settings_io' ) ); ?>\">⬇️ <?php esc_html_e( 'Télécharger', 'infinitycod' ); ?></a>\n"
        "\t\t\t\t</label>\n"
        "\t\t\t\t<label>\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'Importer une configuration', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t\t<form method=\"post\" enctype=\"multipart/form-data\" action=\"<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>\" style=\"display:flex;gap:8px;align-items:center\">\n"
        "\t\t\t\t\t\t<input type=\"hidden\" name=\"action\" value=\"icod_settings_import\" />\n"
        "\t\t\t\t\t\t<?php wp_nonce_field( 'icod_settings_io' ); ?>\n"
        "\t\t\t\t\t\t<input type=\"file\" name=\"icod_settings_json\" accept=\".json\" required />\n"
        "\t\t\t\t\t\t<button type=\"submit\" class=\"button\">⬆️ <?php esc_html_e( 'Importer', 'infinitycod' ); ?></button>\n"
        "\t\t\t\t\t</form>\n"
        "\t\t\t\t</label>\n"
        "\t\t\t</div>\n"
        "\t\t</div>\n\n"
        "\t\t<div class=\"icod-card\">\n"
        "\t\t\t<h2><?php esc_html_e( 'Devise', 'infinitycod' ); ?></h2>",
    ),
])
ok.append("settings-page : schéma + cartes UI")

print("\n".join("OK  " + o for o in ok))
