<?php
/**
 * Page admin : Updates — centre de contrôle des mises à jour.
 *
 * Version installée vs disponible, canal, compatibilité, intégrité,
 * mise à jour en 1 clic (WordPress natif), rollback réel vers une
 * sauvegarde locale, historique.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Admin\Pages;

use InfinityCod\Core\Settings;
use InfinityCod\License\Updater;


defined( 'ABSPATH' ) || exit;

class UpdatesPage {

	/**
	 * Constructeur : handlers.
	 */
	public function __construct() {
		add_action( 'admin_post_icod_save_updates', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_icod_check_updates_now', array( $this, 'handle_check_now' ) );
		add_action( 'admin_post_icod_rollback', array( $this, 'handle_rollback' ) );
		add_action( 'admin_post_icod_force_install', array( $this, 'handle_force_install' ) );
		add_action( 'admin_post_icod_upload_zip', array( $this, 'handle_upload_zip' ) );
	}

	/**
	 * Affiche la page.
	 *
	 * @return void
	 */
	public function render() {
		$updater = infinitycod()->module( 'license' ) ? new Updater() : null;

		$remote = null;
		try {
			$remote = $updater ? $updater->latest() : null;
		} catch ( \Throwable $e ) {
			\InfinityCod\Logging\Logger::log( 'error', 'Updates page : ' . $e->getMessage() );
		}

		/* Auto-réparation : les transients peuvent contenir un marqueur
		   « injoignable » périmé (rate-limit passager) alors que les sources
		   répondent. On purge et retente une fois avant d'afficher. */
		if ( $updater && empty( $remote ) ) {
			delete_transient( 'icod_update_gh' );
			delete_transient( 'icod_update_atom' );
			delete_transient( 'icod_update_mirror' );
			delete_transient( 'icod_update_server' );
			\InfinityCod\License\Updater::clear_cache();
			try {
				$remote = $updater->latest();
			} catch ( \Throwable $e ) {
				\InfinityCod\Logging\Logger::log( 'error', 'Updates page retry : ' . $e->getMessage() );
			}
		}

		$remote = is_array( $remote ) ? $remote : array();

		$latest     = ! empty( $remote['version'] ) ? (string) $remote['version'] : '';
		$has_update = '' !== $latest && version_compare( INFINITYCOD_VERSION, $latest, '<' );

		$history  = get_option( 'infinitycod_update_history', array() );
		$history  = is_array( $history ) ? $history : array();
		$msg      = isset( $_GET['icod_msg'] ) ? rawurldecode( sanitize_text_field( wp_unslash( $_GET['icod_msg'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$upgrade_url = wp_nonce_url(
			self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . urlencode( INFINITYCOD_BASENAME ) ),
			'upgrade-plugin_' . INFINITYCOD_BASENAME
		);
		?>
		<div class="wrap icod-wrap">
			<h1 class="icod-title"><?php esc_html_e( 'Mises à jour', 'infinitycod' ); ?></h1>

			<?php if ( 'checked' === $msg ) : ?>
				<div class="notice notice-info is-dismissible"><p><?php esc_html_e( 'Vérification effectuée.', 'infinitycod' ); ?></p></div>
			<?php endif; ?>
			<?php if ( 'install-ok' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Installation terminée : InfinityCod a été remplacé par la version choisie. Vos données sont intactes.', 'infinitycod' ); ?></p></div>
			<?php endif; ?>
			<?php if ( 'install-fail' === $msg ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Installation impossible : la release n’a pas pu être récupérée depuis GitHub. Utilisez l’installation manuelle par zip ci-dessous.', 'infinitycod' ); ?></p></div>
			<?php endif; ?>
			<?php if ( 'upload-fail' === $msg ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Téléversement impossible : vérifiez que le fichier est un zip InfinityCod valide et réessayez.', 'infinitycod' ); ?></p></div>
			<?php endif; ?>
			<?php if ( 'rollback-ok' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Rollback effectué — version restaurée avec succès.', 'infinitycod' ); ?></p></div>
			<?php endif; ?>
			<?php if ( 'rollback-fail' === $msg ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Rollback impossible : sauvegarde introuvable pour cette version.', 'infinitycod' ); ?></p></div>
			<?php endif; ?>

			<div class="icod-updates-banner <?php echo $has_update ? 'has-upd' : ''; ?>">
		<div><strong>🔄 <?php esc_html_e( 'Mises à jour automatiques actives.', 'infinitycod' ); ?></strong>
		<?php if ( $has_update ) : ?>
			<?php printf( esc_html__( 'La version %s est disponible — installez-la en un clic.', 'infinitycod' ), '<strong>' . esc_html( $latest ) . '</strong>' ); ?> <!-- phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -->
		<?php else : ?>
			<?php esc_html_e( 'Votre site vérifie GitHub toutes les heures. Vous serez notifié dès qu\’une nouvelle version sort.', 'infinitycod' ); ?>
		<?php endif; ?></div>
	</div>
	<div class="icod-card">
				<h2><?php esc_html_e( 'État des mises à jour', 'infinitycod' ); ?></h2>
				<table class="icod-updates-state">
					<tr>
						<td><?php esc_html_e( 'Version installée', 'infinitycod' ); ?></td>
						<td><strong><?php echo esc_html( INFINITYCOD_VERSION ); ?></strong></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Dernière version disponible', 'infinitycod' ); ?></td>
						<td>
							<?php if ( $latest ) : ?>
								<strong><?php echo esc_html( $latest ); ?></strong>
								<span class="icod-hint">
									— <?php echo esc_html( 'beta' === Updater::channel() ? 'canal beta' : 'canal stable' ); ?>
									<?php
									$src           = isset( $remote['source'] ) ? (string) $remote['source'] : '';
									$source_labels = array(
										'github'          => __( '· via GitHub API', 'infinitycod' ),
										'atom'            => __( '· via GitHub (flux atom)', 'infinitycod' ),
										'mirror-raw'      => __( '· via le miroir GitHub brut', 'infinitycod' ),
										'mirror-jsdelivr' => __( '· via le miroir CDN', 'infinitycod' ),
									);
									if ( isset( $source_labels[ $src ] ) ) {
										echo esc_html( $source_labels[ $src ] );
									}
									?>
								</span>
							<?php else : ?>
								<em><?php esc_html_e( 'Inconnue (GitHub injoignable ou aucune release publiée)', 'infinitycod' ); ?></em>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Statut', 'infinitycod' ); ?></td>
						<td>
							<?php if ( $has_update ) : ?>
								<span class="icod-status icod-status-no_answer">
									<?php printf( esc_html__( 'InfinityCod %s disponible', 'infinitycod' ), esc_html( $latest ) ); ?> <!-- phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -->
								</span>
								<a class="button button-primary button-small" href="<?php echo esc_url( $upgrade_url ); ?>"><?php esc_html_e( 'Mettre à jour maintenant', 'infinitycod' ); ?></a>
							<?php elseif ( $latest ) : ?>
								<span class="icod-status icod-status-delivered"><?php esc_html_e( 'À jour', 'infinitycod' ); ?></span>
							<?php else : ?>
								<span class="icod-status icod-status-pending"><?php esc_html_e( 'Inconnu', 'infinitycod' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Licence', 'infinitycod' ); ?></td>
						<td><?php echo esc_html( \InfinityCod\License\LicenseManager::status_label() ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Intégrité du package', 'infinitycod' ); ?></td>
						<td>
							<?php if ( ! empty( $remote['sha256'] ) ) : ?>
								<span class="icod-status icod-status-delivered">SHA-256 <?php esc_html_e( 'vérifié avant installation', 'infinitycod' ); ?></span> <span class="icod-hint" dir="ltr"><?php echo esc_html( substr( (string) ['sha256'], 0, 16 ) ); ?>…</span>
							<?php else : ?>
								<span class="icod-hint"><?php esc_html_e( 'Manifest sans empreinte (installation contrôlée par WordPress)', 'infinitycod' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Compatibilité', 'infinitycod' ); ?></td>
						<td>
							<?php
							if ( $remote ) {
								$compat = $updater->check_compatibility( $remote );
								if ( is_wp_error( $compat ) ) {
									echo '<span class="icod-risk icod-risk-high">' . esc_html( $compat->get_error_message() ) . '</span>';
								} else {
									echo '<span class="icod-status icod-status-delivered">PASS</span> <span class="icod-hint">PHP ' . esc_html( PHP_VERSION ) . ' · WP ' . esc_html( get_bloginfo( 'version' ) ) . '</span>';
								}
							} else {
								echo '—';
							}
							?>
						</td>
					</tr>
				</table>

				<div class="icod-updates-actions">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
						<input type="hidden" name="action" value="icod_check_updates_now" />
						<?php wp_nonce_field( 'icod_check_updates_now' ); ?>
						<button type="submit" class="button">🔄 <?php esc_html_e( 'Vérifier les mises à jour', 'infinitycod' ); ?></button>
					</form>
					<?php if ( $has_update ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( $upgrade_url ); ?>">⬆ <?php esc_html_e( 'Mettre à jour vers', 'infinitycod' ); ?> <?php echo esc_html( $latest ); ?></a>
					<?php elseif ( $latest ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block" onsubmit="return confirm('<?php echo esc_js( __( 'Installer la dernière release GitHub même si le plugin semble à jour ?', 'infinitycod' ) ); ?>');">
							<input type="hidden" name="action" value="icod_force_install" />
							<?php wp_nonce_field( 'icod_force_install' ); ?>
							<button type="submit" class="button button-secondary" title="<?php esc_attr_e( 'Télécharge la dernière release depuis GitHub et remplace les fichiers du plugin (réglages et données conservés).', 'infinitycod' ); ?>">⬇ <?php esc_html_e( 'Forcer l’installation de', 'infinitycod' ); ?> <?php echo esc_html( $latest ); ?></button>
						</form>
					<?php endif; ?>
				</div>

				<details class="icod-updates-manual" <?php echo ! $latest ? 'open' : ''; ?>>
					<summary><?php esc_html_e( 'Installation manuelle (zip) — solution de secours', 'infinitycod' ); ?></summary>
					<p class="description">
						<?php esc_html_e( 'Si votre hébergeur bloque GitHub ou que la détection échoue : téléchargez infinitycod.zip depuis la page des releases, puis téléversez-le ici. Le plugin est remplacé sans perte : réglages, commandes et tarifs sont conservés.', 'infinitycod' ); ?>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
						<input type="hidden" name="action" value="icod_upload_zip" />
						<?php wp_nonce_field( 'icod_upload_zip' ); ?>
						<p>
							<input type="file" name="icod_zip" accept=".zip" required />
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Installer ce zip (remplace la version actuelle)', 'infinitycod' ); ?></button>
						</p>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="icod_upload_zip" />
						<?php wp_nonce_field( 'icod_upload_zip' ); ?>
						<p>
							<input type="url" name="icod_zip_url" dir="ltr" placeholder="https://…/infinitycod.zip" class="regular-text" required />
							<button type="submit" class="button"><?php esc_html_e( 'Installer depuis une URL', 'infinitycod' ); ?></button>
						</p>
					</form>
				</details>

				<?php
				// Diagnostic des sources (résultat du dernier « Vérifier »).
				$probe = get_option( 'icod_source_test', array() );
				$probe = is_array( $probe ) ? $probe : array();
				if ( ! empty( $probe['sources'] ) ) :
					?>
					<details class="icod-updates-manual icod-probe" open>
						<summary><?php esc_html_e( '🔌 Diagnostic des sources', 'infinitycod' ); ?><?php echo ! empty( $probe['time'] ) ? ' <span class="icod-hint">— ' . esc_html( mysql2date( 'd/m/Y H:i', $probe['time'] ) ) . '</span>' : ''; ?></summary>
						<table class="icod-probe-table">
							<tbody>
								<?php foreach ( $probe['sources'] as $src ) : ?>
									<tr>
										<td><?php echo ( $src['ok'] ? '✅' : '❌' ); ?> <strong><?php echo esc_html( $src['name'] ); ?></strong></td>
										<td class="<?php echo $src['ok'] ? 'icod-probe-ok' : 'icod-probe-ko'; ?>"><?php echo esc_html( $src['detail'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p class="description"><?php esc_html_e( 'Si toutes les sources échouent avec une erreur réseau, votre hébergeur bloque les connexions sortantes — utilisez alors l‘installation manuelle par zip ci-dessus.', 'infinitycod' ); ?></p>
					</details>
				<?php endif; ?>
			</div>

			<div class="icod-card">
				<h2><?php esc_html_e( 'Paramètres de mise à jour', 'infinitycod' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="icod_save_updates" />
					<?php wp_nonce_field( 'icod_save_updates' ); ?>
					<div class="icod-grid">
						<label>
							<span><?php esc_html_e( 'Canal de mise à jour', 'infinitycod' ); ?></span>
							<select name="icod[update_channel]">
								<option value="stable" <?php selected( \InfinityCod\License\Updater::channel(), 'stable' ); ?>><?php esc_html_e( 'Stable (recommandé)', 'infinitycod' ); ?></option>
								<option value="beta" <?php selected( \InfinityCod\License\Updater::channel(), 'beta' ); ?>><?php esc_html_e( 'Beta (prereleases)', 'infinitycod' ); ?></option>
							</select>
						</label>
					</div>
					<div class="icod-toggles">
						<label class="icod-toggle">
							<input type="checkbox" name="icod[auto_update]" value="1" <?php checked( (int) Settings::get( 'auto_update' ), 1 ); ?> />
							<span><?php esc_html_e( 'Installation automatique des nouvelles versions', 'infinitycod' ); ?></span>
						</label>
					</div>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'infinitycod' ); ?></button></p>
				</form>
			</div>

			<div class="icod-card">
				<h2><?php esc_html_e( 'Rollback — restaurer une sauvegarde locale', 'infinitycod' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Une sauvegarde complète (fichiers + réglages) est créée automatiquement avant chaque mise à jour. La restaurer remet la version précédente du plugin ; la base de données reste compatible (migrations non destructives).', 'infinitycod' ); ?></p>
				<?php
				$backups = $this->list_backups();
				if ( $backups ) :
					?>
					<table class="widefat striped icod-table" style="max-width:640px">
						<thead><tr>
							<th><?php esc_html_e( 'Version', 'infinitycod' ); ?></th>
							<th><?php esc_html_e( 'Date de sauvegarde', 'infinitycod' ); ?></th>
							<th><?php esc_html_e( 'Action', 'infinitycod' ); ?></th>
						</tr></thead>
						<tbody>
							<?php foreach ( $backups as $backup ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $backup['version'] ); ?></strong></td>
									<td><?php echo esc_html( $backup['date'] ); ?></td>
									<td>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Restaurer cette version et remplacer les fichiers actuels ?', 'infinitycod' ) ); ?>');">
											<input type="hidden" name="action" value="icod_rollback" />
											<input type="hidden" name="version" value="<?php echo esc_attr( $backup['version'] ); ?>" />
											<?php wp_nonce_field( 'icod_rollback' ); ?>
											<button type="submit" class="button button-small">↩ <?php esc_html_e( 'Restaurer', 'infinitycod' ); ?></button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="icod-hint"><?php esc_html_e( 'Aucune sauvegarde locale encore disponible. La première sauvegarde est créée automatiquement avant la prochaine mise à jour.', 'infinitycod' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="icod-card">
				<h2><?php esc_html_e( 'Historique', 'infinitycod' ); ?></h2>
				<?php if ( $history ) : ?>
					<table class="widefat striped icod-table" style="max-width:640px">
						<thead><tr>
							<th><?php esc_html_e( 'Transition', 'infinitycod' ); ?></th>
							<th><?php esc_html_e( 'Action', 'infinitycod' ); ?></th>
							<th><?php esc_html_e( 'Résultat', 'infinitycod' ); ?></th>
							<th><?php esc_html_e( 'Date', 'infinitycod' ); ?></th>
						</tr></thead>
						<tbody>
							<?php foreach ( $history as $entry ) : ?>
								<tr>
									<td><?php echo esc_html( $entry['from'] . ' → ' . $entry['to'] ); ?></td>
									<td><?php echo esc_html( $entry['action'] ); ?></td>
									<td>
										<span class="icod-status <?php echo 'success' === $entry['result'] ? 'icod-status-delivered' : 'icod-status-returned'; ?>">
											<?php echo esc_html( $entry['result'] ); ?>
										</span>
										<?php if ( ! empty( $entry['error'] ) ) : ?>
											<span class="icod-sub"><?php echo esc_html( $entry['error'] ); ?></span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $entry['date'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="icod-hint"><?php esc_html_e( 'Aucune mise à jour encore réalisée sur ce site.', 'infinitycod' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Liste les sauvegardes locales disponibles (uploads/infinitycod-backups).
	 *
	 * @return array[] version, date.
	 */
	private function list_backups() {
		$upload = wp_get_upload_dir();
		$dir    = $upload['basedir'] . '/infinitycod-backups';

		if ( ! is_dir( $dir ) ) {
			return array();
		}

		$out = array();
		foreach ( (array) glob( $dir . '/infinitycod-*', GLOB_ONLYDIR ) as $path ) {
			$name = basename( $path );
			if ( 0 !== strpos( $name, 'infinitycod-' ) ) {
				continue;
			}
			$out[] = array(
				'version' => substr( $name, strlen( 'infinitycod-' ) ),
				'date'    => date_i18n( 'd/m/Y H:i', (int) filemtime( $path ) ),
				'path'    => $path,
			);
		}

		usort( $out, function ( $a, $b ) {
			return version_compare( $b['version'], $a['version'] );
		} );

		return $out;
	}

	/**
	 * Sauvegarde canal + auto-update (formulaire de cette page).
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}

		check_admin_referer( 'icod_save_updates' );

		$channel = isset( $_POST['icod']['update_channel'] ) ? sanitize_key( wp_unslash( $_POST['icod']['update_channel'] ) ) : 'stable';
		\InfinityCod\Core\Settings::set( 'update_channel', in_array( $channel, array( 'stable', 'beta' ), true ) ? $channel : 'stable' );
		\InfinityCod\Core\Settings::set( 'auto_update', empty( $_POST['icod']['auto_update'] ) ? 0 : 1 );

		wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-updates&icod_msg=saved' ) );
		exit;
	}

	/**
	 * Force la vérification immédiate.
	 *
	 * @return void
	 */
	public function handle_check_now() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}

		check_admin_referer( 'icod_check_updates_now' );

		Updater::clear_cache();
		delete_site_transient( 'update_plugins' );

		if ( function_exists( 'wp_update_plugins' ) ) {
			wp_update_plugins();
		}

		// Diagnostic : chaque source est testée individuellement et le
		// résultat est affiché sous l'état des mises à jour.
		update_option( 'icod_source_test', array(
			'time'    => current_time( 'mysql' ),
			'sources' => Updater::probe_sources(),
		), false );

		wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-updates&icod_msg=checked' ) );
		exit;
	}

	/**
	 * Rollback réel : restaure la sauvegarde locale de la version choisie.
	 *
	 * @return void
	 */
	public function handle_rollback() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}

		check_admin_referer( 'icod_rollback' );

		$version = isset( $_POST['version'] ) ? preg_replace( '/[^0-9.]/', '', wp_unslash( $_POST['version'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$updater = new Updater();
		$result  = $updater->restore_backup( $version );

		\InfinityCod\Logging\Logger::log( 'update', 'Rollback vers ' . $version . ' : ' . ( is_wp_error( $result ) ? $result->get_error_message() : 'success' ) );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-updates&icod_msg=rollback-fail' ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-updates&icod_msg=rollback-ok' ) );
		exit;
	}

	/**
	 * Installation forcée : télécharge la dernière release GitHub et remplace
	 * le plugin, même quand la détection ne propose pas de mise à jour
	 * (cas « déjà à jour », cache négatif, hébergeur capricieux).
	 *
	 * @return void
	 */
	public function handle_force_install() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}

		check_admin_referer( 'icod_force_install' );

		Updater::clear_cache();

		$updater = new Updater();
		$remote  = $updater->latest();

		if ( empty( $remote['download_url'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-updates&icod_msg=install-fail' ) );
			exit;
		}

		$installed = $this->install_package( $remote['download_url'] );

		\InfinityCod\Logging\Logger::log( 'update', 'Installation forcée v' . ( isset( $remote['version'] ) ? $remote['version'] : '?' ) . ' : ' . ( is_wp_error( $installed ) ? $installed->get_error_message() : 'success' ) );

		wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-updates&icod_msg=' . ( is_wp_error( $installed ) ? 'install-fail' : 'install-ok' ) ) );
		exit;
	}

	/**
	 * Installation manuelle : remplace le plugin par le zip téléversé.
	 *
	 * @return void
	 */
	public function handle_upload_zip() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}

		check_admin_referer( 'icod_upload_zip' );

		// Variante : installation depuis une URL directe de zip.
		$zip_url = isset( $_POST['icod_zip_url'] ) ? esc_url_raw( wp_unslash( $_POST['icod_zip_url'] ) ) : '';
		if ( '' !== $zip_url && preg_match( '#^https://#', $zip_url ) ) {
			$installed = $this->install_package( $zip_url );

			\InfinityCod\Logging\Logger::log( 'update', 'Installation depuis URL : ' . ( is_wp_error( $installed ) ? $installed->get_error_message() : 'success' ) );

			wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-updates&icod_msg=' . ( is_wp_error( $installed ) ? 'upload-fail' : 'install-ok' ) ) );
			exit;
		}

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- $_FILES échappe à la sanitization classique.
		if ( empty( $_FILES['icod_zip'] ) || ! isset( $_FILES['icod_zip']['error'] ) || UPLOAD_ERR_OK !== (int) $_FILES['icod_zip']['error'] ) {
			wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-updates&icod_msg=upload-fail' ) );
			exit;
		}
		$upload = wp_handle_upload(
			$_FILES['icod_zip'],
			array(
				'test_form' => false,
				'mimes'     => array( 'zip' => 'application/zip|application/x-zip-compressed|application/x-zip' ),
			)
		);
		// phpcs:enable

		if ( ! is_array( $upload ) || empty( $upload['file'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-updates&icod_msg=upload-fail' ) );
			exit;
		}

		$installed = $this->install_package( $upload['file'] );

		// Nettoyage du fichier temporaire, quelle que soit l'issue.
		if ( file_exists( $upload['file'] ) ) {
			wp_delete_file( $upload['file'] );
		}

		\InfinityCod\Logging\Logger::log( 'update', 'Installation manuelle zip : ' . ( is_wp_error( $installed ) ? $installed->get_error_message() : 'success' ) );

		wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-updates&icod_msg=' . ( is_wp_error( $installed ) ? 'upload-fail' : 'install-ok' ) ) );
		exit;
	}

	/**
	 * Exécute Plugin_Upgrader::install() en mode « remplacement » (WP 5.5+).
	 *
	 * @param string $package Chemin local ou URL du zip.
	 * @return true|WP_Error
	 */
	private function install_package( $package ) {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';

		$upgrader = new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin() );
		$result   = $upgrader->install( $package, array( 'overwrite' => true ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_array( $result ) || empty( $result['destination_name'] ) ) {
			return new \WP_Error( 'icod_install', __( 'Résultat d’installation inattendu.', 'infinitycod' ) );
		}

		return true;
	}
}
