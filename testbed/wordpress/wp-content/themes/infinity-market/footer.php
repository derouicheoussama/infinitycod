<?php
/**
 * Pied de page (v2 pro) — ∞ Infinity Coder
 *
 * @package infinity-market
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$inf_has_widgets = is_active_sidebar( 'footer-1' ) || is_active_sidebar( 'footer-2' ) || is_active_sidebar( 'footer-3' );
$inf_wa          = inf_get_setting( 'whatsapp' );
?>
	</main><!-- #inf-content -->

	<footer class="inf-footer">
		<div class="inf-footer__widgets">
			<div class="inf-container inf-footer__grid">
				<?php if ( $inf_has_widgets ) : ?>
					<?php for ( $i = 1; $i <= 3; $i++ ) : ?>
						<?php if ( is_active_sidebar( 'footer-' . $i ) ) : ?>
							<div class="inf-footer__col"><?php dynamic_sidebar( 'footer-' . $i ); ?></div>
						<?php endif; ?>
					<?php endfor; ?>
				<?php else : ?>
					<div class="inf-footer__col">
						<a class="inf-logo inf-logo--footer" href="<?php echo esc_url( home_url( '/' ) ); ?>">
							<span class="inf-logo__mark" aria-hidden="true">∞</span>
							<span class="inf-logo__text"><span class="inf-logo__name"><?php bloginfo( 'name' ); ?></span></span>
						</a>
						<p class="inf-footer__desc"><?php bloginfo( 'description' ); ?></p>
						<div class="inf-socials">
							<?php
							$inf_socials = array(
								'facebook'  => inf_get_setting( 'facebook' ),
								'instagram' => inf_get_setting( 'instagram' ),
								'tiktok'    => inf_get_setting( 'tiktok' ),
								'whatsapp'  => $inf_wa,
							);
							foreach ( $inf_socials as $inf_key => $inf_url ) :
								if ( ! $inf_url ) {
									continue;
								}
								$inf_href = 'whatsapp' === $inf_key ? 'https://wa.me/' . preg_replace( '/\D/', '', $inf_url ) : $inf_url;
								?>
								<a class="inf-socials__link" href="<?php echo esc_url( $inf_href ); ?>" target="_blank" rel="noopener nofollow" aria-label="<?php echo esc_attr( ucfirst( $inf_key ) ); ?>"><?php inf_icon( $inf_key ); ?></a>
							<?php endforeach; ?>
						</div>
					</div>

					<?php $inf_footer_cats = inf_top_categories( 6 ); ?>
					<div class="inf-footer__col">
						<h4 class="inf-widget__title"><?php esc_html_e( 'Nos rayons', 'infinity-market' ); ?></h4>
						<?php if ( $inf_footer_cats ) : ?>
							<ul>
								<?php foreach ( $inf_footer_cats as $inf_c ) : ?>
									<li><a href="<?php echo esc_url( $inf_c['link'] ); ?>"><?php echo esc_html( $inf_c['name'] ); ?></a></li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<ul><?php wp_list_pages( array( 'title_li' => '', 'depth' => 1, 'echo' => 1 ) ); ?></ul>
						<?php endif; ?>
					</div>

					<div class="inf-footer__col">
						<h4 class="inf-widget__title"><?php esc_html_e( 'Informations', 'infinity-market' ); ?></h4>
						<ul>
							<li><a href="<?php echo esc_url( inf_get_setting( 'hero_cta_url', '#inf-cod' ) ); ?>"><?php esc_html_e( 'Commander — Paiement à la livraison', 'infinity-market' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Accueil', 'infinity-market' ); ?></a></li>
							<?php if ( class_exists( 'WooCommerce' ) ) : ?>
								<li><a href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php esc_html_e( 'Mon panier', 'infinity-market' ); ?></a></li>
								<li><a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><?php esc_html_e( 'Mon compte', 'infinity-market' ); ?></a></li>
							<?php endif; ?>
						</ul>
					</div>

					<div class="inf-footer__col">
						<h4 class="inf-widget__title"><?php esc_html_e( 'Contact', 'infinity-market' ); ?></h4>
						<ul class="inf-footer__contact">
							<?php $inf_fphone = inf_get_setting( 'store_phone' ); ?>
							<?php if ( $inf_fphone ) : ?>
								<li><?php inf_icon( 'phone' ); ?> <a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $inf_fphone ) ); ?>"><?php echo esc_html( $inf_fphone ); ?></a></li>
							<?php endif; ?>
							<?php if ( $inf_wa ) : ?>
								<li><?php inf_icon( 'whatsapp' ); ?> <a href="<?php echo esc_url( 'https://wa.me/' . preg_replace( '/\D/', '', $inf_wa ) ); ?>" target="_blank" rel="noopener nofollow">WhatsApp</a></li>
							<?php endif; ?>
							<?php $inf_faddr = inf_get_setting( 'address' ); ?>
							<?php if ( $inf_faddr ) : ?>
								<li><?php inf_icon( 'pin' ); ?> <?php echo esc_html( $inf_faddr ); ?></li>
							<?php endif; ?>
							<li><?php inf_icon( 'truck' ); ?> <?php esc_html_e( 'Livraison : 58 wilayas', 'infinity-market' ); ?></li>
						</ul>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="inf-footer__badges">
			<div class="inf-container inf-footer__badges-inner">
				<span><?php inf_icon( 'truck' ); ?> <?php esc_html_e( 'Livraison 58 wilayas — domicile ou bureau', 'infinity-market' ); ?></span>
				<span class="inf-pay"><?php inf_icon( 'cash' ); ?> <?php esc_html_e( 'Paiement à la livraison', 'infinity-market' ); ?></span>
				<span class="inf-pay">CIB</span>
				<span class="inf-pay">EDAHABIA</span>
				<span><?php inf_icon( 'shield' ); ?> <?php esc_html_e( 'Produits garantis', 'infinity-market' ); ?></span>
			</div>
		</div>

		<div class="inf-footer__bottom">
			<div class="inf-container inf-footer__bottom-inner">
				<p class="inf-copyright">
					&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <strong><?php bloginfo( 'name' ); ?></strong>
					<?php echo esc_html( inf_get_setting( 'footer_note' ) ); ?>
				</p>
				<p class="inf-credit">
					<?php esc_html_e( 'Conçu avec', 'infinity-market' ); ?> <span class="inf-credit__mark">∞</span>
					<a href="https://www.derouicheoussama.com" target="_blank" rel="noopener"><?php esc_html_e( 'Infinity Coder', 'infinity-market' ); ?></a>
				</p>
			</div>
		</div>
	</footer>

	<nav class="inf-toolbar" aria-label="<?php esc_attr_e( 'Navigation rapide', 'infinity-market' ); ?>">
		<a class="inf-toolbar__item" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php inf_icon( 'home' ); ?><span><?php esc_html_e( 'Accueil', 'infinity-market' ); ?></span></a>
		<button class="inf-toolbar__item" type="button" data-inf-nav aria-expanded="false"><?php inf_icon( 'grid' ); ?><span><?php esc_html_e( 'Rayons', 'infinity-market' ); ?></span></button>
		<button class="inf-toolbar__item" type="button" data-inf-search><?php inf_icon( 'search' ); ?><span><?php esc_html_e( 'Recherche', 'infinity-market' ); ?></span></button>
		<a class="inf-toolbar__item" href="<?php echo esc_url( class_exists( 'WooCommerce' ) ? wc_get_cart_url() : inf_get_setting( 'hero_cta_url', '#inf-cod' ) ); ?>">
			<?php inf_icon( 'cart' ); ?>
			<span><?php esc_html_e( 'Panier', 'infinity-market' ); ?></span>
			<span class="inf-cart-count"><?php echo esc_html( class_exists( 'WooCommerce' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0 ); ?></span>
		</a>
		<a class="inf-toolbar__item" href="<?php echo esc_url( $inf_wa ? 'https://wa.me/' . preg_replace( '/\D/', '', $inf_wa ) : ( inf_get_setting( 'store_phone' ) ? 'tel:' . preg_replace( '/\s+/', '', inf_get_setting( 'store_phone' ) ) : '#inf-cod' ) ); ?>" <?php echo $inf_wa ? 'target="_blank" rel="noopener nofollow"' : ''; ?>><?php inf_icon( $inf_wa ? 'whatsapp' : 'phone' ); ?><span><?php esc_html_e( 'Contact', 'infinity-market' ); ?></span></a>
	</nav>
</div><!-- #page -->

<?php $inf_wa_float = inf_get_setting( 'whatsapp' ); ?>
<?php if ( $inf_wa_float && inf_get_setting( 'wa_float', 1 ) ) : ?>
<a class="inf-float-wa" href="<?php echo esc_url( 'https://wa.me/' . preg_replace( '/\D/', '', $inf_wa_float ) ); ?>" target="_blank" rel="noopener nofollow" aria-label="<?php esc_attr_e( 'Discuter sur WhatsApp', 'infinity-market' ); ?>">
	<?php inf_icon( 'whatsapp' ); ?>
</a>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
