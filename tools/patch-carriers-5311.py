# -*- coding: utf-8 -*-
"""Patch 5.31.1 : logos DHD/E-COM réels + interface transporteurs (boîte logo, statut pill)."""
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
            continue  # déjà appliqué (idempotent)
        assert n == 1, rel + " :: " + old[:70].replace("\n", "⏎") + " -> " + str(n)
        d = d.replace(old, new, 1)
    with io.open(p, "w", encoding="utf-8", newline="") as f:
        f.write(d)
    ok.append(rel + " (" + str(len(pairs)) + ")")


# ---------- 1) carriers-page : boîte logo + info colonne + statut pill ----------
patch("includes/admin/pages/class-carriers-page.php", [
    (
        "\t\t\t\t\tif ( file_exists( $_f ) ) {\n"
        "\t\t\t\t\t\t$_logo = '<img class=\"icod-carrier-logo\" src=\"' . esc_url( INFINITYCOD_URL . 'assets/front/img/carriers/' . $entry['code'] . '.' . $_ext ) . '\" alt=\"\" />';\n"
        "\t\t\t\t\t\tbreak;\n"
        "\t\t\t\t\t}",
        "\t\t\t\t\tif ( file_exists( $_f ) ) {\n"
        "\t\t\t\t\t\t$_logo = '<span class=\"icod-carrier-logo-box\"><img class=\"icod-carrier-logo\" src=\"' . esc_url( INFINITYCOD_URL . 'assets/front/img/carriers/' . $entry['code'] . '.' . $_ext ) . '\" alt=\"' . esc_attr( $entry['name'] ) . '\" /></span>';\n"
        "\t\t\t\t\t\tbreak;\n"
        "\t\t\t\t\t}",
    ),
    (
        "\t\t\t\t\t$_logo = '<span class=\"icod-carrier-badge\" style=\"background:linear-gradient(135deg,#1877c2,#0e7a4f)\">' . esc_html( $_initials ) . '</span>';",
        "\t\t\t\t\t$_logo = '<span class=\"icod-carrier-logo-box\"><span class=\"icod-carrier-badge\" style=\"background:linear-gradient(135deg,#1877c2,#0e7a4f)\">' . esc_html( $_initials ) . '</span></span>';",
    ),
])
ok.append("carriers-page markup")

# ---------- 2) ADMIN.CSS : boîte logo, pill statut, réseaux ----------
CSS_ADD = (
    "\n/* ============================================================\n"
    "   Transporteurs : boîte logo normalisée + pastille de statut\n"
    "   ============================================================ */\n"
    ".icod-carrier-logo-box{display:inline-flex;align-items:center;justify-content:center;width:64px;height:44px;"
    "background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:5px;flex:none;overflow:hidden}\n"
    ".icod-carrier-logo{height:100%;width:auto;max-width:100%;object-fit:contain;border-radius:0;"
    "box-shadow:none;background:transparent}\n"
    ".icod-carrier-info{display:flex;flex-direction:column;gap:3px;min-width:0}\n"
    ".icod-carrier-status{display:inline-flex;align-items:center;gap:4px;width:max-content;"
    "font-size:11px;font-weight:700;letter-spacing:.02em;padding:2px 9px;border-radius:999px}\n"
    ".icod-carrier-status.on{color:#0b6e43;background:#e2f5ea;border:1px solid #bfe6d0}\n"
    ".icod-carrier-status.off{color:#5b6b7b;background:#eef1f5;border:1px solid #dbe3ea}\n"
    ".icod-carrier-net{margin-left:auto;font-size:11px;color:#8296a8;white-space:nowrap}\n"
    "@media (max-width:782px){.icod-carrier-net{display:none}}\n"
)
append("assets/admin/css/admin.css", CSS_ADD)

print("\n".join("OK  " + o for o in ok))
