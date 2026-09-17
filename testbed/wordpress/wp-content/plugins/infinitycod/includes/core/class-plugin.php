<?php
/**
 * Classe principale du plugin — orchestration des modules.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrateur : instancie et enregistre chaque module fonctionnel.
 *
 * Un module = une classe avec une méthode register(). L'ordre du tableau
 * est l'ordre d'initialisation ; les modules inexistants (phases futures
 * ou désactivés) sont ignorés silencieusement.
 */
final class Plugin {

	/**
	 * Instance unique.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Modules chargés, indexés par slug.
	 *
	 * @var array<string, object>
	 */
	private $modules = array();

	/**
	 * Liste ordonnée des modules du plugin.
	 *
	 * @var array<string, string> slug => classe FQN.
	 */
	private $module_map = array(
		'i18n'      => __NAMESPACE__ . '\\I18n',
		'logger'    => '\\InfinityCod\\Logging\\Logger',
		'geo'       => '\\InfinityCod\\Geo\\GeoManager',
		'rates'     => '\\InfinityCod\\Shipping\\RatesManager',
		'shield'    => '\\InfinityCod\\AntiFraud\\Shield',
		'orders'    => '\\InfinityCod\\Orders\\OrderStore',
		'abtest'    => '\\InfinityCod\\Orders\\AbTest',
		'webhooks'  => '\\InfinityCod\\Core\\Webhooks',
		'antileak'  => '\\InfinityCod\\Core\\AntiLeak',
		'order_notifications' => '\\InfinityCod\\Orders\\AdminNotifications',
		'form'      => '\\InfinityCod\\Form\\FormManager',
		'payment'   => '\\InfinityCod\\Payment\\PaymentManager',
		'rest'      => '\\InfinityCod\\Rest\\Routes',
		'carriers'  => '\\InfinityCod\\Carriers\\CarrierManager',
		'whatsapp'  => '\\InfinityCod\\Whatsapp\\WhatsappManager',
		'seo'       => '\\InfinityCod\\Seo\\SeoManager',
		'stats'     => '\\InfinityCod\\Stats\\Pnl',
		'tracking'  => '\\InfinityCod\\Tracking\\PixelManager',
		'admin'     => '\\InfinityCod\\Admin\\AdminManager',
		'license'   => '\\InfinityCod\\License\\LicenseManager',
	);

	/**
	 * Singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Démarre le plugin : i18n, modules, assets.
	 *
	 * @return void
	 */
	public function boot() {
		/**
		 * Permet d'activer/désactiver des modules avant le chargement.
		 *
		 * @param array $module_map slug => classe FQN.
		 */
		$module_map = apply_filters( 'infinitycod_modules', $this->module_map );

		foreach ( $module_map as $slug => $class ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}

			if ( ! isset( $this->modules[ $slug ] ) ) {
				$this->modules[ $slug ] = new $class();
			}

			$module = $this->modules[ $slug ];

			if ( method_exists( $module, 'register' ) ) {
				$module->register();
			}
		}

		add_action( 'init', array( $this, 'load_textdomain' ), 1 );
		add_action( 'init', array( $this, 'handle_order_confirm' ) );
	}

	/**
	 * Récupère un module par slug.
	 *
	 * @param string $slug Slug du module.
	 * @return object|null
	 */
	/**
	 * Endpoint public « Je confirme ma commande » (lien WhatsApp signé).
	 *
	 * GET /?icod_confirm=ID&t=TOKEN — passe la commande en « confirmée ».
	 *
	 * @return void
	 */
	public function handle_order_confirm() {
		if ( ! isset( $_GET['icod_confirm'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lien signé à destination du client.
			return;
		}
		$icod_id = absint( $_GET['icod_confirm'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token   = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orders  = $this->module( 'orders' );
		$done    = $orders ? $orders->confirm_from_link( $icod_id, $token ) : false;

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		if ( $done ) {
			echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>✅</title></head>'
				. '<body style="font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:90vh;background:#f6f8f7">'
				. '<div style="text-align:center;padding:32px;background:#fff;border-radius:16px;box-shadow:0 6px 24px rgba(0,0,0,.08);max-width:420px">'
				. '<div style="font-size:52px">✅</div><h1 style="font-size:20px;margin:8px 0">' . esc_html__( 'Commande confirmée !', 'infinitycod' ) . '</h1>'
				. '<p style="color:#555">' . esc_html__( 'Merci ! Votre commande est confirmée. Nous vous contactons très vite.', 'infinitycod' ) . '</p>'
				. '</div></body></html>';
		} else {
			echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>⚠️</title></head>'
				. '<body style="font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:90vh">'
				. '<p>' . esc_html__( 'Lien de confirmation invalide ou déjà utilisé.', 'infinitycod' ) . '</p></body></html>';
		}
		exit;
	}

		public function module( $slug ) {
		if ( isset( $this->modules[ $slug ] ) ) {
			return $this->modules[ $slug ];
		}

		if ( isset( $this->module_map[ $slug ] ) && class_exists( $this->module_map[ $slug ] ) ) {
			$class                 = $this->module_map[ $slug ];
			$this->modules[ $slug ] = new $class();
			return $this->modules[ $slug ];
		}

		return null;
	}

	/**
	 * Charge les traductions.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'infinitycod', false, dirname( INFINITYCOD_BASENAME ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- vente directe hors wp.org : les traductions /languages ne sont pas chargees automatiquement.
	}

	/**
	 * Version courante du plugin.
	 *
	 * @return string
	 */
	public function version() {
		return INFINITYCOD_VERSION;
	}

	/**
	 * Url d'un asset (avec cache-busting par version).
	 *
	 * @param string $path Chemin relatif, ex. 'assets/front/css/form.css'.
	 * @return string
	 */
	public function asset_url( $path ) {
		$path = ltrim( $path, '/' );

		// Variante minifiée générée par le build : même fichier, ~2x plus léger.
		$min = preg_replace( '/\.(css|js)$/i', '.min.$1', $path );
		if ( $min && $min !== $path && file_exists( INFINITYCOD_PATH . $min ) ) {
			return INFINITYCOD_URL . $min;
		}

		return INFINITYCOD_URL . $path;
	}
}
