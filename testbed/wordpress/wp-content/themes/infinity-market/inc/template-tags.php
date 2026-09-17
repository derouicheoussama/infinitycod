<?php
/**
 * Helpers de gabarit — ∞ Infinity Coder
 *
 * @package infinity-market
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Réglages du thème (option unique inf_settings).
 *
 * @param string $key     Clé.
 * @param mixed  $default Valeur par défaut.
 * @return mixed
 */
function inf_get_setting( $key, $default = '' ) {
	$settings = get_option( 'inf_settings', array() );
	return ( isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * Formate un prix en dinars algériens : 1 500 DA.
 *
 * @param float|int $amount Montant.
 * @return string
 */
function inf_price( $amount ) {
	return number_format( (float) $amount, 0, ',', ' ' ) . ' ' . __( 'DA', 'infinity-market' );
}

/**
 * Icônes SVG inline.
 *
 * @param string $name Nom de l'icône.
 */
function inf_icon( $name ) {
	$icons = array(
		'cart'     => '<path d="M3 4h2l2.6 12.4a1 1 0 0 0 1 .6h9.7a1 1 0 0 0 1-.8L21 8H6"/><circle cx="10" cy="20" r="1.6"/><circle cx="17" cy="20" r="1.6"/>',
		'phone'    => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
		'truck'    => '<path d="M1 5h13v10H1zM14 9h4l3 3v3h-7z"/><circle cx="6" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>',
		'cash'     => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M5 9h.01M19 15h.01"/>',
		'shield'   => '<path d="M12 2l8 3v6c0 5-3.5 8.5-8 11-4.5-2.5-8-6-8-11V5z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
		'check'    => '<path d="M4 12.5l5 5L20 6.5"/>',
		'star'     => '<path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4 6.1 20.5l1.2-6.5L2.5 9.4l6.6-.9z"/>',
		'chevron'  => '<path d="M6 9l6 6 6-6"/>',
		'facebook' => '<path d="M15 3h-3a4 4 0 0 0-4 4v3H5v4h3v7h4v-7h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
		'instagram'=> '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1"/>',
		'tiktok'   => '<path d="M15 3c.5 3 2.5 5 6 5.3V12c-2.4 0-4.4-.8-6-2v6.5a6.5 6.5 0 1 1-6.5-6.5c.5 0 1 .1 1.5.2v3.7a3 3 0 1 0 2 2.8V3z"/>',
		'whatsapp' => '<path d="M12 3a9 9 0 0 0-7.8 13.5L3 21l4.6-1.2A9 9 0 1 0 12 3z"/><path d="M8.8 8.5c-.3 1.8 3 5.8 5.9 6.2l1-1.6-2-1.3-1 .8c-.8-.4-1.9-1.4-2.2-2.2l.9-.9-1.2-2z"/>',
		'box'      => '<path d="M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
		'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
		'pin'      => '<path d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
		'search'   => '<circle cx="11" cy="11" r="7"/><path d="M16.5 16.5L21 21"/>',
		'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
		'eye'      => '<path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
		'home'     => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
		'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
		'bolt'     => '<path d="M13 2L4 14h6l-1 8 9-12h-6z"/>',
	);
	$path = isset( $icons[ $name ] ) ? $icons[ $name ] : $icons['check'];
	printf(
		'<svg class="inf-icon inf-icon--%1$s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
		esc_attr( $name ),
		$path // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique interne.
	);
}

/**
 * Étoiles de notation (1 à 5).
 *
 * @param int $count Nombre d'étoiles.
 */
function inf_stars( $count = 5 ) {
	echo '<span class="inf-stars" aria-hidden="true">';
	for ( $i = 0; $i < (int) $count; $i++ ) {
		inf_icon( 'star' );
	}
	echo '</span>';
}

/**
 * Récupère des produits WooCommerce (ou articles récents en secours).
 *
 * @param array $args Arguments (limit, featured, on_sale).
 * @return array[] Chaque entrée : id, title, url, img, price, old_price, badge,
 *                 add_url, rating, reviews, in_stock, cat, is_new, is_hot.
 */
function inf_products( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'limit'    => 8,
			'featured' => false,
			'on_sale'  => false,
			'cat'      => '',
		)
	);

	$items = array();

	if ( class_exists( 'WooCommerce' ) ) {
		$query_args = array(
			'status'  => 'publish',
			'limit'   => $args['limit'],
			'orderby' => 'date',
			'order'   => 'DESC',
		);
		if ( $args['featured'] ) {
			$query_args['featured'] = true;
		}
		if ( $args['on_sale'] ) {
			$query_args['include'] = wc_get_product_ids_on_sale();
		}
		if ( $args['cat'] ) {
			$query_args['category'] = array( $args['cat'] );
		}
		$products = wc_get_products( $query_args );
		foreach ( $products as $product ) {
			$regular     = (float) $product->get_regular_price();
			$sale        = (float) $product->get_price();
			$inf_terms   = get_the_terms( $product->get_id(), 'product_cat' );
			$inf_created = $product->get_date_created();

			$items[] = array(
				'id'        => $product->get_id(),
				'title'     => $product->get_name(),
				'url'       => get_permalink( $product->get_id() ),
				'img'       => wp_get_attachment_image_url( $product->get_image_id(), 'inf-product' ),
				'price'     => $sale ? $sale : $regular,
				'old_price' => ( $sale && $regular > $sale ) ? $regular : 0,
				'badge'     => ( $sale && $regular > $sale ) ? '-' . round( ( ( $regular - $sale ) / $regular ) * 100 ) . '%' : '',
				'add_url'   => $product->add_to_cart_url(),
				'rating'    => (float) $product->get_average_rating(),
				'reviews'   => (int) $product->get_review_count(),
				'in_stock'  => $product->is_in_stock(),
				'cat'       => ( $inf_terms && ! is_wp_error( $inf_terms ) ) ? $inf_terms[0]->name : '',
				'is_new'    => ( $inf_created && ( time() - $inf_created->getTimestamp() ) < 14 * DAY_IN_SECONDS ),
				'is_hot'    => $product->is_featured(),
			);
		}
		return $items;
	}

	// Secours sans WooCommerce : articles récents.
	$posts = get_posts( array( 'numberposts' => $args['limit'] ) );
	foreach ( $posts as $post ) {
		$inf_terms = get_the_category( $post );
		$items[]   = array(
			'id'        => $post->ID,
			'title'     => get_the_title( $post ),
			'url'       => get_permalink( $post ),
			'img'       => get_the_post_thumbnail_url( $post, 'inf-product' ),
			'price'     => 0,
			'old_price' => 0,
			'badge'     => '',
			'add_url'   => '',
			'rating'    => 0,
			'reviews'   => 0,
			'in_stock'  => true,
			'cat'       => ( $inf_terms && ! is_wp_error( $inf_terms ) ) ? $inf_terms[0]->name : '',
			'is_new'    => true,
			'is_hot'    => false,
		);
	}
	return $items;
}

