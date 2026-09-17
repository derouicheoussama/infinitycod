<?php
/**
 * Page d'accueil marketplace — ∞ Infinity Coder
 *
 * @package infinity-market
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$inf_hero_title    = inf_get_setting( 'hero_title', __( 'Tout ce qu\'il vous faut, livré partout en Algérie', 'infinity-market' ) );
$inf_hero_subtitle = inf_get_setting( 'hero_subtitle', __( 'Électroménager, maison, high-tech et plus encore. Vous commandez, vous payez à la livraison — dans les 58 wilayas.', 'infinity-market' ) );
$inf_hero_image    = inf_get_setting( 'hero_image' );

$inf_deal_deadline = gmdate( 'Y-m-d' ) . 'T23:59:59';
$inf_products      = inf_products( array( 'limit' => 12 ) );
?>

<div class="inf-marquee" aria-hidden="true">
	<div class="inf-marquee__track">
		<?php for ( $inf_i = 0; $inf_i < 2; $inf_i++ ) : ?>
			<span>🚚 <?php esc_html_e( 'Livraison 58 wilayas', 'infinity-market' ); ?></span>
			<span>💵 <?php esc_html_e( 'Paiement à la livraison', 'infinity-market' ); ?></span>
			<span>🔥 <?php esc_html_e( 'Nouvelles promos chaque semaine', 'infinity-market' ); ?></span>
			<span>🛡️ <?php esc_html_e( 'Produits garantis', 'infinity-market' ); ?></span>
			<span>📞 <?php esc_html_e( 'Service client 7j/7', 'infinity-market' ); ?></span>
		<?php endfor; ?>
	</div>
</div>

<section class="inf-hero">
	<div class="inf-container inf-hero__inner">
		<div>
			<span class="inf-hero__eyebrow"><?php inf_icon( 'box' ); ?> <?php esc_html_e( 'Le grand marché en ligne algérien', 'infinity-market' ); ?></span>
			<h1 class="inf-hero__title"><?php echo esc_html( $inf_hero_title ); ?></h1>
			<p class="inf-hero__subtitle"><?php echo esc_html( $inf_hero_subtitle ); ?></p>
			<div class="inf-hero__actions">
				<a class="inf-btn inf-btn--primary inf-hero__btn--light" href="#inf-deal"><?php esc_html_e( 'Deal du jour', 'infinity-market' ); ?></a>
				<a class="inf-btn inf-hero__btn--light" href="#inf-cod"><?php inf_icon( 'cash' ); ?> <?php esc_html_e( 'Commander — COD', 'infinity-market' ); ?></a>
			</div>
		</div>
		<div class="inf-hero__media">
			<?php if ( $inf_hero_image ) : ?>
				<img src="<?php echo esc_url( $inf_hero_image ); ?>" alt="<?php echo esc_attr( $inf_hero_title ); ?>" loading="eager">
			<?php else : ?>
				<div class="inf-hero__media-ph" aria-hidden="true">∞</div>
			<?php endif; ?>
		</div>
	</div>
</section>

<section class="inf-section">
	<div class="inf-container">
		<div class="inf-section__head">
			<h2 class="inf-section__title"><?php esc_html_e( 'Nos rayons', 'infinity-market' ); ?></h2>
		</div>
		<div class="inf-rayons">
			<?php
			$inf_rayons = array(
				'Électroménager' => 'box',
				'Smartphones'    => 'phone',
				'Maison & Déco'  => 'pin',
				'Beauté'         => 'star',
				'Informatique'   => 'search',
				'Enfants'        => 'check',
			);
			$inf_terms = class_exists( 'WooCommerce' ) ? get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'number' => 12, 'parent' => 0 ) ) : array();
			if ( ! is_wp_error( $inf_terms ) && $inf_terms ) :
				$inf_n = 0;
				foreach ( $inf_terms as $inf_term ) :
					$inf_icons = array_values( $inf_rayons );
					?>
					<a class="inf-rayon" href="<?php echo esc_url( get_term_link( $inf_term ) ); ?>">
						<span class="inf-rayon__icon"><?php inf_icon( $inf_icons[ $inf_n % count( $inf_icons ) ] ); ?></span>
						<?php echo esc_html( $inf_term->name ); ?>
					</a>
					<?php
					$inf_n++;
				endforeach;
			else :
				foreach ( $inf_rayons as $inf_label => $inf_icon_name ) :
					?>
					<a class="inf-rayon" href="<?php echo esc_url( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>">
						<span class="inf-rayon__icon"><?php inf_icon( $inf_icon_name ); ?></span>
						<?php echo esc_html( $inf_label ); ?>
					</a>
				<?php endforeach;
			endif;
			?>
		</div>
	</div>
</section>

<section class="inf-section" id="inf-deal">
	<div class="inf-container">
		<div class="inf-deal" data-countdown="<?php echo esc_attr( $inf_deal_deadline ); ?>">
			<div>
				<p class="inf-deal__eyebrow"><?php esc_html_e( 'Offre limitée', 'infinity-market' ); ?></p>
				<h2><?php esc_html_e( 'Deal du jour — jusqu\'à -50 %', 'infinity-market' ); ?></h2>
				<p style="margin:0;opacity:.92"><?php esc_html_e( 'L\'offre se termine ce soir à minuit. Payez à la livraison, sans carte bancaire.', 'infinity-market' ); ?></p>
			</div>
			<div class="inf-deal__countdown" role="timer" aria-label="<?php esc_attr_e( 'Temps restant', 'infinity-market' ); ?>">
				<div class="inf-deal__cell"><strong data-cd="d">00</strong><span><?php esc_html_e( 'Jours', 'infinity-market' ); ?></span></div>
				<div class="inf-deal__cell"><strong data-cd="h">00</strong><span><?php esc_html_e( 'Heures', 'infinity-market' ); ?></span></div>
				<div class="inf-deal__cell"><strong data-cd="m">00</strong><span><?php esc_html_e( 'Min', 'infinity-market' ); ?></span></div>
				<div class="inf-deal__cell"><strong data-cd="s">00</strong><span><?php esc_html_e( 'Sec', 'infinity-market' ); ?></span></div>
			</div>
		</div>
	</div>
</section>

<section class="inf-section">
	<div class="inf-container">
		<div class="inf-section__head">
			<h2 class="inf-section__title"><?php esc_html_e( 'Meilleures ventes', 'infinity-market' ); ?></h2>
			<a class="inf-section__more" href="<?php echo esc_url( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>"><?php esc_html_e( 'Tout voir →', 'infinity-market' ); ?></a>
		</div>
		<?php if ( $inf_products ) : ?>
			<div class="inf-grid">
				<?php foreach ( $inf_products as $inf_item ) : ?>
					<?php inf_product_card( $inf_item ); ?>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="inf-muted" style="text-align:center"><?php esc_html_e( 'Ajoutez vos produits WooCommerce pour les afficher ici.', 'infinity-market' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<section class="inf-section">
	<div class="inf-container">
		<div class="inf-badges" style="margin-top:0">
			<div class="inf-badge-item">
				<?php inf_icon( 'cash' ); ?>
				<div><strong><?php esc_html_e( 'Paiement à la livraison', 'infinity-market' ); ?></strong><span><?php esc_html_e( 'Espèces à la réception', 'infinity-market' ); ?></span></div>
			</div>
			<div class="inf-badge-item">
				<?php inf_icon( 'truck' ); ?>
				<div><strong><?php esc_html_e( '58 wilayas', 'infinity-market' ); ?></strong><span><?php esc_html_e( 'Domicile ou Stopdesk', 'infinity-market' ); ?></span></div>
			</div>
			<div class="inf-badge-item">
				<?php inf_icon( 'shield' ); ?>
				<div><strong><?php esc_html_e( 'Retour facile', 'infinity-market' ); ?></strong><span><?php esc_html_e( 'Produit conforme ou remboursé', 'infinity-market' ); ?></span></div>
			</div>
		</div>
	</div>
</section>

<?php
do_action( 'inf_front_page_bottom' );
get_footer();
