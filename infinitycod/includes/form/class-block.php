<?php
/**
 * Bloc Gutenberg « InfinityCod — Formulaire COD ».
 *
 * Rendu côté serveur (ServerSideRender) : le bloc réutilise EXACTEMENT le
 * même moteur que le shortcode — styles, brouillon d'aperçu et anti-fraude
 * identiques. Aucune étape de build : le JS d'édition est en vanilla.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Form;

defined( 'ABSPATH' ) || exit;

class Block {

	/**
	 * Enregistre le bloc et ses assets d'édition.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_assets' ) );
	}

	/**
	 * Déclaration du bloc (rendu serveur).
	 *
	 * @return void
	 */
	public function register_block() {
		register_block_type(
			'infinitycod/form',
			array(
				'api_version'     => 2,
				'editor_script'   => 'icod-block-editor',
				'render_callback' => array( $this, 'render' ),
				'attributes'      => array(
					'id'    => array( 'type' => 'string', 'default' => '' ),
					'title' => array( 'type' => 'string', 'default' => '' ),
				),
				'supports'        => array( 'html' => false ),
			)
		);
	}

	/**
	 * Rendu du bloc (front + éditeur) — même moteur que le shortcode.
	 *
	 * @param array $atts Attributs du bloc (id, title).
	 * @return string
	 */
	public function render( $atts ) {
		$id    = isset( $atts['id'] ) ? absint( $atts['id'] ) : 0;
		$title = isset( $atts['title'] ) ? sanitize_text_field( $atts['title'] ) : '';

		$plugin = infinitycod();
		$form   = $plugin ? $plugin->module( 'form' ) : null;
		if ( ! $form ) {
			return '';
		}
		return $form->render( $id, $title, '' );
	}

	/**
	 * JS d'édition (vanilla, sans build).
	 *
	 * @return void
	 */
	public function editor_assets() {
		wp_enqueue_script(
			'icod-block-editor',
			INFINITYCOD_URL . 'assets/admin/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ),
			INFINITYCOD_VERSION,
			true
		);
	}
}
