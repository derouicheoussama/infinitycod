<?php
/**
 * ∞ Infinity Coder — Mises à jour automatiques du thème.
 *
 * Interroge INF_UPDATES_API (manifeste JSON, cache 12 h) et injecte les mises
 * à jour dans l'écran Apparence → Thèmes. La clé de licence est ajoutée à
 * l'URL du package (?license=…&site=…).
 *
 * @package infinity-market
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Infos distantes de mise à jour pour ce thème.
 *
 * @return array|false
 */
function inf_remote_update_info() {
	$inf_cache_key = 'inf_update_' . INF_THEME_SLUG;
	$inf_cached    = get_transient( $inf_cache_key );
	if ( false !== $inf_cached ) {
		return is_array( $inf_cached ) ? $inf_cached : false;
	}

	$inf_response = wp_remote_get(
		INF_UPDATES_API,
		array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/json' ) )
	);

	if ( is_wp_error( $inf_response ) || 200 !== wp_remote_retrieve_response_code( $inf_response ) ) {
		set_transient( $inf_cache_key, array(), 30 * MINUTE_IN_SECONDS );
		return false;
	}

	$inf_data = json_decode( wp_remote_retrieve_body( $inf_response ), true );
	$inf_info = ( is_array( $inf_data ) && isset( $inf_data[ INF_THEME_SLUG ] ) && is_array( $inf_data[ INF_THEME_SLUG ] ) ) ? $inf_data[ INF_THEME_SLUG ] : false;

	set_transient( $inf_cache_key, is_array( $inf_info ) ? $inf_info : array(), 12 * HOUR_IN_SECONDS );

	return is_array( $inf_info ) ? $inf_info : false;
}

/**
 * URL du package complétée avec licence + domaine.
 *
 * @param string $package URL de base.
 * @return string
 */
function inf_package_url( $package ) {
	if ( ! $package ) {
		return '';
	}
	return add_query_arg(
		array(
			'license' => rawurlencode( (string) inf_get_setting( 'license_key' ) ),
			'site'    => rawurlencode( home_url() ),
		),
		$package
	);
}

/**
 * Injecte la mise à jour dans le transitoire des thèmes.
 *
 * @param object $transient Transitoire update_themes.
 * @return object
 */
function inf_update_check( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$inf_remote = inf_remote_update_info();
	if ( ! $inf_remote || empty( $inf_remote['version'] ) ) {
		return $transient;
	}

	if ( version_compare( INF_THEME_VERSION, $inf_remote['version'], '<' ) ) {
		$transient->response[ INF_THEME_SLUG ] = array(
			'theme'        => INF_THEME_SLUG,
			'new_version'  => $inf_remote['version'],
			'url'          => isset( $inf_remote['url'] ) ? $inf_remote['url'] : '',
			'package'      => inf_package_url( isset( $inf_remote['package'] ) ? $inf_remote['package'] : '' ),
			'requires'     => isset( $inf_remote['requires'] ) ? $inf_remote['requires'] : '6.0',
			'requires_php' => isset( $inf_remote['requires_php'] ) ? $inf_remote['requires_php'] : '7.4',
		);
	}

	return $transient;
}
add_filter( 'pre_set_site_transient_update_themes', 'inf_update_check' );

/**
 * Fiche « Voir les détails du thème ».
 *
 * @param false|object $result Faux ou objet.
 * @param string       $action Action.
 * @param object       $args   Arguments.
 * @return false|object
 */
function inf_theme_info( $result, $action, $args ) {
	if ( 'theme_information' !== $action || empty( $args->slug ) || INF_THEME_SLUG !== $args->slug ) {
		return $result;
	}

	$inf_remote = inf_remote_update_info();
	if ( ! $inf_remote || empty( $inf_remote['version'] ) ) {
		return $result;
	}

	return (object) array(
		'name'          => INF_THEME_LABEL,
		'slug'          => INF_THEME_SLUG,
		'version'       => $inf_remote['version'],
		'download_link' => inf_package_url( isset( $inf_remote['package'] ) ? $inf_remote['package'] : '' ),
		'author'        => 'Derouiche Oussama — ∞ Infinity Coder',
		'homepage'      => isset( $inf_remote['url'] ) ? $inf_remote['url'] : '',
		'requires'      => isset( $inf_remote['requires'] ) ? $inf_remote['requires'] : '6.0',
		'requires_php'  => isset( $inf_remote['requires_php'] ) ? $inf_remote['requires_php'] : '7.4',
		'sections'      => array(
			'description' => '<p>' . sprintf(
				/* translators: %s : nom du thème */
				esc_html__( 'Thème e-commerce ∞ Infinity Coder pour le marché algérien : formulaire COD 58 wilayas, dashboard bleu, mises à jour intégrées. %s', 'infinity-market' ),
				esc_html( INF_THEME_LABEL )
			) . '</p>',
			'changelog'   => '<p><strong>' . esc_html( $inf_remote['version'] ) . '</strong> — ' . esc_html__( 'Améliorations et corrections. Voir le journal complet sur le site de l\'auteur.', 'infinity-market' ) . '</p>',
		),
	);
}
add_filter( 'themes_api', 'inf_theme_info', 20, 3 );

/**
 * Vérification manuelle de la licence (AJAX, page Licence).
 */
add_action( 'wp_ajax_inf_check_license', 'inf_ajax_check_license' );
function inf_ajax_check_license() {
	check_ajax_referer( 'inf_license', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permissions insuffisantes.', 'infinity-market' ) ) );
	}

	$inf_key = (string) inf_get_setting( 'license_key' );
	if ( '' === $inf_key ) {
		wp_send_json_error( array( 'message' => __( 'Enregistrez d\'abord une clé de licence.', 'infinity-market' ) ) );
	}

	$inf_response = wp_remote_post(
		INF_LICENSE_API,
		array(
			'timeout' => 10,
			'body'    => array(
				'license' => $inf_key,
				'site'    => home_url(),
				'theme'   => INF_THEME_SLUG,
			),
		)
	);

	if ( is_wp_error( $inf_response ) ) {
		wp_send_json_error(
			array( 'message' => __( 'Serveur de licence injoignable — les mises à jour du canal restent actives.', 'infinity-market' ) )
		);
	}

	$inf_code = (int) wp_remote_retrieve_response_code( $inf_response );
	$inf_body = json_decode( wp_remote_retrieve_body( $inf_response ), true );

	if ( 200 === $inf_code && ! empty( $inf_body['valid'] ) ) {
		update_option( 'inf_license_state', 'active' );
		wp_send_json_success( array( 'message' => __( 'Licence valide — merci de soutenir ∞ Infinity Coder !', 'infinity-market' ) ) );
	}

	update_option( 'inf_license_state', 'invalid' );
	wp_send_json_error( array( 'message' => __( 'Réponse du serveur de licence : clé non reconnue.', 'infinity-market' ) ) );
}

/**
 * Vide le cache de mise à jour à l'activation du thème.
 */
add_action( 'after_switch_theme', 'inf_flush_update_cache' );
function inf_flush_update_cache() {
	delete_transient( 'inf_update_' . INF_THEME_SLUG );
}
