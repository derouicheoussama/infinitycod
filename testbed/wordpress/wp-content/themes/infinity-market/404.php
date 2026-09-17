<?php
/**
 * Page 404 — ∞ Infinity Coder
 *
 * @package infinity-market
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="inf-container">
	<div class="inf-404">
		<p class="inf-404__code">404</p>
		<h1><?php esc_html_e( 'Oups ! Page introuvable', 'infinity-market' ); ?></h1>
		<p class="inf-muted"><?php esc_html_e( 'La page que vous cherchez n\'existe plus ou a été déplacée.', 'infinity-market' ); ?></p>
		<p>
			<a class="inf-btn inf-btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Retour à l\'accueil', 'infinity-market' ); ?></a>
			<a class="inf-btn inf-btn--ghost" href="#inf-cod"><?php esc_html_e( 'Nous contacter', 'infinity-market' ); ?></a>
		</p>
		<div class="inf-searchpage" style="margin-top:24px"><?php get_search_form(); ?></div>
	</div>
</div>
<?php
get_footer();
