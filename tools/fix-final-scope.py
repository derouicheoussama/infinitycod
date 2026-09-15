# -*- coding: utf-8 -*-
"""Finalisation Plugin Check : disable/enable scopes a la place des ignores inline peu fiables."""
import io, os, sys

ROOT = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod"
results = []

def rep(rel, old, new, expect=1, tag=""):
    path = os.path.join(ROOT, rel)
    with io.open(path, "r", encoding="utf-8", newline="") as f:
        data = f.read()
    eol = "\r\n" if "\r\n" in data else "\n"
    o = old.replace("\n", eol)
    n = new.replace("\n", eol)
    cnt = data.count(o)
    if cnt != expect:
        results.append("MISS(%d!=%d) %s :: %s" % (cnt, expect, rel, tag))
        return
    data = data.replace(o, n, expect)
    with io.open(path, "w", encoding="utf-8", newline="") as f:
        f.write(data)
    results.append("OK   %s :: %s" % (rel, tag))

# --- 1) carriers-page : render() $msg — disable/enable scope au lieu d'un ignore inline ---
rep("includes/admin/pages/class-carriers-page.php",
    "\t\t$msg = isset( $_GET['icod_msg'] ) ? sanitize_key( wp_unslash( $_GET['icod_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended",
    "\t\t// phpcs:disable WordPress.Security.NonceVerification.Recommended -- lecture filtre GET, sanitisee ci-dessous.\n"
    "\t\t$msg = isset( $_GET['icod_msg'] ) ? sanitize_key( wp_unslash( $_GET['icod_msg'] ) ) : '';\n"
    "\t\t// phpcs:enable WordPress.Security.NonceVerification.Recommended",
    tag="render $msg scope")

# --- 2) whatsapp-manager : ligne 222 ---
path = os.path.join(ROOT, "includes/whatsapp/class-whatsapp-manager.php")
with io.open(path, "r", encoding="utf-8", newline="") as f:
    data = f.read()
eol = "\r\n" if "\r\n" in data else "\n"
lines = data.split(eol)
for i, ln in enumerate(lines):
    if "NonceVerification" in ln and "phpcs:ignore" in ln and i < 250:
        results.append("INFO whatsapp ligne %d : %s" % (i + 1, ln.strip()[:120]))
print("\n".join(results))
