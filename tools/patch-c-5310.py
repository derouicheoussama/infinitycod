# -*- coding: utf-8 -*-
"""Patch 5.31.0 — WhatsApp 1-clic, webhooks, Shield, P&L, bordereaux, réglages."""
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
        assert n == 1, rel + " :: " + old[:60].replace("\n", "⏎") + " -> " + str(n)
        d = d.replace(old, new, 1)
    with io.open(p, "w", encoding="utf-8", newline="") as f:
        f.write(d)
    ok.append(rel + " (" + str(len(pairs)) + " patches)")


# ================= 1) WHATSAPP : lien de confirmation 1 clic =================
patch("includes/whatsapp/class-whatsapp-manager.php", [
    (
        "\t\tadd_action( 'infinitycod_order_created', array( $this, 'on_order_created' ), 10, 1 );",
        "\t\tadd_action( 'infinitycod_order_created', array( $this, 'on_order_created' ), 10, 3 );",
    ),
    (
        "\tpublic function on_order_created( $order ) {",
        "\tpublic function on_order_created( $order, $data = array(), $icod_id = 0 ) {",
    ),
    (
        "\t\t$this->send_template( 'msg_order_received', $to, array(\n"
        "\t\t\t'nom'       => $order->get_billing_first_name(),\n"
        "\t\t\t'telephone' => $phone,\n"
        "\t\t\t'commande'  => $order->get_id(),\n"
        "\t\t\t'total'     => number_format_i18n( (float) $order->get_total(), 2 ) . ' DA',\n"
        "\t\t) );",
        "\t\t$this->send_template( 'msg_order_received', $to, array(\n"
        "\t\t\t'nom'       => $order->get_billing_first_name(),\n"
        "\t\t\t'telephone' => $phone,\n"
        "\t\t\t'commande'  => $order->get_id(),\n"
        "\t\t\t'total'     => number_format_i18n( (float) $order->get_total(), 2 ) . ' DA',\n"
        "\t\t\t'lien_confirmation' => self::confirm_link( (int) $icod_id, $phone ),\n"
        "\t\t) );",
    ),
])

# Méthode statique confirm_link (ajout avant render_template).
p = ROOT + r"\includes\whatsapp\class-whatsapp-manager.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
anchor = "\tpublic static function render_template( $template, array $vars ) {"
add = (
    "\t/**\n"
    "\t * Lien signé « Je confirme ma commande » (1 clic depuis WhatsApp).\n"
    "\t *\n"
    "\t * @param int    $icod_id Ligne interne de commande.\n"
    "\t * @param string $phone   Téléphone saisi (entre dans la signature).\n"
    "\t * @return string\n"
    "\t */\n"
    "\tpublic static function confirm_link( $icod_id, $phone ) {\n"
    "\t\t$icod_id = (int) $icod_id;\n"
    "\t\tif ( $icod_id < 1 ) {\n"
    "\t\t\treturn '';\n"
    "\t\t}\n"
    "\t\t$token = substr( hash_hmac( 'sha256', $icod_id . '|' . (string) $phone, wp_salt( 'auth' ) ), 0, 24 );\n"
    "\t\treturn home_url( '/?icod_confirm=' . $icod_id . '&t=' . $token );\n"
    "\t}\n\n"
    "\t"
)
assert d.count(anchor) == 1
d = d.replace(anchor, add + anchor, 1)
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
ok.append("whatsapp confirm_link")

