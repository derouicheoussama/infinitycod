# -*- coding: utf-8 -*-
"""Patch 5.40.0 : réglage notification e-mail nouvelle version + carte Mises à jour + harnais."""
import io

ROOT = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod"
ok = []


def patch(rel, pairs):
    p = ROOT + "\\" + rel.replace("/", "\\")
    with io.open(p, "r", encoding="utf-8", newline="") as f:
        d = f.read()
    for old, new in pairs:
        n = d.count(old)
        assert n == 1, rel + " :: " + old[:70].replace("\n", "⏎") + " -> " + str(n)
        d = d.replace(old, new, 1)
    with io.open(p, "w", encoding="utf-8", newline="") as f:
        f.write(d)
    ok.append(rel + " (" + str(len(pairs)) + ")")


# ---------- 1) DÉFAUT ----------
patch("includes/core/class-settings.php", [
    (
        "\t\t\t// Passerelle de paiement en ligne.",
        "\t\t\t// Notification e-mail au marchand à chaque nouvelle version.\n"
        "\t\t\t'update_email_notify'  => 1,\n\n"
        "\t\t\t// Passerelle de paiement en ligne.",
    ),
])

# ---------- 2) SCHÉMA ----------
patch("includes/admin/pages/class-settings-page.php", [
    (
        "\t\t\t'loyalty_min_points'   => array( 'tab' => 'advanced', 'type' => 'int', 'min' => 0, 'max' => 10000 ),",
        "\t\t\t'loyalty_min_points'   => array( 'tab' => 'advanced', 'type' => 'int', 'min' => 0, 'max' => 10000 ),\n"
        "\t\t\t'update_email_notify'  => array( 'tab' => 'advanced', 'type' => 'toggle' ),",
    ),
])

# ---------- 3) UI PAGE MISES À JOUR : toggle dans la carte Paramètres ----------
patch("includes/admin/pages/class-updates-page.php", [
    (
        "\t\t\t\t<label class=\"icod-toggle\">\n"
        "\t\t\t\t\t<input type=\"checkbox\" name=\"icod[auto_update]\" value=\"1\" <?php checked( (int) Settings::get( 'auto_update' ), 1 ); ?> />\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'Installation automatique des nouvelles versions', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t</label>\n"
        "\t\t\t</div>",
        "\t\t\t\t<label class=\"icod-toggle\">\n"
        "\t\t\t\t\t<input type=\"checkbox\" name=\"icod[auto_update]\" value=\"1\" <?php checked( (int) Settings::get( 'auto_update' ), 1 ); ?> />\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'Installation automatique des nouvelles versions', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t</label>\n"
        "\t\t\t\t<label class=\"icod-toggle\">\n"
        "\t\t\t\t\t<input type=\"checkbox\" name=\"icod[update_email_notify]\" value=\"1\" <?php checked( (int) Settings::get( 'update_email_notify', 1 ), 1 ); ?> />\n"
        "\t\t\t\t\t<span><?php esc_html_e( 'M’avertir par e-mail (adresse admin du site) dès qu’une nouvelle version sort', 'infinitycod' ); ?></span>\n"
        "\t\t\t\t</label>\n"
        "\t\t\t</div>",
    ),
])

print("\n".join("OK  " + o for o in ok))
