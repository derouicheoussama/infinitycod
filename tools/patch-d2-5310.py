# -*- coding: utf-8 -*-
"""Patch 5.31.0 bis : P&L return_fees + Telegram hebdo."""
import io, re

ROOT = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod"
BS = chr(92)
ok = []

# ---- P&L : jointure wilayas + provision retours ----
p = ROOT + BS + r"includes\stats\class-pnl.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()

if "return_fees" not in d:
    # 1) table + $wtab
    m = re.search(r"(\n(\s+)\$table = Schema::table\( 'orders' \);\n)", d)
    assert m, "ancre table"
    d = d.replace(m.group(1), m.group(1) + m.group(2) + "$wtab  = Schema::table( 'wilayas' );\n", 1)

    # 2) ligne shipping_collected -> ajouter return_fees après (indentation souple)
    pat = re.compile(r"(\n(\s+)SUM\(CASE WHEN status = 'delivered' THEN shipping ELSE 0 END\) AS shipping_collected,)")
    m2 = pat.search(d)
    assert m2, "ancre shipping_collected"
    d = pat.sub(lambda mm: mm.group(1) + "\n" + mm.group(2) + "COALESCE(SUM(CASE WHEN o.status = 'returned' THEN w.return_fee ELSE 0 END),0) AS return_fees,", d, count=1)

    # 3) FROM : alias o + jointure wilayas
    pat3 = re.compile(r"(\n\s+)FROM \{\$table\} WHERE created_at BETWEEN %s AND %s")
    m3 = pat3.search(d)
    assert m3, "ancre FROM"
    d = pat3.sub(lambda mm: mm.group(1) + "FROM {$table} o LEFT JOIN {$wtab} w ON w.code = o.wilaya_code" + mm.group(1) + "WHERE o.created_at BETWEEN %s AND %s", d, count=1)

    # 4) clé de sortie
    oldk = "\t\t\t'returned'            => (int) $row['returned'],"
    altk = "\t\t\t'returned'            => (int) $row['returned'],"
    if d.count(oldk) == 1:
        d = d.replace(oldk, oldk + "\n\t\t\t'return_fees'         => (float) ( $row['return_fees'] ?? 0 ),", 1)
    else:
        patk = re.compile(r"(\n(\s+)'returned'(\s+)=> \(int\) \$row\['returned'\],)")
        mk = patk.search(d)
        assert mk, "ancre returned"
        d = patk.sub(lambda mm: mm.group(1) + "\n" + mm.group(2) + "'return_fees'         => (float) ( $row['return_fees'] ?? 0 ),", d, count=1)
    with io.open(p, "w", encoding="utf-8", newline="") as f:
        f.write(d)
    ok.append("pnl return_fees")
else:
    ok.append("pnl déjà patché")

# ---- Admin extras : Telegram hebdo ----
p = ROOT + BS + r"includes\admin\class-admin-extras.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
if "api.telegram.org" not in d:
    anchor = "\t\twp_mail( $to, $subject, $body, array( 'Content-Type: text/html; charset=utf-8' ) );\n\t}"
    new = (
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
        "\t\t}\n\t}"
    )
    assert d.count(anchor) == 1, "ancre wp_mail"
    d = d.replace(anchor, new, 1)
    with io.open(p, "w", encoding="utf-8", newline="") as f:
        f.write(d)
    ok.append("telegram hebdo")
else:
    ok.append("telegram hebdo déjà présent")

print("\n".join("OK  " + o for o in ok))
