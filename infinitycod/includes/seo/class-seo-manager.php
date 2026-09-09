<?php
/**
 * SEO : JSON-LD Product + OpenGraph/Twitter Cards sur les fiches produit.
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
	}

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

		// OpenGraph / WhatsApp / Twitter.
		echo "\n<!-- InfinityCod SEO -->\n";
		printf( '<meta property="og:type" content="product" />' . "\n" );
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $name ) );
		printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( wp_trim_words( $desc, 30 ) ) );
		printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $url ) );
		if ( $img ) { printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $img ) ); }
		printf( '<meta property="product:price:amount" content="%s" />' . "\n", esc_attr( number_format( $price, 2, '.', '' ) ) );
		printf( '<meta property="product:price:currency" content="%s" />' . "\n", esc_attr( $cur ) );
		printf( '<meta name="twitter:card" content="summary_large_image" />' . "\n" );

		// JSON-LD Product (Google Rich Results).
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