# ================= 2) ENDPOINT DE CONFIRMATION (class-plugin) =================
patch("includes/core/class-plugin.php", [
    (
        "\t\tadd_action( 'init', array( $this, 'load_textdomain' ), 1 );",
        "\t\tadd_action( 'init', array( $this, 'load_textdomain' ), 1 );\n"
        "\t\tadd_action( 'init', array( $this, 'handle_order_confirm' ) );",
    ),
])
p = ROOT + r"\includes\core\class-plugin.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
anchor = "\tpublic function module( $slug ) {"
add = (
    "\t/**\n"
    "\t * Endpoint public « Je confirme ma commande » (lien WhatsApp signé).\n"
    "\t *\n"
    "\t * GET /?icod_confirm=ID&t=TOKEN — passe la commande en « confirmée ».\n"
    "\t *\n"
    "\t * @return void\n"
    "\t */\n"
    "\tpublic function handle_order_confirm() {\n"
    "\t\tif ( ! isset( $_GET['icod_confirm'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lien signé à destination du client.\n"
    "\t\t\treturn;\n"
    "\t\t}\n"
    "\t\t$icod_id = absint( $_GET['icod_confirm'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended\n"
    "\t\t$token   = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended\n"
    "\t\t$orders  = $this->module( 'orders' );\n"
    "\t\t$done    = $orders ? $orders->confirm_from_link( $icod_id, $token ) : false;\n"
    "\n"
    "\t\tnocache_headers();\n"
    "\t\theader( 'Content-Type: text/html; charset=utf-8' );\n"
    "\t\tif ( $done ) {\n"
    "\t\t\techo '<!DOCTYPE html><html><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>✅</title></head>'\n"
    "\t\t\t\t. '<body style=\"font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:90vh;background:#f6f8f7\">'\n"
    "\t\t\t\t. '<div style=\"text-align:center;padding:32px;background:#fff;border-radius:16px;box-shadow:0 6px 24px rgba(0,0,0,.08);max-width:420px\">'\n"
    "\t\t\t\t. '<div style=\"font-size:52px\">✅</div><h1 style=\"font-size:20px;margin:8px 0\">' . esc_html__( 'Commande confirmée !', 'infinitycod' ) . '</h1>'\n"
    "\t\t\t\t. '<p style=\"color:#555\">' . esc_html__( 'Merci ! Votre commande est confirmée. Nous vous contactons très vite.', 'infinitycod' ) . '</p>'\n"
    "\t\t\t\t. '</div></body></html>';\n"
    "\t\t} else {\n"
    "\t\t\techo '<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>⚠️</title></head>'\n"
    "\t\t\t\t. '<body style=\"font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:90vh\">'\n"
    "\t\t\t\t. '<p>' . esc_html__( 'Lien de confirmation invalide ou déjà utilisé.', 'infinitycod' ) . '</p></body></html>';\n"
    "\t\t}\n"
    "\t\texit;\n"
    "\t}\n\n"
    "\t"
)
assert d.count(anchor) == 1
d = d.replace(anchor, add + anchor, 1)
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
ok.append("class-plugin handle_order_confirm")

# ================= 3) ORDER STORE : confirm_from_link =================
patch("includes/orders/class-order-store.php", [
    (
        "\tpublic function set_status( $icod_id, $status ) {",
        "\t/**\n"
        "\t * Vérifie le lien signé « je confirme » et passe la commande en confirmée.\n"
        "\t *\n"
        "\t * @param int    $icod_id Ligne interne.\n"
        "\t * @param string $token   Jeton HMAC du lien.\n"
        "\t * @return bool\n"
        "\t */\n"
        "\tpublic function confirm_from_link( $icod_id, $token ) {\n"
        "\t\tglobal $wpdb;\n"
        "\t\t$icod_id = (int) $icod_id;\n"
        "\t\t$row    = $wpdb->get_row( $wpdb->prepare( 'SELECT id, phone, status FROM ' . Schema::table( 'orders' ) . ' WHERE id = %d', $icod_id ), ARRAY_A );\n"
        "\t\tif ( ! is_array( $row ) || '' === (string) $token ) {\n"
        "\t\t\treturn false;\n"
        "\t\t}\n"
        "\t\t$expect = substr( hash_hmac( 'sha256', $icod_id . '|' . (string) $row['phone'], wp_salt( 'auth' ) ), 0, 24 );\n"
        "\t\tif ( ! hash_equals( $expect, (string) $token ) ) {\n"
        "\t\t\treturn false;\n"
        "\t\t}\n"
        "\t\tif ( 'pending' !== $row['status'] ) {\n"
        "\t\t\treturn true; // Déjà traitée : lien consommé, page positive.\n"
        "\t\t}\n"
        "\t\t$this->set_status( $icod_id, 'confirmed' );\n"
        "\t\treturn true;\n"
        "\t}\n\n"
        "\tpublic function set_status( $icod_id, $status ) {",
    ),
])
ok.append("order-store confirm_from_link")

print("\n".join("OK  " + o for o in ok))
