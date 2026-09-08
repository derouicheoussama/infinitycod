<?php
/**
 * Classe principale du plugin — orchestration des modules.
 *
 * @package InfinityCod
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
		'geo'       => '\\InfinityCod\\Geo\\GeoManager',
		'rates'     => '\\InfinityCod\\Shipping\\RatesManager',
		'shield'    => '\\InfinityCod\\AntiFraud\\Shield',
		'orders'    => '\\InfinityCod\\Orders\\OrderStore',
		'form'      => '\\InfinityCod\\Form\\FormManager',
		'rest'      => '\\InfinityCod\\Rest\\Routes',
		'carriers'  => '\\InfinityCod\\Carriers\\CarrierManager',
		'whatsapp'  => '\\InfinityCod\\WhatsApp\\WhatsAppManager',
		'stats'     => '\\InfinityCod\\Stats\\PnL',
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
		$this->module( 'i18n' )->register();

		/**
		 * Permet d'activer/désactiver des modules avant le chargement.
		 *
		 * @param array $module_map slug => classe FQN.
		 */
		$module_map = apply_filters( 'infinitycod_modules', $this->module_map );

		foreach ( $module_map as $slug => $class ) {
			if ( class_exists( $class ) ) {
				$module              = new $class();
				$this->modules[ $slug ] = $module;

				if ( method_exists( $module, 'register' ) ) {
					$module->register();
				}
			}
		}

		add_action( 'init', array( $this, 'load_textdomain' ), 1 );
	}

	/**
	 * Récupère un module par slug.
	 *
	 * @param string $slug Slug du module.
	 * @return object|null
	 */
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
		load_plugin_textdomain( 'infinitycod', false, dirname( INFINITYCOD_BASENAME ) . '/languages' );
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
		return INFINITYCOD_URL . ltrim( $path, '/' );
	}
}
