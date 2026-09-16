# -*- coding: utf-8 -*-
"""Patch FormManager : A/B, wilayas suspendues, hidden ab, ligne délai, localize."""
import io

p = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod\includes\form\class-form-manager.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()

BS = chr(92)  # backslash

# 1) Options wilaya : wilayas suspendues désactivées avec suffixe.
old_opt = (
    "\t\t$wilaya_options = '';\n"
    "\t\tforeach ( $wilayas as $w ) {\n"
    "\t\t\t$wilaya_options .= sprintf(\n"
    "\t\t\t\t'<option value=\"%1$s\" data-country=\"%3$s\">%2$s</option>',\n"
    "\t\t\t\tesc_attr( $w['code'] ),\n"
    "\t\t\t\tesc_html( $geo->wilaya_label( $w ) ),\n"
    "\t\t\t\tesc_attr( isset( $w['country_code'] ) ? $w['country_code'] : 'DZ' )\n"
    "\t\t\t);\n"
    "\t\t}"
)
new_opt = (
    "\t\t$wilaya_options = '';\n"
    "\t\tforeach ( $wilayas as $w ) {\n"
    "\t\t\t$suspended = isset( $w['active'] ) && ! (int) $w['active'];\n"
    "\t\t\t$wilaya_options .= sprintf(\n"
    "\t\t\t\t'<option value=\"%1$s\" data-country=\"%3$s\"%4$s>%2$s</option>',\n"
    "\t\t\t\tesc_attr( $w['code'] ),\n"
    "\t\t\t\tesc_html( $geo->wilaya_label( $w ) . ( $suspended ? ' — ' . __( 'indisponible', 'infinitycod' ) : '' ) ),\n"
    "\t\t\t\tesc_attr( isset( $w['country_code'] ) ? $w['country_code'] : 'DZ' ),\n"
    "\t\t\t\t$suspended ? ' disabled' : ''\n"
    "\t\t\t);\n"
    "\t\t}"
)
assert d.count(old_opt) == 1, "options wilaya : %d" % d.count(old_opt)
d = d.replace(old_opt, new_opt, 1)

# 2) A/B : assignation + surcharges variante B, avant la palette.
old_pal = "\t\t// Palette dérivée de l'accent : la couleur du dashboard pilote tout"
new_pal = (
    "\t\t// A/B test : surcharges de la variante B (titre, bouton, couleur).\n"
    "\t\t$ab_variant = " + BS + "InfinityCod" + BS + "Orders" + BS + "AbTest::assign();\n"
    "\t\t" + BS + "InfinityCod" + BS + "Orders" + BS + "AbTest::track_view( $ab_variant );\n"
    "\t\tif ( 'B' === $ab_variant ) {\n"
    "\t\t\t$b_title = trim( (string) Settings::get( 'ab_b_title', '' ) );\n"
    "\t\t\t$b_btn   = trim( (string) Settings::get( 'ab_b_button', '' ) );\n"
    "\t\t\tif ( '' === $custom_title && '' !== $b_title ) {\n"
    "\t\t\t\t$custom_title = $b_title;\n"
    "\t\t\t}\n"
    "\t\t\tif ( '' === $custom_button && '' !== $b_btn ) {\n"
    "\t\t\t\t$custom_button = $b_btn;\n"
    "\t\t\t}\n"
    "\t\t\t$b_accent = trim( (string) Settings::get( 'ab_b_accent', '' ) );\n"
    "\t\t\tif ( preg_match( '/^#[0-9A-Fa-f]{6}$/', $b_accent ) ) {\n"
    "\t\t\t\t$accent = $b_accent;\n"
    "\t\t\t}\n"
    "\t\t}\n\n"
    "\t\t// Palette dérivée de l'accent : la couleur du dashboard pilote tout"
)
assert d.count(old_pal) == 1, "palette : %d" % d.count(old_pal)
d = d.replace(old_pal, new_pal, 1)

# 3) Champ caché icod_ab.
old_ts = '<input type="hidden" name="icod_ts" value="<?php echo esc_attr( $ts ); ?>" />'
new_ts = old_ts + '\n\t\t\t\t\t<input type="hidden" name="icod_ab" value="<?php echo esc_attr( $ab_variant ); ?>" />'
assert d.count(old_ts) == 1, "ts hidden : %d" % d.count(old_ts)
d = d.replace(old_ts, new_ts, 1)

# 4) Ligne délai estimé dans le récapitulatif (après Livraison).
old_ship = """<div class="icod-summary-line"><span><?php esc_html_e( 'Livraison', 'infinitycod' ); ?></span><span data-summary-shipping>—</span></div>"""
new_ship = old_ship + """
								<div class="icod-summary-line icod-hidden" data-summary-estimate-row><span>⏱ <?php esc_html_e( 'Délai estimé', 'infinitycod' ); ?></span><span data-summary-estimate>—</span></div>"""
assert d.count(old_ship) == 1, "livraison : %d" % d.count(old_ship)
d = d.replace(old_ship, new_ship, 1)

# 5) Localize : poids produit, variante, libellés.
old_loc = "\t\t\t'currencyPosition' => Settings::get( 'currency_position', 'right' ),"
new_loc = (
    old_loc + "\n"
    "\t\t\t'productWeight' => " + BS + "InfinityCod" + BS + "Shipping" + BS + "RatesManager::order_weight( $product_id, 1 ),\n"
    "\t\t\t'abVariant'     => isset( $ab_variant ) ? $ab_variant : '',\n"
    "\t\t\t'showDays'      => (bool) Settings::get( 'show_delivery_days', 1 ),"
)
assert d.count(old_loc) == 1, "localize : %d" % d.count(old_loc)
d = d.replace(old_loc, new_loc, 1)

old_i18n = "\t\t\t\t'minOrder'       => __( 'Commande minimum : {min} pour cette wilaya.', 'infinitycod' ),"
new_i18n = (
    old_i18n + "\n"
    "\t\t\t\t'daysLine'       => __( 'Livraison estimée : {days}', 'infinitycod' ),\n"
    "\t\t\t\t'weightLine'     => __( 'Supplément poids', 'infinitycod' ),\n"
    "\t\t\t\t'weightIncluded' => __( 'inclus dans la livraison', 'infinitycod' ),"
)
assert d.count(old_i18n) == 1, "i18n : %d" % d.count(old_i18n)
d = d.replace(old_i18n, new_i18n, 1)

with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
print("OK form-manager : 5 patches appliqués")
