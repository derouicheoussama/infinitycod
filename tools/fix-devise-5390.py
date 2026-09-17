# -*- coding: utf-8 -*-
"""Supprime le fragment dupliqué après le h2 Devise."""
import io

p = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod\includes\admin\pages\class-settings-page.php"
BS = chr(92)
DQ = chr(34)
CRLF = chr(13) + chr(10)

with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()

old = (
    "?></h2>" + DQ + "\t\t\t<h2><?php esc_html_e( 'Devise', 'infinitycod' ); ?></h2>" + BS + DQ + CRLF + CRLF
    + "\t\t\t<p class=\"description\">"
)
new = (
    "?></h2>" + CRLF + CRLF
    + "\t\t\t<p class=\"description\">"
)
assert d.count(old) == 1, "fragment : %d" % d.count(old)
d = d.replace(old, new, 1)
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
print("OK fragment dupliqué supprimé")
