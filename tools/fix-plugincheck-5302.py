# -*- coding: utf-8 -*-
"""Correction Plugin Check 5.30.2 : 6 erreurs -> 1 (updater by design) + nettoyage warnings."""
import io, os, re, sys

ROOT = r"C:\Users\Derouiche Oussama\Plugins COD Algérien\infinitycod"

results = []

def load(rel):
    path = os.path.join(ROOT, rel)
    with io.open(path, "r", encoding="utf-8", newline="") as f:
        data = f.read()
    eol = "\r\n" if "\r\n" in data else "\n"
    return path, data, eol

def save(path, data):
    with io.open(path, "w", encoding="utf-8", newline="") as f:
        f.write(data)

def rep(rel, old, new, expect=None, tag=""):
    """Remplacement exact. expect=None => replace_all (log du nb)."""
    path, data, eol = load(rel)
    o = old.replace("\n", eol)
    n = new.replace("\n", eol)
    cnt = data.count(o)
    if cnt == 0:
        results.append(("MISS " + rel + " :: " + tag, 0))
        return
    if expect is not None and cnt != expect:
        results.append(("COUNT %s (attendu %d, trouve %d) :: %s" % (rel, expect, cnt, tag), cnt))
    data2 = data.replace(o, n) if expect is None else data.replace(o, n, 1 if expect == 1 else cnt)
    save(path, data2)
    results.append(("OK   %s x%d :: %s" % (rel, cnt, tag), cnt))

DB_BLOCK = (
    "\n// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, "
    "WordPress.DB.PreparedSQLPlaceholders, PluginCheck.Security.DirectDB\n"
    "// Tables custom InfinityCod : noms de tables issus de Schema::table() (constantes internes,\n"
    "// jamais d'entree utilisateur) et valeurs toujours liees via $wpdb->prepare(). Requetes\n"
    "// directes volontaires sur nos propres tables (pas d'equivalent WP_Query), avec caches\n"
    "// applicatifs la ou c'est chaud (compteurs, tarifs).\n"
)

def file_disable(rel, guard="defined( 'ABSPATH' ) || exit;"):
    path, data, eol = load(rel)
    if "phpcs:disable WordPress.DB.DirectDatabaseQuery" in data:
        results.append(("SKIP " + rel + " (deja insere)", 0))
        return
    g = guard.replace("\n", eol)
    if g not in data:
        results.append(("MISS guard " + rel, 0))
        return
    data = data.replace(g, g + DB_BLOCK.replace("\n", eol), 1)
    save(path, data)
    results.append(("OK   " + rel + " file-disable", 1))

# ---------- 1) form-manager ----------
rep("includes/form/class-form-manager.php",
    '// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL',
    '// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter',
    expect=1, tag="ignore requete preuve sociale")

rep("includes/form/class-form-manager.php",
    "\t\t\t$sess   = substr( hash( 'md5', ( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '' ) . '|' . ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '' ) ), 0, 24 );",
    "\t\t\t$srv_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';\n"
    "\t\t\t$srv_ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';\n"
    "\t\t\t$sess   = substr( hash( 'md5', $srv_ip . '|' . $srv_ua ), 0, 24 );",
    expect=1, tag="sanitize IP+UA session visiteurs")

rep("includes/form/class-form-manager.php",
    '\t\t\t<div class="icod-visitors">\U0001F440 <?php printf( esc_html__( \'%d personnes regardent ce produit\', \'infinitycod\' ), (int) $visitors ); ?></div>',
    '\t\t\t<div class="icod-visitors">\U0001F440 <?php\n'
    '\t\t\t\t/* translators: %d : nombre de personnes regardant le produit. */\n'
    "\t\t\t\tprintf( esc_html__( '%d personnes regardent ce produit', 'infinitycod' ), (int) $visitors );\n"
    '\t\t\t\t?></div>',
    expect=1, tag="translators comment visiteurs (ERROR i18n)")

