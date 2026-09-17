# -*- coding: utf-8 -*-
"""Fix v3 : remplace [début bloc corrompu -> ligne h2 Devise] par les 5 cartes + réouverture Devise."""
import io

p = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod\includes\admin\pages\class-settings-page.php"
BS = chr(92)
DQ = chr(34)
SQ = chr(39)
TAB = chr(9)
LF = chr(10)
CRLF = chr(13) + chr(10)

with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()

# 1) Début du bloc corrompu : la ligne `"\t\t<div class=\"icod-card\">\"` après la carte SEO.
i_faq = d.find("seo_faq")
assert i_faq > 0
probe = "<div class=" + BS + DQ + "icod-card" + BS + DQ + ">"
start = d.find(probe, i_faq)
assert start > 0, "div corrompu introuvable"
# Recule au début de la ligne corrompue puis au `"` parasite (fin de la ligne précédente).
line_start = d.rfind(LF, 0, start) + 1
if d[line_start:line_start + 1] == DQ:
    line_start = d.rfind(LF, 0, line_start - 1) + 1
start = line_start

# 2) Fin : la ligne du h2 Devise (propre, à conserver).
i_dev = d.find("esc_html_e( 'Devise'", start)
assert i_dev > 0
h2_dev = d.rfind(LF, 0, i_dev) + 1

