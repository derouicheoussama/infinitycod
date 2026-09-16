# -*- coding: utf-8 -*-
"""Patch 5.31.0 ter : bordereaux, stats carte retours, module webhooks."""
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


# ---------- 1) MODULE WEBHOOKS ----------
patch("includes/core/class-plugin.php", [
    (
        "\t\t'abtest'    => '\\\\InfinityCod\\\\Orders\\\\AbTest',",
        "\t\t'abtest'    => '\\\\InfinityCod\\\\Orders\\\\AbTest',\n\t\t'webhooks'  => '\\\\InfinityCod\\\\Core\\\\Webhooks',",
    ),
])

# ---------- 2) STATS : carte provision retours ----------
patch("includes/admin/pages/class-stats-page.php", [
    (
        "\t\t\t\t<div class=\"icod-card icod-kpi-card\">\n"
        "\t\t\t\t\t<h2><?php esc_html_e( 'CA confirmé', 'infinitycod' ); ?></h2>",
        "\t\t\t\t<div class=\"icod-card icod-kpi-card\">\n"
        "\t\t\t\t\t<h2><?php esc_html_e( 'Provision retours', 'infinitycod' ); ?></h2>\n"
        "\t\t\t\t\t<p class=\"icod-kpi-value\"><?php echo esc_html( number_format_i18n( $kpis['return_fees'] ?? 0, 0 ) ); ?></p>\n"
        "\t\t\t\t\t<p class=\"icod-kpi-label\">DA</p>\n"
        "\t\t\t\t</div>\n"
        "\t\t\t\t<div class=\"icod-card icod-kpi-card\">\n"
        "\t\t\t\t\t<h2><?php esc_html_e( 'CA confirmé', 'infinitycod' ); ?></h2>",
    ),
])

# ---------- 3) ORDERS : bouton bordereaux (URL signée via data-url) ----------
patch("includes/admin/pages/class-orders-page.php", [
    (
        "\t\t\t\t\t\t<option value=\"delete\"><?php esc_html_e( '→ Supprimer (corbeille WC)', 'infinitycod' ); ?></option>\n"
        "\t\t\t\t\t</select>",
        "\t\t\t\t\t\t<option value=\"delete\"><?php esc_html_e( '→ Supprimer (corbeille WC)', 'infinitycod' ); ?></option>\n"
        "\t\t\t\t\t</select>\n"
        "\t\t\t\t\t<a href=\"#\" id=\"icod-bordereaux\" class=\"button\" data-url=\"<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=icod_bordereaux' ), 'icod_bordereaux' ) ); ?>\">🖨️ <?php esc_html_e( 'Bordereaux (sélection)', 'infinitycod' ); ?></a>",
    ),
])

# JS du bouton (admin.js, chargé sur les écrans du plugin).
append("assets/admin/js/admin.js", """

/* Bordereaux en lot : ouvre la vue imprimable des commandes cochées. */
(function () {
	var btn = document.getElementById('icod-bordereaux');
	if (!btn) { return; }
	btn.addEventListener('click', function (e) {
		e.preventDefault();
		var ids = [];
		document.querySelectorAll('#icod-orders-form input[name="ids[]"]:checked').forEach(function (cb) {
			ids.push(cb.value);
		});
		if (!ids.length) { window.alert(icodAdmin && icodAdmin.bordereauxEmpty ? icodAdmin.bordereauxEmpty : 'Cochez d\u2019abord des commandes.'); return; }
		window.open(btn.getAttribute('data-url') + '&ids=' + ids.join(','), '_blank', 'width=840,height=980');
	});
})();
""")

print("\n".join("OK  " + o for o in ok))