/**
 * Carte produit réutilisable (design v2 : badges, note, stock, actions au survol).
 *
 * @param array $item Entrée produite par inf_products().
 */
function inf_product_card( $item ) {
	?>
	<article class="inf-card">
		<div class="inf-card__media">
			<?php if ( $item['badge'] ) : ?>
				<span class="inf-card__badge inf-card__badge--sale"><?php echo esc_html( $item['badge'] ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $item['is_hot'] ) ) : ?>
				<span class="inf-card__badge inf-card__badge--hot"><?php esc_html_e( 'Top', 'infinity-market' ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $item['is_new'] ) && empty( $item['badge'] ) ) : ?>
				<span class="inf-card__badge inf-card__badge--new"><?php esc_html_e( 'Nouveau', 'infinity-market' ); ?></span>
			<?php endif; ?>

			<a class="inf-card__link" href="<?php echo esc_url( $item['url'] ); ?>" aria-label="<?php echo esc_attr( $item['title'] ); ?>">
				<?php if ( $item['img'] ) : ?>
					<img src="<?php echo esc_url( $item['img'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" loading="lazy">
				<?php else : ?>
					<span class="inf-card__placeholder"><?php inf_icon( 'box' ); ?></span>
				<?php endif; ?>
			</a>

			<?php if ( $item['add_url'] ) : ?>
				<div class="inf-card__actions">
					<a class="inf-card__action" href="<?php echo esc_url( $item['add_url'] ); ?>" title="<?php esc_attr_e( 'Ajouter au panier', 'infinity-market' ); ?>"><?php inf_icon( 'cart' ); ?></a>
					<a class="inf-card__action" href="<?php echo esc_url( $item['url'] ); ?>" title="<?php esc_attr_e( 'Voir le produit', 'infinity-market' ); ?>"><?php inf_icon( 'eye' ); ?></a>
				</div>
			<?php endif; ?>
		</div>

		<div class="inf-card__body">
			<?php if ( ! empty( $item['cat'] ) ) : ?>
				<span class="inf-card__cat"><?php echo esc_html( $item['cat'] ); ?></span>
			<?php endif; ?>
			<h3 class="inf-card__title"><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h3>

			<?php if ( $item['rating'] ) : ?>
				<div class="inf-card__rating">
					<?php inf_stars( (int) round( $item['rating'] ) ); ?>
					<span>(<?php echo esc_html( $item['reviews'] ); ?>)</span>
				</div>
			<?php endif; ?>

			<div class="inf-card__foot">
				<?php if ( $item['price'] ) : ?>
					<p class="inf-card__price">
						<span class="inf-price"><?php echo esc_html( inf_price( $item['price'] ) ); ?></span>
						<?php if ( $item['old_price'] ) : ?>
							<del class="inf-price--old"><?php echo esc_html( inf_price( $item['old_price'] ) ); ?></del>
						<?php endif; ?>
					</p>
					<span class="inf-card__stock <?php echo $item['in_stock'] ? 'is-in' : 'is-out'; ?>">
						<?php echo $item['in_stock'] ? esc_html__( 'En stock', 'infinity-market' ) : esc_html__( 'Rupture', 'infinity-market' ); ?>
					</span>
				<?php else : ?>
					<a class="inf-card__more" href="<?php echo esc_url( $item['url'] ); ?>"><?php esc_html_e( 'Lire la suite →', 'infinity-market' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</article>
	<?php
}

