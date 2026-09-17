# -*- coding: utf-8 -*-
"""Fusionne les sections 5.39.2 dupliquées et crée 5.39.3 (aperçu + discrétion + carte neutre)."""
import io

p = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod\readme.txt"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
print("readme avant : stable =", "5.39.2" in d, "| sections 5.39.2 =", d.count("= 5.39.2 ="))
print("readme 5.39.3:", d.count("= 5.39.3 ="))
