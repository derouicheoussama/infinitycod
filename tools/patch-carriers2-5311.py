# -*- coding: utf-8 -*-
"""Patch 5.31.1 fin : head transporteurs avec réseau + toggle sans marge inline."""
import io

ROOT = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod"
BS = chr(92)
p = ROOT + BS + r"includes\admin\pages\class-carriers-page.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()

T4 = "\t" * 4
T5 = "\t" * 5
T6 = "\t" * 6

old = (
    T4 + '<div class="icod-carrier-head">\n'
    + T5 + "<?php echo $_logo; // phpcs:ignore WordPress.Security.EscapeOutput ?>\n"
    + T5 + '<div class="icod-carrier-info">\n'
    + T6 + "<h2 class=\"icod-carrier-name\"><?php echo esc_html( $entry['name'] ); ?></h2>\n"
    + T6 + "<span class=\"icod-carrier-status <?php echo $is_cfg ? 'on' : 'off'; ?>\"><?php echo $is_cfg ? '✓ Connecté' : '○ Non configuré'; ?></span>\n"
    + T5 + "</div>\n"
    + T5 + '<label class="icod-toggle" style="margin-left:auto">\n'
    + T6 + "<input type=\"checkbox\" name=\"icod_carrier[<?php echo esc_attr( $entry['code'] ); ?>][enabled]\" value=\"1\" <?php checked( $enabled ); ?> />\n"
    + T6 + "<span><?php esc_html_e( 'Activé', 'infinitycod' ); ?></span>\n"
    + T5 + "</label>\n"
    + T4 + "</div>"
)
new = (
    T4 + '<div class="icod-carrier-head">\n'
    + T5 + "<?php echo $_logo; // phpcs:ignore WordPress.Security.EscapeOutput ?>\n"
    + T5 + '<div class="icod-carrier-info">\n'
    + T6 + "<h2 class=\"icod-carrier-name\"><?php echo esc_html( $entry['name'] ); ?></h2>\n"
    + T6 + "<span class=\"icod-carrier-status <?php echo $is_cfg ? 'on' : 'off'; ?>\"><?php echo $is_cfg ? '✓ Connecté' : '○ Non configuré'; ?></span>\n"
    + T5 + "</div>\n"
    + T5 + "<span class=\"icod-carrier-net\"><?php printf( esc_html__( 'Réseau : %s', 'infinitycod' ), esc_html( 'Ecotrack' === $entry['adapter'] ? 'Ecotrack' : 'API directe' ) ); ?></span>\n"
    + T5 + "<label class=\"icod-toggle\">\n"
    + T6 + "<input type=\"checkbox\" name=\"icod_carrier[<?php echo esc_attr( $entry['code'] ); ?>][enabled]\" value=\"1\" <?php checked( $enabled ); ?> />\n"
    + T6 + "<span><?php esc_html_e( 'Activé', 'infinitycod' ); ?></span>\n"
    + T5 + "</label>\n"
    + T4 + "</div>"
)
assert d.count(old) == 1, "head : %d" % d.count(old)
d = d.replace(old, new, 1)

with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
print("OK head transporteurs (net + toggle)")
