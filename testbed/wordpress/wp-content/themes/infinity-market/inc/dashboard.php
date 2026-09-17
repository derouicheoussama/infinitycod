<?php
/**
 * ∞ Infinity Coder — Tableau de bord admin (thème bleu Infinity).
 *
 * Pages : Tableau de bord, Réglages, Livraison & Wilayas, Licence, À propos.
 *
 * @package infinity-market
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menu d'administration.
 */
add_action( 'admin_menu', 'inf_admin_menu' );
function inf_admin_menu() {
	add_menu_page( INF_THEME_NAME, INF_THEME_NAME, 'manage_options', 'inf-dashboard', 'inf_render_dashboard', 'dashicons-store', 3 );
	add_submenu_page( 'inf-dashboard', __( 'Tableau de bord', 'infinity-market' ), __( 'Tableau de bord', 'infinity-market' ), 'manage_options', 'inf-dashboard', 'inf_render_dashboard' );
	add_submenu_page( 'inf-dashboard', __( 'Réglages', 'infinity-market' ), __( 'Réglages', 'infinity-market' ), 'manage_options', 'inf-settings', 'inf_render_settings' );
	add_submenu_page( 'inf-dashboard', __( 'Livraison & Wilayas', 'infinity-market' ), __( 'Livraison', 'infinity-market' ), 'manage_options', 'inf-shipping', 'inf_render_shipping' );
	add_submenu_page( 'inf-dashboard', __( 'Commandes COD', 'infinity-market' ), __( 'Commandes COD', 'infinity-market' ), 'manage_options', 'edit.php?post_type=inf_cod_order' );
	add_submenu_page( 'inf-dashboard', __( 'Licence & Mises à jour', 'infinity-market' ), __( 'Licence', 'infinity-market' ), 'manage_options', 'inf-license', 'inf_render_license' );
	add_submenu_page( 'inf-dashboard', __( 'À propos', 'infinity-market' ), __( 'À propos', 'infinity-market' ), 'manage_options', 'inf-about', 'inf_render_about' );
}

/**
 * Styles/scripts admin (dashboard bleu) uniquement sur nos écrans.
 */
add_action( 'admin_enqueue_scripts', 'inf_admin_assets' );
function inf_admin_assets( $hook ) {
	$inf_screen  = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$inf_is_ours = false !== strpos( $hook, 'inf-' )
		|| ( $inf_screen && ( 'inf_cod_order' === $inf_screen->post_type || false !== strpos( $inf_screen->id, 'inf_cod_order' ) ) );
	if ( ! $inf_is_ours ) {
		return;
	}
	wp_enqueue_style( 'inf-admin', INF_THEME_URI . 'assets/css/admin.css', array(), INF_THEME_VERSION );
	wp_enqueue_script( 'inf-admin', INF_THEME_URI . 'assets/js/admin.js', array( 'jquery' ), INF_THEME_VERSION, true );
	wp_localize_script(
		'inf-admin',
		'infAdminData',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'inf_license' ),
		)
	);
}

/* -----------------------------------------------------------------
 * Sanitisation
 * ----------------------------------------------------------------- */

/**
 * Sanitise les réglages généraux.
 *
 * @param array $input Données brutes.
 * @return array
 */
