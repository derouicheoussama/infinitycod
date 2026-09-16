# -*- coding: utf-8 -*-
"""Patch 5.32.0 : UX Commandes — avatars, temps relatif, WhatsApp direct, chronologie, bordereau."""
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


# ============ 1) ORDERS PAGE : cellule client enrichie + date relative + WhatsApp ============
patch("includes/admin/pages/class-orders-page.php", [
    # Cellule client : avatar initiales + #numéro + bouton WhatsApp.
    (
        "\t\t<td data-label=\"<?php esc_attr_e( 'Client', 'infinitycod' ); ?>\">\n"
        "\t\t\t<strong><?php echo esc_html( $row['customer_name'] ); ?></strong>\n"
        "\t\t\t<span class=\"icod-sub\"><?php echo esc_html( $row['phone'] ); ?></span>\n"
        "\t\t</td>",
        "\t\t<td data-label=\"<?php esc_attr_e( 'Client', 'infinitycod' ); ?>\">\n"
        "\t\t\t<div class=\"icod-client\">\n"
        "\t\t\t\t<span class=\"icod-avatar\" data-name=\"<?php echo esc_attr( $row['customer_name'] ); ?>\"><?php echo esc_html( strtoupper( mb_substr( $row['customer_name'], 0, 1 ) ) ); ?></span>\n"
        "\t\t\t\t<span class=\"icod-client-meta\">\n"
        "\t\t\t\t\t<strong>#<?php echo (int) $row['id']; ?> — <?php echo esc_html( $row['customer_name'] ); ?></strong>\n"
        "\t\t\t\t\t<span class=\"icod-sub\"><?php echo esc_html( $row['phone'] ); ?></span>\n"
        "\t\t\t\t</span>\n"
        "\t\t\t</div>\n"
        "\t\t</td>",
    ),
    # Date : temps relatif + horodatage exact en title.
    (
        "\t\t<td data-label=\"<?php esc_attr_e( 'Date', 'infinitycod' ); ?>\">\n"
        "\t\t\t<?php echo esc_html( mysql2date( 'd/m/Y H:i', $row['created_at'] ) ); ?>\n"
        "\t\t</td>",
        "\t\t<td data-label=\"<?php esc_attr_e( 'Date', 'infinitycod' ); ?>\">\n"
        "\t\t\t<span title=\"<?php echo esc_attr( mysql2date( 'd/m/Y H:i', $row['created_at'] ) ); ?>\"><?php echo esc_html( self::time_diff_i18n( $row['created_at'] ) ); ?></span>\n"
        "\t\t</td>",
    ),
    # Actions : bouton WhatsApp rapide en tête de ligne.
    (
        "\t\t\t<td class=\"icod-col-actions icod-row-actions\">\n"
        "\t\t\t\t<button type=\"button\" class=\"button button-small icod-open\" data-order=\"<?php echo esc_attr( wp_json_encode( $this->order_payload( $row ) ) ); ?>\" title=\"<?php esc_attr_e( 'Voir les détails et modifier', 'infinitycod' ); ?>\">🔍</button>",
        "\t\t\t<td class=\"icod-col-actions icod-row-actions\">\n"
        "\t\t\t\t<a class=\"button button-small\" href=\"https://wa.me/213<?php echo esc_attr( ltrim( preg_replace( '/[^0-9]/', '', (string) $row['phone'] ), '0' ) ); ?>\" target=\"_blank\" rel=\"noopener\" title=\"<?php esc_attr_e( 'WhatsApp client', 'infinitycod' ); ?>\">💬</a>\n"
        "\t\t\t\t<button type=\"button\" class=\"button button-small icod-open\" data-order=\"<?php echo esc_attr( wp_json_encode( $this->order_payload( $row ) ) ); ?>\" title=\"<?php esc_attr_e( 'Voir les détails et modifier', 'infinitycod' ); ?>\">🔍</button>",
    ),
])

# Payload : chronologie + lien bordereau individuel.
p = ROOT + BS + r"includes\admin\pages\class-orders-page.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
old = "\t\t\t'created_at'     => mysql2date( 'd/m/Y H:i', $row['created_at'] ),\n\t\t);"
new = (
    "\t\t\t'created_at'     => mysql2date( 'd/m/Y H:i', $row['created_at'] ),\n"
    "\t\t\t'confirmed_at'   => ! empty( $row['confirmed_at'] ) ? mysql2date( 'd/m/Y H:i', $row['confirmed_at'] ) : '',\n"
    "\t\t\t'shipped_at'     => ! empty( $row['shipped_at'] ) ? mysql2date( 'd/m/Y H:i', $row['shipped_at'] ) : '',\n"
    "\t\t\t'delivered_at'   => ! empty( $row['delivered_at'] ) ? mysql2date( 'd/m/Y H:i', $row['delivered_at'] ) : '',\n"
    "\t\t\t'bordereau_url'  => wp_nonce_url( admin_url( 'admin-post.php?action=icod_bordereaux&ids=' . (int) $row['id'] ), 'icod_bordereaux' ),\n"
    "\t\t);"
)
assert d.count(old) == 1, "payload : %d" % d.count(old)
d = d.replace(old, new, 1)

# Helper temps relatif (méthode statique privée) : inséré avant render_row.
anchor = "\tprivate function render_row( array $row ) {"
helper = (
    "\t/**\n"
    "\t * Temps écoulé lisible (« il y a 2 h ») pour la colonne Date.\n"
    "\t *\n"
    "\t * @param string $mysql Datetime MySQL.\n"
    "\t * @return string\n"
    "\t */\n"
    "\tprivate static function time_diff_i18n( $mysql ) {\n"
    "\t\t$ts = mysql2date( 'U', $mysql );\n"
    "\t\t$diff = max( 0, current_time( 'timestamp' ) - (int) $ts );\n"
    "\t\tif ( $diff < 60 ) {\n"
    "\t\t\treturn __( 'à l’instant', 'infinitycod' );\n"
    "\t\t}\n"
    "\t\tif ( $diff < HOUR_IN_SECONDS ) {\n"
    "\t\t\t/* translators: %d : nombre de minutes. */\n"
    "\t\t\treturn sprintf( __( 'il y a %d min', 'infinitycod' ), (int) ( $diff / 60 ) );\n"
    "\t\t}\n"
    "\t\tif ( $diff < DAY_IN_SECONDS ) {\n"
    "\t\t\t/* translators: %d : nombre d’heures. */\n"
    "\t\t\treturn sprintf( __( 'il y a %d h', 'infinitycod' ), (int) ( $diff / HOUR_IN_SECONDS ) );\n"
    "\t\t}\n"
    "\t\tif ( $diff < 7 * DAY_IN_SECONDS ) {\n"
    "\t\t\t/* translators: %d : nombre de jours. */\n"
    "\t\t\treturn sprintf( __( 'il y a %d j', 'infinitycod' ), (int) ( $diff / DAY_IN_SECONDS ) );\n"
    "\t\t}\n"
    "\t\treturn mysql2date( 'd/m/Y', $mysql );\n"
    "\t}\n\n"
    "\t"
)
assert d.count(anchor) == 1, "ancre render_row : %d" % d.count(anchor)
d = d.replace(anchor, helper + anchor, 1)
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
ok.append("orders-page (client, date, WhatsApp, timeline payload, time_diff)")

print("\n".join("OK  " + o for o in ok))
