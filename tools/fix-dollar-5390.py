# -*- coding: utf-8 -*-
"""Répare les séquences backslash-dollar introduites par les patches (admin-extras + admin-manager)."""
import io
import re

ROOT = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod"
BS = chr(92)
DOL = chr(36)

TARGETS = [
    r"includes\admin\class-admin-extras.php",
    r"includes\admin\class-admin-manager.php",
]

PAT = re.compile(re.escape(BS + DOL))  # backslash + dollar (littéral)

for rel in TARGETS:
    p = ROOT + BS + rel
    with io.open(p, "r", encoding="utf-8", newline="") as f:
        lines = f.readlines()
    fixed = 0
    for i, line in enumerate(lines):
        if PAT.search(line):
            lines[i] = line.replace(BS + DOL, DOL)
            fixed += 1
    if fixed:
        with io.open(p, "w", encoding="utf-8", newline="") as f:
            f.writelines(lines)
    print(f"{rel} : {fixed} ligne(s) corrigée(s)")