function inf_sanitize_settings( $input ) {
	$saved = get_option( 'inf_settings', array() );
	$saved = is_array( $saved ) ? $saved : array();
	$input = is_array( $input ) ? $input : array();

	// Les champs absents du formulaire (ex. sauvegarde depuis la page Licence)
	// conservent leur valeur enregistrée.
	$clean = $saved;

	$text_fields   = array( 'store_phone', 'whatsapp', 'address', 'facebook', 'instagram', 'tiktok', 'footer_note', 'license_key', 'hero_title', 'hero_subtitle', 'hero_cta_url', 'product_name' );
	$url_fields    = array( 'facebook', 'instagram', 'tiktok', 'hero_image', 'product_image', 'bg_image' );
	$number_fields = array( 'free_shipping', 'product_price', 'product_old_price' );

	for ( $inf_n = 1; $inf_n <= 3; $inf_n++ ) {
		$text_fields = array_merge( $text_fields, array( 'slide' . $inf_n . '_title', 'slide' . $inf_n . '_subtitle', 'slide' . $inf_n . '_btn' ) );
		$url_fields  = array_merge( $url_fields, array( 'slide' . $inf_n . '_image', 'slide' . $inf_n . '_url' ) );
	}
	$text_fields   = array_merge( $text_fields, array( 'flash_title', 'flash_subtitle', 'flash_end' ) );
	$url_fields    = array_merge( $url_fields, array( 'flash_image', 'flash_url' ) );
	$number_fields = array_merge( $number_fields, array( 'flash_price', 'flash_old' ) );

	foreach ( $text_fields as $key ) {
		if ( isset( $input[ $key ] ) ) {
			$clean[ $key ] = sanitize_text_field( wp_unslash( $input[ $key ] ) );
		}
	}
	foreach ( $url_fields as $key ) {
		if ( isset( $input[ $key ] ) ) {
			$clean[ $key ] = esc_url_raw( wp_unslash( $input[ $key ] ) );
		}
	}
	foreach ( $number_fields as $key ) {
		if ( isset( $input[ $key ] ) ) {
			$clean[ $key ] = max( 0, (int) $input[ $key ] );
		}
	}
	foreach ( array( 'cod_enabled', 'cod_on_product', 'wa_float' ) as $key ) {
		if ( isset( $input[ $key ] ) ) {
			$clean[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}
	}
	if ( isset( $input['accent'] ) ) {
		$clean['accent'] = sanitize_hex_color( $input['accent'] );
	}
	if ( isset( $input['bg_color'] ) ) {
		$clean['bg_color'] = sanitize_hex_color( $input['bg_color'] );
	}

	// Choix à liste blanche (partagés avec le Customizer).
	if ( isset( $input['palette'] ) ) {
		$clean['palette'] = inf_sanitize_palette( $input['palette'] );
	}
	if ( isset( $input['body_font'] ) ) {
		$clean['body_font'] = inf_sanitize_font( $input['body_font'] );
	}
	if ( isset( $input['heading_font'] ) ) {
		$clean['heading_font'] = inf_sanitize_font( $input['heading_font'] );
	}
	if ( isset( $input['header_style'] ) ) {
		$clean['header_style'] = inf_sanitize_header_style( $input['header_style'] );
	}
	if ( isset( $input['footer_style'] ) ) {
		$clean['footer_style'] = inf_sanitize_footer_style( $input['footer_style'] );
	}
	if ( isset( $input['radius_style'] ) ) {
		$clean['radius_style'] = inf_sanitize_radius_style( $input['radius_style'] );
	}
	if ( isset( $input['font_size'] ) ) {
		$clean['font_size'] = inf_sanitize_font_size( $input['font_size'] );
	}
	if ( isset( $input['container_width'] ) ) {
		$clean['container_width'] = inf_sanitize_container( $input['container_width'] );
	}

	// URLs de réseaux : forcer http(s).
	foreach ( array( 'facebook', 'instagram', 'tiktok' ) as $key ) {
		if ( ! empty( $clean[ $key ] ) && ! preg_match( '#^https?://#i', $clean[ $key ] ) ) {
			$clean[ $key ] = 'https://' . $clean[ $key ];
		}
	}

	return $clean;
}

/**
 * Sanitise la table des frais de livraison.
 *
 * @param array $input Données brutes.
 * @return array
 */
function inf_sanitize_shipping( $input ) {
	$clean = array();
	foreach ( array_keys( inf_default_wilayas() ) as $code ) {
		$clean[ $code ] = array(
			'home'   => isset( $input[ $code ]['home'] ) ? max( 0, (int) $input[ $code ]['home'] ) : 0,
			'desk'   => isset( $input[ $code ]['desk'] ) ? max( 0, (int) $input[ $code ]['desk'] ) : 0,
			'active' => empty( $input[ $code ]['active'] ) ? 0 : 1,
		);
	}
	return $clean;
}

add_action( 'admin_init', 'inf_register_settings' );
function inf_register_settings() {
	register_setting( 'inf_settings_group', 'inf_settings', array( 'sanitize_callback' => 'inf_sanitize_settings' ) );
	register_setting( 'inf_shipping_group', 'inf_shipping', array( 'sanitize_callback' => 'inf_sanitize_shipping' ) );
}

/* -----------------------------------------------------------------
 * Pages
 * ----------------------------------------------------------------- */

/**
 * En-tête commun des pages Infinity (dégradé bleu).
 *
 * @param string $title    Titre.
 * @param string $subtitle Sous-titre.
 */
function inf_admin_header( $title, $subtitle = '' ) {
	printf(
		'<div class="inf-hero"><div class="inf-hero__row"><h1><span class="inf-hero__mark">∞</span> %1$s</h1><span class="inf-hero__version">%2$s · v%3$s</span></div><p>%4$s</p></div>',
		esc_html( $title ),
		esc_html( INF_THEME_LABEL ),
		esc_html( INF_THEME_VERSION ),
		esc_html( $subtitle )
	);
}

/**
 * Tableau de bord : statistiques + accès rapides.
 */
function inf_render_dashboard() {
	$inf_counts   = inf_order_counts();
	$inf_revenue  = inf_order_revenue();
	$inf_shipping = (array) get_option( 'inf_shipping', array() );
	?>
	<div class="wrap inf-wrap">
		<?php inf_admin_header( INF_THEME_NAME, __( 'Tableau de bord e-commerce — paiements à la livraison (COD), 58 wilayas.', 'infinity-market' ) ); ?>

		<div class="inf-cards">
			<div class="inf-card-stat">
				<span class="inf-card-stat__value"><?php echo esc_html( $inf_counts['today'] ); ?></span>
				<span class="inf-card-stat__label"><?php esc_html_e( 'Commandes aujourd\'hui', 'infinity-market' ); ?></span>
			</div>
			<div class="inf-card-stat">
				<span class="inf-card-stat__value"><?php echo esc_html( $inf_counts['total'] ); ?></span>
				<span class="inf-card-stat__label"><?php esc_html_e( 'Commandes au total', 'infinity-market' ); ?></span>
			</div>
			<div class="inf-card-stat">
				<span class="inf-card-stat__value"><?php echo esc_html( $inf_counts['new'] ); ?></span>
				<span class="inf-card-stat__label"><?php esc_html_e( 'Nouvelles commandes', 'infinity-market' ); ?></span>
			</div>
			<div class="inf-card-stat inf-card-stat--green">
				<span class="inf-card-stat__value"><?php echo esc_html( inf_price( $inf_revenue ) ); ?></span>
				<span class="inf-card-stat__label"><?php esc_html_e( 'Chiffre d\'affaires livré', 'infinity-market' ); ?></span>
			</div>
		</div>

		<div class="inf-grid-2">
			<div class="inf-panel">
				<h2 class="inf-panel__title"><?php esc_html_e( 'Configuration rapide', 'infinity-market' ); ?></h2>
				<ul class="inf-checklist">
					<li class="<?php echo inf_get_setting( 'store_phone' ) ? 'is-ok' : ''; ?>"><span class="inf-checklist__icon"><?php inf_icon( 'check' ); ?></span><a href="<?php echo esc_url( admin_url( 'admin.php?page=inf-settings' ) ); ?>"><?php esc_html_e( 'Renseigner le téléphone et WhatsApp de la boutique', 'infinity-market' ); ?></a></li>
					<li class="<?php echo $inf_shipping ? 'is-ok' : ''; ?>"><span class="inf-checklist__icon"><?php inf_icon( 'truck' ); ?></span><a href="<?php echo esc_url( admin_url( 'admin.php?page=inf-shipping' ) ); ?>"><?php esc_html_e( 'Vérifier les frais de livraison des 58 wilayas', 'infinity-market' ); ?></a></li>
					<li class="<?php echo has_custom_logo() ? 'is-ok' : ''; ?>"><span class="inf-checklist__icon"><?php inf_icon( 'star' ); ?></span><a href="<?php echo esc_url( admin_url( 'customize.php' ) ); ?>"><?php esc_html_e( 'Logo, couleurs, polices, fond (Personnaliser → ∞ Apparence)', 'infinity-market' ); ?></a></li>
					<li class="<?php echo inf_get_setting( 'license_key' ) ? 'is-ok' : ''; ?>"><span class="inf-checklist__icon"><?php inf_icon( 'shield' ); ?></span><a href="<?php echo esc_url( admin_url( 'admin.php?page=inf-license' ) ); ?>"><?php esc_html_e( 'Activer votre licence pour les mises à jour', 'infinity-market' ); ?></a></li>
				</ul>
			</div>

			<div class="inf-panel">
				<h2 class="inf-panel__title"><?php esc_html_e( 'Dernières commandes', 'infinity-market' ); ?></h2>
				<?php $inf_recent = inf_recent_orders( 5 ); ?>
				<?php if ( empty( $inf_recent ) ) : ?>
					<p class="inf-muted"><?php esc_html_e( 'Aucune commande pour le moment. Le formulaire COD apparaît sur vos fiches produit (ou via le shortcode [infinity_cod]).', 'infinity-market' ); ?></p>
				<?php else : ?>
					<table class="inf-table">
						<thead><tr><th><?php esc_html_e( 'Réf.', 'infinity-market' ); ?></th><th><?php esc_html_e( 'Client', 'infinity-market' ); ?></th><th><?php esc_html_e( 'Wilaya', 'infinity-market' ); ?></th><th><?php esc_html_e( 'Total', 'infinity-market' ); ?></th><th><?php esc_html_e( 'Statut', 'infinity-market' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $inf_recent as $inf_order ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( get_edit_post_link( $inf_order['id'] ) ); ?>"><?php echo esc_html( $inf_order['ref'] ); ?></a></td>
								<td><?php echo esc_html( $inf_order['name'] ); ?></td>
								<td><?php echo esc_html( $inf_order['wilaya'] ); ?></td>
								<td><?php echo esc_html( inf_price( $inf_order['total'] ) ); ?></td>
								<td><span class="inf-badge inf-badge--<?php echo esc_attr( inf_status_slug( $inf_order['status'] ) ); ?>"><?php echo esc_html( $inf_order['status'] ); ?></span></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=inf_cod_order' ) ); ?>"><?php esc_html_e( 'Voir toutes les commandes', 'infinity-market' ); ?></a></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Réglages généraux.
 */
function inf_render_settings() {
	$s = get_option( 'inf_settings', array() );
	$inf_val = fn( $key, $default = '' ) => isset( $s[ $key ] ) ? $s[ $key ] : $default;
	?>
	<div class="wrap inf-wrap">
		<?php inf_admin_header( __( 'Réglages', 'infinity-market' ), __( 'Identité de la boutique, formulaire COD et apparence.', 'infinity-market' ) ); ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'inf_settings_group' ); ?>

			<div class="inf-panel">
				<h2 class="inf-panel__title"><?php esc_html_e( 'Boutique', 'infinity-market' ); ?></h2>
				<table class="form-table">
					<tr><th><label for="inf-store-phone"><?php esc_html_e( 'Téléphone', 'infinity-market' ); ?></label></th>
						<td><input id="inf-store-phone" type="text" name="inf_settings[store_phone]" value="<?php echo esc_attr( $inf_val( 'store_phone' ) ); ?>" class="regular-text" placeholder="0555 00 00 00">
						<p class="description"><?php esc_html_e( 'Affiché en haut du site.', 'infinity-market' ); ?></p></td></tr>
					<tr><th><label for="inf-whatsapp"><?php esc_html_e( 'WhatsApp', 'infinity-market' ); ?></label></th>
						<td><input id="inf-whatsapp" type="text" name="inf_settings[whatsapp]" value="<?php echo esc_attr( $inf_val( 'whatsapp' ) ); ?>" class="regular-text" placeholder="0555000000"></td></tr>
					<tr><th><label for="inf-address"><?php esc_html_e( 'Adresse', 'infinity-market' ); ?></label></th>
						<td><input id="inf-address" type="text" name="inf_settings[address]" value="<?php echo esc_attr( $inf_val( 'address' ) ); ?>" class="regular-text"></td></tr>
				</table>
			</div>

			<div class="inf-panel">
				<h2 class="inf-panel__title"><?php esc_html_e( 'Formulaire COD', 'infinity-market' ); ?></h2>
				<table class="form-table">
					<tr><th><?php esc_html_e( 'Commandes', 'infinity-market' ); ?></th>
						<td><label><input type="checkbox" name="inf_settings[cod_enabled]" value="1" <?php checked( $inf_val( 'cod_enabled', 1 ), 1 ); ?>> <?php esc_html_e( 'Activer le formulaire de commande (paiement à la livraison)', 'infinity-market' ); ?></label><br>
						<label><input type="checkbox" name="inf_settings[cod_on_product]" value="1" <?php checked( $inf_val( 'cod_on_product', 1 ), 1 ); ?>> <?php esc_html_e( 'Afficher le formulaire sur les fiches produit WooCommerce', 'infinity-market' ); ?></label></td></tr>
					<tr><th><label for="inf-free-shipping"><?php esc_html_e( 'Livraison gratuite à partir de (DA)', 'infinity-market' ); ?></label></th>
						<td><input id="inf-free-shipping" type="number" min="0" step="1" name="inf_settings[free_shipping]" value="<?php echo esc_attr( $inf_val( 'free_shipping', 0 ) ); ?>" class="small-text">
						<p class="description"><?php esc_html_e( '0 = désactivé.', 'infinity-market' ); ?></p></td></tr>
				</table>
			</div>

			<div class="inf-panel">
				<h2 class="inf-panel__title"><?php esc_html_e( 'Page d\'accueil (hero)', 'infinity-market' ); ?></h2>
				<table class="form-table">
					<tr><th><label for="inf-hero-title"><?php esc_html_e( 'Titre', 'infinity-market' ); ?></label></th>
						<td><input id="inf-hero-title" type="text" name="inf_settings[hero_title]" value="<?php echo esc_attr( $inf_val( 'hero_title' ) ); ?>" class="regular-text"></td></tr>
					<tr><th><label for="inf-hero-subtitle"><?php esc_html_e( 'Sous-titre', 'infinity-market' ); ?></label></th>
						<td><textarea id="inf-hero-subtitle" name="inf_settings[hero_subtitle]" rows="2" class="large-text"><?php echo esc_textarea( $inf_val( 'hero_subtitle' ) ); ?></textarea></td></tr>
					<tr><th><label for="inf-hero-image"><?php esc_html_e( 'Image du hero (URL)', 'infinity-market' ); ?></label></th>
						<td><input id="inf-hero-image" type="url" name="inf_settings[hero_image]" value="<?php echo esc_attr( $inf_val( 'hero_image' ) ); ?>" class="large-text code"></td></tr>
					<tr><th><label for="inf-hero-cta"><?php esc_html_e( 'Lien du bouton « Commander »', 'infinity-market' ); ?></label></th>
						<td><input id="inf-hero-cta" type="text" name="inf_settings[hero_cta_url]" value="<?php echo esc_attr( $inf_val( 'hero_cta_url', '#inf-cod' ) ); ?>" class="regular-text"></td></tr>
				</table>
			</div>

			<div class="inf-panel">
				<h2 class="inf-panel__title"><?php esc_html_e( 'Slider de la page d\'accueil (3 diapositives)', 'infinity-market' ); ?></h2>
				<p class="inf-muted"><?php esc_html_e( 'Laissez vide pour afficher le hero simple. Image recommandée : 1200 × 700 px.', 'infinity-market' ); ?></p>
				<?php for ( $inf_n = 1; $inf_n <= 3; $inf_n++ ) : ?>
					<h3 style="margin:18px 0 6px;font-size:14px;color:#1e3a8a"><?php
						/* translators: %d : numéro de diapositive */
						printf( esc_html__( 'Diapositive %d', 'infinity-market' ), (int) $inf_n );
					?></h3>
					<table class="form-table">
						<tr><th><label for="inf-slide-title-<?php echo esc_attr( (string) $inf_n ); ?>"><?php esc_html_e( 'Titre', 'infinity-market' ); ?></label></th>
							<td><input id="inf-slide-title-<?php echo esc_attr( (string) $inf_n ); ?>" type="text" name="inf_settings[slide<?php echo esc_attr( (string) $inf_n ); ?>_title]" value="<?php echo esc_attr( $inf_val( 'slide' . $inf_n . '_title' ) ); ?>" class="regular-text"></td></tr>
						<tr><th><label for="inf-slide-sub-<?php echo esc_attr( (string) $inf_n ); ?>"><?php esc_html_e( 'Sous-titre', 'infinity-market' ); ?></label></th>
							<td><input id="inf-slide-sub-<?php echo esc_attr( (string) $inf_n ); ?>" type="text" name="inf_settings[slide<?php echo esc_attr( (string) $inf_n ); ?>_subtitle]" value="<?php echo esc_attr( $inf_val( 'slide' . $inf_n . '_subtitle' ) ); ?>" class="large-text"></td></tr>
						<tr><th><label for="inf-slide-img-<?php echo esc_attr( (string) $inf_n ); ?>"><?php esc_html_e( 'Image (URL)', 'infinity-market' ); ?></label></th>
							<td><input id="inf-slide-img-<?php echo esc_attr( (string) $inf_n ); ?>" type="url" name="inf_settings[slide<?php echo esc_attr( (string) $inf_n ); ?>_image]" value="<?php echo esc_attr( $inf_val( 'slide' . $inf_n . '_image' ) ); ?>" class="large-text code"></td></tr>
						<tr><th><label for="inf-slide-url-<?php echo esc_attr( (string) $inf_n ); ?>"><?php esc_html_e( 'Lien du bouton', 'infinity-market' ); ?></label></th>
							<td><input id="inf-slide-url-<?php echo esc_attr( (string) $inf_n ); ?>" type="text" name="inf_settings[slide<?php echo esc_attr( (string) $inf_n ); ?>_url]" value="<?php echo esc_attr( $inf_val( 'slide' . $inf_n . '_url' ) ); ?>" class="regular-text">
							<label style="margin-left:10px"><?php esc_html_e( 'Texte :', 'infinity-market' ); ?> <input type="text" name="inf_settings[slide<?php echo esc_attr( (string) $inf_n ); ?>_btn]" value="<?php echo esc_attr( $inf_val( 'slide' . $inf_n . '_btn' ) ); ?>" class="regular-text"></label></td></tr>
					</table>
				<?php endfor; ?>
			</div>

			<div class="inf-panel">
				<h2 class="inf-panel__title"><?php esc_html_e( 'Vente flash (carte latérale du slider)', 'infinity-market' ); ?></h2>
				<p class="inf-muted"><?php esc_html_e( 'Laissez le titre vide pour utiliser automatiquement votre premier produit en promotion.', 'infinity-market' ); ?></p>
				<table class="form-table">
					<tr><th><label for="inf-flash-title"><?php esc_html_e( 'Titre', 'infinity-market' ); ?></label></th>
						<td><input id="inf-flash-title" type="text" name="inf_settings[flash_title]" value="<?php echo esc_attr( $inf_val( 'flash_title' ) ); ?>" class="regular-text"></td></tr>
					<tr><th><label for="inf-flash-sub"><?php esc_html_e( 'Sur-titre (ex. : Vente flash)', 'infinity-market' ); ?></label></th>
						<td><input id="inf-flash-sub" type="text" name="inf_settings[flash_subtitle]" value="<?php echo esc_attr( $inf_val( 'flash_subtitle' ) ); ?>" class="regular-text"></td></tr>
					<tr><th><label for="inf-flash-price"><?php esc_html_e( 'Prix / ancien prix (DA)', 'infinity-market' ); ?></label></th>
						<td><input id="inf-flash-price" type="number" min="0" name="inf_settings[flash_price]" value="<?php echo esc_attr( $inf_val( 'flash_price', 0 ) ); ?>" class="small-text">
						<label style="margin-left:12px"><?php esc_html_e( 'Ancien :', 'infinity-market' ); ?> <input type="number" min="0" name="inf_settings[flash_old]" value="<?php echo esc_attr( $inf_val( 'flash_old', 0 ) ); ?>" class="small-text"></label></td></tr>
					<tr><th><label for="inf-flash-img"><?php esc_html_e( 'Image (URL)', 'infinity-market' ); ?></label></th>
						<td><input id="inf-flash-img" type="url" name="inf_settings[flash_image]" value="<?php echo esc_attr( $inf_val( 'flash_image' ) ); ?>" class="large-text code"></td></tr>
					<tr><th><label for="inf-flash-url"><?php esc_html_e( 'Lien', 'infinity-market' ); ?></label></th>
						<td><input id="inf-flash-url" type="text" name="inf_settings[flash_url]" value="<?php echo esc_attr( $inf_val( 'flash_url' ) ); ?>" class="regular-text"></td></tr>
					<tr><th><label for="inf-flash-end"><?php esc_html_e( 'Fin de l\'offre', 'infinity-market' ); ?></label></th>
						<td><input id="inf-flash-end" type="datetime-local" name="inf_settings[flash_end]" value="<?php echo esc_attr( $inf_val( 'flash_end' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Format : 2026-12-31T23:59:59. Par défaut : ce soir à minuit.', 'infinity-market' ); ?></p></td></tr>
				</table>
			</div>

			<div class="inf-panel">
				<h2 class="inf-panel__title"><?php esc_html_e( 'Produit mis en avant (page d\'accueil InfinityLanding)', 'infinity-market' ); ?></h2>
				<table class="form-table">
					<tr><th><label for="inf-product-name"><?php esc_html_e( 'Nom du produit', 'infinity-market' ); ?></label></th>
						<td><input id="inf-product-name" type="text" name="inf_settings[product_name]" value="<?php echo esc_attr( $inf_val( 'product_name' ) ); ?>" class="regular-text"></td></tr>
					<tr><th><label for="inf-product-price"><?php esc_html_e( 'Prix (DA)', 'infinity-market' ); ?></label></th>
						<td><input id="inf-product-price" type="number" min="0" name="inf_settings[product_price]" value="<?php echo esc_attr( $inf_val( 'product_price', 0 ) ); ?>" class="small-text">
						<label style="margin-left:12px"><?php esc_html_e( 'Ancien prix :', 'infinity-market' ); ?> <input type="number" min="0" name="inf_settings[product_old_price]" value="<?php echo esc_attr( $inf_val( 'product_old_price', 0 ) ); ?>" class="small-text"></label></td></tr>
					<tr><th><label for="inf-product-image"><?php esc_html_e( 'Image produit (URL)', 'infinity-market' ); ?></label></th>
						<td><input id="inf-product-image" type="url" name="inf_settings[product_image]" value="<?php echo esc_attr( $inf_val( 'product_image' ) ); ?>" class="large-text code"></td></tr>
				</table>
			</div>

			<div class="inf-panel">
				<h2 class="inf-panel__title"><?php esc_html_e( 'Apparence — couleurs, polices & mise en page', 'infinity-market' ); ?></h2>
				<p class="inf-muted"><?php esc_html_e( 'Conseil : ces réglages sont aussi disponibles avec aperçu en direct dans Personnaliser → ∞ Apparence.', 'infinity-market' ); ?></p>
				<table class="form-table">
					<?php
					$inf_select_field = function ( $id, $label, $key, $choices, $default ) use ( $inf_val ) {
						?>
						<tr><th><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><select id="<?php echo esc_attr( $id ); ?>" name="inf_settings[<?php echo esc_attr( $key ); ?>]">
							<?php foreach ( $choices as $inf_c_id => $inf_c_label ) : ?>
								<option value="<?php echo esc_attr( $inf_c_id ); ?>" <?php selected( $inf_val( $key, $default ), $inf_c_id ); ?>><?php echo esc_html( $inf_c_label ); ?></option>
							<?php endforeach; ?>
							</select></td></tr>
						<?php
					};

					$inf_palette_choices = array();
					foreach ( inf_palette_presets() as $inf_p_id => $inf_p ) {
						$inf_palette_choices[ $inf_p_id ] = $inf_p['label'];
					}
					$inf_palette_choices['custom'] = __( 'Personnalisée (couleur ci-dessous)', 'infinity-market' );

					$inf_font_choices = array();
					foreach ( inf_fonts() as $inf_f_id => $inf_f ) {
						$inf_font_choices[ $inf_f_id ] = $inf_f['label'];
					}

					$inf_select_field( 'inf-palette', __( 'Palette de couleurs', 'infinity-market' ), 'palette', $inf_palette_choices, 'blue' );
					?>
					<tr><th><label for="inf-accent"><?php esc_html_e( 'Couleur d\'accent personnalisée', 'infinity-market' ); ?></label></th>
						<td><input id="inf-accent" type="color" name="inf_settings[accent]" value="<?php echo esc_attr( $inf_val( 'accent' ) ? $inf_val( 'accent' ) : inf_default_accent() ); ?>">
						<p class="description"><?php esc_html_e( 'Prioritaire sur la palette si renseignée.', 'infinity-market' ); ?></p></td></tr>
					<?php
					$inf_select_field( 'inf-body-font', __( 'Police du texte', 'infinity-market' ), 'body_font', $inf_font_choices, 'system' );
					$inf_select_field( 'inf-heading-font', __( 'Police des titres', 'infinity-market' ), 'heading_font', $inf_font_choices, 'system' );
					$inf_select_field( 'inf-header-style', __( 'Style du menu', 'infinity-market' ), 'header_style', array( 'gradient' => __( 'Dégradé (défaut)', 'infinity-market' ), 'flat' => __( 'Uni', 'infinity-market' ), 'dark' => __( 'Bleu nuit', 'infinity-market' ) ), 'gradient' );
					$inf_select_field( 'inf-footer-style', __( 'Style du pied de page', 'infinity-market' ), 'footer_style', array( 'dark' => __( 'Sombre (défaut)', 'infinity-market' ), 'light' => __( 'Clair', 'infinity-market' ) ), 'dark' );
					$inf_select_field( 'inf-radius-style', __( 'Arrondi des blocs', 'infinity-market' ), 'radius_style', array( 'square' => __( 'Angles droits', 'infinity-market' ), 'soft' => __( 'Légèrement arrondi', 'infinity-market' ), 'round' => __( 'Arrondi (défaut)', 'infinity-market' ), 'pill' => __( 'Très arrondi', 'infinity-market' ) ), 'round' );
					?>
					<tr><th><label for="inf-font-size"><?php esc_html_e( 'Taille du texte (px)', 'infinity-market' ); ?></label></th>
						<td><input id="inf-font-size" type="number" min="14" max="20" name="inf_settings[font_size]" value="<?php echo esc_attr( $inf_val( 'font_size', 16 ) ); ?>" class="small-text"></td></tr>
					<tr><th><label for="inf-container"><?php esc_html_e( 'Largeur du site (px)', 'infinity-market' ); ?></label></th>
						<td><input id="inf-container" type="number" min="960" max="1440" step="10" name="inf_settings[container_width]" value="<?php echo esc_attr( $inf_val( 'container_width', 1200 ) ); ?>" class="small-text"></td></tr>
					<tr><th><label for="inf-bg-color"><?php esc_html_e( 'Couleur de fond du site', 'infinity-market' ); ?></label></th>
						<td><input id="inf-bg-color" type="color" name="inf_settings[bg_color]" value="<?php echo esc_attr( $inf_val( 'bg_color' ) ? $inf_val( 'bg_color' ) : '#f6f8fc' ); ?>"></td></tr>
					<tr><th><label for="inf-bg-image"><?php esc_html_e( 'Image de fond (URL)', 'infinity-market' ); ?></label></th>
						<td><input id="inf-bg-image" type="url" name="inf_settings[bg_image]" value="<?php echo esc_attr( $inf_val( 'bg_image' ) ); ?>" class="large-text code">
						<p class="description"><?php esc_html_e( 'Un voile clair est appliqué automatiquement pour garder le texte lisible.', 'infinity-market' ); ?></p></td></tr>
				</table>
			</div>

			<div class="inf-panel">
				<h2 class="inf-panel__title"><?php esc_html_e( 'Réseaux sociaux & pied de page', 'infinity-market' ); ?></h2>
				<table class="form-table">
					<tr><th><?php esc_html_e( 'WhatsApp flottant', 'infinity-market' ); ?></th>
						<td><label><input type="checkbox" name="inf_settings[wa_float]" value="1" <?php checked( $inf_val( 'wa_float', 1 ), 1 ); ?>> <?php esc_html_e( 'Afficher le bouton WhatsApp flottant sur le site', 'infinity-market' ); ?></label></td></tr>
					<tr><th><label for="inf-facebook"><?php esc_html_e( 'Facebook', 'infinity-market' ); ?></label></th>
						<td><input id="inf-facebook" type="text" name="inf_settings[facebook]" value="<?php echo esc_attr( $inf_val( 'facebook' ) ); ?>" class="regular-text"></td></tr>
					<tr><th><label for="inf-instagram"><?php esc_html_e( 'Instagram', 'infinity-market' ); ?></label></th>
						<td><input id="inf-instagram" type="text" name="inf_settings[instagram]" value="<?php echo esc_attr( $inf_val( 'instagram' ) ); ?>" class="regular-text"></td></tr>
					<tr><th><label for="inf-tiktok"><?php esc_html_e( 'TikTok', 'infinity-market' ); ?></label></th>
						<td><input id="inf-tiktok" type="text" name="inf_settings[tiktok]" value="<?php echo esc_attr( $inf_val( 'tiktok' ) ); ?>" class="regular-text"></td></tr>
					<tr><th><label for="inf-footer-note"><?php esc_html_e( 'Texte de copyright', 'infinity-market' ); ?></label></th>
						<td><input id="inf-footer-note" type="text" name="inf_settings[footer_note]" value="<?php echo esc_attr( $inf_val( 'footer_note' ) ); ?>" class="regular-text"></td></tr>
				</table>
			</div>

			<?php submit_button( __( 'Enregistrer les réglages', 'infinity-market' ) ); ?>
		</form>
	</div>
	<?php
}

/**
 * Frais de livraison des 58 wilayas.
 */
function inf_render_shipping() {
	$wilayas = inf_get_wilayas();
	?>
	<div class="wrap inf-wrap">
		<?php inf_admin_header( __( 'Livraison & Wilayas', 'infinity-market' ), __( 'Frais en DA par wilaya — domicile ou bureau (Stopdesk). Décochez les wilayas non desservies.', 'infinity-market' ) ); ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'inf_shipping_group' ); ?>
			<div class="inf-panel">
				<div class="inf-shipping-tools">
					<button type="button" class="button" id="inf-bulk-toggle"><?php esc_html_e( 'Tout activer / désactiver', 'infinity-market' ); ?></button>
					<span class="inf-bulk-label"><?php esc_html_e( 'Appliquer un tarif à toutes :', 'infinity-market' ); ?></span>
					<input type="number" id="inf-bulk-home" placeholder="<?php esc_attr_e( 'Domicile', 'infinity-market' ); ?>" class="small-text">
					<input type="number" id="inf-bulk-desk" placeholder="<?php esc_attr_e( 'Bureau', 'infinity-market' ); ?>" class="small-text">
					<button type="button" class="button" id="inf-bulk-apply"><?php esc_html_e( 'Appliquer', 'infinity-market' ); ?></button>
				</div>
				<table class="inf-table inf-table--shipping">
					<thead><tr>
						<th><?php esc_html_e( 'N°', 'infinity-market' ); ?></th>
						<th><?php esc_html_e( 'Wilaya', 'infinity-market' ); ?></th>
						<th><?php esc_html_e( 'Domicile (DA)', 'infinity-market' ); ?></th>
						<th><?php esc_html_e( 'Bureau (DA)', 'infinity-market' ); ?></th>
						<th><?php esc_html_e( 'Desservie', 'infinity-market' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $wilayas as $code => $w ) : ?>
						<tr>
							<td><?php echo esc_html( (int) $code ); ?></td>
							<td><?php echo esc_html( $w['fr'] ); ?> <span class="inf-muted" dir="rtl"><?php echo esc_html( $w['ar'] ); ?></span></td>
							<td><input type="number" min="0" class="small-text" name="inf_shipping[<?php echo esc_attr( $code ); ?>][home]" value="<?php echo esc_attr( $w['home'] ); ?>"></td>
							<td><input type="number" min="0" class="small-text" name="inf_shipping[<?php echo esc_attr( $code ); ?>][desk]" value="<?php echo esc_attr( $w['desk'] ); ?>"></td>
							<td><input type="checkbox" class="inf-wilaya-active" name="inf_shipping[<?php echo esc_attr( $code ); ?>][active]" value="1" <?php checked( $w['active'], 1 ); ?>></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php submit_button( __( 'Enregistrer les frais', 'infinity-market' ) ); ?>
		</form>
	</div>
	<?php
}

/**
 * Licence & mises à jour.
 */
function inf_render_license() {
	$s = get_option( 'inf_settings', array() );
	$inf_key   = isset( $s['license_key'] ) ? $s['license_key'] : '';
	$inf_state = get_option( 'inf_license_state', '' );
	?>
	<div class="wrap inf-wrap">
		<?php inf_admin_header( __( 'Licence & Mises à jour', 'infinity-market' ), sprintf( /* translators: %s : version */ __( 'Version installée : %s — les mises à jour sont vérifiées toutes les 12 heures.', 'infinity-market' ), INF_THEME_VERSION ) ); ?>

		<div class="inf-grid-2">
			<div class="inf-panel">
				<h2 class="inf-panel__title"><?php esc_html_e( 'Clé de licence', 'infinity-market' ); ?></h2>
				<form method="post" action="options.php">
					<?php settings_fields( 'inf_settings_group' ); ?>
					<input type="hidden" name="inf_settings[store_phone]" value="<?php echo esc_attr( inf_get_setting( 'store_phone' ) ); ?>">
					<input type="hidden" name="inf_settings[cod_enabled]" value="<?php echo esc_attr( inf_get_setting( 'cod_enabled', 1 ) ); ?>">
					<p><label for="inf-license-key" class="screen-reader-text"><?php esc_html_e( 'Clé de licence', 'infinity-market' ); ?></label>
					<input id="inf-license-key" type="text" name="inf_settings[license_key]" value="<?php echo esc_attr( $inf_key ); ?>" class="regular-text code" placeholder="XXXX-XXXX-XXXX-XXXX"></p>
					<?php if ( $inf_state ) : ?>
						<p class="inf-license-state inf-badge inf-badge--<?php echo esc_attr( 'active' === $inf_state ? 'livre' : 'nouveau' ); ?>"><?php echo esc_html( 'active' === $inf_state ? __( 'Licence active — merci !', 'infinity-market' ) : __( 'Licence non vérifiée.', 'infinity-market' ) ); ?></p>
					<?php endif; ?>
					<?php submit_button( __( 'Enregistrer la clé', 'infinity-market' ) ); ?>
				</form>
				<p>
					<button type="button" class="button button-secondary" id="inf-check-license"><?php esc_html_e( 'Vérifier la licence maintenant', 'infinity-market' ); ?></button>
					<span class="spinner" id="inf-license-spinner" style="float:none"></span>
				</p>
				<div id="inf-license-result" class="inf-muted"></div>
			</div>

			<div class="inf-panel">
				<h2 class="inf-panel__title"><?php esc_html_e( 'Mises à jour', 'infinity-market' ); ?></h2>
				<table class="inf-table">
					<tbody>
						<tr><th><?php esc_html_e( 'Version installée', 'infinity-market' ); ?></th><td><?php echo esc_html( INF_THEME_VERSION ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Canal de mises à jour', 'infinity-market' ); ?></th><td class="code" style="word-break:break-all"><?php echo esc_html( INF_UPDATES_API ); ?></td></tr>
						<tr><th><?php esc_html_e( 'API de licence', 'infinity-market' ); ?></th><td class="code" style="word-break:break-all"><?php echo esc_html( INF_LICENSE_API ); ?></td></tr>
					</tbody>
				</table>
				<p class="inf-muted"><?php esc_html_e( 'La clé de licence est transmise automatiquement au serveur de téléchargement (?license=…&site=…). Sans serveur de licence, les mises à jour du canal restent actives.', 'infinity-market' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( admin_url( 'update-core.php?checked=1' ) ); ?>"><?php esc_html_e( 'Rechercher les mises à jour', 'infinity-market' ); ?></a></p>
			</div>
		</div>
	</div>
	<?php
}

/**
 * À propos — carte auteur, produits et protection ∞ Infinity Coder.
 */
function inf_render_about() {
	$inf_links = array(
		__( 'Site web', 'infinity-market' )    => 'https://www.derouicheoussama.com',
		'GitHub'                             => 'https://github.com/derouicheoussama',
		'WordPress.org'                      => 'https://profiles.wordpress.org/derouicheoussama/',
		'Instagram'                          => 'https://www.instagram.com/derouiche.oussama/',
		'Facebook'                           => 'https://www.facebook.com/derouiche.oussama',
		'TikTok'                             => 'https://www.tiktok.com/@derouiche.oussama',
	);
	?>
	<div class="wrap inf-wrap">
		<?php inf_admin_header( __( 'À propos', 'infinity-market' ), sprintf( /* translators: 1 : nom du thème, 2 : version */ __( '%1$s v%2$s — une création ∞ Infinity Coder.', 'infinity-market' ), INF_THEME_NAME, INF_THEME_VERSION ) ); ?>

		<div class="inf-grid-2">
			<div class="inf-panel inf-panel--author">
				<div class="inf-author">
					<span class="inf-author__avatar" aria-hidden="true">DO</span>
					<div>
						<h2 class="inf-author__name">Derouiche Oussama</h2>
						<p class="inf-muted"><?php esc_html_e( 'Développeur WordPress — Algérie', 'infinity-market' ); ?></p>
					</div>
				</div>
				<div class="inf-author-links">
					<?php foreach ( $inf_links as $inf_label => $inf_url ) : ?>
						<a class="inf-author-links__item" href="<?php echo esc_url( $inf_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $inf_label ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="inf-panel">
				<h2 class="inf-panel__title"><?php esc_html_e( 'Protection du contenu', 'infinity-market' ); ?></h2>
				<div class="inf-dmca">
					<span class="inf-dmca__mark">∞</span>
					<div>
						<p><strong><?php esc_html_e( '∞ Infinity Coder', 'infinity-market' ); ?></strong> — © <?php echo esc_html( gmdate( 'Y' ) ); ?> Derouiche Oussama.</p>
						<p class="inf-muted"><?php esc_html_e( 'Ce thème et ses éléments graphiques sont protégés (DMCA). Toute revente ou redistribution non autorisée est interdite.', 'infinity-market' ); ?></p>
					</div>
				</div>
				<h2 class="inf-panel__title"><?php esc_html_e( 'Autres produits', 'infinity-market' ); ?></h2>
				<p><a href="https://github.com/derouicheoussama/loginfennec" target="_blank" rel="noopener">LoginFennec Pro</a> — <?php esc_html_e( 'personnalisation de la page de connexion + sécurité WordPress.', 'infinity-market' ); ?></p>
				<h2 class="inf-panel__title"><?php esc_html_e( 'Journal des versions', 'infinity-market' ); ?></h2>
				<p class="inf-muted"><strong>1.0.0</strong> — <?php esc_html_e( 'Lancement : dashboard bleu Infinity, moteur COD 58 wilayas, mises à jour automatiques, WooCommerce, RTL.', 'infinity-market' ); ?></p>
			</div>
		</div>
	</div>
	<?php
}

/* -----------------------------------------------------------------
 * Statistiques commandes
 * ----------------------------------------------------------------- */

/**
 * Compteurs de commandes COD.
 *
 * @return array{total:int,today:int,new:int}
 */
function inf_order_counts() {
	$inf = array( 'total' => 0, 'today' => 0, 'new' => 0 );

	$inf['total'] = (int) wp_count_posts( 'inf_cod_order' )->publish;

	$inf['today'] = count(
		get_posts(
			array(
				'post_type'      => 'inf_cod_order',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'date_query'     => array( array( 'after' => gmdate( 'Y-m-d 00:00:00' ) ) ),
			)
		)
	);

	$inf['new'] = (int) count(
		get_posts(
			array(
				'post_type'      => 'inf_cod_order',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_inf_status',
				'meta_value'     => 'nouveau',
			)
		)
	);

	return $inf;
}

/**
 * Chiffre d'affaires des commandes livrées.
 *
 * @return float
 */
function inf_order_revenue() {
	$inf_total = 0.0;
	foreach ( get_posts( array( 'post_type' => 'inf_cod_order', 'posts_per_page' => 500, 'fields' => 'ids', 'meta_key' => '_inf_status', 'meta_value' => 'livre' ) ) as $inf_id ) {
		$inf_total += (float) get_post_meta( $inf_id, '_inf_total', true );
	}
	return $inf_total;
}

/**
 * 5 dernières commandes (tableau de bord).
 *
 * @param int $count Nombre.
 * @return array[]
 */
function inf_recent_orders( $count = 5 ) {
	$inf_out = array();
	foreach ( get_posts( array( 'post_type' => 'inf_cod_order', 'posts_per_page' => $count ) ) as $inf_post ) {
		$inf_out[] = array(
			'id'     => $inf_post->ID,
			'ref'    => '#IC' . str_pad( (string) $inf_post->ID, 5, '0', STR_PAD_LEFT ),
			'name'   => get_the_title( $inf_post ),
			'phone'  => get_post_meta( $inf_post->ID, '_inf_phone', true ),
			'wilaya' => get_post_meta( $inf_post->ID, '_inf_wilaya_name', true ),
			'total'  => (float) get_post_meta( $inf_post->ID, '_inf_total', true ),
			'status' => get_post_meta( $inf_post->ID, '_inf_status', true ),
		);
	}
	return $inf_out;
}

/**
 * Slug de statut pour les badges CSS.
 *
 * @param string $status Statut.
 * @return string
 */
function inf_status_slug( $status ) {
	$map = array(
		'nouveau'  => 'nouveau',
		'confirmé' => 'confirme',
		'expédié'  => 'expedie',
		'livré'    => 'livre',
		'annulé'   => 'annule',
	);
	return isset( $map[ $status ] ) ? $map[ $status ] : 'nouveau';
}
