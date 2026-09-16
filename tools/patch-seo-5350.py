# -*- coding: utf-8 -*-
"""Patch 5.35.0 : réglages SEO/GEO (LocalBusiness, FAQ, llms.txt) + carte UI + harnais."""
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


# ---------- 1) DEFAULTS ----------
patch("includes/core/class-settings.php", [
    (
        "\t\t\t'integrity_check'      => 1,   // Vérification d'intégrité des fichiers cœur.",
        "\t\t\t'integrity_check'      => 1,   // Vérification d'intégrité des fichiers cœur.\n\n"
        "\t\t\t// SEO & GEO (référencement + moteurs génératifs).\n"
        "\t\t\t'seo_local_enabled'    => 0,  // LocalBusiness + zones desservies en JSON-LD.\n"
        "\t\t\t'seo_business_city'    => '',  // Ville du marchand (adresse LocalBusiness).\n"
        "\t\t\t'seo_business_phone'   => '',  // Téléphone public (LocalBusiness + llms.txt).\n"
        "\t\t\t'seo_faq'              => '',  // FAQ produit : lignes « question | réponse ».\n"
        "\t\t\t'seo_llms_enabled'     => 1,   // /llms.txt pour les moteurs génératifs.",
    ),
])

# ---------- 2) SCHEMA + CARTE UI (avancé) ----------
patch("includes/admin/pages/class-settings-page.php", [
    (
        "\t\t\t'integrity_check'      => array( 'tab' => 'advanced', 'type' => 'toggle' ),",
        "\t\t\t'integrity_check'      => array( 'tab' => 'advanced', 'type' => 'toggle' ),\n"
        "\t\t\t'seo_local_enabled'    => array( 'tab' => 'advanced', 'type' => 'toggle' ),\n"
        "\t\t\t'seo_business_city'    => array( 'tab' => 'advanced', 'type' => 'text' ),\n"
        "\t\t\t'seo_business_phone'   => array( 'tab' => 'advanced', 'type' => 'text' ),\n"
        "\t\t\t'seo_faq'              => array( 'tab' => 'advanced', 'type' => 'textarea' ),\n"
        "\t\t\t'seo_llms_enabled'     => array( 'tab' => 'advanced', 'type' => 'toggle' ),",
    ),
    (
        "\t\t<div class=\"icod-card\">\n"
        "\t\t\t<h2><?php esc_html_e( 'Devise', 'infinitycod' ); ?></h2>",
        "\t\t<div class=\"icod-card\">\n"
        "\t\t\t<h2>🌍 <?php esc_html_e( 'SEO & GEO (référencement + moteurs IA)', 'infinitycod' ); ?></h2>\n"
        "\t\t\t<p class=\"description\"><?php esc_html_e( 'Données structurées produit (prix, livraison, avis), fiche LocalBusiness avec zones desservies, FAQ en JSON-LD et fichier /llms.txt pour ChatGPT, Perplexity et les AI Overviews. Les pages « livraison-{wilaya} » sont automatiquement optimisées.', 'infinitycod' ); ?></p>\n"
        "\t\t\t<div class=\"icod-toggles\">\n"
        "\t\t\t\t<label class=\"icod-toggle\">\n"
        "\t\t\t\t\t<input type=\"checkbox\" name=\"icod[seo_local_enabled]\" value=\"1\" <?php checked( (int) Settings::get( 'seo_local_enabled' ), 1 ); ?> />\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'Fiche LocalBusiness JSON-LD avec les zones desservies (toutes les wilayas actives)', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t</label>\n"
        "\t\t\t\t<label class=\"icod-toggle\">\n"
        "\t\t\t\t\t<input type=\"checkbox\" name=\"icod[seo_llms_enabled]\" value=\"1\" <?php checked( (int) Settings::get( 'seo_llms_enabled', 1 ), 1 ); ?> />\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'Fichier /llms.txt (GEO : rendre la boutique citable par ChatGPT, Perplexity, AI Overviews)', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t</label>\n"
        "\t\t\t</div>\n"
        "\t\t\t<div class=\"icod-grid\">\n"
        "\t\t\t\t<label>\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'Ville du marchand', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t\t<input type=\"text\" name=\"icod[seo_business_city]\" value=\"<?php echo esc_attr( Settings::get( 'seo_business_city', '' ) ); ?>\" placeholder=\"Alger\" />\n"
        "\t\t\t\t</label>\n"
        "\t\t\t\t<label>\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'Téléphone public (LocalBusiness + llms.txt)', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t\t<input type=\"text\" name=\"icod[seo_business_phone]\" dir=\"ltr\" value=\"<?php echo esc_attr( Settings::get( 'seo_business_phone', '' ) ); ?>\" placeholder=\"0555 00 00 00\" />\n"
        "\t\t\t\t</label>\n"
        "\t\t\t\t<label class=\"icod-m-full\">\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'FAQ produit — une ligne par question au format « question | réponse » (JSON-LD FAQPage + affichage sur les landings wilaya)', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t\t<textarea name=\"icod[seo_faq]\" rows=\"4\" placeholder=\"Livrez-vous à Alger ? | Oui, en 24-48h, paiement à la livraison.\"></textarea>\n"
        "\t\t\t\t</label>\n"
        "\t\t\t</div>\n"
        "\t\t</div>\n\n"
        "\t\t<div class=\"icod-card\">\n"
        "\t\t\t<h2><?php esc_html_e( 'Devise', 'infinitycod' ); ?></h2>",
    ),
])

print("\n".join("OK  " + o for o in ok))
