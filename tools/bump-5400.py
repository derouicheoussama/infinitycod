# -*- coding: utf-8 -*-
"""Bump 5.40.0 + changelogs (notification e-mail nouvelle version)."""
import io

ROOT = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod"


def rep(p, old, new):
    full = ROOT + "\\" + p.replace("/", "\\")
    with io.open(full, "r", encoding="utf-8", newline="") as f:
        d = f.read()
    assert d.count(old) == 1, p + " :: " + old[:50]
    d = d.replace(old, new, 1)
    with io.open(full, "w", encoding="utf-8", newline="") as f:
        f.write(d)
    print("OK", p)


rep("infinitycod.php", "* Version:           5.39.3", "* Version:           5.40.0")
rep("infinitycod.php",
    "define( 'INFINITYCOD_VERSION', '5.39.3' );",
    "define( 'INFINITYCOD_VERSION', '5.40.0' );")
rep("readme.txt", "Stable tag: 5.39.3", "Stable tag: 5.40.0")

entry = (
    "= 5.40.0 =\n"
    "* Nouveau : notification e-mail au marchand dès qu’une nouvelle version d’InfinityCod est disponible (une seule fois par version, e-mail de l’admin du site, désactivable dans Mises à jour).\n"
)
p = ROOT + "\\" + "readme.txt"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
idx = d.find("= 5.39.3 =")
d = d[:idx] + entry + "\n" + d[idx:]
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
print("OK readme entry 5.40.0")

p2 = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\CHANGELOG.md"
with io.open(p2, "r", encoding="utf-8", newline="") as f:
    d2 = f.read()
entry2 = (
    "## 5.40.0 — 2026-09-17\n\n"
    "- **Notification e-mail des mises à jour** : le marchand reçoit un e-mail dès qu'une nouvelle version sort (une seule fois par version), en complément de la vérification horaire et de la mise à jour automatique. Désactivable dans Mises à jour.\n\n"
)
head = "# Changelog\n\n"
d2 = head + entry2 + d2[len(head):]
with io.open(p2, "w", encoding="utf-8", newline="") as f:
    f.write(d2)
print("OK CHANGELOG.md")
