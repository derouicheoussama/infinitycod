# -*- coding: utf-8 -*-
"""Patch 5.39.0 A : modules, défauts, schéma, migration, loyalty OrderStore."""
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


def append(rel, text):
    p = ROOT + BS + rel.replace("/", BS)
    with io.open(p, "a", encoding="utf-8", newline="") as f:
        f.write(text)
    ok.append(rel + " (append)")


# ---------- 1) MODULES ----------
patch("includes/core/class-plugin.php", [
    (
        "\t\t'antileak'  => '\\\\InfinityCod\\\\Core\\\\AntiLeak',",
        "\t\t'antileak'  => '\\\\InfinityCod\\\\Core\\\\AntiLeak',\n"
        "\t\t'track'     => '\\\\InfinityCod\\\\Track\\\\TrackPortal',\n"
        "\t\t'invoice'   => '\\\\InfinityCod\\\\Orders\\\\Invoice',\n"
        "\t\t'pwa'       => '\\\\InfinityCod\\\\Core\\\\Pwa',",
    ),
])

# ---------- 2) DÉFAUTS ----------
patch("includes/core/class-settings.php", [
    (
        "\t\t\t'seo_llms_enabled'     => 1,   // /llms.txt pour les moteurs génératifs.",
        "\t\t\t'seo_llms_enabled'     => 1,   // /llms.txt pour les moteurs génératifs.\n\n"
        "\t\t\t// Portail de suivi client public.\n"
        "\t\t\t'track_portal_enabled' => 1,   // Page /suivi-commande/.\n\n"
        "\t\t\t// Facture PDF.\n"
        "\t\t\t'invoice_enabled'      => 1,   // Téléchargement + pièce jointe.\n"
        "\t\t\t'invoice_email'        => 1,   // Jointe à l'e-mail de confirmation.\n\n"
        "\t\t\t// Alertes de stock.\n"
        "\t\t\t'stock_alert_threshold' => 0,  // Alerte quand le stock passe sous ce seuil (0 = jamais).\n\n"
        "\t\t\t// Rapport quotidien WhatsApp (au marchand lui-même).\n"
        "\t\t\t'wa_daily_report'      => 0,\n"
        "\t\t\t'wa_owner_phone'       => '',\n\n"
        "\t\t\t// Fidélité : points gagnés sur les commandes livrées, remise auto au formulaire.\n"
        "\t\t\t'loyalty_enabled'      => 0,\n"
        "\t\t\t'loyalty_point_da'     => 1000, // 1 point par tranche de 1000 DA livrés.\n"
        "\t\t\t'loyalty_point_value'  => 10,   // 1 point = 10 DA de remise.\n"
        "\t\t\t'loyalty_min_points'   => 20,   // Minimum de points pour déclencher la remise.",
    ),
])

# ---------- 3) SCHÉMA PAIEMENT/AVANCÉ : nouvelles clés ----------
patch("includes/admin/pages/class-settings-page.php", [
    (
        "\t\t\t'seo_llms_enabled'     => array( 'tab' => 'advanced', 'type' => 'toggle' ),",
        "\t\t\t'seo_llms_enabled'     => array( 'tab' => 'advanced', 'type' => 'toggle' ),\n"
        "\t\t\t'track_portal_enabled' => array( 'tab' => 'advanced', 'type' => 'toggle' ),\n"
        "\t\t\t'invoice_enabled'      => array( 'tab' => 'advanced', 'type' => 'toggle' ),\n"
        "\t\t\t'invoice_email'        => array( 'tab' => 'advanced', 'type' => 'toggle' ),\n"
        "\t\t\t'stock_alert_threshold' => array( 'tab' => 'advanced', 'type' => 'int', 'min' => 0, 'max' => 1000 ),\n"
        "\t\t\t'wa_daily_report'      => array( 'tab' => 'advanced', 'type' => 'toggle' ),\n"
        "\t\t\t'wa_owner_phone'       => array( 'tab' => 'advanced', 'type' => 'text' ),\n"
        "\t\t\t'loyalty_enabled'      => array( 'tab' => 'advanced', 'type' => 'toggle' ),\n"
        "\t\t\t'loyalty_point_da'     => array( 'tab' => 'advanced', 'type' => 'int', 'min' => 100, 'max' => 100000 ),\n"
        "\t\t\t'loyalty_point_value'  => array( 'tab' => 'advanced', 'type' => 'int', 'min' => 1, 'max' => 1000 ),\n"
        "\t\t\t'loyalty_min_points'   => array( 'tab' => 'advanced', 'type' => 'int', 'min' => 0, 'max' => 10000 ),",
    ),
])

# ---------- 4) MIGRATION : orders.loyalty_used ----------
p = ROOT + BS + r"includes\core\class-activator.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
anchor = "\t\t\t'5.34.0_order_indexes' => function () {"
L = []
L.append("\t\t\t'5.39.0_loyalty' => function () {")
L.append("\t\t\t\tglobal $wpdb;")
L.append("\t\t\t\t$otable = " + BS + "InfinityCod" + BS + "Core" + BS + "Schema::table( 'orders' );")
L.append("\t\t\t\t$ocolumns = (array) $wpdb->get_col( \"DESCRIBE {$otable}\", 0 );")
L.append("\t\t\t\tif ( ! in_array( 'loyalty_used', $ocolumns, true ) ) {")
L.append("\t\t\t\t\t$wpdb->query( \"ALTER TABLE {$otable} ADD COLUMN loyalty_used decimal(10,2) NOT NULL DEFAULT 0\" );")
L.append("\t\t\t\t}")
L.append("\t\t\t},")
L.append(anchor)
add = "\n".join(L)
if "'5.39.0_loyalty'" not in d:
    assert d.count(anchor) == 1
    d = d.replace(anchor, add, 1)
    with io.open(p, "w", encoding="utf-8", newline="") as f:
        f.write(d)
    ok.append("migration 5.39.0_loyalty")
else:
    ok.append("migration déjà présente")

# ---------- 5) SCHÉMA ORDERS : colonne loyalty_used ----------
patch("includes/core/class-schema.php", [
    (
        "\t\t\tab_variant varchar(1) NOT NULL DEFAULT '',",
        "\t\t\tab_variant varchar(1) NOT NULL DEFAULT '',\n\t\t\tloyalty_used decimal(10,2) NOT NULL DEFAULT 0,",
    ),
])

print("\n".join("OK  " + o for o in ok))
