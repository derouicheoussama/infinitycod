<?php
/**
 * En-tête du site (v2 pro) — ∞ Infinity Coder
 *
 * @package infinity-market
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$inf_phone = inf_get_setting( 'store_phone' );
$inf_cats  = inf_top_categories( 12 );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'inf-body' ); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#inf-content"><?php esc_html_e( 'Aller au contenu', 'infinity-market' ); ?></a>

<div id="page" class="inf-site">

	<div class="inf-topbar">
		<div class="inf-container inf-topbar__inner">
			<span class="inf-topbar__badges">
				<span class="inf-topbar__badge"><?php inf_icon( 'truck' ); ?> <?php esc_html_e( 'Livraison 58 wilayas', 'infinity-market' ); ?></span>
				<span class="inf-topbar__badge"><?php inf_icon( 'cash' ); ?> <?php esc_html_e( 'Paiement à la livraison', 'infinity-market' ); ?></span>
			</span>
			<span class="inf-topbar__right">
				<?php if ( $inf_phone ) : ?>
					<a class="inf-topbar__phone" href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $inf_phone ) ); ?>"><?php inf_icon( 'phone' ); ?> <?php echo esc_html( $inf_phone ); ?></a>
				<?php endif; ?>
				<?php $inf_wa_top = inf_get_setting( 'whatsapp' ); ?>
				<?php if ( $inf_wa_top ) : ?>
					<a class="inf-topbar__phone" href="<?php echo esc_url( 'https://wa.me/' . preg_replace( '/\D/', '', $inf_wa_top ) ); ?>" target="_blank" rel="noopener nofollow"><?php inf_icon( 'whatsapp' ); ?> <?php esc_html_e( 'WhatsApp', 'infinity-market' ); ?></a>
				<?php endif; ?>
			</span>
		</div>
	</div>

	<header class="inf-header" id="inf-header">
		<div class="inf-container inf-header__inner">

			<button class="inf-burger" id="inf-burger" aria-expanded="false" aria-controls="inf-nav" aria-label="<?php esc_attr_e( 'Ouvrir le menu', 'infinity-market' ); ?>">
				<span></span><span></span><span></span>
			</button>

			<div class="inf-branding">
				<?php if ( has_custom_logo() ) : ?>
					<?php the_custom_logo(); ?>
				<?php else : ?>
					<a class="inf-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
						<span class="inf-logo__mark" aria-hidden="true">∞</span>
						<span class="inf-logo__text">
							<span class="inf-logo__name"><?php bloginfo( 'name' ); ?></span>
							<span class="inf-logo__desc"><?php bloginfo( 'description' ); ?></span>
						</span>
					</a>
				<?php endif; ?>
			</div>

			<div class="inf-search">
				<?php if ( class_exists( 'WooCommerce' ) ) : ?>
					<form role="search" method="get" class="inf-search__form" action="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
						<?php if ( $inf_cats ) : ?>
							<select class="inf-search__cat" name="product_cat" aria-label="<?php esc_attr_e( 'Catégorie', 'infinity-market' ); ?>">
								<option value=""><?php esc_html_e( 'Toutes les catégories', 'infinity-market' ); ?></option>
								<?php foreach ( $inf_cats as $inf_c ) : ?>
									<option value="<?php echo esc_attr( $inf_c['slug'] ); ?>"><?php echo esc_html( $inf_c['name'] ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>
						<label class="screen-reader-text" for="inf-s"><?php esc_html_e( 'Rechercher un produit…', 'infinity-market' ); ?></label>
						<input id="inf-s" type="search" name="s" placeholder="<?php esc_attr_e( 'Rechercher un produit…', 'infinity-market' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>">
						<input type="hidden" name="post_type" value="product">
						<button type="submit" aria-label="<?php esc_attr_e( 'Rechercher', 'infinity-market' ); ?>"><?php inf_icon( 'search' ); ?></button>
					</form>
				<?php else : ?>
					<?php get_search_form(); ?>
				<?php endif; ?>
			</div>

			<div class="inf-header__actions">
				<?php if ( class_exists( 'WooCommerce' ) ) : ?>
					<a class="inf-header__link" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
						<?php inf_icon( 'user' ); ?>
						<span class="inf-header__link-text"><?php esc_html_e( 'Mon compte', 'infinity-market' ); ?></span>
					</a>
					<a class="inf-header__link inf-cart-btn" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
						<?php inf_icon( 'cart' ); ?>
						<span class="inf-cart-count"><?php echo esc_html( WC()->cart ? WC()->cart->get_cart_contents_count() : 0 ); ?></span>
						<span class="inf-header__link-text">
							<small><?php esc_html_e( 'Panier', 'infinity-market' ); ?></small>
							<strong><?php echo wp_kses_post( WC()->cart ? WC()->cart->get_cart_subtotal() : '' ); ?></strong>
						</span>
					</a>
				<?php else : ?>
					<a class="inf-header__link inf-cart-btn" href="<?php echo esc_url( inf_get_setting( 'hero_cta_url', '#inf-cod' ) ); ?>">
						<?php inf_icon( 'cash' ); ?>
						<span class="inf-header__link-text"><?php esc_html_e( 'Commander — COD', 'infinity-market' ); ?></span>
					</a>
				<?php endif; ?>
			</div>

		</div>

		<nav class="inf-nav" id="inf-nav" aria-label="<?php esc_attr_e( 'Menu principal', 'infinity-market' ); ?>">
			<div class="inf-container inf-nav__inner">

				<?php if ( $inf_cats ) : ?>
					<div class="inf-mega" id="inf-mega">
						<button class="inf-mega__btn" aria-expanded="false" aria-controls="inf-mega-panel">
							<?php inf_icon( 'grid' ); ?> <?php esc_html_e( 'Tous les rayons', 'infinity-market' ); ?> <?php inf_icon( 'chevron' ); ?>
						</button>
						<div class="inf-mega__panel" id="inf-mega-panel">
							<ul class="inf-mega__list">
								<?php foreach ( $inf_cats as $inf_c ) : ?>
									<li>
										<a href="<?php echo esc_url( $inf_c['link'] ); ?>"><strong><?php echo esc_html( $inf_c['name'] ); ?></strong><small><?php echo esc_html( (int) $inf_c['count'] ); ?></small></a>
										<?php if ( $inf_c['children'] ) : ?>
											<ul>
												<?php foreach ( $inf_c['children'] as $inf_k ) : ?>
													<li><a href="<?php echo esc_url( $inf_k['link'] ); ?>"><?php echo esc_html( $inf_k['name'] ); ?></a></li>
												<?php endforeach; ?>
											</ul>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
				<?php endif; ?>

				<?php
				if ( has_nav_menu( 'primary' ) ) {
					wp_nav_menu(
						array(
							'theme_location' => 'primary',
							'container'      => false,
							'menu_class'     => 'inf-menu',
							'depth'          => 2,
						)
					);
				} else {
					echo '<ul class="inf-menu">';
					wp_list_pages( array( 'title_li' => '', 'depth' => 1 ) );
					echo '</ul>';
				}
				?>
				<?php $inf_cta = inf_get_setting( 'hero_cta_url', '#inf-cod' ); ?>
				<a class="inf-nav__cta" href="<?php echo esc_url( $inf_cta ); ?>"><?php inf_icon( 'bolt' ); ?> <?php esc_html_e( 'Commander — COD', 'infinity-market' ); ?></a>
			</div>
		</nav>
	</header>

	<main id="inf-content" class="inf-main">
