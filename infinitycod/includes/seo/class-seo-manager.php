<?php
/**
 * SEO : JSON-LD Product, OpenGraph et landing pages wilaya (SEO local).
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 */

namespace InfinityCod\Seo;

use InfinityCod\Core\Settings;

defined( 'ABSPATH' ) || exit;

class SeoManager {

	public function register() {
		add_action( 'wp_head', array( $this, 'output' ), 20 );
		add_action( 'init', array( $this, 'add_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render_landing' ) );
	}

	/** Landing « livraison-{wilaya} » : rewrite + flush unique. */
	public function add_rewrite() {
		add_rewrite_rule( '^livraison-([a-z]+)/?$', 'index.php?icod_wilaya_landing=$matches[1]', 'top' );
		if ( ! get_option( 'infinitycod_landing_flushed' ) ) {
			flush_rewrite_rules();
			update_option( 'infinitycod_landing_flushed', 1, true );
		}
	}

	public function add_query_var( $vars ) {
		$vars[] = 'icod_wilaya_landing';
		return $vars;
	}

	/** Rend la landing SEO de la wilaya demandée. */
	public function maybe_render_landing() {
		$code = strtoupper( (string) get_query_var( 'icod_wilaya_landing' ) );
		if ( '' === $code ) { return; }

		$geo    = infinitycod()->module( 'geo' );
		$rates  = infinitycod()->module( 'rates' );
		$wilaya = $geo ? $geo->wilaya( $code ) : null;
		if ( ! $wilaya ) { return; }

		$name   = $wilaya['name_fr'];
		$name_ar = $wilaya['name_ar'] ?? '';
		$home   = (float) ( $rates ? $rates->price( $code, '', \InfinityCod\Shipping\RatesManager::MODE_HOME ) : -1 );
		$desk   = (float) ( $rates ? $rates->price( $code, '', \InfinityCod\Shipping\RatesManager::MODE_DESK ) : -1 );
		$fmt    = fn( $v ) => $v >= 0 ? number_format_i18n( $v, 0 ) . ' ' . Settings::currency_label() : __( 'Nous contacter', 'infinitycod' );

		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html><html <?php language_attributes(); ?>><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
/* translators: 1 : nom de la wilaya, 2 : nom du site ou compte. */
<title><?php /* translators: 1 : wilaya, 2 : nom du site. */ printf( esc_html__( 'Livraison à %1$s — Prix, délais & commande COD | %2$s', 'infinitycod' ), esc_html( $name ), esc_html( get_bloginfo( 'name' ) ) ); ?></title>
/* translators: 1 : nom de la wilaya, 2 : nom du site ou compte. */
<meta name="description" content="<?php /* translators: 1 : wilaya, 2 : arabe, 3 : prix. */ printf( esc_attr__( 'Commandez en ligne avec livraison à domicile ou stopdesk à %1$s (%2$s). Paiement à la livraison, prix dès %3$s.', 'infinitycod' ), esc_html( $name ), esc_html( $name_ar ), esc_html( $fmt( min( array_filter( array( $home, $desk ), fn( $v ) => $v >= 0 ) ?: array( 0 ) ) ) ) ); ?>">
<?php wp_head(); ?>
<style>.icod-land{max-width:760px;margin:0 auto;padding:40px 20px;font-family:inherit}.icod-land h1{font-size:clamp(24px,4vw,36px)}.icod-land table{width:100%;border-collapse:collapse;margin:18px 0}.icod-land td,.icod-land th{padding:10px 12px;border-bottom:1px solid #e2e8f0;text-align:left}.icod-land .btn{display:inline-block;background:#1877c2;color:#fff;padding:13px 26px;border-radius:10px;text-decoration:none;font-weight:700}</style>
</head><body>
<div class="icod-land">
	/* translators: 1 : nom de la wilaya, 2 : nom du site ou compte. */
	<h1><?php /* translators: %s : wilaya. */ printf( esc_html__( 'Livraison à %s', 'infinitycod' ), esc_html( $name ) ); ?> <?php echo esc_html( $name_ar ? '— ' . $name_ar : '' ); ?></h1>
	/* translators: 1 : nom de la wilaya, 2 : nom du site ou compte. */
	<p><?php /* translators: 1 : wilaya. */ printf( esc_html__( 'Commandez en ligne et payez à la livraison partout à %1$s et dans les %2$d wilayas d’Algérie.', 'infinitycod' ), esc_html( $name ), 58 ); ?></p>
	<table>
		<tr><th><?php esc_html_e( 'Mode', 'infinitycod' ); ?></th><th><?php esc_html_e( 'Prix', 'infinitycod' ); ?></th></tr>
		<tr><td><?php esc_html_e( '🏠 Livraison à domicile', 'infinitycod' ); ?></td><td><?php echo esc_html( $fmt( $home ) ); ?></td></tr>
		<?php if ( $desk >= 0 ) : ?><tr><td><?php esc_html_e( '🏢 Retrait au bureau (stopdesk)', 'infinitycod' ); ?></td><td><?php echo esc_html( $fmt( $desk ) ); ?></td></tr><?php endif; ?>
	</table>
	<p style="margin-top:24px"><a class="btn" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php esc_html_e( 'Voir les produits livrables →', 'infinitycod' ); ?></a></p>
	<p style="color:#777;font-size:13px"><?php esc_html_e( 'Paiement à la livraison · Vérification du colis à la réception · Service client WhatsApp', 'infinitycod' ); ?></p>
</div>
<?php wp_footer(); ?>
</body></html>
		<?php
		exit;
	}

	/* ---------- JSON-LD + OpenGraph ---------- */
	public function output() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
		global $product;
		if ( ! $product instanceof \WC_Product ) { return; }

		$price = (float) $product->get_price();
		$cur   = Settings::currency();
		$img   = wp_get_attachment_image_url( $product->get_image_id(), 'large' );
		$name  = wp_strip_all_tags( $product->get_name() );
		$desc  = wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() );
		$url   = get_permalink();

		echo "\n<!-- InfinityCod SEO -->\n";
		printf( '<meta property="og:type" content="product" />' . "\n" );
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $name ) );
		printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( wp_trim_words( $desc, 30 ) ) );
		printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $url ) );
		if ( $img ) { printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $img ) ); }
		printf( '<meta property="product:price:amount" content="%s" />' . "\n", esc_attr( number_format( $price, 2, '.', '' ) ) );
		printf( '<meta property="product:price:currency" content="%s" />' . "\n", esc_attr( $cur ) );
		printf( '<meta name="twitter:card" content="summary_large_image" />' . "\n" );

		$ld = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => $name,
			'description' => wp_trim_words( $desc, 60 ),
			'sku'         => $product->get_sku() ?: (string) $product->get_id(),
			'offers'      => array(
				'@type'         => 'Offer',
				'url'           => $url,
				'price'         => number_format( $price, 2, '.', '' ),
				'priceCurrency' => $cur,
				'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
				'itemCondition' => 'https://schema.org/NewCondition',
			),
		);
		if ( $img ) { $ld['image'] = $img; }
		echo '<script type="application/ld+json">' . wp_json_encode( $ld ) . '</script>' . "\n";
	}
}