/**
 * Catégories de premier niveau (pour le mega menu et la recherche).
 *
 * @param int $limit Nombre max.
 * @return array[] id, name, slug, link, img, count, children[].
 */
function inf_top_categories( $limit = 12 ) {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return array();
	}

	$inf_terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'number'     => $limit,
			'parent'     => 0,
		)
	);
	if ( is_wp_error( $inf_terms ) ) {
		return array();
	}

	$inf_out = array();
	foreach ( $inf_terms as $inf_term ) {
		$inf_thumb_id = (int) get_term_meta( $inf_term->term_id, 'thumbnail_id', true );
		$inf_children = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 6,
				'parent'     => $inf_term->term_id,
			)
		);

		$inf_kids = array();
		if ( ! is_wp_error( $inf_children ) ) {
			foreach ( $inf_children as $inf_child ) {
				$inf_kids[] = array(
					'name' => $inf_child->name,
					'link' => get_term_link( $inf_child ),
				);
			}
		}

		$inf_out[] = array(
			'id'       => $inf_term->term_id,
			'name'     => $inf_term->name,
			'slug'     => $inf_term->slug,
			'link'     => get_term_link( $inf_term ),
			'img'      => $inf_thumb_id ? wp_get_attachment_image_url( $inf_thumb_id, 'inf-product' ) : '',
			'count'    => (int) $inf_term->count,
			'children' => $inf_kids,
		);
	}
	return $inf_out;
}

/**
 * Diapositives du slider d'accueil (réglages, avec repli sur le hero).
 *
 * @return array[] title, subtitle, image, url, btn.
 */