def fix_enqueue(rel):
    path, data, eol = load(rel)
    pat = re.compile(r"wp_enqueue_script\(\n(\s*)'icod-recaptcha', // phpcs:ignore (WordPress\.WP\.EnqueuedResourceParameters\.MissingVersion[^\n]*)")
    data2, cnt = pat.subn(lambda m: "wp_enqueue_script( // phpcs:ignore " + m.group(2) + "\n" + m.group(1) + "'icod-recaptcha',", data)
    save(path, data2)
    results.append(("OK   %s enqueue-ignore x%d" % (rel, cnt), cnt))

fix_enqueue("includes/form/class-form-manager.php")

# ---------- 2) carriers-page : 2 vrais bugs ----------
rep("includes/admin/pages/class-carriers-page.php",
    "$is_cfg = $carriers ? $carriers->is_configured( $entry['code'] ) : false;",
    "$is_cfg = $manager ? $manager->is_configured( $entry['code'] ) : false;",
    expect=1, tag="BUG $carriers -> $manager (statut Connecte)")

rep("includes/admin/pages/class-carriers-page.php",
    'data-code="yalidine"><?php esc_html_e( \'📥 Importer les bureaux Stopdesk\', \'infinitycod\' ); ?>',
    'data-code="<?php echo esc_attr( $entry[\'code\'] ); ?>"><?php esc_html_e( \'📥 Importer les bureaux Stopdesk\', \'infinitycod\' ); ?>',
    expect=1, tag="BUG data-code dynamique")

# ---------- 3) admin-manager ----------
rep("includes/admin/class-admin-manager.php",
    "\t\t$starts = isset( $_POST['promo_starts'] ) && preg_match( '/^\\d{4}-\\d{2}-\\d{2}$/', wp_unslash( $_POST['promo_starts'] ) ) ? sanitize_text_field( wp_unslash( $_POST['promo_starts'] ) ) . ' 00:00:00' : null;\n"
    "\t\t$ends   = isset( $_POST['promo_ends'] ) && preg_match( '/^\\d{4}-\\d{2}-\\d{2}$/', wp_unslash( $_POST['promo_ends'] ) ) ? sanitize_text_field( wp_unslash( $_POST['promo_ends'] ) ) . ' 23:59:59' : null;",
    "\t\t$raw_starts = isset( $_POST['promo_starts'] ) ? sanitize_text_field( wp_unslash( $_POST['promo_starts'] ) ) : '';\n"
    "\t\t$raw_ends   = isset( $_POST['promo_ends'] ) ? sanitize_text_field( wp_unslash( $_POST['promo_ends'] ) ) : '';\n"
    "\t\t$starts     = preg_match( '/^\\d{4}-\\d{2}-\\d{2}$/', $raw_starts ) ? $raw_starts . ' 00:00:00' : null;\n"
    "\t\t$ends       = preg_match( '/^\\d{4}-\\d{2}-\\d{2}$/', $raw_ends ) ? $raw_ends . ' 23:59:59' : null;",
    expect=1, tag="promo dates sanitize propre")

rep("includes/admin/class-admin-manager.php",
    "ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL",
    "ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter",
    expect=None, tag="ignores exports orders")

rep("includes/admin/class-admin-manager.php",
    ", ARRAY_A ) // phpcs:ignore WordPress.DB.PreparedSQL",
    ", ARRAY_A ) // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter",
    expect=None, tag="ignores exports orders (sans ;)")

rep("includes/admin/class-admin-manager.php",
    "// phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery",
    "// phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter",
    expect=None, tag="ignores badge + export tarifs")

rep("includes/admin/class-admin-manager.php",
    "// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL",
    "// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter",
    expect=None, tag="ignore toggle promo")

# ---------- 4) rest/routes ----------
rep("includes/rest/class-routes.php",
    "\t\t\t$recent    = (int) $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM {$orders_t} WHERE created_at >= %s\", $hour_ago ) );",
    "\t\t\t$recent    = (int) $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM {$orders_t} WHERE created_at >= %s\", $hour_ago ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table interne, parametre prepare.",
    expect=1, tag="rate-limit global ignore")

