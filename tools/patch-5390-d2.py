# -*- coding: utf-8 -*-
"""Patch 5.39.0 D : cartes réglages (suivi, facture, stock, WA quotidien, fidélité)."""
import io

ROOT = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod"
BS = chr(92)
Q = chr(34)
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


def L(s):
    return Q + s.replace(BS, BS + BS).replace(Q, BS + Q) + BS + Q + "\n"


CARDS = (
    L("\t\t<div class=\"icod-card\">")
    + L("\t\t\t<h2>📦 <?php esc_html_e( 'Portail de suivi client', 'infinitycod' ); ?></h2>")
    + L("\t\t\t<p class=\"description\"><?php esc_html_e( 'Vos clients suivent leur commande en direct sur /suivi-commande/ (numéro + téléphone). Moins d’appels, plus d’autonomie.', 'infinitycod' ); ?></p>")
    + L("\t\t\t<div class=\"icod-toggles\">")
    + L("\t\t\t\t<label class=\"icod-toggle\">")
    + L("\t\t\t\t\t<input type=\"checkbox\" name=\"icod[track_portal_enabled]\" value=\"1\" <?php checked( (int) Settings::get( 'track_portal_enabled', 1 ), 1 ); ?> />")
    + L("\t\t\t\t\t<span><?php esc_html_e( 'Activer le portail de suivi public /suivi-commande/', 'infinitycod' ); ?></span>")
    + L("\t\t\t\t</label>")
    + L("\t\t\t</div>")
    + L("\t\t</div>")
    + L("")
    + L("\t\t<div class=\"icod-card\">")
    + L("\t\t\t<h2>🧾 <?php esc_html_e( 'Facture PDF', 'infinitycod' ); ?></h2>")
    + L("\t\t\t<div class=\"icod-toggles\">")
    + L("\t\t\t\t<label class=\"icod-toggle\">")
    + L("\t\t\t\t\t<input type=\"checkbox\" name=\"icod[invoice_enabled]\" value=\"1\" <?php checked( (int) Settings::get( 'invoice_enabled', 1 ), 1 ); ?> />")
    + L("\t\t\t\t\t<span><?php esc_html_e( 'Facture PDF téléchargeable (bouton « Facture » dans la fiche commande)', 'infinitycod' ); ?></span>")
    + L("\t\t\t\t</label>")
    + L("\t\t\t\t<label class=\"icod-toggle\">")
    + L("\t\t\t\t\t<input type=\"checkbox\" name=\"icod[invoice_email]\" value=\"1\" <?php checked( (int) Settings::get( 'invoice_email', 1 ), 1 ); ?> />")
    + L("\t\t\t\t\t<span><?php esc_html_e( 'Joindre automatiquement la facture à l’e-mail de confirmation (si le client a laissé son e-mail)', 'infinitycod' ); ?></span>")
    + L("\t\t\t\t</label>")
    + L("\t\t\t</div>")
    + L("\t\t</div>")
    + L("")
    + L("\t\t<div class=\"icod-card\">")
    + L("\t\t\t<h2>📦 <?php esc_html_e( 'Alertes de stock', 'infinitycod' ); ?></h2>")
    + L("\t\t\t<p class=\"description\"><?php esc_html_e( 'Badge « Bientôt épuisé » sur le formulaire + e-mail quotidien récapitulatif des produits sous le seuil.', 'infinitycod' ); ?></p>")
    + L("\t\t\t<div class=\"icod-grid\">")
    + L("\t\t\t\t<label>")
    + L("\t\t\t\t\t<span><?php esc_html_e( 'Seuil d’alerte (quantité, 0 = désactivé)', 'infinitycod' ); ?></span>")
    + L("\t\t\t\t\t<input type=\"number\" min=\"0\" max=\"1000\" name=\"icod[stock_alert_threshold]\" value=\"<?php echo esc_attr( Settings::get( 'stock_alert_threshold', 0 ) ); ?>\" />")
    + L("\t\t\t\t</label>")
    + L("\t\t\t</div>")
    + L("\t\t</div>")
    + L("")
    + L("\t\t<div class=\"icod-card\">")
    + L("\t\t\t<h2>📲 <?php esc_html_e( 'Rapport quotidien WhatsApp', 'infinitycod' ); ?></h2>")
    + L("\t\t\t<p class=\"description\"><?php esc_html_e( 'Chaque matin : commandes du jour et chiffre d’affaires envoyés sur votre propre WhatsApp (nécessite une passerelle WhatsApp configurée dans l’onglet Formulaire).', 'infinitycod' ); ?></p>")
    + L("\t\t\t<div class=\"icod-toggles\">")
    + L("\t\t\t\t<label class=\"icod-toggle\">")
    + L("\t\t\t\t\t<input type=\"checkbox\" name=\"icod[wa_daily_report]\" value=\"1\" <?php checked( (int) Settings::get( 'wa_daily_report' ), 1 ); ?> />")
    + L("\t\t\t\t\t<span><?php esc_html_e( 'Recevoir le rapport quotidien sur WhatsApp', 'infinitycod' ); ?></span>")
    + L("\t\t\t\t</label>")
    + L("\t\t\t</div>")
    + L("\t\t\t<div class=\"icod-grid\">")
    + L("\t\t\t\t<label>")
    + L("\t\t\t\t\t<span><?php esc_html_e( 'Votre numéro WhatsApp (format international, ex. 2136…)', 'infinitycod' ); ?></span>")
    + L("\t\t\t\t\t<input type=\"text\" name=\"icod[wa_owner_phone]\" dir=\"ltr\" value=\"<?php echo esc_attr( Settings::get( 'wa_owner_phone', '' ) ); ?>\" />")
    + L("\t\t\t\t</label>")
    + L("\t\t\t</div>")
    + L("\t\t</div>")
    + L("")
    + L("\t\t<div class=\"icod-card\">")
    + L("\t\t\t<h2>💛 <?php esc_html_e( 'Fidélité', 'infinitycod' ); ?></h2>")
    + L("\t\t\t<p class=\"description\"><?php esc_html_e( 'Points gagnés sur les commandes livrées ; si le client récommande avec le même numéro et a assez de points, la remise s’applique automatiquement (max 30 % du sous-total).', 'infinitycod' ); ?></p>")
    + L("\t\t\t<div class=\"icod-toggles\">")
    + L("\t\t\t\t<label class=\"icod-toggle\">")
    + L("\t\t\t\t\t<input type=\"checkbox\" name=\"icod[loyalty_enabled]\" value=\"1\" <?php checked( (int) Settings::get( 'loyalty_enabled' ), 1 ); ?> />")
    + L("\t\t\t\t\t<span><?php esc_html_e( 'Activer la remise fidélité automatique', 'infinitycod' ); ?></span>")
    + L("\t\t\t\t</label>")
    + L("\t\t\t</div>")
    + L("\t\t\t<div class=\"icod-grid\">")
    + L("\t\t\t\t<label>")
    + L("\t\t\t\t\t<span><?php esc_html_e( '1 point par (DA livrés)', 'infinitycod' ); ?></span>")
    + L("\t\t\t\t\t<input type=\"number\" min=\"100\" step=\"100\" name=\"icod[loyalty_point_da]\" value=\"<?php echo esc_attr( Settings::get( 'loyalty_point_da', 1000 ) ); ?>\" />")
    + L("\t\t\t\t</label>")
    + L("\t\t\t\t<label>")
    + L("\t\t\t\t\t<span><?php esc_html_e( 'Remise par point (DA)', 'infinitycod' ); ?></span>")
    + L("\t\t\t\t\t<input type=\"number\" min=\"1\" name=\"icod[loyalty_point_value]\" value=\"<?php echo esc_attr( Settings::get( 'loyalty_point_value', 10 ) ); ?>\" />")
    + L("\t\t\t\t</label>")
    + L("\t\t\t\t<label>")
    + L("\t\t\t\t\t<span><?php esc_html_e( 'Points minimum pour déclencher', 'infinitycod' ); ?></span>")
    + L("\t\t\t\t\t<input type=\"number\" min=\"0\" name=\"icod[loyalty_min_points]\" value=\"<?php echo esc_attr( Settings::get( 'loyalty_min_points', 20 ) ); ?>\" />")
    + L("\t\t\t\t</label>")
    + L("\t\t\t</div>")
    + L("\t\t</div>")
    + L("")
    + L("\t\t<div class=\"icod-card\">")
    + L("\t\t\t<h2><?php esc_html_e( 'Devise', 'infinitycod' ); ?></h2>")
)

patch("includes/admin/pages/class-settings-page.php", [
    (
        "\t\t<div class=\"icod-card\">\n"
        "\t\t\t<h2><?php esc_html_e( 'Devise', 'infinitycod' ); ?></h2>",
        CARDS,
    ),
])

print("\n".join("OK  " + o for o in ok))
