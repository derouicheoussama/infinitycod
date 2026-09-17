<?php
/**
 * Gabarit principal (liste d'articles) — ∞ Infinity Coder
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
			<div class="inf-page-head" style="margin-bottom:24px">
				<h1><?php echo esc_html( is_home() && ! is_front_page() ? get_the_title( (int) get_option( 'page_for_posts' ) ) : __( 'Actualités', 'infinity-market' ) ); ?></h1>
			</div>
			<?php if ( have_posts() ) : ?>
				<div class="inf-entry-grid">
					<?php
					while ( have_posts() ) :
						the_post();
						?>
						<article <?php post_class( 'inf-entry' ); ?>>
							<?php if ( has_post_thumbnail() ) : ?>
								<a class="inf-entry__media" href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'medium_large' ); ?></a>
							<?php endif; ?>
							<div class="inf-entry__body">
								<p class="inf-entry__meta"><?php echo esc_html( get_the_date() ); ?> · <?php the_category( ', ' ); ?></p>
								<h2 class="inf-entry__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
								<p style="margin:0;font-size:14px;color:var(--inf-muted)"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18 ) ); ?></p>
							</div>
						</article>
					<?php endwhile; ?>
				</div>
				<nav class="inf-pagination">
					<?php echo paginate_links( array( 'prev_text' => '←', 'next_text' => '→' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</nav>
			<?php else : ?>
				<div class="inf-article">
					<h2><?php esc_html_e( 'Aucun article pour le moment', 'infinity-market' ); ?></h2>
					<p><?php esc_html_e( 'Revenez bientôt — les nouveautés arrivent.', 'infinity-market' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php get_sidebar(); ?>
	</div>
</div>
<?php
get_footer();
