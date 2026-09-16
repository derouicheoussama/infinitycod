# -*- coding: utf-8 -*-
"""Patch 5.34.0 : perf (index composite, cache KPI) + design/fluidité."""
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


# 1) Schéma : index composite.
patch("includes/core/class-schema.php", [
    (
        "\t\t\tKEY status (status),\n\t\t\tKEY phone (phone),",
        "\t\t\tKEY status (status),\n\t\t\tKEY status_created (status, created_at),\n\t\t\tKEY phone (phone),",
    ),
])

# 2) Migration : ajout de l'index si absent.
p = ROOT + BS + r"includes\core\class-activator.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
anchor = "\t\t\t'5.31.1_carrier_logos' => function () {"
L = []
L.append("\t\t\t'5.34.0_order_indexes' => function () {")
L.append("\t\t\t\tglobal $wpdb;")
L.append("\t\t\t\t// Les listes filtrent par statut et trient par date : l'index")
L.append("\t\t\t\t// composite évite le filesort sur les grosses tables.")
L.append("\t\t\t\t$otable = " + BS + "InfinityCod" + BS + "Core" + BS + "Schema::table( 'orders' );")
L.append("\t\t\t\t$idx    = array_column( (array) $wpdb->get_results( \"SHOW INDEX FROM {$otable}\", ARRAY_A ), 'Key_name' );")
L.append("\t\t\t\tif ( ! in_array( 'status_created', $idx, true ) ) {")
L.append("\t\t\t\t\t$wpdb->query( \"ALTER TABLE {$otable} ADD KEY status_created (status, created_at)\" );")
L.append("\t\t\t\t}")
L.append("\t\t\t},")
L.append(anchor)
add = "\n".join(L)
if "'5.34.0_order_indexes'" not in d:
    assert d.count(anchor) == 1
    d = d.replace(anchor, add, 1)
    with io.open(p, "w", encoding="utf-8", newline="") as f:
        f.write(d)
    ok.append("migration 5.34.0_order_indexes")
else:
    ok.append("migration déjà présente")

# 3) P&L : cache 3 min des KPI.
patch("includes/stats/class-pnl.php", [
    (
        "\tpublic function kpis( $from, $to ) {\n\t\tglobal $wpdb;\n\n\t\t$table = Schema::table( 'orders' );",
        "\tpublic function kpis( $from, $to ) {\n\t\tglobal $wpdb;\n\n"
        "\t\t// Cache court : dashboard et stats relisent souvent les mêmes plages.\n"
        "\t\t// Invalidation à chaque création/changement de statut (icod_kpis_ver).\n"
        "\t\t$cache_key = 'icod_kpis_' . (int) get_option( 'icod_kpis_ver', 1 ) . '_' . md5( $from . '|' . $to );\n"
        "\t\t$cached = get_transient( $cache_key );\n"
        "\t\tif ( is_array( $cached ) ) {\n"
        "\t\t\treturn $cached;\n"
        "\t\t}\n\n"
        "\t\t$table = Schema::table( 'orders' );",
    ),
])

p = ROOT + BS + r"includes\stats\class-pnl.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
old_ret = "\t\treturn array(\n\t\t\t'total'               => $total,"
new_ret = "\t\t$kpis = array(\n\t\t\t'total'               => $total,"
assert d.count(old_ret) == 1, "return kpis : %d" % d.count(old_ret)
d = d.replace(old_ret, new_ret, 1)
# Fermer : la fin du tableau kpis -> set_transient + return.
old_close = "\t\t\t'return_fees'         => (float) ( $row['return_fees'] ?? 0 ),\n\t\t);"
new_close = "\t\t\t'return_fees'         => (float) ( $row['return_fees'] ?? 0 ),\n\t\t);\n\t\tset_transient( $cache_key, $kpis, 3 * MINUTE_IN_SECONDS );\n\t\treturn $kpis;"
assert d.count(old_close) == 1, "fermeture kpis : %d" % d.count(old_close)
d = d.replace(old_close, new_close, 1)
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
ok.append("pnl cache kpis")

# 4) Invalidation à chaque mouvement de commande.
patch("includes/admin/class-admin-manager.php", [
    (
        "\tpublic static function flush_pending_count() {\n\t\tdelete_transient( 'icod_pending_count' );\n\t}",
        "\tpublic static function flush_pending_count() {\n"
        "\t\tdelete_transient( 'icod_pending_count' );\n"
        "\t\t// Invalide aussi le cache des KPI (tableau de bord + stats).\n"
        "\t\tupdate_option( 'icod_kpis_ver', (int) get_option( 'icod_kpis_ver', 1 ) + 1, false );\n\t}",
    ),
])

print("\n".join("OK  " + o for o in ok))
