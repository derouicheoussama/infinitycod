# -*- coding: utf-8 -*-
"""Scope tous les phpcs:enable nus + vrai fix version recaptcha."""
import io, os, sys

ROOT = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod"
results = []

def rep(rel, old, new, expect=None, tag=""):
    path = os.path.join(ROOT, rel)
    with io.open(path, "r", encoding="utf-8", newline="") as f:
        data = f.read()
    eol = "\r\n" if "\r\n" in data else "\n"
    o = old.replace("\n", eol)
    n = new.replace("\n", eol)
    cnt = data.count(o)
    if cnt == 0:
        results.append("MISS %s :: %s" % (rel, tag))
        return
    data = data.replace(o, n) if expect is None else data.replace(o, n, 1)
    with io.open(path, "w", encoding="utf-8", newline="") as f:
        f.write(data)
    results.append("OK   %s x%d :: %s" % (rel, cnt, tag))

# --- 1) enables nus -> scopes (chacun ferme uniquement son disable) ---
rep("includes/admin/class-admin-manager.php",
    "\t\t// phpcs:enable",
    "\t\t// phpcs:enable WordPress.Security.NonceVerification",
    expect=1, tag="scope enable export GET")

rep("includes/admin/pages/class-geo-page.php",
    "\t\t// phpcs:enable",
    "\t\t// phpcs:enable WordPress.Security.NonceVerification",
    expect=1, tag="scope enable onglets")

rep("includes/admin/pages/class-orders-page.php",
    "\t\t// phpcs:enable\n\t}",
    "\t\t// phpcs:enable WordPress.Security.NonceVerification\n\t}",
    expect=1, tag="scope enable filtres")

rep("includes/admin/pages/class-orders-page.php",
    "\t\t// phpcs:enable\n\t\treturn array( $rows",
    "\t\t// phpcs:enable WordPress.DB, PluginCheck.Security\n\t\treturn array( $rows",
    expect=1, tag="scope enable query()")

rep("includes/admin/class-product-meta-box.php",
    "\t\t// phpcs:enable",
    "\t\t// phpcs:enable WordPress.Security.NonceVerification",
    expect=1, tag="scope enable meta-box")

rep("includes/admin/pages/class-carriers-page.php",
    "\t\t// phpcs:enable",
    "\t\t// phpcs:enable WordPress.Security.NonceVerification",
    expect=1, tag="scope enable onglets carriers")

rep("includes/anti-fraud/class-shield.php",
    "\t\t// phpcs:enable\n\t\treturn '0.0.0.0';",
    "\t\t// phpcs:enable WordPress.Security.ValidatedSanitizedInput\n\t\treturn '0.0.0.0';",
    expect=1, tag="scope enable client_ip")

rep("includes/admin/pages/class-settings-page.php",
    "\t\t// phpcs:enable",
    "\t\t// phpcs:enable WordPress.Security.NonceVerification",
    expect=1, tag="scope enable onglets settings")

rep("includes/admin/pages/class-stats-page.php",
    "\t\t// phpcs:enable",
    "\t\t// phpcs:enable WordPress.Security.NonceVerification",
    expect=1, tag="scope enable filtre stats")

rep("includes/admin/pages/class-updates-page.php",
    "\t\t// phpcs:enable",
    "\t\t// phpcs:enable WordPress.Security.ValidatedSanitizedInput",
    expect=1, tag="scope enable upload zip")

rep("includes/payment/class-payment-manager.php",
    "\t\t// phpcs:enable",
    "\t\t// phpcs:enable WordPress.Security.NonceVerification",
    expect=1, tag="scope enable chargily")

# --- 2) recaptcha : version reelle au lieu de null + suppression des ignores devenus inutiles ---
FM = "includes/form/class-form-manager.php"

import re
path = os.path.join(ROOT, FM)
with io.open(path, "r", encoding="utf-8", newline="") as f:
    data = f.read()
eol = "\r\n" if "\r\n" in data else "\n"

# 2a) retirer le commentaire phpcs:ignore deplace sur la 1re ligne de l'appel
pat = re.compile(r"wp_enqueue_script\( // phpcs:ignore WordPress\.WP\.EnqueuedResourceParameters\.MissingVersion -- versionne cote service Google\." + re.escape(eol) + r"(\s*)'icod-recaptcha',")
data, c1 = pat.subn(lambda m: "wp_enqueue_script(" + eol + m.group(1) + "'icod-recaptcha',", data)

# 2b) null -> INFINITYCOD_VERSION dans les appels recaptcha (2 occurrences attendues)
pat2 = re.compile(r"(" + re.escape(eol) + r"\s*)null,(" + re.escape(eol) + r"\s*)true")
data, c2 = pat2.subn(lambda m: m.group(1) + "INFINITYCOD_VERSION," + m.group(2) + "true", data)

with io.open(path, "w", encoding="utf-8", newline="") as f:
    f.write(data)
results.append("OK   %s ignore-retire x%d, null->VERSION x%d" % (FM, c1, c2))

print("\n".join(results))
miss = sum(1 for r in results if r.startswith("MISS"))
print("---")
print("EDITS OK: %d / MISS: %d" % (len(results) - miss, miss))
sys.exit(1 if miss or c1 != 2 or c2 != 2 else 0)