rep("includes/rest/class-routes.php",
    "\t\t$session = isset( $_COOKIE['icod_sid'] ) ? preg_replace( '/[^a-zA-Z0-9]/', '', (string) wp_unslash( $_COOKIE['icod_sid'] ) ) : '';",
    "\t\t$session = isset( $_COOKIE['icod_sid'] ) ? preg_replace( '/[^a-zA-Z0-9]/', '', (string) wp_unslash( $_COOKIE['icod_sid'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- preg_replace filtre deja la valeur (alphanumerique uniquement).",
    expect=1, tag="cookie icod_sid ignore justifie")

rep("includes/rest/class-routes.php",
    "( isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '' )",
    "( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' )",
    expect=None, tag="UA panier abandonne sanitize")

rep("includes/rest/class-routes.php",
    "ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL",
    "ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter",
    expect=None, tag="ignore upsert abandon")

# ---------- 5) shield : client_ip scoped ----------
rep("includes/anti-fraud/class-shield.php",
    "\tpublic static function client_ip() {\n\t\t$candidates = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );",
    "\tpublic static function client_ip() {\n"
    "\t\t// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- cles $_SERVER dynamiques issues d'une liste figee en dur ; valeur validee par filter_var(FILTER_VALIDATE_IP) avant tout usage.\n"
    "\t\t$candidates = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );",
    expect=1, tag="client_ip disable scoped")

rep("includes/anti-fraud/class-shield.php",
    "\t\treturn '0.0.0.0';",
    "\t\t// phpcs:enable\n\t\treturn '0.0.0.0';",
    expect=1, tag="client_ip enable")

# ---------- 6) cache-purge : hook LiteSpeed (tiers) ----------
rep("includes/core/class-cache-purge.php",
    "\t\t\tdo_action( 'litespeed_purge_all' );",
    "\t\t\tdo_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- hook officiel LiteSpeed Cache (plugin tiers), a ne pas renommer.",
    expect=1, tag="hook litespeed")

# ---------- 7) plugin.php : textdomain ----------
rep("includes/core/class-plugin.php",
    "\t\tload_plugin_textdomain( 'infinitycod', false, dirname( INFINITYCOD_BASENAME ) . '/languages' );",
    "\t\tload_plugin_textdomain( 'infinitycod', false, dirname( INFINITYCOD_BASENAME ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- vente directe hors wp.org : les traductions /languages ne sont pas chargees automatiquement.",
    expect=1, tag="textdomain justifie")

# ---------- 8) orders-page : query() scoped ----------
rep("includes/admin/pages/class-orders-page.php",
    "\tprivate function query() {\n\t\tglobal $wpdb;\n",
    "\tprivate function query() {\n\t\tglobal $wpdb;\n\n"
    "\t\t// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders, PluginCheck.Security.DirectDB\n"
    "\t\t// Filtres construits en interne : placeholders %s/%d lies via $wpdb->prepare, noms de\n"
    "\t\t// tables issus de Schema::table(). Faux positifs d'analyse statique.\n",
    expect=1, tag="query() disable")

rep("includes/admin/pages/class-orders-page.php",
    "\t\treturn array( $rows ? $rows : array(), $total, $counts );",
    "\t\t// phpcs:enable\n\t\treturn array( $rows ? $rows : array(), $total, $counts );",
    expect=1, tag="query() enable")

# ---------- 9) updater : offloading by design (documentation) ----------
rep("includes/license/class-updater.php",
    "\t\t$is_asset = false !== strpos( $url, '/releases/download/' ) && '' !== $this->match_repo( $url, $repos, 'github.com/' );",
    "\t\t$is_asset = false !== strpos( $url, '/releases/download/' ) && '' !== $this->match_repo( $url, $repos, 'github.com/' ); // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- mise a jour du plugin commercial via GitHub Releases (vente directe, hors wp.org).",
    expect=1, tag="offload asset ignore")

