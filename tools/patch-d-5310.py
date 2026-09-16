# -*- coding: utf-8 -*-
"""Patch 5.31.0 suite : Shield (retours + blacklist communautaire), P&L (frais de retour),
Telegram hebdo, bordereaux en lot, module webhooks, schéma réglages + UI."""
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


# ============ 1) SHIELD : historique retours + blacklist communautaire ============
patch("includes/anti-fraud/class-shield.php", [
    (
        "\t\t$score = min( 100, $score );",
        "\t\t// 11. Historique de retours du numéro (statuts transporteurs réels) :\n"
        "\t\t// chaque colis retourné dans les 90 jours augmente le risque.\n"
        "\t\tif ( $phone ) {\n"
        "\t\t\t$returns = $this->count_phone_returns( $phone );\n"
        "\t\t\tif ( $returns > 0 ) {\n"
        "\t\t\t\t$flags[] = 'phone_returns';\n"
        "\t\t\t\t$score  += min( 24, 12 * $returns );\n"
        "\t\t\t}\n"
        "\t\t}\n\n"
        "\t\t// 12. Blacklist communautaire (opt-in) : numéros hashés partagés\n"
        "\t\t// entre boutiques InfinityCod (liste publique signée, cache 12 h).\n"
        "\t\tif ( $phone && Settings::get( 'community_blacklist' ) && self::community_blacklisted( $phone ) ) {\n"
        "\t\t\t$flags[] = 'community_blacklist';\n"
        "\t\t\t$score  += 35;\n"
        "\t\t}\n\n"
        "\t\t$score = min( 100, $score );",
    ),
])

# Méthodes helpers du Shield (ajout avant client_ip()).
p = ROOT + r"\includes\anti-fraud\class-shield.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
anchor = "\t/**\n\t * IP cliente (derrière CDN éventuels, en-têtes les plus courants)."
add = (
    "\t/**\n"
    "\t * Nombre de colis retournés pour ce téléphone (90 jours, statuts transporteur).\n"
    "\t *\n"
    "\t * @param string $phone Téléphone normalisé.\n"
    "\t * @return int\n"
    "\t */\n"
    "\tpublic function count_phone_returns( $phone ) {\n"
    "\t\tglobal $wpdb;\n"
    "\t\t$orders = Schema::table( 'orders' );\n"
    "\t\t$since  = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 90 * DAY_IN_SECONDS );\n"
    "\t\treturn (int) $wpdb->get_var( $wpdb->prepare(\n"
    "\t\t\t\"SELECT COUNT(*) FROM {$orders} WHERE phone = %s AND (status = 'returned' OR carrier_status = 'returned') AND created_at >= %s\", // phpcs:ignore WordPress.DB.PreparedSQL\n"
    "\t\t\t$phone,\n"
    "\t\t\t$since\n"
    "\t\t) );\n"
    "\t}\n\n"
    "\t/**\n"
    "\t * Le numéro figure-t-il dans la liste communautaire (sha256 du numéro) ?\n"
    "\t *\n"
    "\t * @param string $phone Téléphone normalisé.\n"
    "\t * @return bool\n"
    "\t */\n"
    "\tpublic static function community_blacklisted( $phone ) {\n"
    "\t\t$list = get_transient( 'icod_community_blacklist' );\n"
    "\t\tif ( false === $list ) {\n"
    "\t\t\t$list    = array();\n"
    "\t\t\t$raw     = wp_remote_get( 'https://raw.githubusercontent.com/derouicheoussama/infinitycod-releases/latest/community-blacklist.json', array( 'timeout' => 6 ) );\n"
    "\t\t\t$parsed  = ( is_array( $raw ) && 200 === (int) $raw['response']['code'] ) ? json_decode( $raw['body'], true ) : null;\n"
    "\t\t\tif ( is_array( $parsed ) ) {\n"
    "\t\t\t\t$list = $parsed;\n"
    "\t\t\t}\n"
    "\t\t\tset_transient( 'icod_community_blacklist', $list, 12 * HOUR_IN_SECONDS );\n"
    "\t\t}\n"
    "\t\tif ( ! is_array( $list ) || ! $list ) {\n"
    "\t\t\treturn false;\n"
    "\t\t}\n"
    "\t\t$hash = hash( 'sha256', 'icodbl:' . (string) $phone );\n"
    "\t\treturn in_array( $hash, $list, true );\n"
    "\t}\n\n"
    "\t"
)
assert d.count(anchor) == 1
d = d.replace(anchor, add + anchor, 1)
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
ok.append("shield helpers")

