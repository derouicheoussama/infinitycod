<?php
/**
 * Page statique — ∞ Infinity Coder
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
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article <?php post_class( 'inf-article' ); ?>>
			<h1 class="inf-article__title"><?php the_title(); ?></h1>
			<div class="inf-article__content">
				<?php
				the_content();
				wp_link_pages( array( 'before' => '<nav class="inf-pagination">', 'after' => '</nav>' ) );
				?>
			</div>
			<?php
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
			?>
		</article>
	<?php endwhile; ?>
</div>
<?php
get_footer();
