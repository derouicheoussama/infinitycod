# -*- coding: utf-8 -*-
"""Patch 5.33.0 ter : trace complète sur CSV commandes + XLS commandes/paniers + bordereaux."""
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

# ---------- ADMIN-MANAGER : CSV commandes (header + définition trace + log) ----------
patch("includes/admin/class-admin-manager.php", [
    (
        "fputcsv( $out, array( 'ID', 'WC #', 'Date', 'Nom', 'Telephone', 'Wilaya', 'Commune', 'Mode', 'Bureau', 'Produit', 'Qte', 'Sous-total', 'Remise', 'Livraison', 'Total', 'Statut', 'Transporteur', 'Suivi', 'Score risque', 'IP' ), ';' );",
        "fputcsv( $out, array( 'ID', 'WC #', 'Date', 'Nom', 'Telephone', 'Wilaya', 'Commune', 'Mode', 'Bureau', 'Produit', 'Qte', 'Sous-total', 'Remise', 'Livraison', 'Total', 'Statut', 'Transporteur', 'Suivi', 'Score risque', 'IP', 'Trace' ), ';' );",
    ),
    (
        "\t\t$statuses = \\InfinityCod\\Orders\\OrderStore::STATUSES;\n\n\t\tnocache_headers();\n\t\theader( 'Content-Type: text/csv; charset=utf-8' );\n\t\theader( 'Content-Disposition: attachment; filename=infinitycod-commandes-' . gmdate( 'Ymd-Hi' ) . '.csv' );",
        "\t\t$statuses = \\InfinityCod\\Orders\\OrderStore::STATUSES;\n\n"
        "\t\t// Anti-leak : filigrane + journal + alerte si extraction massive.\n"
        "\t\t$trace = " + AL + "::trace_code();\n"
        "\t\t" + AL + "::log_export( 'orders_csv', count( (array) $rows ), $trace );\n\n"
        "\t\tnocache_headers();\n\t\theader( 'Content-Type: text/csv; charset=utf-8' );\n\t\theader( 'Content-Disposition: attachment; filename=infinitycod-commandes-' . gmdate( 'Ymd-Hi' ) . '.csv' );",
    ),
])

# ---------- ADMIN-EXTRAS : paniers XLS + bordereaux (log + trace) ----------
patch("includes/admin/class-admin-extras.php", [
    (
        "\t\tcheck_admin_referer( 'icod_abandoned_export' );",
        "\t\tcheck_admin_referer( 'icod_abandoned_export' );\n\n"
        "\t\t$trace = " + AL + "::trace_code();\n"
        "\t\t" + AL + "::log_export( 'abandoned_xls', 0, $trace ); // Comptage réel journalisé après rendu des lignes.\n"
        "\t\t$GLOBALS['icod_export_trace'] = $trace;",
    ),
    (
        "\t\tcheck_admin_referer( 'icod_bordereaux' );",
        "\t\tcheck_admin_referer( 'icod_bordereaux' );\n\n"
        "\t\t$bl_ids = isset( $_GET['ids'] ) ? array_filter( array_map( 'absint', explode( ',', wp_unslash( $_GET['ids'] ) ) ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- ids absints un par un.\n"
        "\t\t" + AL + "::log_export( 'bordereaux', count( $bl_ids ), " + AL + "::trace_code() );",
    ),
])

print("\n".join("OK  " + o for o in ok))
