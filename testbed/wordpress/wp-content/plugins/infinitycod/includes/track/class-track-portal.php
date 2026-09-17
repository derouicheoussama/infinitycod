<?php
/**
 * Portail de suivi client public.
 *
 * Le client saisit son numéro de commande et son téléphone : s'ils correspondent,
 * il voit le statut en direct (chronologie, transporteur, suivi) sans appeler.
 * Protection : correspondance stricte + limite de consultations par IP.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Track;

use InfinityCod\Core\Schema;
use InfinityCod\Core\Settings;
use InfinityCod\Orders\OrderStore;

defined( 'ABSPATH' ) || exit;

class TrackPortal {

	/**
	 * Hooks : rewrite + rendu.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'add_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );
	}

	/**
	 * Rewrite /suivi-commande/.
	 *
	 * @return void
	 */
	public function add_rewrite() {
		add_rewrite_rule( '^suivi-commande/?$', 'index.php?icod_track_portal=1', 'top' );
		if ( ! get_option( 'icod_track_flushed' ) ) {
			flush_rewrite_rules();
			update_option( 'icod_track_flushed', 1, true );
		}
	}

	/**
	 * Variable de requête.
	 *
	 * @param array $vars Variables existantes.
	 * @return array
	 */
	public function add_query_var( $vars ) {
		$vars[] = 'icod_track_portal';
		return $vars;
	}

	/**
	 * Rend le portail (formulaire + résultat si recherche).
	 *
	 * @return void
	 */
	public function maybe_render() {
		if ( ! get_query_var( 'icod_track_portal' ) || ! Settings::get( 'track_portal_enabled', 1 ) ) {
			return;
		}

		// Anti-abus : 30 consultations par IP et par heure.
		$ip      = \InfinityCod\AntiFraud\Shield::client_ip();
		$rl_key  = 'icod_track_rl_' . md5( $ip );
		$hits    = (int) get_transient( $rl_key );
		if ( $hits >= 30 ) {
			status_header( 429 );
			header( 'Content-Type: text/html; charset=utf-8' );
			echo '<!doctype html><meta charset="utf-8"><body style="font-family:system-ui;text-align:center;padding:60px"><p>' . esc_html__( 'Trop de consultations. Réessayez plus tard.', 'infinitycod' ) . '</p></body>';
			exit;
		}
		set_transient( $rl_key, $hits + 1, HOUR_IN_SECONDS );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- recherche publique, correspondance stricte numéro + téléphone exigée.
		$q_order = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
		$q_phone = isset( $_GET['tel'] ) ? preg_replace( '/[^0-9]/', '', wp_unslash( $_GET['tel'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		// phpcs:enable WordPress.Security.NonceVerification

		$found  = null;
		$notfound = false;
		if ( $q_order > 0 && strlen( $q_phone ) >= 4 ) {
			$found = Finder::find( $q_order, $q_phone );
			if ( ! $found ) {
				$notfound = true;
			}
		}

		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html><html <?php language_attributes(); ?>><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php esc_html_e( 'Suivi de commande', 'infinitycod' ); ?> — <?php bloginfo( 'name' ); ?></title>
<meta name="robots" content="noindex">
<?php wp_head(); ?>
<style>
body{font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;background:#f4f7f5;margin:0;color:#1c2530}
.tpk{max-width:520px;margin:40px auto;padding:28px;background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(16,24,40,.10)}
.tpk h1{font-size:1.3rem;margin:0 0 16px}
.tpk label{display:block;font-size:13px;font-weight:700;margin:12px 0 4px}
.tpk input{width:100%;box-sizing:border-box;padding:11px 13px;border:1.5px solid #d8e0e8;border-radius:10px;font-size:15px}
.tpk button{width:100%;margin-top:16px;padding:13px;border:0;border-radius:10px;background:#0e7a4f;color:#fff;font-size:15px;font-weight:800;cursor:pointer}
.tpk .st{display:inline-block;padding:3px 12px;border-radius:999px;font-size:13px;font-weight:700;background:#eef1f4;color:#556}
.tpk ul{list-style:none;padding:0;margin:14px 0}
.tpk li{display:flex;gap:8px;align-items:baseline;padding:5px 0;font-size:14px}
.tpk .dot{width:10px;height:10px;border-radius:50%;background:#c9d2db;flex:none;align-self:center}
.tpk li.done .dot{background:#0e7a4f}
.tpk li.done{color:#0b6e43;font-weight:700}
.tpk .muted{color:#789;font-size:12px}
</style></head><body>
<div class="tpk">
	<h1>📦 <?php esc_html_e( 'Suivi de votre commande', 'infinitycod' ); ?></h1>
	<form method="get">
		<input type="hidden" name="icod_track_portal" value="1">
		<label for="o"><?php esc_html_e( 'Numéro de commande', 'infinitycod' ); ?></label>
		<input id="o" name="order" type="number" min="1" value="<?php echo esc_attr( $q_order > 0 ? $q_order : '' ); ?>" required>
		<label for="t"><?php esc_html_e( 'Votre téléphone', 'infinitycod' ); ?></label>
		<input id="t" name="tel" type="tel" value="<?php echo esc_attr( $q_phone ); ?>" required>
		<button type="submit"><?php esc_html_e( 'Suivre ma commande', 'infinitycod' ); ?></button>
	</form>
	<?php if ( $notfound ) : ?>
		<p style="color:#b32d2e;font-weight:700">❌ <?php esc_html_e( 'Aucune commande ne correspond à ce numéro et ce téléphone.', 'infinitycod' ); ?></p>
	<?php endif; ?>
	<?php if ( is_array( $found ) ) : ?>
		<?php
		$statuses = OrderStore::STATUSES;
		$st       = (string) $found['status'];
		?>
		<h2 style="font-size:1.05rem;margin:22px 0 6px"><?php
			printf(
				/* translators: %d : numéro de commande. */
				esc_html__( 'Commande #%d', 'infinitycod' ),
				(int) $found['id']
			);
		?></h2>
		<p><span class="st"><?php echo esc_html( isset( $statuses[ $st ] ) ? $statuses[ $st ] : $st ); ?></span></p>
		<ul>
			<?php
			$steps = array(
				array( __( 'Créée', 'infinitycod' ), ! empty( $found['created_at'] ), $found['created_at'] ),
				array( __( 'Confirmée', 'infinitycod' ), in_array( $st, array( 'confirmed', 'shipped', 'delivered' ), true ), $found['confirmed_at'] ?? '' ),
				array( __( 'Expédiée', 'infinitycod' ), in_array( $st, array( 'shipped', 'delivered' ), true ), $found['shipped_at'] ?? '' ),
				array( __( 'Livrée', 'infinitycod' ), 'delivered' === $st, $found['delivered_at'] ?? '' ),
			);
			foreach ( $steps as $s ) :
				?>
				<li class="<?php echo $s[1] ? 'done' : 'todo'; ?>"><span class="dot"></span><span class="lbl"><?php echo esc_html( $s[0] ); ?></span><?php echo ( $s[1] && $s[2] ) ? '<span class="muted">' . esc_html( mysql2date( 'd/m H:i', (string) $s[2] ) ) . '</span>' : ''; ?></li>
			<?php endforeach; ?>
		</ul>
		<?php if ( ! empty( $found['carrier'] ) ) : ?>
			<p class="muted">🚚 <?php echo esc_html( $found['carrier'] ); ?><?php echo ! empty( $found['tracking'] ) ? ' — ' . esc_html( $found['tracking'] ) : ''; ?></p>
		<?php endif; ?>
		<p class="muted"><?php esc_html_e( 'Merci pour votre confiance 🧡', 'infinitycod' ); ?></p>
	<?php endif; ?>
</div>
<?php wp_footer(); ?>
</body></html>
		<?php
		exit;
	}
}
