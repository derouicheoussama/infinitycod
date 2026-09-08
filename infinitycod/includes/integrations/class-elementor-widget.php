<?php
/**
 * Widget Elementor natif — formulaire COD InfinityCod.
 *
 * Enregistré uniquement si Elementor est actif. Le widget rend le
 * formulaire COD complet (assets et sécurité inclus) avec contrôles :
 * produit, titre, bouton, thème visuel.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 * @since 2.2.0
 */

namespace InfinityCod\Integrations;

use InfinityCod\Core\Settings;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

class ElementorWidget extends Widget_Base {

	/**
	 * Nom du widget.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'infinitycod_form';
	}

	/**
	 * Titre affiché dans Elementor.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'InfinityCod — Formulaire COD', 'infinitycod' );
	}

	/**
	 * Icône.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-cart';
	}

	/**
	 * Catégories Elementor.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'general', 'woocommerce' );
	}

	/**
	 * Mots-clés de recherche.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'infinitycod', 'cod', 'livraison', 'algérie', 'commande' );
	}

	/**
	 * Contrôles du widget.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array( 'label' => __( 'Formulaire COD', 'infinitycod' ) )
		);

		$products = array( 0 => __( '— Produit de la page courante —', 'infinitycod' ) );
		if ( function_exists( 'wc_get_products' ) ) {
			foreach ( (array) wc_get_products( array( 'limit' => 300, 'status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ) ) as $product ) {
				$products[ $product->get_id() ] = $product->get_name();
			}
		}

		$this->add_control(
			'product_id',
			array(
				'label'   => __( 'Produit', 'infinitycod' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $products,
				'default' => '0',
			)
		);

		$this->add_control(
			'custom_title',
			array(
				'label'   => __( 'Titre personnalisé', 'infinitycod' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);

		$this->add_control(
			'custom_button',
			array(
				'label'   => __( 'Texte du bouton', 'infinitycod' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);

		$this->add_control(
			'preset',
			array(
				'label'   => __( 'Thème visuel', 'infinitycod' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					''        => __( 'Thème des réglages', 'infinitycod' ),
					'modern'  => __( 'Moderne', 'infinitycod' ),
					'elegant' => __( 'Élégant', 'infinitycod' ),
					'sunset'  => __( 'Sunset', 'infinitycod' ),
					'ocean'   => __( 'Océan', 'infinitycod' ),
					'minimal' => __( 'Minimal', 'infinitycod' ),
				),
				'default' => '',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Rendu : délègue au FormManager (sécurité et assets inclus).
	 *
	 * Passe temporairement le preset du widget via un filtre dédié.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$product_id = absint( $settings['product_id'] ?? 0 );
		$title      = sanitize_text_field( $settings['custom_title'] ?? '' );
		$button     = sanitize_text_field( $settings['custom_button'] ?? '' );
		$preset     = sanitize_key( $settings['preset'] ?? '' );

		// Applique le preset du widget si choisi (sinon thème des réglages).
		$override = static function ( $channel ) use ( $preset ) {
			return '' !== $preset ? $preset : $channel;
		};
		if ( '' !== $preset ) {
			add_filter( 'infinitycod_widget_preset', $override );
		}

		$form = infinitycod()->module( 'form' );
		echo $form ? $form->render( $product_id, $title, $button ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- rendu internalisé échappé.

		if ( '' !== $preset ) {
			remove_filter( 'infinitycod_widget_preset', $override );
		}
	}
}
