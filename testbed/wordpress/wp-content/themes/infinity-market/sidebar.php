<?php
/**
 * Colonne latérale — ∞ Infinity Coder
 *
 * @package infinity-market
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_active_sidebar( 'sidebar-1' ) ) {
	return;
}
?>
<aside class="inf-sidebar" role="complementary">
	<?php dynamic_sidebar( 'sidebar-1' ); ?>
</aside>