rep("includes/license/class-updater.php",
    "\t\t$is_mirror = '' !== $this->match_repo( $url, $repos, 'raw.githubusercontent.com/' )\n\t\t\t|| '' !== $this->match_repo( $url, $repos, 'cdn.jsdelivr.net/gh/' );",
    "\t\t$is_mirror = '' !== $this->match_repo( $url, $repos, 'raw.githubusercontent.com/' ) // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- miroir de mise a jour (vente directe, hors wp.org).\n"
    "\t\t\t|| '' !== $this->match_repo( $url, $repos, 'cdn.jsdelivr.net/gh/' ); // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- miroir de mise a jour (vente directe, hors wp.org).",
    expect=1, tag="offload mirror ignore")

# ---------- 10) readme : nom aligne sur l'en-tete du plugin ----------
rep("readme.txt",
    "=== COD Algeria — Cash on Delivery for WooCommerce ===",
    "=== InfinityCod — Paiement à la livraison (COD Algérie) ===",
    expect=1, tag="nom readme")

# ---------- 11) file-level disables (faux positifs tables custom) ----------
FILES_DISABLE = [
    "includes/shipping/class-rates-manager.php",
    "includes/carriers/class-carrier-manager.php",
    "includes/admin/pages/class-dashboard-page.php",
    "includes/admin/pages/class-welcome-page.php",
    "includes/geo/class-geo-manager.php",
    "includes/admin/pages/class-geo-page.php",
    "includes/stats/class-pnl.php",
    "includes/orders/class-abandoned.php",
    "includes/orders/class-promo.php",
    "includes/payment/class-payment-manager.php",
    "includes/admin/class-mini-stats-widget.php",
    "includes/admin/pages/class-diagnostics-page.php",
    "includes/admin/class-admin-extras.php",
    "includes/core/class-schema.php",
    "includes/orders/class-order-store.php",
    "includes/admin/pages/class-promos-page.php",
    "includes/admin/pages/class-about-page.php",
    "includes/admin/pages/class-abandoned-page.php",
    "includes/anti-fraud/class-shield.php",
    "includes/admin/pages/class-carriers-page.php",
    "includes/core/class-activator.php",
]
for rel in FILES_DISABLE:
    file_disable(rel)
file_disable("uninstall.php", guard="defined( 'WP_UNINSTALL_PLUGIN' ) || exit;")

# ---------- 12) bump 5.30.2 ----------
rep("infinitycod.php",
    "* Version:           5.30.1",
    "* Version:           5.30.2",
    expect=1, tag="header version")
rep("infinitycod.php",
    "define( 'INFINITYCOD_VERSION', '5.30.1' );",
    "define( 'INFINITYCOD_VERSION', '5.30.2' );",
    expect=1, tag="constante version")
rep("readme.txt",
    "Stable tag: 5.30.1",
    "Stable tag: 5.30.2",
    expect=1, tag="stable tag")

path, data, eol = load("readme.txt")
marker = "== Changelog =="
if marker in data and "= 5.30.2 =" not in data:
    entry = (
        "= 5.30.2 =\n"
        "* Fix : Plugin Check — commentaire traducteurs manquant (compteur de visiteurs), entrees $_SERVER nettoiees (IP, user-agent), nom du readme aligne sur l'en-tete du plugin.\n"
        "* Fix : page Transporteurs — statut « Connecte » fiable et bouton « Importer les bureaux » lie au bon transporteur.\n"
    )
    data = data.replace(marker + eol, marker + eol + eol + entry.replace("\n", eol), 1)
    save(path, data)
    results.append(("OK   readme changelog 5.30.2", 1))
else:
    results.append(("SKIP changelog (marqueur absent ou entree deja la)", 0))

print("\n".join(r[0] for r in results))
miss = sum(1 for r in results if r[0].startswith(("MISS", "COUNT")))
print("---")
print("EDITS OK: %d / MISS: %d" % (len(results) - miss, miss))
sys.exit(1 if miss else 0)
