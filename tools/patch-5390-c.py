# -*- coding: utf-8 -*-
"""Patch 5.39.0 C : bordereaux étiquettes + WA quotidien + carte réglages + modal facture."""
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
        if n == 0:
            continue
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


# ---------- 1) BORDEAUX : mode étiquettes 10x15 (format=labels) ----------
patch("includes/admin/class-admin-manager.php", [
    (
        "\t\techo '<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>' . esc_html__( 'Bordereaux InfinityCod', 'infinitycod' ) . '</title>';",
        "\t\t\$labels = isset( \$_GET['format'] ) && 'labels' === \$_GET['format']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce vérifié ci-dessus.\n"
        "\t\techo '<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>' . esc_html__( 'Bordereaux InfinityCod', 'infinitycod' ) . '</title>';\n"
        "\t\tif ( \$labels ) {\n"
        "\t\t\techo '<style>body{font-family:Arial,sans-serif;margin:0}.etq{width:10cm;height:15cm;padding:10px 12px;box-sizing:border-box;border-bottom:1px dashed #999;page-break-after:always}.etq h2{margin:0 0 2px;font-size:16px}.etq .ref{float:right;font-weight:800}.etq table{width:100%;border-collapse:collapse;font-size:13px;margin-top:6px}.etq td{padding:2px 0;vertical-align:top}.etq td:first-child{color:#555;width:110px}.etq .cod{font-size:18px;font-weight:800;margin-top:6px}.etq .code{font-size:26px;font-weight:800;letter-spacing:2px}</style>';\n"
        "\t\t} else {\n"
        "\t\t\techo '<style>';\n"
        "\t\t}\n"
        "\t\techo '</head><body>';",
    ),
])

# Ligne produit : si labels, cadres différents (chaque commande = bloc étiquette).
p = ROOT + BS + r"includes\admin\class-admin-manager.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
old_cls = "\t\t\techo '<div class=\"bordereau\">';"
new_cls = "\t\t\techo '<div class=\"' . ( \$labels ? 'etq' : 'bordereau' ) . '\">';"
assert d.count(old_cls) == 1
d = d.replace(old_cls, new_cls, 1)
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
ok.append("bordereaux mode étiquettes")

# ---------- 2) ORDERS PAGE : bouton étiquettes ----------
patch("includes/admin/pages/class-orders-page.php", [
    (
        "<a href=\"#\" id=\"icod-bordereaux\" class=\"button\" data-url=\"<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=icod_bordereaux' ), 'icod_bordereaux' ) ); ?>\">🖨️ <?php esc_html_e( 'Bordereaux (sélection)', 'infinitycod' ); ?></a>",
        "<a href=\"#\" id=\"icod-bordereaux\" class=\"button\" data-url=\"<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=icod_bordereaux' ), 'icod_bordereaux' ) ); ?>\">🖨️ <?php esc_html_e( 'Bordereaux (sélection)', 'infinitycod' ); ?></a>\n"
        "\t\t\t\t\t<a href=\"#\" id=\"icod-etiquettes\" class=\"button\" data-url=\"<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=icod_bordereaux&format=labels' ), 'icod_bordereaux' ) ); ?>\">🏷️ <?php esc_html_e( 'Étiquettes (sélection)', 'infinitycod' ); ?></a>",
    ),
])

append("assets/admin/js/admin.js", """

/* Étiquettes en lot : vue imprimable 10x15 des commandes cochées. */
(function () {
	var btn = document.getElementById('icod-etiquettes');
	if (!btn) { return; }
	btn.addEventListener('click', function (e) {
		e.preventDefault();
		var ids = [];
		document.querySelectorAll('#icod-orders-form input[name="ids[]"]:checked').forEach(function (cb) {
			ids.push(cb.value);
		});
		if (!ids.length) { toast((icodAdmin && icodAdmin.i18n && icodAdmin.i18n.error) || 'Sélection vide', 'error'); return; }
		window.open(btn.getAttribute('data-url') + '&ids=' + ids.join(','), '_blank', 'width=640,height=980');
	});
})();
""")

