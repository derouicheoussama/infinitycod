# -*- coding: utf-8 -*-
"""Patch 5.33.0 bis : journal + filigrane + alerte sur les exports (orders CSV/XLS, paniers XLS, bordereaux)."""
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


# ---------- 1) EXPORT ORDERS CSV ----------
patch("includes/admin/class-admin-manager.php", [
    # En-tête CSV : colonne Trace.
    (
        "fputcsv( $out, array( 'code', 'wilaya', 'domicile', 'stopdesk', 'active', 'gratuite', 'delai', 'min', 'poids_1_5', 'poids_5_10', 'poids_supp_kg', 'frais_retour' ), ';' );",
        "fputcsv( $out, array( 'code', 'wilaya', 'domicile', 'stopdesk', 'active', 'gratuite', 'delai', 'min', 'poids_1_5', 'poids_5_10', 'poids_supp_kg', 'frais_retour' ), ';' );"
        "\n\t\t// Anti-leak : filigrane de traçabilité (journalisé côté admin).",
    ),
])
# Le handler orders CSV : retrouver ses lignes (requête + fputcsv entête).
p = ROOT + BS + r"includes\admin\class-admin-manager.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()

# 1a) Dans handle_orders_export (CSV commandes) : injecter trace + log après le nocache.
anchor = "\t\tnocache_headers();\n\t\theader( 'Content-Type: text/csv; charset=utf-8' );\n\t\theader( 'Content-Disposition: attachment; filename=infinitycod-tarifs-' . gmdate( 'Ymd' ) . '.csv' );"
# (celui-ci est l'export tarifs — pas de données clients : pas de journal)

# 1b) Export commandes CSV : repérer son en-tête de colonnes.
old_csv_head = "\t\tfputcsv( $out, array( 'Commande', 'Date', 'Client', 'Téléphone', 'Wilaya', 'Commune', 'Produit', 'Quantité', 'Sous-total', 'Remise', 'Livraison', 'Total', 'Statut', 'Transporteur', 'Suivi', 'Risque', 'IP' ), ';' );"
if d.count(old_csv_head) == 1:
    new_csv_head = (
        "\t\t$trace = \\InfinityCod\\Core\\AntiLeak::trace_code();\n"
        "\t\t\\InfinityCod\\Core\\AntiLeak::log_export( 'orders_csv', $total ?? 0, $trace );\n"
        "\t\tfputcsv( $out, array( 'Commande', 'Date', 'Client', 'Téléphone', 'Wilaya', 'Commune', 'Produit', 'Quantité', 'Sous-total', 'Remise', 'Livraison', 'Total', 'Statut', 'Transporteur', 'Suivi', 'Risque', 'IP', 'Trace' ), ';' );"
    )
    d = d.replace(old_csv_head, new_csv_head, 1)
    ok_count = "orders csv head ok"
else:
    ok_count = "orders csv head : motif à vérifier (" + str(d.count(old_csv_head)) + ")"

# 1c) Colonnes de chaque ligne CSV commandes : ajouter la trace en fin.
old_csv_row = "\t\t\t\t$row['ip'],\n\t\t\t);"
new_csv_row = "\t\t\t\t$row['ip'],\n\t\t\t\t$trace,\n\t\t\t);"
n_rows = d.count(old_csv_row)
if n_rows == 1:
    d = d.replace(old_csv_row, new_csv_row, 1)

with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
ok.append("admin-manager csv (" + ok_count + ", rows=" + str(n_rows) + ")")

print("\n".join("OK  " + o for o in ok))
