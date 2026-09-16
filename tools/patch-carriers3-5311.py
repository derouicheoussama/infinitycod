# -*- coding: utf-8 -*-
"""Patch 5.31.1 fin : lookup png d'abord, migration suppression placeholders, CSS."""
import io

ROOT = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod"
BS = chr(92)
ok = []

# 1) Lookup png -> webp -> svg (les vrais logos raster priment sur les
#    placeholders vectoriels, y compris après une mise à jour qui ne
#    supprime pas les anciens fichiers).
p = ROOT + BS + r"includes\admin\pages\class-carriers-page.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
old = "foreach ( array( 'svg', 'png', 'webp' ) as $_ext ) {"
new = "foreach ( array( 'png', 'webp', 'svg' ) as $_ext ) {"
if d.count(old) == 1:
    d = d.replace(old, new, 1)
    with io.open(p, "w", encoding="utf-8", newline="") as f:
        f.write(d)
    ok.append("lookup png d'abord")
else:
    ok.append("lookup déjà inversé")

# 2) Migration : suppression des placeholders remplacés + CSS.
p = ROOT + BS + r"assets\admin\css\admin.css"
with io.open(p, "a", encoding="utf-8", newline="") as f:
    f.write("""
/* Zones régionales (Géo & Tarifs). */
.icod-zones-card .icod-zone-row{display:flex;gap:8px;align-items:center;margin:6px 0;flex-wrap:wrap}
.icod-zones-card .icod-zone-row input[type=text]{flex:1 1 200px;min-width:120px}
.icod-zones-card .icod-zone-row input[type=text][dir=ltr]{flex:1.4 1 220px}
.icod-zones-card .icod-zone-row input[type=number]{width:110px}
.icod-zone-del{color:#b32d2e}
.icod-wilayas-table input[name$="[w5]"],.icod-wilayas-table input[name$="[w10]"],
.icod-wilayas-table input[name$="[w_over]"],.icod-wilayas-table input[name$="[return_fee]"]{font-size:12px;padding:2px 4px}
""")
ok.append("CSS zones/paliers")

# 3) Migration 5.31.1_cleanup dans l'activator.
p = ROOT + BS + r"includes\core\class-activator.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
anchor = "\t\t\t'5.31.0_logistics' => function () {"
L = []
L.append("\t\t\t'5.31.1_carrier_logos' => function () {")
L.append("\t\t\t\t// Placeholders SVG remplacés par les vrais logos PNG (E-COM / DHD) :")
L.append("\t\t\t\t// WordPress ne supprime pas les fichiers obsolètes lors des mises à jour.")
L.append("\t\t\t\tforeach ( array( 'ecom.svg', 'dhd.svg' ) as $old_logo ) {")
L.append("\t\t\t\t\t$file = INFINITYCOD_PATH . 'assets/front/img/carriers/' . $old_logo;")
L.append("\t\t\t\t\tif ( file_exists( $file ) ) {")
L.append("\t\t\t\t\t\twp_delete_file( $file );")
L.append("\t\t\t\t\t}")
L.append("\t\t\t\t}")
L.append("\t\t\t},")
L.append(anchor)
add = "\n".join(L)
if "'5.31.1_carrier_logos'" not in d:
    assert d.count(anchor) == 1
    d = d.replace(anchor, add, 1)
    with io.open(p, "w", encoding="utf-8", newline="") as f:
        f.write(d)
    ok.append("migration 5.31.1_carrier_logos")
else:
    ok.append("migration déjà présente")

print("\n".join("OK  " + o for o in ok))
