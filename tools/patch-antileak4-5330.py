# -*- coding: utf-8 -*-
"""Patch 5.33.0 quater : logs sur bordereaux (admin-manager) + paniers XLS (admin-extras)."""
import io

ROOT = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod"
BS = chr(92)
ok = []


def patch(rel, pairs):
    p = ROOT + BS + rel.replace("/", BS)
    with io.open(p, "r", encoding="utf-8", newline="") as f:
        d = f.read()
    for old, new in pairs:
        n = d.count(old)
        assert n == 1, rel + " :: " + old[:70].replace("\n", "⏎") + " -> " + str(n)
        d = d.replace(old, new, 1)
    with io.open(p, "w", encoding="utf-8", newline="") as f:
        f.write(d)
    ok.append(rel + " (" + str(len(pairs)) + ")")


AL = BS + "InfinityCod" + BS + "Core" + BS + "AntiLeak"

patch("includes/admin/class-admin-manager.php", [
    (
        "\t\tcheck_admin_referer( 'icod_bordereaux' );",
        "\t\tcheck_admin_referer( 'icod_bordereaux' );\n\n"
        "\t\t" + AL + "::log_export( 'bordereaux', count( array_filter( array_map( 'absint', explode( ',', (string) ( $_GET['ids'] ?? '' ) ) ) ) ), " + AL + "::trace_code() );",
    ),
])

patch("includes/admin/class-admin-extras.php", [
    (
        "\t\tcheck_admin_referer( 'icod_abandoned_export' );",
        "\t\tcheck_admin_referer( 'icod_abandoned_export' );\n\n"
        "\t\t$trace = " + AL + "::trace_code();\n"
        "\t\t" + AL + "::log_export( 'abandoned_xls', 0, $trace );",
    ),
])

print("\n".join("OK  " + o for o in ok))
