<?php
/**
 * Intégration Elementor : enregistre le widget InfinityCod si Elementor est actif.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 * @since 2.2.0
 */

namespace InfinityCod\Integrations;

defined( 'ABSPATH' ) || exit;

class ElementorIntegration {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
	}

	/**
	 * Enregistre le widget (Elementor 3.5+).
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Gestionnaire.
	 * @return void
	 */
	public function register_widget( $widgets_manager ) {
		if ( class_exists( '\Elementor\Widget_Base' ) && class_exists( __NAMESPACE__ . '\ElementorWidget' ) ) {
			$widgets_manager->register( new ElementorWidget() );
		}
	}
}
