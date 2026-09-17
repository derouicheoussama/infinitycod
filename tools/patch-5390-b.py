# -*- coding: utf-8 -*-
"""Patch 5.39.0 B : fidélité (quote + submit), alertes stock, WA quotidien, bordereaux étiquettes."""
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


# ---------- 1) REST quote : aperçu fidélité pour le téléphone saisi ----------
patch("includes/rest/class-routes.php", [
    (
        "\t\t$mode        = ( isset( $body['mode'] ) && 'desk' === $body['mode'] ) ? RatesManager::MODE_DESK : RatesManager::MODE_HOME;\n\n\t\t$product = $variation_id ? wc_get_product( $variation_id ) : wc_get_product( $product_id );",
        "\t\t$mode        = ( isset( $body['mode'] ) && 'desk' === $body['mode'] ) ? RatesManager::MODE_DESK : RatesManager::MODE_HOME;\n"
        "\t\t$quote_phone = isset( $body['phone'] ) ? sanitize_text_field( (string) $body['phone'] ) : '';\n\n"
        "\t\t$product = $variation_id ? wc_get_product( $variation_id ) : wc_get_product( $product_id );",
    ),
    (
        "\t\t\t'min_order'      => $min_order,\n\t\t\t'total'          => max( 0, $subtotal - $discount['amount'] - $coupon_out['amount'] + max( 0, $shipping ) ),\n\t\t)",
        "\t\t\t'min_order'      => $min_order,\n"
        "\t\t\t'loyalty'        => \\InfinityCod\\Loyalty\\Loyalty::preview( $quote_phone, max( 0, $subtotal - $discount['amount'] - $coupon_out['amount'] ) ),\n"
        "\t\t\t'total'          => max( 0, $subtotal - $discount['amount'] - $coupon_out['amount'] - $loyalty['discount'] + max( 0, $shipping ) ),\n\t\t)",
    ),
])
# $loyalty doit être calculé avant le return : insertion après le bloc coupon.
p = ROOT + BS + r"includes\rest\class-routes.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
anchor = "\t\t$coupon_out  = array(\n\t\t\t'code'   => $coupon_code,"
add = (
    "\t\t// Fidélité : aperçu de la remise fidélité pour ce téléphone (si le client\n"
    "\t\t// a assez de points issus de ses commandes livrées).\n"
    "\t\t$loyalty = \\InfinityCod\\Loyalty\\Loyalty::preview( $quote_phone, $base );\n\n"
    "\t\t"
)
assert d.count(anchor) == 1
d = d.replace(anchor, add + anchor, 1)
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
ok.append("routes quote loyalty")

# ---------- 2) Soumission : remise fidélité serveur + loyalty_used ----------
patch("includes/orders/class-order-store.php", [
    (
        "\t\t$coupon_amount = $coupon['valid'] ? (float) $coupon['amount'] : 0.0;\n\n\t\t// Statistiques du code promo InfinityCod (utilisations + montants).",
        "\t\t$coupon_amount = $coupon['valid'] ? (float) $coupon['amount'] : 0.0;\n\n"
        "\t\t// Fidélité : remise automatique en points (commandes livrées), validée\n"
        "\t\t// côté serveur uniquement — plafonnée à 30 % du sous-total remisé.\n"
        "\t\t$loyalty_discount = 0.0;\n"
        "\t\tif ( \\InfinityCod\\Core\\Settings::get( 'loyalty_enabled' ) && ! empty( $data['loyalty_use'] ) ) {\n"
        "\t\t\t$loyalty_discount = \\InfinityCod\\Loyalty\\Loyalty::discount_for( (string) $row['phone'], $subtotal - $discount_amount );\n"
        "\t\t}\n\n"
        "\t\t// Statistiques du code promo InfinityCod (utilisations + montants).",
    ),
    (
        "\t\t$total = max( 0, $subtotal - $discount_amount - $coupon_amount + $shipping_price );",
        "\t\t$total = max( 0, $subtotal - $discount_amount - $coupon_amount - $loyalty_discount + $shipping_price );",
    ),
    (
        "\t\t\t\t'ab_variant'    => ( isset( $data['ab'] ) && 'B' === $data['ab'] ) ? 'B' : 'A',",
        "\t\t\t\t'ab_variant'    => ( isset( $data['ab'] ) && 'B' === $data['ab'] ) ? 'B' : 'A',\n"
        "\t\t\t\t'loyalty_used'  => $loyalty_discount,",
    ),
])
ok.append("order-store fidélité")

print("\n".join("OK  " + o for o in ok))
