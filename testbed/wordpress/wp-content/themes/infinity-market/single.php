<?php
/**
 * Article — ∞ Infinity Coder
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
	<div class="inf-layout <?php echo is_active_sidebar( 'sidebar-1' ) ? '' : 'inf-layout--no-sidebar'; ?>">
		<div>
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article <?php post_class( 'inf-article' ); ?>>
					<h1 class="inf-article__title"><?php the_title(); ?></h1>
					<p class="inf-article__meta"><?php echo esc_html( get_the_date() ); ?> · <?php the_author(); ?></p>
					<?php if ( has_post_thumbnail() ) : ?>
						<p><?php the_post_thumbnail( 'large' ); ?></p>
					<?php endif; ?>
					<div class="inf-article__content">
						<?php
						the_content();
						wp_link_pages( array( 'before' => '<nav class="inf-pagination">', 'after' => '</nav>' ) );
						?>
					</div>
					<footer style="margin-top:24px;border-top:2px solid var(--inf-line);padding-top:14px">
						<?php the_tags( '<span class="inf-badge inf-badge--nouveau">#', '</span> <span class="inf-badge inf-badge--nouveau">#', '</span>' ); ?>
					</footer>
					<?php
					if ( comments_open() || get_comments_number() ) {
						comments_template();
					}
					?>
				</article>
			<?php endwhile; ?>
		</div>
		<?php get_sidebar(); ?>
	</div>
</div>
<?php
get_footer();
