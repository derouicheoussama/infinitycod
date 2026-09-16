# -*- coding: utf-8 -*-
"""Patch form.js : envoi variante A/B + message wilaya fermée + estimate summary."""
import io

p = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod\assets\front\js\form.js"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()

# 1) Payload submit : variante A/B (icod_ab est un hidden du formulaire,
#    déjà collecté par la boucle inputs[name^=icod_] ? Non : slice(3) garde
#    'ab' via name icod_ab -> le payload cfields? Non, la boucle vise des
#    champs data-icod-field. On l'ajoute explicitement.)
old = "\t\t\t\tts: (el(form, '[name=\"icod_ts\"]') || { value: '' }).value,"
new = (
    "\t\t\t\tts: (el(form, '[name=\"icod_ts\"]') || { value: '' }).value,\n"
    "\t\t\t\tab: (el(form, '[name=\"icod_ab\"]') || { value: '' }).value,"
)
assert d.count(old) == 1, "ts payload : %d" % d.count(old)
d = d.replace(old, new, 1)

# 2) Message wilaya fermée (code REST : icod_wilaya_closed).
old_err = "\t\t\t\t\tif (json.code === 'blocked') {"
new_err = (
    "\t\t\t\t\tif (json.code === 'icod_wilaya_closed') {\n"
    "\t\t\t\t\t\tshowMsg(I18N.wilayaClosed || 'Livraison temporairement indisponible vers cette wilaya.', 'error');\n"
    "\t\t\t\t\t\treturn;\n"
    "\t\t\t\t\t}\n"
    "\t\t\t\t\tif (json.code === 'blocked') {"
)
assert d.count(old_err) == 1, "blocked : %d" % d.count(old_err)
d = d.replace(old_err, new_err, 1)

with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
print("OK form.js (ab + wilaya_closed)")