# ---------- 3) ADMIN-EXTRAS : WA quotidien + alerte stock (crons journaliers) ----------
patch("includes/admin/class-admin-extras.php", [
    (
        "\t\tadd_action( 'init', array( $this, 'schedule_weekly' ), 20 );",
        "\t\tadd_action( 'init', array( $this, 'schedule_weekly' ), 20 );\n"
        "\t\tadd_action( 'init', array( $this, 'schedule_daily_extras' ), 20 );\n"
        "\t\tadd_action( 'infinitycod_daily_wa_report', array( $this, 'send_daily_wa_report' ) );\n"
        "\t\tadd_action( 'infinitycod_stock_alert_check', array( $this, 'run_stock_alert_check' ) );",
    ),
    (
        "\tpublic function schedule_weekly() {",
        "\t/**\n"
        "\t * Crons journaliers : rapport WhatsApp marchand + vérification des stocks.\n"
        "\t *\n"
        "\t * @return void\n"
        "\t */\n"
        "\tpublic function schedule_daily_extras() {\n"
        "\t\tif ( Settings::get( 'wa_daily_report' ) && ! wp_next_scheduled( 'infinitycod_daily_wa_report' ) ) {\n"
        "\t\t\twp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'infinitycod_daily_wa_report' );\n"
        "\t\t}\n"
        "\t\tif ( (int) Settings::get( 'stock_alert_threshold', 0 ) > 0 && ! wp_next_scheduled( 'infinitycod_stock_alert_check' ) ) {\n"
        "\t\t\twp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', 'infinitycod_stock_alert_check' );\n"
        "\t\t}\n"
        "\t}\n\n"
        "\t/**\n"
        "\t * Rapport quotidien du jour envoyé au marchand sur son WhatsApp.\n"
        "\t *\n"
        "\t * @return void\n"
        "\t */\n"
        "\tpublic function send_daily_wa_report() {\n"
        "\t\tglobal \$wpdb;\n"
        "\t\t\$wa_owner = trim( (string) Settings::get( 'wa_owner_phone', '' ) );\n"
        "\t\tif ( '' === \$wa_owner ) {\n"
        "\t\t\treturn;\n"
        "\t\t}\n"
        "\t\t\$orders = Schema::table( 'orders' );\n"
        "\t\t\$today  = current_time( 'Y-m-d' ) . ' 00:00:00';\n"
        "\t\t\$row    = \$wpdb->get_row( \$wpdb->prepare(\n"
        "\t\t\t\"SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN status IN ('confirmed','shipped','delivered') THEN total ELSE 0 END),0) AS revenue FROM {\$orders} WHERE created_at >= %s\", // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB\n"
        "\t\t\t\$today\n"
        "\t\t), ARRAY_A );\n"
        "\t\t\$wa = infinitycod()->module( 'whatsapp' );\n"
        "\t\tif ( \$wa && is_array( \$row ) ) {\n"
        "\t\t\t\$text = '📊 ' . get_bloginfo( 'name' ) . ' — aujourd\\'hui : ' . (int) \$row['n'] . ' commande(s), ' . number_format_i18n( (float) \$row['revenue'], 0 ) . ' ' . Settings::currency_label() . '.';\n"
        "\t\t\t\$wa->send( \$wa_owner, \$text );\n"
        "\t\t}\n"
        "\t}\n\n"
        "\t/**\n"
        "\t * Alerte stock : produits sous le seuil → e-mail admin.\n"
        "\t *\n"
        "\t * @return void\n"
        "\t */\n"
        "\tpublic function run_stock_alert_check() {\n"
        "\t\t\$threshold = (int) Settings::get( 'stock_alert_threshold', 0 );\n"
        "\t\tif ( \$threshold < 1 || ! function_exists( 'wc_get_products' ) ) {\n"
        "\t\t\treturn;\n"
        "\t\t}\n"
        "\t\t\$low = array();\n"
        "\t\t\$products = wc_get_products( array( 'limit' => 500, 'status' => 'publish', 'type' => array( 'simple' ) ) );\n"
        "\t\tforeach ( (array) \$products as \$product ) {\n"
        "\t\t\tif ( ! \$product->managing_stock() ) {\n"
        "\t\t\t\tcontinue;\n"
        "\t\t\t}\n"
        "\t\t\t\$qty = (int) \$product->get_stock_quantity();\n"
        "\t\t\tif ( \$qty >= 0 && \$qty <= \$threshold ) {\n"
        "\t\t\t\t\$low[] = '• ' . \$product->get_name() . ' — ' . \$qty;\n"
        "\t\t\t}\n"
        "\t\t}\n"
        "\t\tif ( \$low ) {\n"
        "\t\t\twp_mail( get_option( 'admin_email' ),\n"
        "\t\t\t\tsprintf( /* translators: 1 : site, 2 : nombre. */ __( '[%1$s] Stock faible : %2$d produit(s)', 'infinitycod' ), get_bloginfo( 'name' ), count( \$low ) ),\n"
        "\t\t\t\timplode( \"\\n\", \$low )\n"
        "\t\t\t);\n"
        "\t\t}\n"
        "\t}\n\n"
        "\tpublic function schedule_weekly() {",
    ),
])

# ---------- 4) ORDERS MODAL : bouton facture ----------
patch("includes/admin/pages/class-orders-page.php", [
    (
        "\t\t\t'bordereau_url'  => wp_nonce_url( admin_url( 'admin-post.php?action=icod_bordereaux&ids=' . (int) $row['id'] ), 'icod_bordereaux' ),",
        "\t\t\t'bordereau_url'  => wp_nonce_url( admin_url( 'admin-post.php?action=icod_bordereaux&ids=' . (int) $row['id'] ), 'icod_bordereaux' ),\n"
        "\t\t\t'invoice_url'    => wp_nonce_url( admin_url( 'admin-post.php?action=icod_invoice&id=' . (int) $row['id'] ), 'icod_invoice' ),",
    ),
])

# ---------- 5) ADMIN.JS : bouton facture dans la modale ----------
patch("assets/admin/js/admin.js", [
    (
        "\t\t\t\t\t\t((o.bordereau_url) ? '<a class=\"button button-small\" href=\"' + escHtml(o.bordereau_url) + '\" target=\"_blank\" rel=\"noopener\">🖨️ Bordereau</a> ' : '') +",
        "\t\t\t\t\t\t((o.bordereau_url) ? '<a class=\"button button-small\" href=\"' + escHtml(o.bordereau_url) + '\" target=\"_blank\" rel=\"noopener\">🖨️ Bordereau</a> ' : '') +\n"
        "\t\t\t\t\t\t((o.invoice_url) ? '<a class=\"button button-small\" href=\"' + escHtml(o.invoice_url) + '\" target=\"_blank\" rel=\"noopener\">🧾 Facture</a> ' : '') +",
    ),
])

print("\n".join("OK  " + o for o in ok))
