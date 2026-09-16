# -*- coding: utf-8 -*-
"""Patch 5.33.0 fin : avis admin intégrité + harnais anti-leak."""
import io

ROOT = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod"
BS = chr(92)
ok = []

# ---------- 1) AVIS ADMIN si fichier modifié ----------
p = ROOT + BS + r"includes\core\class-antileak.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
old = "\t\tadd_action( 'admin_init', array( $this, 'maybe_integrity_check' ) );"
new = (
    "\t\tadd_action( 'admin_init', array( $this, 'maybe_integrity_check' ) );\n"
    "\t\tadd_action( 'admin_notices', array( $this, 'integrity_notice' ) );"
)
assert d.count(old) == 1
d = d.replace(old, new, 1)

anchor = "\t/**\n\t * Calcule la base d'intégrité des fichiers cœur (à chaque nouvelle version)."
add = (
    "\t/**\n"
    "\t * Avis admin : fichiers du plugin modifiés (copie piratée / altérée).\n"
    "\t *\n"
    "\t * @return void\n"
    "\t */\n"
    "\tpublic function integrity_notice() {\n"
    "\t\t$alert = get_option( 'icod_integrity_alert' );\n"
    "\t\tif ( ! is_array( $alert ) || empty( $alert['file'] ) ) {\n"
    "\t\t\treturn;\n"
    "\t\t}\n"
    "\t\techo '<div class=\"notice notice-error\"><p><strong>⚠️ InfinityCod — ' . esc_html__( 'fichier modifié détecté :', 'infinitycod' ) . '</strong> <code>' . esc_html( (string) $alert['file'] ) . '</code>. '\n"
    "\t\t\t. esc_html__( 'Cette copie du plugin est potentiellement piratée ou altérée : réinstallez le zip officiel et contactez le support.', 'infinitycod' ) . '</p></div>';\n"
    "\t}\n\n"
    "\t"
)
assert d.count(anchor) == 1
d = d.replace(anchor, add + anchor, 1)
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
ok.append("avis intégrité")

# ---------- 2) HARNais : section 52 anti-leak ----------
p = ROOT + BS + r"..\tools\test-form-engine.php"
p = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\tools\test-form-engine.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
anchor = 'echo "\\n=== BILAN : {$pass} OK, {$fail} échec(s) ===\\n";'
assert d.count(anchor) == 1
section = r'''
/* ---------- 5.33.0 : Anti-leak (pixels, données, piratage) ---------- */

echo "\n52) 5.33.0 — Anti-leak : garde pixel, journal exports, intégrité\n";
check( 'garde pixel : achat navigateur coupé si antileak', false !== strpos( file_get_contents( $plugin_dir . 'assets/front/js/pixels.js' ), 'if (CFG.antileak) { return; }' ) );
check( 'garde pixel : flag transmis au navigateur', false !== strpos( file_get_contents( $plugin_dir . 'includes/tracking/class-pixel-manager.php' ), "'antileak'  => (bool) Settings::get( 'antileak_pixels' )" ) );
check( 'anti-leak : classe + journal + trace', file_exists( $plugin_dir . 'includes/core/class-antileak.php' ) && false !== strpos( file_get_contents( $plugin_dir . 'includes/core/class-antileak.php' ), 'log_export' ) && false !== strpos( file_get_contents( $plugin_dir . 'includes/core/class-antileak.php' ), 'trace_code' ) );
check( 'anti-leak : alerte e-mail export massif', false !== strpos( file_get_contents( $plugin_dir . 'includes/core/class-antileak.php' ), 'export_alert_min' ) );
check( 'anti-leak : masquage téléphone dans la liste', false !== strpos( file_get_contents( $plugin_dir . 'includes/admin/pages/class-orders-page.php' ), 'AntiLeak::mask_phone' ) );
check( 'anti-leak : filigrane Trace dans les exports', false !== strpos( $am_src, "log_export( 'orders_csv'" ) && false !== strpos( $am_src, '$trace,' ) );
check( 'anti-leak : journal sur paniers + bordereaux', false !== strpos( file_get_contents( $plugin_dir . 'includes/admin/class-admin-extras.php' ), "log_export( 'abandoned_xls'" ) && false !== strpos( $am_src, "log_export( 'bordereaux'" ) );
check( 'anti-leak : intégrité fichiers + avis admin', false !== strpos( file_get_contents( $plugin_dir . 'includes/core/class-antileak.php' ), 'maybe_integrity_check' ) && false !== strpos( file_get_contents( $plugin_dir . 'includes/core/class-antileak.php' ), 'integrity_notice' ) );
$set_page = file_get_contents( $plugin_dir . 'includes/admin/pages/class-settings-page.php' );
check( 'réglages : carte anti-leak complète (pixels/masque/alerte/intégrité)', false !== strpos( $set_page, "'antileak_pixels'" ) && false !== strpos( $set_page, "'mask_phones'" ) && false !== strpos( $set_page, "'export_alert_min'" ) && false !== strpos( $set_page, "'integrity_check'" ) );
check( 'réglages : journal des exports affiché', false !== strpos( $set_page, 'AntiLeak::get_log' ) );
'''
d = d.replace(anchor, section + "\n" + anchor, 1)
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
ok.append("harnais section 52")

print("\n".join("OK  " + o for o in ok))
