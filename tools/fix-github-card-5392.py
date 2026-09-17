# -*- coding: utf-8 -*-
"""Remplace la carte « Mises à jour via GitHub » par une carte neutre (manipulation par lignes)."""
import io

p = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod\includes\admin\pages\class-settings-page.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    lines = f.readlines()

gh_line = next(i for i, l in enumerate(lines) if "via GitHub" in l and "Mises" in l)

# Remonte au début de la carte : dernière ligne `<div class="icod-card">` avant gh_line.
card_open = max(i for i in range(gh_line) if "<div class=\"icod-card\">" in lines[i])

# Descend au début de la carte Notifications : première ligne `<div class="icod-card">`
# après la carte GitHub... puis remonte avant son h2 : on cherche la ligne contenant
# 'Notifications marchand' et on remonte à l'ouverture de SA carte.
notif_line = next(i for i, l in enumerate(lines) if "Notifications marchand" in l and "esc_html_e" in l)
notif_card_open = max(i for i in range(notif_line) if "<div class=\"icod-card\">" in lines[i] and i > gh_line)

replacement = (
    "\t\t<div class=\"icod-card\">\n"
    "\t\t\t<h2>🔄 <?php esc_html_e( 'Mises à jour', 'infinitycod' ); ?></h2>\n"
    "\t\t\t<div class=\"icod-notice-ok\">✅ <?php esc_html_e( 'Automatiques — aucune configuration requise. Votre site vérifie les nouvelles versions toutes les heures et vous notifie (ou les installe) tout seul.', 'infinitycod' ); ?></div>\n"
    "\t\t\t<p class=\"description\"><a href=\"<?php echo esc_url( admin_url( 'admin.php?page=infinitycod-updates' ) ); ?>\"><?php esc_html_e( 'Voir l’état détaillé des mises à jour →', 'infinitycod' ); ?></a></p>\n"
    "\t\t</div>\n"
)

lines[card_open:notif_card_open] = [replacement]

with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.writelines(lines)
print("OK carte GitHub remplacée par carte neutre (lignes %d-%d -> %d)" % (card_open + 1, notif_card_open, card_open + 1))
