<?php
/**
 * Résultats de recherche — ∞ Infinity Coder
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
			<div class="inf-page-head" style="margin-bottom:24px;text-align:center;border-radius:24px">
				<h1>
					<?php
					/* translators: %s : terme recherché */
					printf( esc_html__( 'Résultats pour « %s »', 'infinity-market' ), esc_html( get_search_query() ) );
					?>
				</h1>
				<div class="inf-searchpage" style="margin-top:14px"><?php get_search_form(); ?></div>
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
								<p class="inf-entry__meta"><?php echo esc_html( get_post_type() ); ?></p>
								<h2 class="inf-entry__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
								<p style="margin:0;font-size:14px;color:var(--inf-muted)"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 16 ) ); ?></p>
							</div>
						</article>
					<?php endwhile; ?>
				</div>
				<nav class="inf-pagination">
					<?php echo paginate_links( array( 'prev_text' => '←', 'next_text' => '→' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</nav>
			<?php else : ?>
				<div class="inf-article" style="text-align:center">
					<h2><?php esc_html_e( 'Aucun résultat', 'infinity-market' ); ?></h2>
					<p><?php esc_html_e( 'Essayez un autre mot-clé, ou contactez-nous directement par téléphone.', 'infinity-market' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php
get_footer();
