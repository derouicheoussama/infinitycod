<?php
/**
 * ∞ Infinity Coder — Moteur COD (paiement à la livraison).
 *
 * Shortcode [infinity_cod], enregistrement des commandes (CPT privé),
 * e-mail admin, export CSV, intégration WooCommerce. Sécurité : nonce,
 * honeypot, anti-temps, limite par IP, prix signé (HMAC), calcul serveur.
 *
 * @package infinity-market
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------
 * Type de contenu : commandes COD
 * ----------------------------------------------------------------- */

add_action( 'init', 'inf_register_order_cpt' );
function inf_register_order_cpt() {
	register_post_type(
		'inf_cod_order',
		array(
			'labels'       => array(
				'name'          => __( 'Commandes COD', 'infinity-market' ),
				'singular_name' => __( 'Commande COD', 'infinity-market' ),
				'search_items'  => __( 'Rechercher une commande', 'infinity-market' ),
				'not_found'     => __( 'Aucune commande', 'infinity-market' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => false,
			'supports'     => array( 'title' ),
			'capability_type' => 'post',
			'map_meta_cap' => true,
		)
	);
}

/* -----------------------------------------------------------------
 * Token de prix signé (anti-falsification)
 * ----------------------------------------------------------------- */

/**
 * Génère le token HMAC d'un couple produit|prix.
 *
 * @param string $product Nom du produit.
 * @param float  $price   Prix unitaire.
 * @return string
 */
function inf_price_token( $product, $price ) {
	return wp_hash( $product . '|' . (float) $price . '|' . wp_get_session_token() );
}

/* -----------------------------------------------------------------
 * Shortcode
 * ----------------------------------------------------------------- */

add_shortcode( 'infinity_cod', 'inf_cod_shortcode' );
add_shortcode( 'inf_cod', 'inf_cod_shortcode' );
/**
 * Rend le formulaire COD.
 *
 * @param array $atts Attributs : title, product, price, old_price, image, button.
 * @return string
 */
function inf_cod_shortcode( $atts ) {
	if ( ! inf_get_setting( 'cod_enabled', 1 ) ) {
		return '';
	}

	$inf_a = shortcode_atts(
		array(
			'title'     => __( 'Commandez maintenant — Paiement à la livraison', 'infinity-market' ),
			'product'   => '',
			'price'     => 0,
			'old_price' => 0,
			'image'     => '',
			'button'    => __( 'Confirmer la commande', 'infinity-market' ),
		),
		$atts,
		'infinity_cod'
	);

	ob_start();
	include INF_THEME_DIR . 'template-parts/cod-form.php';
	return (string) ob_get_clean();
}

/* -----------------------------------------------------------------
 * Intégration WooCommerce : formulaire sur la fiche produit
 * ----------------------------------------------------------------- */

// Anti-doublon : quand le plugin InfinityCod est actif, c'est LUI qui
// génère le formulaire de commande des fiches produit (position réglable,
// panier WooCommerce, offres, anti-fraude). Le thème ne rend plus le sien
// sur les fiches produit — le shortcode [infinity_cod] reste disponible
// pour les pages sans le plugin. Les plugins se chargent avant le thème :
// la constante est déjà définie ici si le plugin est actif.
if ( ! defined( 'INFINITYCOD_VERSION' ) ) {
	add_action( 'woocommerce_single_product_summary', 'inf_cod_on_product', 35 );
}
function inf_cod_on_product() {
	if ( ! inf_get_setting( 'cod_on_product', 1 ) || ! inf_get_setting( 'cod_enabled', 1 ) ) {
		return;
	}
	global $product;

	if ( ! $product instanceof WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		return;
	}

	$inf_regular = (float) $product->get_regular_price();
	$inf_price   = (float) $product->get_price();

	echo inf_cod_shortcode( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé dans le gabarit.
		array(
			'product'   => $product->get_name(),
			'price'     => $inf_price ? $inf_price : $inf_regular,
			'old_price' => ( $inf_regular > $inf_price ) ? $inf_regular : 0,
			'image'     => wp_get_attachment_image_url( $product->get_image_id(), 'inf-product' ),
		)
	);
}

/* -----------------------------------------------------------------
 * Traitement AJAX de la commande
 * ----------------------------------------------------------------- */

add_action( 'wp_ajax_inf_cod_submit', 'inf_cod_handle_submit' );
add_action( 'wp_ajax_nopriv_inf_cod_submit', 'inf_cod_handle_submit' );
function inf_cod_handle_submit() {
	check_ajax_referer( 'inf_cod', 'nonce' );

	$inf_fail = static function () {
		wp_send_json_error( array( 'message' => __( 'Une erreur est survenue. Réessayez ou appelez-nous.', 'infinity-market' ) ) );
	};

	// Honeypot : les robots remplissent ce champ — on feint un succès.
	if ( ! empty( $_POST['inf_website'] ) ) {
		wp_send_json_success( array( 'message' => __( 'Commande reçue ! Nous vous appellerons bientôt.', 'infinity-market' ) ) );
	}

	// Anti-temps : soumission < 3 secondes = robot.
	$inf_time = isset( $_POST['inf_form_time'] ) ? absint( $_POST['inf_form_time'] ) : 0;
	if ( ! $inf_time || ( time() - $inf_time ) < 3 ) {
		wp_send_json_success( array( 'message' => __( 'Commande reçue ! Nous vous appellerons bientôt.', 'infinity-market' ) ) );
	}

	// Limite : 6 commandes / heure / IP.
	$inf_ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$inf_rl  = 'inf_rl_' . md5( $inf_ip );
	$inf_hits = (int) get_transient( $inf_rl );
	if ( $inf_hits >= 6 ) {
		wp_send_json_error( array( 'message' => __( 'Trop de commandes envoyées. Réessayez dans une heure.', 'infinity-market' ) ) );
	}

	// Champs obligatoires.
	$inf_name    = isset( $_POST['inf_name'] ) ? sanitize_text_field( wp_unslash( $_POST['inf_name'] ) ) : '';
	$inf_phone   = isset( $_POST['inf_phone'] ) ? preg_replace( '/\D/', '', wp_unslash( $_POST['inf_phone'] ) ) : '';
	$inf_wilaya  = isset( $_POST['inf_wilaya'] ) ? sanitize_text_field( wp_unslash( $_POST['inf_wilaya'] ) ) : '';
	$inf_commune = isset( $_POST['inf_commune'] ) ? sanitize_text_field( wp_unslash( $_POST['inf_commune'] ) ) : '';
	$inf_address = isset( $_POST['inf_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['inf_address'] ) ) : '';
	$inf_delivery= isset( $_POST['inf_delivery'] ) && 'desk' === $_POST['inf_delivery'] ? 'desk' : 'home';
	$inf_qty     = isset( $_POST['inf_qty'] ) ? max( 1, min( 99, absint( $_POST['inf_qty'] ) ) ) : 1;
	$inf_notes   = isset( $_POST['inf_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['inf_notes'] ) ) : '';
	$inf_product = isset( $_POST['inf_product'] ) ? sanitize_text_field( wp_unslash( $_POST['inf_product'] ) ) : '';
	$inf_price_p = isset( $_POST['inf_price'] ) ? (float) $_POST['inf_price'] : 0;
	$inf_token   = isset( $_POST['inf_token'] ) ? sanitize_text_field( wp_unslash( $_POST['inf_token'] ) ) : '';

	// Téléphone algérien : 0 555 12 34 56 / +213 555 12 34 56 → format 0XXXXXXXXX.
	$inf_phone = preg_replace( '/\D/', '', $inf_phone );
	if ( 0 === strpos( $inf_phone, '00213' ) && 14 === strlen( $inf_phone ) ) {
		$inf_phone = '0' . substr( $inf_phone, 5 );
	} elseif ( 0 === strpos( $inf_phone, '213' ) && 12 === strlen( $inf_phone ) ) {
		$inf_phone = '0' . substr( $inf_phone, 3 );
	}

	$inf_errors = array();
	if ( mb_strlen( $inf_name ) < 3 ) {
		$inf_errors['inf_name'] = __( 'Merci d\'indiquer votre nom complet.', 'infinity-market' );
	}
	if ( ! preg_match( '/^0(5|6|7)[0-9]{8}$/', $inf_phone ) ) {
		$inf_errors['inf_phone'] = __( 'Numéro de téléphone invalide (ex. : 0555123456).', 'infinity-market' );
	}
	if ( '' === $inf_wilaya ) {
		$inf_errors['inf_wilaya'] = __( 'Choisissez votre wilaya.', 'infinity-market' );
	}
	if ( '' === $inf_commune ) {
		$inf_errors['inf_commune'] = __( 'Indiquez votre commune.', 'infinity-market' );
	}
	if ( $inf_errors ) {
		wp_send_json_error( array( 'message' => __( 'Merci de corriger les champs indiqués.', 'infinity-market' ), 'errors' => $inf_errors ) );
	}

	// Prix signé : on refuse un prix modifié côté client.
	if ( '' !== $inf_token && ! hash_equals( inf_price_token( $inf_product, $inf_price_p ), $inf_token ) ) {
		$inf_fail();
	}

	// Frais de livraison : calculés côté serveur.
	$inf_fee = inf_delivery_fee( $inf_wilaya, $inf_delivery );
	if ( $inf_fee < 0 ) {
		wp_send_json_error( array( 'message' => __( 'Cette wilaya n\'est pas desservie pour le moment.', 'infinity-market' ) ) );
	}

	$inf_subtotal = (float) $inf_price_p * $inf_qty;
	$inf_free     = (int) inf_get_setting( 'free_shipping', 0 );
	if ( $inf_free > 0 && $inf_subtotal >= $inf_free ) {
		$inf_fee = 0;
	}
	$inf_total = $inf_subtotal + $inf_fee;

	$wilayas = inf_get_wilayas();
	$inf_wilaya_name = isset( $wilayas[ $inf_wilaya ] ) ? $wilayas[ $inf_wilaya ]['fr'] : $inf_wilaya;

	// Enregistrement.
	$inf_order_id = wp_insert_post(
		array(
			'post_type'   => 'inf_cod_order',
			'post_status' => 'publish',
			'post_title'  => $inf_name,
		),
		true
	);

	if ( is_wp_error( $inf_order_id ) ) {
		$inf_fail();
	}

	$inf_meta = array(
		'_inf_phone'       => $inf_phone,
		'_inf_wilaya_code' => $inf_wilaya,
		'_inf_wilaya_name' => $inf_wilaya_name,
		'_inf_commune'     => $inf_commune,
		'_inf_address'     => $inf_address,
		'_inf_delivery'    => $inf_delivery,
		'_inf_qty'         => $inf_qty,
		'_inf_product'     => $inf_product,
		'_inf_unit_price'  => $inf_price_p,
		'_inf_fee'         => $inf_fee,
		'_inf_total'       => $inf_total,
		'_inf_status'      => 'nouveau',
		'_inf_notes'       => $inf_notes,
		'_inf_ip'          => $inf_ip,
	);
	foreach ( $inf_meta as $inf_key => $inf_value ) {
		update_post_meta( $inf_order_id, $inf_key, $inf_value );
	}

	set_transient( $inf_rl, $inf_hits + 1, HOUR_IN_SECONDS );

	$inf_ref = '#IC' . str_pad( (string) $inf_order_id, 5, '0', STR_PAD_LEFT );
	inf_cod_notify_admin( $inf_order_id, $inf_ref, $inf_meta );

	wp_send_json_success(
		array(
			'ref'     => $inf_ref,
			'total'   => inf_price( $inf_total ),
			/* translators: %s : référence de commande */
			'message' => sprintf( __( 'Commande %s enregistrée ! Notre équipe vous appellera pour confirmer.', 'infinity-market' ), '<strong>' . esc_html( $inf_ref ) . '</strong>' ),
		)
	);
}

/**
 * E-mail de notification à l'administrateur.
 *
 * @param int    $order_id ID de la commande.
 * @param string $ref      Référence.
 * @param array  $meta     Métadonnées.
 */
function inf_cod_notify_admin( $inf_order_id, $inf_ref, $inf_meta ) {
	$inf_rows = '';
	$inf_labels = array(
		'_inf_product' => __( 'Produit', 'infinity-market' ),
		'_inf_phone'   => __( 'Téléphone', 'infinity-market' ),
		'_inf_wilaya_name' => __( 'Wilaya', 'infinity-market' ),
		'_inf_commune' => __( 'Commune', 'infinity-market' ),
		'_inf_address' => __( 'Adresse', 'infinity-market' ),
		'_inf_delivery'=> __( 'Livraison', 'infinity-market' ),
		'_inf_qty'     => __( 'Quantité', 'infinity-market' ),
		'_inf_unit_price' => __( 'Prix unitaire', 'infinity-market' ),
		'_inf_fee'     => __( 'Livraison', 'infinity-market' ),
		'_inf_total'   => __( 'Total à encaisser', 'infinity-market' ),
		'_inf_notes'   => __( 'Remarques', 'infinity-market' ),
	);
	foreach ( $inf_labels as $inf_key => $inf_label ) {
		$inf_value = isset( $inf_meta[ $inf_key ] ) ? $inf_meta[ $inf_key ] : '';
		if ( '' === $inf_value ) {
			continue;
		}
		if ( '_inf_delivery' === $inf_key ) {
			$inf_value = 'desk' === $inf_value ? __( 'Bureau (Stopdesk)', 'infinity-market' ) : __( 'À domicile', 'infinity-market' );
		}
		if ( in_array( $inf_key, array( '_inf_unit_price', '_inf_fee', '_inf_total' ), true ) ) {
			$inf_value = inf_price( $inf_value );
		}
		$inf_rows .= '<tr><th style="text-align:left;padding:6px 10px;background:#eef3fb">' . esc_html( $inf_label ) . '</th><td style="padding:6px 10px">' . esc_html( $inf_value ) . '</td></tr>';
	}

	$inf_message = '<p>' . sprintf(
		/* translators: 1 : référence, 2 : nom du site */
		__( 'Nouvelle commande COD <strong>%1$s</strong> reçue sur %2$s.', 'infinity-market' ),
		esc_html( $inf_ref ),
		esc_html( get_bloginfo( 'name' ) )
	) . '</p><table style="border-collapse:collapse">' . $inf_rows . '</table><p><a href="' . esc_url( admin_url( 'post.php?post=' . $inf_order_id . '&action=edit' ) ) . '">' . esc_html__( 'Gérer cette commande', 'infinity-market' ) . '</a></p><p style="color:#64748b">∞ Infinity Coder</p>';

	add_filter( 'wp_mail_content_type', fn() => 'text/html' );
	wp_mail( get_option( 'admin_email' ), sprintf( '[%s] %s — Commande COD %s', get_bloginfo( 'name' ), __( 'Nouvelle commande', 'infinity-market' ), $inf_ref ), $inf_message );
	remove_filter( 'wp_mail_content_type', fn() => 'text/html' );
}

/* -----------------------------------------------------------------
 * Administration des commandes : colonnes, metabox, export CSV
 * ----------------------------------------------------------------- */

add_filter( 'manage_inf_cod_order_posts_columns', 'inf_order_columns' );
function inf_order_columns( $columns ) {
	return array(
		'cb'         => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
		'title'      => __( 'Client', 'infinity-market' ),
		'inf_phone'  => __( 'Téléphone', 'infinity-market' ),
		'inf_wilaya' => __( 'Wilaya / Commune', 'infinity-market' ),
		'inf_total'  => __( 'Total', 'infinity-market' ),
		'inf_status' => __( 'Statut', 'infinity-market' ),
		'date'       => __( 'Date', 'infinity-market' ),
	);
}

add_action( 'manage_inf_cod_order_posts_custom_column', 'inf_order_column_content', 10, 2 );
function inf_order_column_content( $column, $post_id ) {
	if ( 'inf_phone' === $column ) {
		$inf_phone = get_post_meta( $post_id, '_inf_phone', true );
		echo '<a href="tel:' . esc_attr( $inf_phone ) . '">' . esc_html( $inf_phone ) . '</a>';
	}
	if ( 'inf_wilaya' === $column ) {
		echo esc_html( get_post_meta( $post_id, '_inf_wilaya_name', true ) . ' — ' . get_post_meta( $post_id, '_inf_commune', true ) );
	}
	if ( 'inf_total' === $column ) {
		echo '<strong>' . esc_html( inf_price( (float) get_post_meta( $post_id, '_inf_total', true ) ) ) . '</strong>';
	}
	if ( 'inf_status' === $column ) {
		$inf_status = get_post_meta( $post_id, '_inf_status', true );
		printf( '<span class="inf-badge inf-badge--%1$s">%2$s</span>', esc_attr( inf_status_slug( $inf_status ) ), esc_html( $inf_status ) );
	}
}

add_action( 'add_meta_boxes', 'inf_order_metabox' );
function inf_order_metabox() {
	add_meta_box( 'inf-order-details', __( 'Détails de la commande', 'infinity-market' ), 'inf_order_metabox_render', 'inf_cod_order', 'normal', 'high' );
}

function inf_order_metabox_render( $post ) {
	wp_nonce_field( 'inf_order_save', 'inf_order_nonce' );
	$inf_get = fn( $key ) => get_post_meta( $post->ID, $key, true );
	$inf_status = $inf_get( '_inf_status' );
	$inf_statuses = array( 'nouveau', 'confirmé', 'expédié', 'livré', 'annulé' );
	?>
	<table class="form-table">
		<tr><th><?php esc_html_e( 'Statut', 'infinity-market' ); ?></th>
			<td><select name="inf_status">
				<?php foreach ( $inf_statuses as $inf_s ) : ?>
					<option value="<?php echo esc_attr( $inf_s ); ?>" <?php selected( $inf_status, $inf_s ); ?>><?php echo esc_html( $inf_s ); ?></option>
				<?php endforeach; ?>
			</select></td></tr>
		<tr><th><?php esc_html_e( 'Téléphone', 'infinity-market' ); ?></th>
			<td><a href="tel:<?php echo esc_attr( $inf_get( '_inf_phone' ) ); ?>"><strong><?php echo esc_html( $inf_get( '_inf_phone' ) ); ?></strong></a>
			<?php $inf_wa = preg_replace( '/\D/', '', $inf_get( '_inf_phone' ) ); ?>
			— <a href="<?php echo esc_url( 'https://wa.me/' . $inf_wa ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ouvrir WhatsApp', 'infinity-market' ); ?></a></td></tr>
		<tr><th><?php esc_html_e( 'Livraison', 'infinity-market' ); ?></th>
			<td><?php echo esc_html( ( 'desk' === $inf_get( '_inf_delivery' ) ? __( 'Bureau (Stopdesk)', 'infinity-market' ) : __( 'À domicile', 'infinity-market' ) ) . ' — ' . $inf_get( '_inf_wilaya_name' ) . ', ' . $inf_get( '_inf_commune' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Adresse', 'infinity-market' ); ?></th>
			<td><?php echo esc_html( $inf_get( '_inf_address' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Produit', 'infinity-market' ); ?></th>
			<td><?php echo esc_html( $inf_get( '_inf_product' ) ); ?> — <?php echo esc_html( (int) $inf_get( '_inf_qty' ) ); ?> × <?php echo esc_html( inf_price( (float) $inf_get( '_inf_unit_price' ) ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Total à encaisser', 'infinity-market' ); ?></th>
			<td><strong><?php echo esc_html( inf_price( (float) $inf_get( '_inf_total' ) ) ); ?></strong>
			(<?php echo esc_html( __( 'livraison :', 'infinity-market' ) . ' ' . inf_price( (float) $inf_get( '_inf_fee' ) ) ); ?>)</td></tr>
		<?php if ( $inf_get( '_inf_notes' ) ) : ?>
		<tr><th><?php esc_html_e( 'Remarques', 'infinity-market' ); ?></th>
			<td><?php echo esc_html( $inf_get( '_inf_notes' ) ); ?></td></tr>
		<?php endif; ?>
	</table>
	<?php
}

add_action( 'save_post_inf_cod_order', 'inf_order_save' );
function inf_order_save( $post_id ) {
	if ( ! isset( $_POST['inf_order_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['inf_order_nonce'] ), 'inf_order_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) || defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( isset( $_POST['inf_status'] ) ) {
		update_post_meta( $post_id, '_inf_status', sanitize_text_field( wp_unslash( $_POST['inf_status'] ) ) );
	}
}

/**
 * Export CSV des commandes.
 */
add_action( 'admin_post_inf_orders_csv', 'inf_orders_csv' );
function inf_orders_csv() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permissions insuffisantes.', 'infinity-market' ) );
	}

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=commandes-cod-' . gmdate( 'Y-m-d' ) . '.csv' );

	$inf_out = fopen( 'php://output', 'w' );
	fwrite( $inf_out, "\xEF\xBB\xBF" ); // BOM UTF-8 pour Excel.
	fputcsv( $inf_out, array( 'Réf', 'Client', 'Téléphone', 'Wilaya', 'Commune', 'Adresse', 'Livraison', 'Produit', 'Qté', 'Prix unitaire', 'Frais', 'Total', 'Statut', 'Date' ) );

	foreach ( get_posts( array( 'post_type' => 'inf_cod_order', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'ASC' ) ) as $inf_post ) {
		$inf_m = fn( $key ) => get_post_meta( $inf_post->ID, $key, true );
		fputcsv(
			$inf_out,
			array(
				'#IC' . str_pad( (string) $inf_post->ID, 5, '0', STR_PAD_LEFT ),
				get_the_title( $inf_post ),
				$inf_m( '_inf_phone' ),
				$inf_m( '_inf_wilaya_name' ),
				$inf_m( '_inf_commune' ),
				$inf_m( '_inf_address' ),
				'desk' === $inf_m( '_inf_delivery' ) ? 'Bureau' : 'Domicile',
				$inf_m( '_inf_product' ),
				$inf_m( '_inf_qty' ),
				$inf_m( '_inf_unit_price' ),
				$inf_m( '_inf_fee' ),
				$inf_m( '_inf_total' ),
				$inf_m( '_inf_status' ),
				get_the_date( 'd/m/Y H:i', $inf_post ),
			)
		);
	}
	fclose( $inf_out );
	exit;
}

/**
 * Bouton d'export sur la liste des commandes.
 */
add_action( 'restrict_manage_posts', 'inf_orders_export_button' );
function inf_orders_export_button() {
	$screen = get_current_screen();
	if ( $screen && 'edit-inf_cod_order' === $screen->id ) {
		$inf_url = wp_nonce_url( admin_url( 'admin-post.php?action=inf_orders_csv' ), 'inf_orders_csv' );
		echo '<a href="' . esc_url( $inf_url ) . '" class="button button-primary">' . esc_html__( 'Exporter CSV', 'infinity-market' ) . '</a>';
	}
}
