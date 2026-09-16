<?php
/**
 * SEO + GEO (Generative Engine Optimization).
 *
 * - JSON-LD Product enrichi : avis, livraison (shippingDetails + délai).
 * - OpenGraph complet (locale, site_name) + Twitter Card.
 * - LocalBusiness avec zones desservies (areaServed = wilayas actives).
 * - FAQPage JSON-LD depuis la FAQ marchand (Réglages → Avancé).
 * - Landing « livraison-{wilaya} » : canonical, robots, JSON-LD Service.
 * - /llms.txt : fichier structuré pour les moteurs génératifs (ChatGPT,
 *   Perplexity, AI Overviews) — boutique, zones, tarifs, politique COD.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Seo;

use InfinityCod\Core\Settings;
use InfinityCod\Shipping\RatesManager;

defined( 'ABSPATH' ) || exit;

class SeoManager {

	/**
	 * Hooks : head produit, rewrites (landing + llms.txt).
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_head', array( $this, 'output' ), 20 );
		add_action( 'init', array( $this, 'add_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render_landing' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render_llms' ) );
	}

	/** Landing « livraison-{wilaya} » + /llms.txt : rewrite + flush unique. */
	public function add_rewrite() {
		add_rewrite_rule( '^livraison-([a-z]+)/?$', 'index.php?icod_wilaya_landing=$matches[1]', 'top' );
		add_rewrite_rule( '^llms\.txt/?$', 'index.php?icod_llms=1', 'top' );
		if ( ! get_option( 'infinitycod_landing_flushed' ) ) {
			flush_rewrite_rules();
			update_option( 'infinitycod_landing_flushed', 1, true );
		}
	}

	/**
	 * Variables de requête des pages spéciales InfinityCod.
	 *
	 * @param array $vars Variables existantes.
	 * @return array
	 */
	public function add_query_var( $vars ) {
		$vars[] = 'icod_wilaya_landing';
		$vars[] = 'icod_llms';
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

		$name    = $wilaya['name_fr'];
		$name_ar = $wilaya['name_ar'] ?? '';
		$home    = (float) ( $rates ? $rates->price( $code, '', RatesManager::MODE_HOME ) : -1 );
		$desk    = (float) ( $rates ? $rates->price( $code, '', RatesManager::MODE_DESK ) : -1 );
		$days    = (string) ( $rates ? $rates->delivery_estimate( $code ) : '' );
		$fmt     = fn( $v ) => $v >= 0 ? number_format_i18n( $v, 0 ) . ' ' . Settings::currency_label() : __( 'Nous contacter', 'infinitycod' );
		$url     = home_url( '/livraison-' . strtolower( $code ) . '/' );

		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html><html <?php language_attributes(); ?>><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php /* translators: 1 : wilaya, 2 : nom du site. */ printf( esc_html__( 'Livraison à %1$s — Prix, délais & commande COD | %2$s', 'infinitycod' ), esc_html( $name ), esc_html( get_bloginfo( 'name' ) ) ); ?></title>
<meta name="robots" content="index,follow">
<link rel="canonical" href="<?php echo esc_url( $url ); ?>">
<meta name="description" content="<?php /* translators: 1 : wilaya, 2 : arabe, 3 : prix. */ printf( esc_attr__( 'Commandez en ligne avec livraison à domicile ou stopdesk à %1$s (%2$s). Paiement à la livraison, prix dès %3$s.', 'infinitycod' ), esc_html( $name ), esc_html( $name_ar ), esc_html( $fmt( min( array_filter( array( $home, $desk ), fn( $v ) => $v >= 0 ) ?: array( 0 ) ) ) ) ); ?>">
<?php
/* JSON-LD Service + zone desservie (SEO & GEO local). */
$ld_service = array(
	'@context'   => 'https://schema.org',
	'@type'      => 'Service',
	'name'       => sprintf( /* translators: %s : wilaya. */ __( 'Livraison COD à %s', 'infinitycod' ), $name ),
	'provider'   => array(
		'@type' => 'LocalBusiness',
		'name'  => get_bloginfo( 'name' ),
		'url'   => home_url( '/' ),
	),
	'areaServed' => array(
		'@type' => 'AdministrativeArea',
		'name'  => $name,
	),
	'offers'     => array(
		'@type'           => 'OfferCatalog',
		'name'            => __( 'Options de livraison', 'infinitycod' ),
		'itemListElement' => array_filter( array(
			$home >= 0 ? array( '@type' => 'Offer', 'name' => __( 'Livraison à domicile', 'infinitycod' ), 'price' => $home, 'priceCurrency' => Settings::currency() ) : null,
			$desk >= 0 ? array( '@type' => 'Offer', 'name' => __( 'Retrait au bureau (stopdesk)', 'infinitycod' ), 'price' => $desk, 'priceCurrency' => Settings::currency() ) : null,
		) ),
	),
);
echo '<script type="application/ld+json">' . wp_json_encode( $ld_service ) . '</script>' . "\n";
?>
<?php wp_head(); ?>
<style>.icod-land{max-width:760px;margin:0 auto;padding:40px 20px;font-family:inherit}.icod-land h1{font-size:clamp(24px,4vw,36px)}.icod-land table{width:100%;border-collapse:collapse;margin:18px 0}.icod-land td,.icod-land th{padding:10px 12px;border-bottom:1px solid #e2e8f0;text-align:left}.icod-land .btn{display:inline-block;background:#1877c2;color:#fff;padding:13px 26px;border-radius:10px;text-decoration:none;font-weight:700}.icod-land dl{margin:12px 0}.icod-land dt{font-weight:700;margin-top:10px}.icod-land dd{margin:0 0 6px;color:#475569}</style>
</head><body>
<div class="icod-land">
	<?php /* translators: %s : wilaya. */ ?>
	<h1><?php printf( esc_html__( 'Livraison à %s', 'infinitycod' ), esc_html( $name ) ); ?> <?php echo esc_html( $name_ar ? '— ' . $name_ar : '' ); ?></h1>
	<?php /* translators: 1 : wilaya, 2 : nombre de wilayas. */ ?>
	<p><?php printf( esc_html__( 'Commandez en ligne et payez à la livraison partout à %1$s et dans les %2$d wilayas d’Algérie.', 'infinitycod' ), esc_html( $name ), 58 ); ?></p>
	<table>
		<tr><th><?php esc_html_e( 'Mode', 'infinitycod' ); ?></th><th><?php esc_html_e( 'Prix', 'infinitycod' ); ?></th></tr>
		<tr><td><?php esc_html_e( '🏠 Livraison à domicile', 'infinitycod' ); ?></td><td><?php echo esc_html( $fmt( $home ) ); ?></td></tr>
		<?php if ( $desk >= 0 ) : ?><tr><td><?php esc_html_e( '🏢 Retrait au bureau (stopdesk)', 'infinitycod' ); ?></td><td><?php echo esc_html( $fmt( $desk ) ); ?></td></tr><?php endif; ?>
	</table>
	<?php if ( $days ) : ?>
	<?php /* translators: 1 : wilaya, 2 : délai. */ ?>
	<p>⏱ <?php printf( esc_html__( 'Délai estimé à %1$s : %2$s', 'infinitycod' ), esc_html( $name ), esc_html( $days ) ); ?></p>
	<?php endif; ?>
	<p style="margin-top:24px"><a class="btn" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php esc_html_e( 'Voir les produits livrables →', 'infinitycod' ); ?></a></p>
	<?php $faq = self::faq_pairs(); ?>
	<?php if ( $faq ) : ?>
	<h2><?php esc_html_e( 'Questions fréquentes', 'infinitycod' ); ?></h2>
	<dl>
		<?php foreach ( $faq as $entry ) : ?>
		<dt><?php echo esc_html( $entry['q'] ); ?></dt><dd><?php echo esc_html( $entry['a'] ); ?></dd>
		<?php endforeach; ?>
	</dl>
	<?php endif; ?>
	<p style="color:#777;font-size:13px"><?php esc_html_e( 'Paiement à la livraison · Vérification du colis à la réception · Service client WhatsApp', 'infinitycod' ); ?></p>
</div>
<?php wp_footer(); ?>
</body></html>
		<?php
		exit;
	}

	/**
	 * /llms.txt — fichier structuré pour les moteurs génératifs (GEO).
	 *
	 * Décrit la boutique, les zones desservies, les tarifs et la politique COD
	 * en texte clair : c'est ce que ChatGPT, Perplexity et les AI Overviews
	 * citent en référence.
	 *
	 * @return void
	 */
	public function maybe_render_llms() {
		if ( ! get_query_var( 'icod_llms' ) || ! Settings::get( 'seo_llms_enabled', 1 ) ) {
			return;
		}

		$geo     = infinitycod()->module( 'geo' );
		$wilayas = $geo ? $geo->wilayas( true ) : array();
		$names   = array_slice( array_column( (array) $wilayas, 'name_fr' ), 0, 58 );
		$rates   = infinitycod()->module( 'rates' );
		$home    = $rates ? $rates->price( '', '', RatesManager::MODE_HOME ) : 0;
		$desk    = $rates ? $rates->price( '', '', RatesManager::MODE_DESK ) : 0;

		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );

		$lines   = array();
		$lines[] = '# ' . get_bloginfo( 'name' );
		$lines[] = '';
		$lines[] = '> ' . ( get_bloginfo( 'description' ) ? get_bloginfo( 'description' ) : __( 'Boutique de vente en ligne avec paiement à la livraison (Algérie).', 'infinitycod' ) );
		$lines[] = '';
		$lines[] = __( '## Politique de commande', 'infinitycod' );
		$lines[] = '- ' . __( 'Paiement à la livraison (COD) disponible partout en Algérie.', 'infinitycod' );
		$lines[] = '- ' . __( 'Commande en ligne sans compte, confirmation par téléphone ou WhatsApp.', 'infinitycod' );
		if ( \InfinityCod\Payment\PaymentManager::enabled() ) {
			$lines[] = '- ' . __( 'Paiement en ligne par carte CIB / Edahabia également accepté.', 'infinitycod' );
		}
		$lines[] = '';
		$lines[] = __( '## Zones de livraison', 'infinitycod' );
		$lines[] = '- ' . implode( ', ', $names );
		if ( $home > 0 ) {
			$lines[] = '- ' . sprintf( /* translators: 1 : prix domicile, 2 : prix stopdesk. */ __( 'Livraison : dès %1$s (domicile) / %2$s (stopdesk).', 'infinitycod' ), number_format_i18n( $home, 0 ) . ' ' . Settings::currency_label(), number_format_i18n( $desk, 0 ) . ' ' . Settings::currency_label() );
		}
		$lines[] = '';
		$lines[] = __( '## Pages principales', 'infinitycod' );
		$lines[] = '- Boutique : ' . home_url( '/shop/' );
		foreach ( array_slice( array_column( (array) $wilayas, 'code' ), 0, 10 ) as $code ) {
			$lines[] = '- ' . sprintf( /* translators: %s : code wilaya. */ __( 'Livraison wilaya %s', 'infinitycod' ), $code ) . ' : ' . home_url( '/livraison-' . strtolower( $code ) . '/' );
		}
		$lines[] = '';
		$lines[] = __( '## Contact', 'infinitycod' );
		if ( Settings::get( 'seo_business_phone' ) ) {
			$lines[] = '- ' . __( 'Téléphone / WhatsApp', 'infinitycod' ) . ' : ' . Settings::get( 'seo_business_phone' );
		}
		if ( Settings::get( 'seo_business_city' ) ) {
			$lines[] = '- ' . __( 'Ville', 'infinitycod' ) . ' : ' . Settings::get( 'seo_business_city' );
		}
		$lines[] = '';

		echo esc_html( implode( "\n", $lines ) );
		exit;
	}

	/**
	 * FAQ marchand : lignes « question | réponse » du réglage seo_faq.
	 *
	 * @return array<int, array{q:string,a:string}>
	 */
	public static function faq_pairs() {
		$raw = (string) Settings::get( 'seo_faq', '' );
		if ( '' === trim( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( count( $parts ) === 2 && '' !== $parts[0] && '' !== $parts[1] ) {
				$out[] = array( 'q' => $parts[0], 'a' => $parts[1] );
			}
		}
		return array_slice( $out, 0, 10 );
	}

	/* ---------- JSON-LD + OpenGraph ---------- */

	/**
	 * Head des fiches produit : OG complet, Product enrichi (avis + livraison),
	 * LocalBusiness (zones desservies) et FAQPage selon les réglages.
	 *
	 * @return void
	 */
	public function output() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
		global $product;
		if ( ! $product instanceof \WC_Product ) { return; }

		$price  = (float) $product->get_price();
		$cur    = Settings::currency();
		$img    = wp_get_attachment_image_url( $product->get_image_id(), 'large' );
		$name   = wp_strip_all_tags( $product->get_name() );
		$desc   = wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() );
		$url    = get_permalink();
		$locale = get_locale() ?: 'fr_FR';

		echo "\n<!-- InfinityCod SEO -->\n";
		printf( '<meta property="og:type" content="product" />' . "\n" );
		printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
		printf( '<meta property="og:locale" content="%s" />' . "\n", esc_attr( str_replace( '-', '_', $locale ) ) );
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $name ) );
		printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( wp_trim_words( $desc, 30 ) ) );
		printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $url ) );
		if ( $img ) { printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $img ) ); }
		printf( '<meta property="product:price:amount" content="%s" />' . "\n", esc_attr( number_format( $price, 2, '.', '' ) ) );
		printf( '<meta property="product:price:currency" content="%s" />' . "\n", esc_attr( $cur ) );
		printf( '<meta name="twitter:card" content="summary_large_image" />' . "\n" );

		// Livraison : tarif plancher + délai minimal (données Wilayas & Tarifs).
		$rates     = infinitycod()->module( 'rates' );
		$geo       = infinitycod()->module( 'geo' );
		$all       = $geo ? $geo->wilayas( true ) : array();
		$min_price = null;
		$min_days  = null;
		foreach ( (array) $all as $w ) {
			$p = $rates ? (float) $rates->price( (string) $w['code'], '', RatesManager::MODE_HOME ) : -1;
			if ( $p >= 0 && ( null === $min_price || $p < $min_price ) ) {
				$min_price = $p;
			}
			$d = $rates ? $rates->delivery_estimate( (string) $w['code'] ) : '';
			if ( preg_match( '/^(\d+)/', (string) $d, $m2 ) ) {
				$v = (int) $m2[1];
				if ( null === $min_days || $v < $min_days ) {
					$min_days = $v;
				}
			}
		}

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

		// Avis WooCommerce → étoiles dans les résultats de recherche.
		if ( $product->get_review_count() > 0 && (float) $product->get_average_rating() > 0 ) {
			$ld['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (float) $product->get_average_rating(),
				'reviewCount' => (int) $product->get_review_count(),
			);
		}

		// shippingDetails + délai : infos de livraison dans le SERP.
		if ( null !== $min_price && $min_price >= 0 ) {
			$shipping = array(
				'@type'               => 'OfferShippingDetails',
				'shippingRate'        => array(
					'@type'    => 'MonetaryAmount',
					'value'    => $min_price,
					'currency' => $cur,
				),
				'shippingDestination' => array(
					'@type'          => 'DefinedRegion',
					'addressCountry' => 'DZ',
				),
			);
			if ( null !== $min_days && $min_days > 0 ) {
				$shipping['deliveryTime'] = array(
					'@type'        => 'ShippingDeliveryTime',
					'handlingTime' => array( '@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY' ),
					'transitTime'  => array( '@type' => 'QuantitativeValue', 'minValue' => $min_days, 'maxValue' => $min_days + 3, 'unitCode' => 'DAY' ),
				);
			}
			$ld['offers']['shippingDetails'] = $shipping;
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $ld ) . '</script>' . "\n";

		// LocalBusiness avec zones desservies (référencement local).
		if ( Settings::get( 'seo_local_enabled' ) ) {
			$area = array();
			foreach ( (array) $all as $w ) {
				$area[] = array( '@type' => 'AdministrativeArea', 'name' => (string) $w['name_fr'] );
			}
			$local = array(
				'@context'   => 'https://schema.org',
				'@type'      => 'LocalBusiness',
				'name'       => get_bloginfo( 'name' ),
				'url'        => home_url( '/' ),
				'areaServed' => $area,
			);
			if ( Settings::get( 'seo_business_phone' ) ) {
				$local['telephone'] = Settings::get( 'seo_business_phone' );
			}
			if ( Settings::get( 'seo_business_city' ) ) {
				$local['address'] = array( '@type' => 'PostalAddress', 'addressLocality' => Settings::get( 'seo_business_city' ), 'addressCountry' => 'DZ' );
			}
			if ( $img ) { $local['image'] = $img; }
			echo '<script type="application/ld+json">' . wp_json_encode( $local ) . '</script>' . "\n";
		}

		// FAQPage : mêmes questions que celles saisies par le marchand.
		$faq = self::faq_pairs();
		if ( $faq ) {
			$entities = array();
			foreach ( $faq as $entry ) {
				$entities[] = array(
					'@type'          => 'Question',
					'name'           => $entry['q'],
					'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $entry['a'] ),
				);
			}
			echo '<script type="application/ld+json">' . wp_json_encode( array(
				'@context'   => 'https://schema.org',
				'@type'      => 'FAQPage',
				'mainEntity' => $entities,
			) ) . '</script>' . "\n";
		}
	}
}
