# -*- coding: utf-8 -*-
# Passe tolerant : applique chaque correctif restant, signale ceux deja appliques.
import io
import os

BS = chr(92)
ROOT = 'infinitycod'


def read(path):
    return io.open(os.path.join(ROOT, path), encoding='utf-8').read()


def write(path, src):
    io.open(os.path.join(ROOT, path), 'w', encoding='utf-8', newline=chr(10)).write(src)


def swap(path, old, new):
    src = read(path)
    n = src.count(old)
    if n == 0:
        print('  SKIP : ' + old.strip()[:60])
        return
    src = src.replace(old, new, 1)
    write(path, src)
    print('  OK x' + str(n) + ' : ' + old.strip()[:60])


def insert_before_line(path, marker, new_line):
    lines = read(path).split(chr(10))
    hits = 0
    out = []
    for l in lines:
        if marker in l and 'translators' not in l and 'phpcs:ignore' not in l:
            indent = l[:len(l) - len(l.lstrip())]
            out.append(indent + new_line)
            hits += 1
        out.append(l)
    if hits == 0:
        print('  SKIP (deja applique) : ' + marker[:50])
        return
    write(path, chr(10).join(out))
    print('  insere x' + str(hits))


# ---------- updates-page ----------
swap('includes/admin/pages/class-updates-page.php',
     "sanitize_text_field( rawurldecode( wp_unslash( $_GET['icod_msg'] ) ) )",
     "rawurldecode( sanitize_text_field( wp_unslash( $_GET['icod_msg'] ) ) )")
insert_before_line('includes/admin/pages/class-updates-page.php',
                   'La version %s est disponible',
                   '<?php // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- un seul placeholder. ?>')
insert_before_line('includes/admin/pages/class-updates-page.php',
                   'InfinityCod %s disponible',
                   '<?php // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- un seul placeholder. ?>')
swap('includes/admin/pages/class-updates-page.php',
     "'mirror-raw'      => __( '· via miroir raw.githubusercontent.com', 'infinitycod' ),",
     "'mirror-raw'      => __( '· via le miroir GitHub brut', 'infinitycod' ),")
swap('includes/admin/pages/class-updates-page.php',
     "'mirror-jsdelivr' => __( '· via miroir jsDelivr (CDN)', 'infinitycod' ),",
     "'mirror-jsdelivr' => __( '· via le miroir CDN', 'infinitycod' ),")

# ---------- geo-page ----------
insert_before_line('includes/admin/pages/class-geo-page.php',
                   '%d communes',
                   '/* translators: %d : nombre de communes. */')

# ---------- promos-page ----------
insert_before_line('includes/admin/pages/class-promos-page.php',
                   'Historique',
                   '/* translators: %s : code promo. */')
swap('includes/admin/pages/class-promos-page.php',
     "<?php echo $limit > 0 ? ' / ' . $limit : ''; ?>",
     "<?php echo $limit > 0 ? esc_html( ' / ' . (int) $limit ) : ''; ?>")

# ---------- seo-manager (4 x commentaires dans le tag php) ----------
swap('includes/seo/class-seo-manager.php',
     "<title><?php printf( esc_html__( 'Livraison",
     "<title><?php /* translators: 1 : wilaya, 2 : nom du site. */ printf( esc_html__( 'Livraison")
swap('includes/seo/class-seo-manager.php',
     'content="<?php printf( esc_attr__( ' + chr(39) + 'Commandez en ligne',
     'content="<?php /* translators: 1 : wilaya, 2 : arabe, 3 : prix. */ printf( esc_attr__( ' + chr(39) + 'Commandez en ligne')
swap('includes/seo/class-seo-manager.php',
     "<h1><?php printf( esc_html__( 'Livraison",
     "<h1><?php /* translators: %s : wilaya. */ printf( esc_html__( 'Livraison")
swap('includes/seo/class-seo-manager.php',
     "<p><?php printf( esc_html__( 'Commandez en ligne et payez",
     "<p><?php /* translators: 1 : wilaya. */ printf( esc_html__( 'Commandez en ligne et payez")

# ---------- stats-page : devise en argument echappe + retire les refs cassees ----------
swap('includes/admin/pages/class-stats-page.php',
     "' . " + BS + "InfinityCod" + BS + "Core" + BS + "Settings::currency_label() . '</td></tr>',",
     "'</td></tr>',")
swap('includes/admin/pages/class-stats-page.php',
     "' . " + BS + "InfinityCod" + BS + "Core" + BS + "Settings::currency_label() . '</strong></li>',",
     "'</strong></li>',")
swap('includes/admin/pages/class-stats-page.php',
     BS + "Infinity" + BS + "Core" + BS + "Settings::currency_label()\n\t\t\t);",
     "esc_html( " + BS + "InfinityCod" + BS + "Core" + BS + "Settings::currency_label() )\n\t\t\t);")

# ---------- admin-manager ----------
swap('includes/admin/class-admin-manager.php',
     "preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['wilaya_code'] ) )",
     "preg_replace( '/[^0-9]/', '', sanitize_text_field( wp_unslash( $_POST['wilaya_code'] ) ) )")
swap('includes/admin/class-admin-manager.php',
     "preg_match( '/^" + BS + "d{4}-" + BS + "d{2}-" + BS + "d{2}$/', $_POST['promo_starts'] ) ? sanitize_text_field( $_POST['promo_starts'] )",
     "preg_match( '/^" + BS + "d{4}-" + BS + "d{2}-" + BS + "d{2}$/', wp_unslash( $_POST['promo_starts'] ) ) ? sanitize_text_field( wp_unslash( $_POST['promo_starts'] ) )")
