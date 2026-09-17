# -*- coding: utf-8 -*-
"""Normalise les changelogs 5.39.2/5.39.3 et bump final."""
import io

ROOT = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod"
BS = chr(92)

# 1) readme.txt : fusionne les deux sections 5.39.2 → une seule, puis ajoute 5.39.3.
p = ROOT + BS + "readme.txt"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()

old_5392 = (
    "= 5.39.2 =\n"
    "* Correctif : l’aperçu en direct du formulaire fonctionne désormais même quand l’URL d’administration diffère de l’URL du site (www, https, préproduction) — requête AJAX ancrée sur l’origine courante et CSS de l’aperçu en chemin relatif.\n"
)
assert d.count(old_5392) == 1
d = d.replace(old_5392, "", 1)

old_5392_disc = (
    "= 5.39.2 =\n"
    "* Réglages → Avancé : la carte technique « Mises à jour » est remplacée par un statut clair — le marchand ne voit plus aucun réglage d’infrastructure (miroirs, dépôts, token). Tout fonctionne sans configuration.\n"
)
new_5392 = (
    "= 5.39.2 =\n"
    "* Réglages → Avancé : la carte technique « Mises à jour » est remplacée par un statut clair — le marchand ne voit plus aucun réglage d’infrastructure (miroirs, dépôts, token). Tout fonctionne sans configuration.\n"
    "* Correctif : l’aperçu en direct du formulaire fonctionne désormais même quand l’URL d’administration diffère de l’URL du site (www, https, préproduction).\n"
)
assert d.count(old_5392_disc) == 1
d = d.replace(old_5392_disc, new_5392, 1)

old_5393 = "= 5.39.1 ="
entry_5393 = (
    "= 5.39.3 =\n"
    "* Correctif : l’aperçu en direct du formulaire s’applique instantanément, sans dépendre de l’URL du site stockée en base (ancrage AJAX sur l’origine courante + CSS de l’aperçu en chemin relatif).\n"
)
# Insère 5.39.3 au-dessus de 5.39.1 (l'entrée 5.39.2 est déjà au-dessus).
idx = d.find(old_5393)
d = d[:idx] + entry_5393 + d[idx:]
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
print("OK readme fusionné + 5.39.3")

# 2) Bump infinitycod.php.
p2 = ROOT + BS + "infinitycod.php"
with io.open(p2, "r", encoding="utf-8", newline="") as f:
    d2 = f.read()
d2 = d2.replace("* Version:           5.39.2", "* Version:           5.39.3", 1)
d2 = d2.replace("define( 'INFINITYCOD_VERSION', '5.39.2' );", "define( 'INFINITYCOD_VERSION', '5.39.3' );", 1)
with io.open(p2, "w", encoding="utf-8", newline="") as f:
    f.write(d2)
print("OK infinitycod.php 5.39.3")

rep2 = ROOT + BS + "readme.txt"
with io.open(rep2, "r", encoding="utf-8", newline="") as f:
    d3 = f.read()
d3 = d3.replace("Stable tag: 5.39.2", "Stable tag: 5.39.3", 1)
with io.open(rep2, "w", encoding="utf-8", newline="") as f:
    f.write(d3)
print("OK stable tag 5.39.3")
