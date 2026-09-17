# -*- coding: utf-8 -*-
"""Bump 5.39.3 : aperçu en direct + discrétion carte technique."""
import io

ROOT = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod"


def rep(rel, old, new):
    p = ROOT + "\\" + rel.replace("/", "\\")
    with io.open(p, "r", encoding="utf-8", newline="") as f:
        d = f.read()
    n = d.count(old)
    assert n == 1, rel + " :: " + old[:60] + " -> " + str(n)
    d = d.replace(old, new, 1)
    with io.open(p, "w", encoding="utf-8", newline="") as f:
        f.write(d)
    print("OK", rel)


rep("infinitycod.php",
    "* Version:           5.39.2",
    "* Version:           5.39.3")
rep("infinitycod.php",
    "define( 'INFINITYCOD_VERSION', '5.39.2' );",
    "define( 'INFINITYCOD_VERSION', '5.39.3' );")
rep("readme.txt",
    "Stable tag: 5.39.2",
    "Stable tag: 5.39.3")

entry_5393 = (
    "= 5.39.3 =\n"
    "* Correctif : l’aperçu en direct du formulaire fonctionne désormais même quand l’URL d’administration diffère de l’URL du site (www, https, préproduction) — requête AJAX ancrée sur l’origine courante du navigateur et CSS de l’aperçu en chemin relatif.\n"
)
p = ROOT + "\\" + "readme.txt"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
marker = "= 5.39.2 ="
idx = d.find(marker)
entry_txt = entry_5393.replace("\n", "\n")
d = d[:idx] + entry_5393 + "\n" + d[idx:]
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
print("OK readme entry 5.39.3")