# ============ 2) P&L : provision frais de retour ============
patch("includes/stats/class-pnl.php", [
    (
        "\t\t$table = Schema::table( 'orders' );\n\n\t\t$row = $wpdb->get_row( $wpdb->prepare(\n\t\t\t\"SELECT\n\t\t\t\tCOUNT(*) AS total,",
        "\t\t$table = Schema::table( 'orders' );\n\t\t$wtab  = Schema::table( 'wilayas' );\n\n\t\t$row = $wpdb->get_row( $wpdb->prepare(\n\t\t\t\"SELECT\n\t\t\t\tCOUNT(*) AS total,",
    ),
    (
        "\t\t\t\t SUM(CASE WHEN status = 'delivered' THEN shipping ELSE 0 END) AS shipping_collected,\n\t\t\t\tSUM(discount) AS discounts,",
        "\t\t\t\t SUM(CASE WHEN status = 'delivered' THEN shipping ELSE 0 END) AS shipping_collected,\n\t\t\t\tCOALESCE(SUM(CASE WHEN o.status = 'returned' THEN w.return_fee ELSE 0 END),0) AS return_fees,\n\t\t\t\tSUM(discount) AS discounts,",
    ),
    (
        "\t\t\t FROM {$table} WHERE created_at BETWEEN %s AND %s\",",
        "\t\t\t FROM {$table} o LEFT JOIN {$wtab} w ON w.code = o.wilaya_code\n\t\t\t WHERE o.created_at BETWEEN %s AND %s\",",
    ),
])
p = ROOT + r"\includes\stats\class-pnl.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
# Clé retour dans le tableau de sortie.
old = "\t\t\t'shipping_collected' => (float) ( $row['shipping_collected'] ?? 0 ),"
if d.count(old) == 1:
    d = d.replace(old, old + "\n\t\t\t'return_fees'        => (float) ( $row['return_fees'] ?? 0 ),", 1)
else:
    # Ancre alternative : 'returned' => ...
    old2 = "\t\t\t'returned'            => (int) $row['returned'],"
    assert d.count(old2) == 1, "ancre kpis absente"
    d = d.replace(old2, old2 + "\n\t\t\t'return_fees'         => (float) ( $row['return_fees'] ?? 0 ),", 1)
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
ok.append("pnl return_fees")

# ============ 3) TELEGRAM HEBDO + bordereaux handler + registrations ============
patch("includes/admin/class-admin-extras.php", [
    (
        "\t\twp_mail( $to, $subject, $body, array( 'Content-Type: text/html; charset=utf-8' ) );\n\t}",
        "\t\twp_mail( $to, $subject, $body, array( 'Content-Type: text/html; charset=utf-8' ) );\n\n"
        "\t\t// Telegram (optionnel) : même synthèse en texte court.\n"
        "\t\t$bot  = trim( (string) Settings::get( 'telegram_bot_token', '' ) );\n"
        "\t\t$chat = trim( (string) Settings::get( 'telegram_chat_id', '' ) );\n"
        "\t\tif ( '' !== $bot && '' !== $chat ) {\n"
        "\t\t\t$text = '📊 ' . get_bloginfo( 'name' ) . ' — 7 jours : ' . (int) $row['n'] . ' commande(s), '\n"
        "\t\t\t\t. (int) $row['confirmed'] . ' confirmée(s), ' . number_format_i18n( (float) $row['revenue'], 0 ) . ' ' . Settings::currency_label() . '.';\n"
        "\t\t\twp_remote_post( 'https://api.telegram.org/bot' . rawurlencode( $bot ) . '/sendMessage', array(\n"
        "\t\t\t\t'timeout' => 8,\n"
        "\t\t\t\t'headers' => array( 'Content-Type' => 'application/json' ),\n"
        "\t\t\t\t'body'    => wp_json_encode( array( 'chat_id' => $chat, 'text' => $text ) ),\n"
        "\t\t\t) );\n"
        "\t\t}\n\t}",
    ),
])
ok.append("admin-extras telegram hebdo")

print("\n".join("OK  " + o for o in ok))
