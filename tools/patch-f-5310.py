# -*- coding: utf-8 -*-
"""Patch 5.31.0 quater : handler bordereaux, réglages A/B + intégrations, export/import JSON."""
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


# ---------- 1) ADMIN-MANAGER : registration + handler bordereaux ----------
patch("includes/admin/class-admin-manager.php", [
    (
        "\t\tadd_action( 'admin_post_icod_rates_import', array( $this, 'handle_rates_import' ) );",
        "\t\tadd_action( 'admin_post_icod_rates_import', array( $this, 'handle_rates_import' ) );\n"
        "\t\tadd_action( 'admin_post_icod_bordereaux', array( $this, 'handle_bordereaux' ) );\n"
        "\t\tadd_action( 'admin_post_icod_settings_export', array( $this, 'handle_settings_export' ) );\n"
        "\t\tadd_action( 'admin_post_icod_settings_import', array( $this, 'handle_settings_import' ) );",
    ),
])

# Handler bordereaux + export/import réglages (append à la fin du fichier).
HANDLERS = '''

	/**
	 * Vue imprimable des bordereaux des commandes cochées (admin-post).
	 *
	 * @return void
	 */
	public function handle_bordereaux() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}
		check_admin_referer( 'icod_bordereaux' );

		$ids = isset( $_GET['ids'] ) ? array_filter( array_map( 'absint', explode( ',', wp_unslash( $_GET['ids'] ) ) ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- ids absints un par un.
		$ids = array_values( array_unique( array_slice( $ids, 0, 200 ) ) );

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );

		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . esc_html__( 'Bordereaux InfinityCod', 'infinitycod' ) . '</title>';
		echo '<style>body{font-family:Arial,sans-serif;color:#111;margin:16px}.bordereau{border:2px solid #111;border-radius:12px;padding:14px 18px;margin:0 0 18px;page-break-after:always;max-width:720px}.bordereau h2{margin:0 0 4px;font-size:18px;display:flex;justify-content:space-between}.bordereau .muted{color:#555;font-size:12px}.bordereau table{width:100%;border-collapse:collapse;margin:10px 0;font-size:14px}.bordereau td{padding:3px 0;vertical-align:top}.bordereau td:first-child{color:#555;width:130px}.sign{display:flex;gap:24px;margin-top:26px}.sign div{flex:1;border-top:1px dashed #777;padding-top:6px;text-align:center;font-size:12px;color:#555}.cod{font-size:22px;font-weight:800}@media print{.noprint{display:none}}</style>';
		echo '</head><body>';
		echo '<p class="noprint"><button onclick="window.print()" style="padding:8px 16px">🖨️ ' . esc_html__( 'Imprimer / PDF', 'infinitycod' ) . '</button></p>';

		if ( ! $ids ) {
			echo '<p>' . esc_html__( 'Aucune commande sélectionnée.', 'infinitycod' ) . '</p></body></html>';
			exit;
		}

		global $wpdb;
		$table = \\InfinityCod\\Core\\Schema::table( 'orders' );
		$in    = implode( ',', $ids );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} WHERE id IN ({$in}) ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB
		$geo   = infinitycod()->module( 'geo' );

		foreach ( (array) $rows as $r ) {
			$wilaya_name = '';
			if ( $geo ) {
				$wrow = $geo->wilaya( (string) $r['wilaya_code'] );
				$wilaya_name = is_array( $wrow ) && isset( $wrow['name_fr'] ) ? (string) $wrow['name_fr'] : '';
			}
			$mode = 'desk' === $r['delivery_mode']
				? /* translators: %s : nom de la wilaya. */ sprintf( __( 'Stopdesk — %s', 'infinitycod' ), $wilaya_name . ( $r['stopdesk'] ? ' (' . $r['stopdesk'] . ')' : '' ) )
				: /* translators: %s : nom de la wilaya. */ sprintf( __( 'Domicile — %s', 'infinitycod' ), $wilaya_name . ( $r['commune'] ? ' (' . $r['commune'] . ')' : '' ) );

			echo '<div class="bordereau">';
			echo '<h2>' . esc_html( get_bloginfo( 'name' ) ) . '<span>#' . esc_html( $r['wc_order_id'] ? $r['wc_order_id'] : $r['id'] ) . '</span></h2>';
			echo '<div class="muted">' . esc_html( mysql2date( 'd/m/Y H:i', $r['created_at'] ) ) . '</div>';
			echo '<table>';
			echo '<tr><td>' . esc_html__( 'Client', 'infinitycod' ) . '</td><td><strong>' . esc_html( $r['customer_name'] ) . '</strong></td></tr>';
			echo '<tr><td>' . esc_html__( 'Téléphone', 'infinitycod' ) . '</td><td>' . esc_html( $r['phone'] ) . '</td></tr>';
			echo '<tr><td>' . esc_html__( 'Livraison', 'infinitycod' ) . '</td><td>' . esc_html( $mode ) . '</td></tr>';
			echo '<tr><td>' . esc_html__( 'Produit', 'infinitycod' ) . '</td><td>' . esc_html( $r['product_id'] ? get_the_title( (int) $r['product_id'] ) : '' ) . ' × ' . (int) $r['quantity'] . '</td></tr>';
			echo '<tr><td>' . esc_html__( 'Statut', 'infinitycod' ) . '</td><td>' . esc_html( $r['status'] ) . '</td></tr>';
			echo '</table>';
			echo '<div class="cod">' . esc_html__( 'À encaisser', 'infinitycod' ) . ' : ' . esc_html( number_format_i18n( (float) $r['total'], 0 ) . ' ' . \\InfinityCod\\Core\\Settings::currency_label() ) . '</div>';
			echo '<div class="sign"><div>' . esc_html__( 'Signature du client', 'infinitycod' ) . '</div><div>' . esc_html__( 'Cachet boutique', 'infinitycod' ) . '</div></div>';
			echo '</div>';
		}

		echo '</body></html>';
		exit;
	}

	/**
	 * Export JSON de la configuration (backup / duplication boutique).
	 *
	 * @return void
	 */
	public function handle_settings_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}
		check_admin_referer( 'icod_settings_io' );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=infinitycod-config-' . gmdate( 'Ymd' ) . '.json' );

		$settings = get_option( 'infinitycod_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		unset( $settings['webhook_secret'] ); // Secret régénérable : jamais exporté.
		echo (string) wp_json_encode(
			array(
				'plugin'    => 'infinitycod',
				'version'   => INFINITYCOD_VERSION,
				'exported'  => gmdate( 'c' ),
				'site'      => home_url(),
				'settings'  => $settings,
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
		);
		exit;
	}

	/**
	 * Import JSON de la configuration (fusion sur les clés connues).
	 *
	 * @return void
	 */
	public function handle_settings_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}
		check_admin_referer( 'icod_settings_io' );

		$redirect = admin_url( 'admin.php?page=infinitycod-settings&tab=advanced&icod_msg=' );

		if ( empty( $_FILES['icod_settings_json']['tmp_name'] ) ) {
			wp_safe_redirect( $redirect . 'import-empty' );
			exit;
		}

		$raw  = (string) file_get_contents( $_FILES['icod_settings_json']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents, WordPress.Security.ValidatedSanitizedInput -- lecture du fichier téléversé, validé par json_decode + clés connues.
		$pars = json_decode( $raw, true );
		if ( ! is_array( $pars ) || empty( $pars['plugin'] ) || 'infinitycod' !== $pars['plugin'] || empty( $pars['settings'] ) || ! is_array( $pars['settings'] ) ) {
			wp_safe_redirect( $redirect . 'import-bad' );
			exit;
		}

		// Fusion : uniquement les clés connues des défauts, jamais les secrets.
		$known     = \\InfinityCod\\Core\\Settings::defaults();
		$incoming  = $pars['settings'];
		$merged    = array();
		foreach ( $incoming as $key => $value ) {
			if ( ! array_key_exists( $key, $known ) || 'webhook_secret' === $key ) {
				continue;
			}
			if ( is_array( $known[ $key ] ) && ! is_array( $value ) ) {
				continue;
			}
			$merged[ $key ] = is_string( $value ) ? sanitize_textarea_field( $value ) : $value;
		}

		if ( $merged ) {
			\\InfinityCod\\Core\\Settings::set( $merged );
		}
		\\InfinityCod\\Core\\CachePurge::purge_all();

		wp_safe_redirect( $redirect . 'imported&count=' . count( $merged ) );
		exit;
	}
}
'''
# Remplacer la dernière accolade du fichier par nos handlers + accolade.
p = ROOT + BS + r"includes\admin\class-admin-manager.php"
with io.open(p, "r", encoding="utf-8", newline="") as f:
    d = f.read()
assert d.rstrip().endswith("}")
idx = d.rstrip().rfind("}")
d = d.rstrip()[:idx] + HANDLERS.replace(BS + BS, BS)
with io.open(p, "w", encoding="utf-8", newline="") as f:
    f.write(d)
ok.append("admin-manager : handlers bordereaux + settings io")

print("\n".join("OK  " + o for o in ok))
