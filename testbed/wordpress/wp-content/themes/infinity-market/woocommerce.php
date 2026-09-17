<?php
/**
 * Gabarit WooCommerce — ∞ Infinity Coder
 *
 * @package infinity-market
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="inf-container">
	<?php inf_breadcrumb(); ?>
	<div class="inf-layout inf-layout--no-sidebar">
		<div>
			<?php woocommerce_content(); ?>
		</div>
	</div>
</div>
<?php
get_footer();