function inf_slider_slides() {
	$inf_slides = array();
	for ( $inf_n = 1; $inf_n <= 3; $inf_n++ ) {
		$inf_title = inf_get_setting( 'slide' . $inf_n . '_title' );
		$inf_image = inf_get_setting( 'slide' . $inf_n . '_image' );
		if ( ! $inf_title && ! $inf_image ) {
			continue;
		}
		$inf_slides[] = array(
			'title'    => $inf_title,
			'subtitle' => inf_get_setting( 'slide' . $inf_n . '_subtitle' ),
			'image'    => $inf_image,
			'url'      => inf_get_setting( 'slide' . $inf_n . '_url' ),
			'btn'      => inf_get_setting( 'slide' . $inf_n . '_btn' ),
		);
	}

	if ( empty( $inf_slides ) ) {
		$inf_slides[] = array(
			'title'    => inf_get_setting( 'hero_title', __( 'Votre boutique en ligne, livrée partout en Algérie', 'infinity-market' ) ),
			'subtitle' => inf_get_setting( 'hero_subtitle', __( 'Commandez sans carte bancaire : vous payez en espèces à la réception de votre colis, dans les 58 wilayas.', 'infinity-market' ) ),
			'image'    => inf_get_setting( 'hero_image' ),
			'url'      => inf_get_setting( 'hero_cta_url', '#inf-cod' ),
			'btn'      => __( 'Commander maintenant', 'infinity-market' ),
		);
	}
	return $inf_slides;
}

/**
 * Offre « vente flash » de la carte latérale (réglages, repli sur un produit en promo).
 *
 * @return array|false
 */
function inf_flash_deal() {
	$inf_title = inf_get_setting( 'flash_title' );
	if ( $inf_title ) {
		return array(
			'title'    => $inf_title,
			'subtitle' => inf_get_setting( 'flash_subtitle' ),
			'price'    => (float) inf_get_setting( 'flash_price', 0 ),
			'old_price'=> (float) inf_get_setting( 'flash_old', 0 ),
			'image'    => inf_get_setting( 'flash_image' ),
			'url'      => inf_get_setting( 'flash_url' ),
			'end'      => inf_get_setting( 'flash_end', gmdate( 'Y-m-d' ) . 'T23:59:59' ),
		);
	}

	if ( class_exists( 'WooCommerce' ) ) {
		$inf_ids = wc_get_product_ids_on_sale();
		if ( $inf_ids ) {
			$inf_product = wc_get_product( $inf_ids[0] );
			if ( $inf_product ) {
				$inf_regular = (float) $inf_product->get_regular_price();
				$inf_sale    = (float) $inf_product->get_price();
				return array(
					'title'    => $inf_product->get_name(),
					'subtitle' => __( 'Offre limitée', 'infinity-market' ),
					'price'    => $inf_sale ? $inf_sale : $inf_regular,
					'old_price'=> ( $inf_regular > $inf_sale ) ? $inf_regular : 0,
					'image'    => wp_get_attachment_image_url( $inf_product->get_image_id(), 'inf-product' ),
					'url'      => get_permalink( $inf_product->get_id() ),
					'end'      => gmdate( 'Y-m-d' ) . 'T23:59:59',
				);
			}
		}
	}
	return false;
}

/**
 * Fil d'Ariane simple.
 */
function inf_breadcrumb() {
	echo '<nav class="inf-breadcrumb" aria-label="' . esc_attr__( 'Fil d\'Ariane', 'infinity-market' ) . '">';
	echo '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Accueil', 'infinity-market' ) . '</a>';
	if ( is_singular() ) {
		echo ' <span>/</span> ';
		if ( is_single() && 'post' === get_post_type() ) {
			$cat = get_the_category();
			if ( $cat ) {
				echo '<a href="' . esc_url( get_category_link( $cat[0] ) ) . '">' . esc_html( $cat[0]->name ) . '</a> <span>/</span> ';
			}
		}
		echo '<span class="inf-breadcrumb__current">' . esc_html( wp_trim_words( get_the_title(), 8 ) ) . '</span>';
	} elseif ( is_archive() ) {
		echo ' <span>/</span> <span class="inf-breadcrumb__current">' . esc_html( get_the_archive_title() ) . '</span>';
	} elseif ( is_search() ) {
		echo ' <span>/</span> <span class="inf-breadcrumb__current">' . esc_html__( 'Recherche', 'infinity-market' ) . '</span>';
	}
	echo '</nav>';
}