swap('includes/admin/class-admin-manager.php',
     "preg_match( '/^" + BS + "d{4}-" + BS + "d{2}-" + BS + "d{2}$/', $_POST['promo_ends'] ) ? sanitize_text_field( $_POST['promo_ends'] )",
     "preg_match( '/^" + BS + "d{4}-" + BS + "d{2}-" + BS + "d{2}$/', wp_unslash( $_POST['promo_ends'] ) ) ? sanitize_text_field( wp_unslash( $_POST['promo_ends'] ) )")
swap('includes/admin/class-admin-manager.php',
     "\t\tfwrite( $out, \"" + BS + "xEF" + BS + "xBB" + BS + "xBF\" );\n\t\tfputcsv( $out, array( 'ID', 'WC #', 'Date', 'Nom', 'Telephone', 'Wilaya', 'Commune', 'Mode', 'Bureau', 'Produit', 'Qte', 'Sous-total', 'Remise', 'Livraison', 'Total', 'Statut', 'Transporteur', 'Suivi', 'Score risque', 'IP' ), ';' );",
     "\t\tfwrite( $out, \"" + BS + "xEF" + BS + "xBB" + BS + "xBF\" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- flux de telechargement CSV.\n\t\tfputcsv( $out, array( 'ID', 'WC #', 'Date', 'Nom', 'Telephone', 'Wilaya', 'Commune', 'Mode', 'Bureau', 'Produit', 'Qte', 'Sous-total', 'Remise', 'Livraison', 'Total', 'Statut', 'Transporteur', 'Suivi', 'Score risque', 'IP' ), ';' );")
swap('includes/admin/class-admin-manager.php',
     "\t\tfwrite( $out, \"" + BS + "xEF" + BS + "xBB" + BS + "xBF\" );\n\t\tfputcsv( $out, array( 'code', 'wilaya', 'domicile', 'stopdesk', 'active', 'gratuite' ), ';' );",
     "\t\tfwrite( $out, \"" + BS + "xEF" + BS + "xBB" + BS + "xBF\" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- flux de telechargement CSV.\n\t\tfputcsv( $out, array( 'code', 'wilaya', 'domicile', 'stopdesk', 'active', 'gratuite' ), ';' );")
swap('includes/admin/class-admin-manager.php',
     "$url = 'https://raw.githubusercontent.com/derouicheoussama/infinitycod-releases/main/latest/update.json';",
     "$url = 'https://raw.githubusercontent.com/derouicheoussama/infinitycod-releases/main/latest/update.json'; // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- verification du miroir de secours.")

# ---------- diagnostics ----------
swap('includes/admin/pages/class-diagnostics-page.php',
     "$writable = is_writable( dirname( $upload['basedir'] ) );",
     "$writable = wp_is_writable( dirname( $upload['basedir'] ) );")
swap('includes/admin/pages/class-diagnostics-page.php',
     "'mirror-raw'      => __( 'Miroir raw.githubusercontent.com', 'infinitycod' ),",
     "'mirror-raw'      => __( 'Miroir GitHub brut', 'infinitycod' ),")
swap('includes/admin/pages/class-diagnostics-page.php',
     "'mirror-jsdelivr' => __( 'Miroir jsDelivr (CDN)', 'infinitycod' ),",
     "'mirror-jsdelivr' => __( 'Miroir CDN', 'infinitycod' ),")

# ---------- logger ----------
swap('includes/logging/class-logger.php',
     "@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors",
     "wp_delete_file( $file );")

# ---------- updater : wp_parse_url + ignores offloading ----------
swap('includes/license/class-updater.php',
     "\t$host = parse_url( $url, PHP_URL_HOST );",
     "\t$host = wp_parse_url( $url, PHP_URL_HOST );")
swap('includes/license/class-updater.php',
     "\t\t\t$custom_host = parse_url( $custom, PHP_URL_HOST );",
     "\t\t\t$custom_host = wp_parse_url( $custom, PHP_URL_HOST );")
swap('includes/license/class-updater.php',
     "\t\tprivate static $allowed_hosts = array( 'raw.githubusercontent.com', 'cdn.jsdelivr.net', 'objects.githubusercontent.com' );",
     "\t\tprivate static $allowed_hosts = array( 'raw.githubusercontent.com', 'cdn.jsdelivr.net', 'objects.githubusercontent.com' ); // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- hotes de mise a jour.")
swap('includes/license/class-updater.php',
     "\t\t$mirrors['raw']      = 'https://raw.githubusercontent.com/' . $repo . '/main/latest/';\n\t\t$mirrors['jsdelivr'] = 'https://cdn.jsdelivr.net/gh/' . $repo . '@main/latest/';",
     "\t\t$mirrors['raw']      = 'https://raw.githubusercontent.com/' . $repo . '/main/latest/'; // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent\n\t\t$mirrors['jsdelivr'] = 'https://cdn.jsdelivr.net/gh/' . $repo . '@main/latest/'; // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent")
swap('includes/license/class-updater.php',
     "\t\t\t'Miroir raw.githubusercontent.com' => 'https://raw.githubusercontent.com/' . $repo . '/main/latest/update.json',\n\t\t\t'Miroir jsDelivr (CDN)'          => 'https://cdn.jsdelivr.net/gh/' . $repo . '@main/latest/update.json',",
     "\t\t\t'Miroir raw.githubusercontent.com' => 'https://raw.githubusercontent.com/' . $repo . '/main/latest/update.json', // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent\n\t\t\t'Miroir jsDelivr (CDN)'          => 'https://cdn.jsdelivr.net/gh/' . $repo . '@main/latest/update.json', // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent")

print('BATCH TERMINE')