# 3) Les 5 cartes + la réouverture de la carte Devise (le fichier continue sur son <p>).
cards = (
    "\t\t<div class=\"icod-card\">\n"
    "\t\t\t<h2>📦 <?php esc_html_e( 'Portail de suivi client', 'infinitycod' ); ?></h2>\n"
    "\t\t\t<p class=\"description\"><?php esc_html_e( 'Vos clients suivent leur commande en direct sur /suivi-commande/ (numéro + téléphone). Moins d’appels, plus d’autonomie.', 'infinitycod' ); ?></p>\n"
    "\t\t\t<div class=\"icod-toggles\">\n"
    "\t\t\t\t<label class=\"icod-toggle\">\n"
    "\t\t\t\t\t<input type=\"checkbox\" name=\"icod[track_portal_enabled]\" value=\"1\" <?php checked( (int) Settings::get( 'track_portal_enabled', 1 ), 1 ); ?> />\n"
    "\t\t\t\t\t<span><?php esc_html_e( 'Activer le portail de suivi public /suivi-commande/', 'infinitycod' ); ?></span>\n"
    "\t\t\t\t</label>\n"
    "\t\t\t</div>\n"
    "\t\t</div>\n\n"
    "\t\t<div class=\"icod-card\">\n"
    "\t\t\t<h2>🧾 <?php esc_html_e( 'Facture PDF', 'infinitycod' ); ?></h2>\n"
    "\t\t\t<div class=\"icod-toggles\">\n"
    "\t\t\t\t<label class=\"icod-toggle\">\n"
    "\t\t\t\t\t<input type=\"checkbox\" name=\"icod[invoice_enabled]\" value=\"1\" <?php checked( (int) Settings::get( 'invoice_enabled', 1 ), 1 ); ?> />\n"
    "\t\t\t\t\t<span><?php esc_html_e( 'Facture PDF téléchargeable (bouton « Facture » dans la fiche commande)', 'infinitycod' ); ?></span>\n"
    "\t\t\t\t</label>\n"
    "\t\t\t\t<label class=\"icod-toggle\">\n"
    "\t\t\t\t\t<input type=\"checkbox\" name=\"icod[invoice_email]\" value=\"1\" <?php checked( (int) Settings::get( 'invoice_email', 1 ), 1 ); ?> />\n"
    "\t\t\t\t\t<span><?php esc_html_e( 'Joindre automatiquement la facture à l’e-mail de confirmation (si le client a laissé son e-mail)', 'infinitycod' ); ?></span>\n"
    "\t\t\t\t</label>\n"
    "\t\t\t</div>\n"
    "\t\t</div>\n\n"
    "\t\t<div class=\"icod-card\">\n"
    "\t\t\t<h2>📦 <?php esc_html_e( 'Alertes de stock', 'infinitycod' ); ?></h2>\n"
    "\t\t\t<p class=\"description\"><?php esc_html_e( 'Badge « Bientôt épuisé » sur le formulaire + e-mail quotidien récapitulatif des produits sous le seuil.', 'infinitycod' ); ?></p>\n"
    "\t\t\t<div class=\"icod-grid\">\n"
    "\t\t\t\t<label>\n"
    "\t\t\t\t\t<span><?php esc_html_e( 'Seuil d’alerte (quantité, 0 = désactivé)', 'infinitycod' ); ?></span>\n"
    "\t\t\t\t\t<input type=\"number\" min=\"0\" max=\"1000\" name=\"icod[stock_alert_threshold]\" value=\"<?php echo esc_attr( Settings::get( 'stock_alert_threshold', 0 ) ); ?>\" />\n"
    "\t\t\t\t</label>\n"
    "\t\t\t</div>\n"
    "\t\t</div>\n\n"
    "\t\t<div class=\"icod-card\">\n"
    "\t\t\t<h2>📲 <?php esc_html_e( 'Rapport quotidien WhatsApp', 'infinitycod' ); ?></h2>\n"
    "\t\t\t<p class=\"description\"><?php esc_html_e( 'Chaque matin : commandes du jour et chiffre d’affaires envoyés sur votre propre WhatsApp (nécessite une passerelle WhatsApp configurée dans l’onglet Formulaire).', 'infinitycod' ); ?></p>\n"
    "\t\t\t<div class=\"icod-toggles\">\n"
    "\t\t\t\t<label class=\"icod-toggle\">\n"
    "\t\t\t\t\t<input type=\"checkbox\" name=\"icod[wa_daily_report]\" value=\"1\" <?php checked( (int) Settings::get( 'wa_daily_report' ), 1 ); ?> />\n"
    "\t\t\t\t\t<span><?php esc_html_e( 'Recevoir le rapport quotidien sur WhatsApp', 'infinitycod' ); ?></span>\n"
    "\t\t\t\t</label>\n"
    "\t\t\t</div>\n"
    "\t\t\t<div class=\"icod-grid\">\n"
    "\t\t\t\t<label>\n"
    "\t\t\t\t\t<span><?php esc_html_e( 'Votre numéro WhatsApp (format international, ex. 2136…)', 'infinitycod' ); ?></span>\n"
    "\t\t\t\t\t<input type=\"text\" name=\"icod[wa_owner_phone]\" dir=\"ltr\" value=\"<?php echo esc_attr( Settings::get( 'wa_owner_phone', '' ) ); ?>\" />\n"
    "\t\t\t\t</label>\n"
    "\t\t\t</div>\n"
    "\t\t</div>\n\n"
    "\t\t<div class=\"icod-card\">\n"
    "\t\t\t<h2>💛 <?php esc_html_e( 'Fidélité', 'infinitycod' ); ?></h2>\n"
    "\t\t\t<p class=\"description\"><?php esc_html_e( 'Points gagnés sur les commandes livrées ; si le client récommande avec le même numéro et a assez de points, la remise s’applique automatiquement (max 30 % du sous-total).', 'infinitycod' ); ?></p>\n"
    "\t\t\t<div class=\"icod-toggles\">\n"
    "\t\t\t\t<label class=\"icod-toggle\">\n"
    "\t\t\t\t\t<input type=\"checkbox\" name=\"icod[loyalty_enabled]\" value=\"1\" <?php checked( (int) Settings::get( 'loyalty_enabled' ), 1 ); ?> />\n"
    "\t\t\t\t\t<span><?php esc_html_e( 'Activer la remise fidélité automatique', 'infinitycod' ); ?></span>\n"
    "\t\t\t\t</label>\n"
    "\t\t\t</div>\n"
    "\t\t\t<div class=\"icod-grid\">\n"
    "\t\t\t\t<label>\n"
    "\t\t\t\t\t<span><?php esc_html_e( '1 point par (DA livrés)', 'infinitycod' ); ?></span>\n"
    "\t\t\t\t\t<input type=\"number\" min=\"100\" step=\"100\" name=\"icod[loyalty_point_da]\" value=\"<?php echo esc_attr( Settings::get( 'loyalty_point_da', 1000 ) ); ?>\" />\n"
    "\t\t\t\t</label>\n"
    "\t\t\t\t<label>\n"
    "\t\t\t\t\t<span><?php esc_html_e( 'Remise par point (DA)', 'infinitycod' ); ?></span>\n"
    "\t\t\t\t\t<input type=\"number\" min=\"1\" name=\"icod[loyalty_point_value]\" value=\"<?php echo esc_attr( Settings::get( 'loyalty_point_value', 10 ) ); ?>\" />\n"
    "\t\t\t\t</label>\n"
    "\t\t\t\t<label>\n"
    "\t\t\t\t\t<span><?php esc_html_e( 'Points minimum pour déclencher', 'infinitycod' ); ?></span>\n"
    "\t\t\t\t\t<input type=\"number\" min=\"0\" name=\"icod[loyalty_min_points]\" value=\"<?php echo esc_attr( Settings::get( 'loyalty_min_points', 20 ) ); ?>\" />\n"
    "\t\t\t\t</label>\n"
    "\t\t\t</div>\n"
    "\t\t</div>\n\n"
    "\t\t<div class=\"icod-card\">\n"
    "\t\t\t<h2><?php esc_html_e( 'Devise', 'infinitycod' ); ?></h2>"
)

d = d[:start] + cards + d[h2_dev:]
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
print("OK bloc corrompu remplacé proprement")
