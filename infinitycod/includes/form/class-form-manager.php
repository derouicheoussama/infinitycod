<?php
/**
 * Formulaire COD : shortcode, insertion automatique, rendu, assets.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Form;

use InfinityCod\AntiFraud\Shield;
use InfinityCod\Core\Settings;

defined( 'ABSPATH' ) || exit;

class FormManager {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( 'infinitycod_form', array( $this, 'shortcode' ) );
		add_shortcode( 'icod_form', array( $this, 'shortcode' ) );

		add_action( 'wp', array( $this, 'maybe_auto_insert' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Attributs supportés : [infinitycod_form id="123" title="" button=""].
	 *
	 * @param array $atts Attributs du shortcode.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'     => 0,
				'title'  => '',
				'button' => '',
			),
			$atts,
			'infinitycod_form'
		);

		$product_id = absint( $atts['id'] );

		if ( ! $product_id ) {
			global $product;
			$product_id = ( $product instanceof \WC_Product ) ? $product->get_id() : 0;
		}

		return $this->render( $product_id, $atts['title'], $atts['button'] );
	}

	/**
	 * Insère automatiquement le formulaire sur les fiches produit
	 * (désactivé si un shortcode est déjà présent dans le contenu).
	 *
	 * @return void
	 */
	public function maybe_auto_insert() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$post = get_post();
		if ( $post && ( has_shortcode( $post->post_content, 'infinitycod_form' ) || has_shortcode( $post->post_content, 'icod_form' ) ) ) {
			return;
		}

		// Position choisie dans les réglages.
		$positions = array(
			'before_summary' => array( 'woocommerce_single_product_summary', 5 ),
			'after_price'    => array( 'woocommerce_single_product_summary', 15 ),
			'after_excerpt'  => array( 'woocommerce_single_product_summary', 20 ),
			'before_cart'    => array( 'woocommerce_single_product_summary', 24 ),
			'after_cart'     => array( 'woocommerce_single_product_summary', 29 ),
			'after_summary'  => array( 'woocommerce_single_product_summary', 35 ),
			'end_product'    => array( 'woocommerce_after_single_product', 10 ),
		);
		$position  = Settings::get( 'form_position', 'after_summary' );
		$hook      = isset( $positions[ $position ] ) ? $positions[ $position ][0] : 'woocommerce_single_product_summary';
		$priority  = isset( $positions[ $position ] ) ? $positions[ $position ][1] : 35;

		add_action( $hook, array( $this, 'render_auto' ), $priority );
	}

	/**
	 * Rendu pour l'insertion automatique.
	 *
	 * @return void
	 */
	public function render_auto() {
		global $product;
		if ( $product instanceof \WC_Product ) {
			echo $this->render( $product->get_id(), '', '' ); // phpcs:ignore WordPress.Security.EscapeOutput -- rendu internalisé.
		}
	}

	/**
	 * Construit le HTML du formulaire pour un produit.
	 *
	 * @param int    $product_id    ID du produit.
	 * @param string $custom_title  Titre imposé (sinon réglage).
	 * @param string $custom_button Texte bouton imposé (sinon réglage).
	 * @return string HTML (vide si produit indisponible).
	 */
	public function render( $product_id, $custom_title = '', $custom_button = '' ) {
		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product->is_purchasable() ) {
			return '';
		}

		$this->enqueue();

		$geo     = infinitycod()->module( 'geo' );
		$wilayas = $geo ? $geo->wilayas( true ) : array();
		$theme   = Settings::get( 'form_theme', 'light' );
		$preset  = Settings::get( 'form_preset', 'modern' );
		$accent  = Settings::get( 'accent_color', '#0e7a4f' );
		$title   = $custom_title ? $custom_title : Settings::get( 'form_title' );
		$button  = $custom_button ? $custom_button : Settings::get( 'button_text' );
		$rtl     = \InfinityCod\Core\I18n::is_rtl();

		// Libellés personnalisés.
		$label_name    = Settings::get( 'label_name' );
		$label_phone   = Settings::get( 'label_phone' );
		$label_wilaya  = Settings::get( 'label_wilaya' );
		$label_commune = Settings::get( 'label_commune' );
		$label_note    = Settings::get( 'label_note' );

		// Blocs activables/désactivables.
		$show_qty         = (bool) Settings::get( 'show_qty_selector', 1 );
		$show_stopdesk    = (bool) Settings::get( 'show_stopdesk', 1 );
		$show_note        = (bool) Settings::get( 'show_note', 0 );
		$show_offers      = (bool) Settings::get( 'show_offers', 1 );
		$show_reassurance = (bool) Settings::get( 'show_reassurance', 1 );
		$show_email       = (bool) Settings::get( 'show_email', 0 );
		$wa_order         = (bool) Settings::get( 'wa_order_enabled', 0 ) && Settings::get( 'whatsapp_number' );
		$wa_order         = (bool) Settings::get( 'wa_order_enabled', 0 ) && Settings::get( 'whatsapp_number' );
		$wa_order         = (bool) Settings::get( 'wa_order_enabled', 0 ) && Settings::get( 'whatsapp_number' );
		$wa_order         = (bool) Settings::get( 'wa_order_enabled', 0 ) && Settings::get( 'whatsapp_number' );
		$payment_online   = infinitycod()->module( 'payment' ) ? \InfinityCod\Payment\PaymentManager::enabled() : false;

		$ts  = time();
		$sig = Shield::sign_timestamp( $ts );

		$wilaya_options = '';
		foreach ( $wilayas as $w ) {
			$wilaya_options .= sprintf(
				'<option value="%1$s">%2$s</option>',
				esc_attr( $w['code'] ),
				esc_html( $geo->wilaya_label( $w ) )
			);
		}

		// Variations : injectées en JSON, le JS résout l'ID selon les attributs.
		$variations_json = '[]';
		if ( $product->is_type( 'variable' ) ) {
			$available = array();
			foreach ( $product->get_available_variations() as $variation ) {
				if ( empty( $variation['variation_id'] ) ) {
					continue;
				}
				$available[] = array(
					'id'          => (int) $variation['variation_id'],
					'price'       => (float) $variation['display_price'],
					'attributes'  => isset( $variation['attributes'] ) ? (array) $variation['attributes'] : array(),
					'is_in_stock' => ! empty( $variation['is_in_stock'] ),
				);
			}
			$variations_json = wp_json_encode( $available );
		}

		$offers_tiers = $show_offers ? OffersEngine::tiers_for_product( $product->get_id() ) : array();

		$max_width = max( 400, min( 900, (int) Settings::get( 'form_max_width', 680 ) ) );

		// Après commande : redirection + upsell.
		$redirect_on    = (bool) Settings::get( 'redirect_enabled' ) && Settings::get( 'redirect_url' );
		$redirect_delay = max( 3, min( 60, (int) Settings::get( 'redirect_delay', 8 ) ) );
		$upsell_enabled = (bool) Settings::get( 'upsell_enabled' );
		$upsell_ids     = array_slice( array_filter( array_map( 'absint', (array) Settings::get( 'upsell_ids', array() ) ) ), 0, 3 );

		ob_start();
		?>
		<div class="icod-root icod-theme-<?php echo esc_attr( $theme ); ?>"
			data-preset="<?php echo esc_attr( $preset ); ?>"
			data-product="<?php echo esc_attr( $product->get_id() ); ?>"
			data-variations="<?php echo esc_attr( $variations_json ); ?>"
			data-unit-price="<?php echo esc_attr( $product->get_price() ); ?>"
			data-qty-max="<?php echo esc_attr( (int) Settings::get( 'qty_max', 20 ) ); ?>"
			data-sticky="<?php echo esc_attr( (int) Settings::get( 'sticky_bar', 1 ) ); ?>"
			data-redirect="<?php echo $redirect_on ? esc_attr( Settings::get( 'redirect_url' ) ) : ''; ?>"
			data-redirect-delay="<?php echo $redirect_on ? (int) $redirect_delay : 0; ?>"
			style="max-width:<?php echo (int) $max_width; ?>px"
			dir="<?php echo $rtl ? 'rtl' : 'ltr'; ?>">

			<style>:root{--icod-accent:<?php echo esc_attr( $accent ); ?>;}</style>

			<section class="icod-card" aria-labelledby="icod-form-title">
				<header class="icod-head">
					<span class="icod-head-icon" aria-hidden="true"><?php echo esc_html( Settings::get( 'form_icon', '🛒' ) ); ?></span>
					<div class="icod-head-text">
						<h2 class="icod-form-title" id="icod-form-title"><?php echo esc_html( $title ); ?></h2>
						<?php if ( Settings::get( 'form_subtitle' ) ) : ?>
							<p class="icod-form-subtitle"><?php echo esc_html( Settings::get( 'form_subtitle' ) ); ?></p>
						<?php endif; ?>
					</div>
				</header>

				<form class="icod-form" novalidate>
					<input type="text" name="icod_hp" class="icod-hp" tabindex="-1" autocomplete="off" aria-hidden="true" />
					<input type="hidden" name="icod_ts" value="<?php echo esc_attr( $ts ); ?>" />
					<input type="hidden" name="icod_sig" value="<?php echo esc_attr( $sig ); ?>" />
					<input type="hidden" name="icod_fp" class="icod-fp" value="" />

					<div class="icod-layout">
						<div class="icod-main">

							<?php if ( $product->is_type( 'variable' ) ) : ?>
								<?php foreach ( $product->get_variation_attributes() as $taxonomy => $terms ) : ?>
									<?php
									$attribute_label = wc_attribute_label( $taxonomy );
									$options         = '';
									foreach ( $terms as $term ) {
										$slug      = sanitize_title( $term );
										$options  .= sprintf( '<option value="%1$s">%2$s</option>', esc_attr( $slug ), esc_html( $term ) );
									}
									?>
									<div class="icod-field" data-attribute="<?php echo esc_attr( $taxonomy ); ?>">
										<label><?php echo esc_html( $attribute_label ); ?></label>
										<select class="icod-input icod-attr" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>">
											<option value=""><?php esc_html_e( '— Choisir —', 'infinitycod' ); ?></option>
											<?php echo $options; // phpcs:ignore WordPress.Security.EscapeOutput -- options échappées ci-dessus. ?>
										</select>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>

							<div class="icod-field">
								<label for="icod-name-<?php echo esc_attr( $product->get_id() ); ?>"><?php echo esc_html( $label_name ); ?></label>
								<input type="text" name="icod_name" id="icod-name-<?php echo esc_attr( $product->get_id() ); ?>" class="icod-input icod-input-name" autocomplete="name" required data-icod-field="name" />
							</div>

							<div class="icod-field">
								<label for="icod-phone-<?php echo esc_attr( $product->get_id() ); ?>"><?php echo esc_html( $label_phone ); ?></label>
								<input type="tel" name="icod_phone" id="icod-phone-<?php echo esc_attr( $product->get_id() ); ?>" class="icod-input icod-input-phone" inputmode="tel" autocomplete="tel" placeholder="<?php echo esc_attr( Settings::get( 'phone_placeholder' ) ); ?>" required data-icod-field="phone" />
							</div>

							<?php if ( $show_email ) : ?>
							<div class="icod-field">
								<label for="icod-email-<?php echo esc_attr( $product->get_id() ); ?>"><?php echo esc_html( Settings::get( 'label_email' ) ); ?></label>
								<input type="email" name="icod_email" id="icod-email-<?php echo esc_attr( $product->get_id() ); ?>" class="icod-input" autocomplete="email" />
							</div>
							<?php endif; ?>




							<div class="icod-row">
								<div class="icod-field">
									<label for="icod-wilaya-<?php echo esc_attr( $product->get_id() ); ?>"><?php echo esc_html( $label_wilaya ); ?></label>
									<select name="icod_wilaya" id="icod-wilaya-<?php echo esc_attr( $product->get_id() ); ?>" class="icod-input icod-wilaya" required data-icod-field="wilaya">
										<option value=""><?php esc_html_e( '— Wilaya —', 'infinitycod' ); ?></option>
										<?php echo $wilaya_options; // phpcs:ignore WordPress.Security.EscapeOutput -- construit échappé. ?>
									</select>
								</div>

								<div class="icod-field">
									<label for="icod-commune-<?php echo esc_attr( $product->get_id() ); ?>"><?php echo esc_html( $label_commune ); ?></label>
									<select name="icod_commune" id="icod-commune-<?php echo esc_attr( $product->get_id() ); ?>" class="icod-input icod-commune" disabled required data-icod-field="commune">
										<option value=""><?php esc_html_e( '— Commune —', 'infinitycod' ); ?></option>
									</select>
								</div>
							</div>

							<?php if ( $show_stopdesk ) : ?>
								<fieldset class="icod-mode">
									<legend><?php esc_html_e( 'Mode de livraison', 'infinitycod' ); ?></legend>

									<div class="icod-mode-grid">
										<label class="icod-mode-option">
											<input type="radio" name="icod_mode" value="home" class="icod-mode-radio" checked />
											<span class="icod-mode-box">
												<span class="icod-mode-title">🏠 <?php esc_html_e( 'À domicile', 'infinitycod' ); ?></span>
												<span class="icod-mode-price" data-price-home>—</span>
											</span>
										</label>

										<label class="icod-mode-option">
											<input type="radio" name="icod_mode" value="desk" class="icod-mode-radio" />
											<span class="icod-mode-box">
												<span class="icod-mode-title">🏢 <?php esc_html_e( 'Au bureau', 'infinitycod' ); ?></span>
												<span class="icod-mode-price" data-price-desk>—</span>
											</span>
										</label>
									</div>
								</fieldset>

								<div class="icod-field icod-stopdesk-wrap icod-hidden">
									<label for="icod-desk-<?php echo esc_attr( $product->get_id() ); ?>"><?php esc_html_e( 'Bureau de retrait', 'infinitycod' ); ?></label>
									<select name="icod_desk" id="icod-desk-<?php echo esc_attr( $product->get_id() ); ?>" class="icod-input icod-desk">
										<option value=""><?php esc_html_e( '— Choisir un bureau —', 'infinitycod' ); ?></option>
									</select>
								</div>
							<?php else : ?>
								<input type="hidden" name="icod_mode" value="home" />
							<?php endif; ?>

							<?php if ( $show_qty ) : ?>
								<div class="icod-field icod-qty-field">
									<label><?php esc_html_e( 'Quantité', 'infinitycod' ); ?></label>
									<div class="icod-qty">
										<button type="button" class="icod-qty-btn" data-step="-1" aria-label="<?php esc_attr_e( 'Diminuer', 'infinitycod' ); ?>">−</button>
										<input type="number" class="icod-qty-input" value="1" min="1" max="<?php echo esc_attr( (int) Settings::get( 'qty_max', 20 ) ); ?>" inputmode="numeric" />
										<button type="button" class="icod-qty-btn" data-step="1" aria-label="<?php esc_attr_e( 'Augmenter', 'infinitycod' ); ?>">+</button>
									</div>
								</div>
							<?php endif; ?>

							<?php if ( $show_note ) : ?>
								<div class="icod-field">
									<label for="icod-note-<?php echo esc_attr( $product->get_id() ); ?>"><?php echo esc_html( $label_note ); ?></label>
									<textarea name="icod_note" id="icod-note-<?php echo esc_attr( $product->get_id() ); ?>" class="icod-input icod-note" rows="2" maxlength="500"></textarea>
								</div>
							<?php endif; ?>

							<?php if ( $payment_online ) : ?>
								<fieldset class="icod-mode icod-pay">
									<legend><?php esc_html_e( 'Méthode de paiement', 'infinitycod' ); ?></legend>
									<div class="icod-mode-grid">
										<label class="icod-mode-option">
											<input type="radio" name="icod_payment" value="cod" class="icod-pay-radio" checked />
											<span class="icod-mode-box">
												<span class="icod-mode-title"><?php echo esc_html( Settings::get( 'cod_label' ) ); ?></span>
												<span class="icod-mode-sub"><?php esc_html_e( 'Vous payez en recevant le colis', 'infinitycod' ); ?></span>
											</span>
										</label>
										<label class="icod-mode-option">
											<input type="radio" name="icod_payment" value="online" class="icod-pay-radio" />
											<span class="icod-mode-box">
												<span class="icod-mode-title"><?php echo esc_html( Settings::get( 'payment_label' ) ); ?></span>
												<span class="icod-mode-sub"><?php esc_html_e( 'Paiement sécurisé CIB / Edahabia', 'infinitycod' ); ?></span>
											</span>
										</label>
									</div>
								</fieldset>
								<input type="hidden" name="icod_payment_default" value="cod" />
							<?php endif; ?>

							<?php if ( $offers_tiers ) : ?>
								<ul class="icod-offers">
									<?php
									foreach ( $offers_tiers as $min_qty => $pct ) :
										printf(
											'<li>%s</li>',
											esc_html(
												sprintf(
													/* translators: 1 : quantité, 2 : pourcentage. */
													__( '🛒 %d articles ou plus : −%s%%', 'infinitycod' ),
													(int) $min_qty,
													(float) $pct
												)
											)
										);
									endforeach;
									?>
								</ul>
							<?php endif; ?>

						</div>

						<aside class="icod-aside">
							<div class="icod-summary" role="status" aria-live="polite">
								<?php if ( Settings::get( 'free_amount_enabled' ) ) : ?>
								<div class="icod-freebar icod-hidden" data-icod-freebar>
									<span data-icod-freebar-text></span>
									<div class="icod-freebar-track"><div class="icod-freebar-fill" data-icod-freebar-fill></div></div>
								</div>
								<?php endif; ?>
								<div class="icod-summary-line"><span><?php esc_html_e( 'Sous-total', 'infinitycod' ); ?></span><span data-summary-subtotal>—</span></div>
								<div class="icod-summary-line icod-hidden" data-summary-discount-row><span data-summary-discount-label><?php esc_html_e( 'Remise', 'infinitycod' ); ?></span><span data-summary-discount>—</span></div>
								<div class="icod-summary-line"><span><?php esc_html_e( 'Livraison', 'infinitycod' ); ?></span><span data-summary-shipping>—</span></div>
								<div class="icod-summary-total"><span><?php esc_html_e( 'Total à payer à la livraison', 'infinitycod' ); ?></span><span data-summary-total>—</span></div>
							</div>

							<div class="icod-msg icod-hidden" data-icod-msg role="alert"></div>

							<button type="submit" class="icod-submit">
								<?php echo esc_html( $button ); ?>
							</button>

							<?php if ( $wa_order ) : ?>
								<button type="button" class="icod-submit icod-wa-btn" data-icod-wa>
									💬 <?php echo esc_html( Settings::get( 'wa_order_label' ) ); ?>
								</button>
							<?php endif; ?>

							<?php if ( $wa_order ) : ?>
							<?php endif; ?>

							<?php if ( $wa_order ) : ?>
							<?php endif; ?>

							<?php if ( $wa_order ) : ?>
							<?php endif; ?>

							<?php if ( $show_reassurance ) : ?>
								<p class="icod-reassurance">
									<span>💵 <?php esc_html_e( 'Paiement à la livraison', 'infinitycod' ); ?></span>
									<span>🚚 <?php esc_html_e( 'Livraison 58 wilayas', 'infinitycod' ); ?></span>
									<span>↩️ <?php esc_html_e( 'Vérifiez le colis à la réception', 'infinitycod' ); ?></span>
								</p>
							<?php endif; ?>
						</aside>
					</div>
				</form>
			</section>

			<div class="icod-success icod-hidden" data-icod-success hidden>
				<h3 data-icod-success-title><?php echo esc_html( Settings::get( 'success_title' ) ); ?></h3>
				<p data-icod-success-text></p>
				<p class="icod-success-meta" data-icod-success-meta hidden></p>

				<?php if ( $upsell_enabled && $upsell_ids ) : ?>
					<div class="icod-upsell icod-hidden" data-icod-upsell>
						<p class="icod-upsell-title"><?php echo esc_html( Settings::get( 'upsell_title' ) ); ?></p>
						<div class="icod-upsell-grid">
							<?php
							foreach ( $upsell_ids as $upsell_id ) :
								$upsell_product = wc_get_product( $upsell_id );
								if ( ! $upsell_product || ! $upsell_product->is_purchasable() ) {
									continue;
								}
								$image_url = wp_get_attachment_image_url( $upsell_product->get_image_id(), 'woocommerce_thumbnail' );
								?>
								<a class="icod-upsell-item" href="<?php echo esc_url( $upsell_product->get_permalink() ); ?>">
									<?php if ( $image_url ) : ?>
										<img src="<?php echo esc_url( $image_url ); ?>" alt="" loading="lazy" />
									<?php endif; ?>
									<span class="icod-upsell-name"><?php echo esc_html( $upsell_product->get_name() ); ?></span>
									<span class="icod-upsell-price"><?php echo wp_kses_post( wc_price( $upsell_product->get_price() ) ); ?></span>
									<span class="icod-upsell-cta"><?php esc_html_e( 'Commander', 'infinitycod' ); ?> →</span>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<a class="icod-new-order" href="#" data-icod-restart><?php esc_html_e( 'Passer une autre commande', 'infinitycod' ); ?></a>
				<p class="icod-redirect-note icod-hidden" data-icod-redirect-note></p>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Demande le chargement des assets front (appelé par render()).
	 *
	 * Le formulaire est rendu pendant le corps de la page, souvent APRÈS
	 * wp_head : un style enqueued à ce moment ne serait jamais imprimé.
	 * D'où le fallback qui écrit le lien CSS inline au besoin.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( wp_style_is( 'icod-form', 'registered' ) ) {
			wp_enqueue_style( 'icod-form' );
			wp_enqueue_script( 'icod-form' );
		}

		// wp_head déjà passé => le style ne serait pas imprimé : lien inline.
		if ( function_exists( 'did_action' ) && did_action( 'wp_head' ) && ! wp_style_is( 'icod-form', 'done' ) ) {
			global $wp_styles;
			$style = isset( $wp_styles ) ? $wp_styles->query( 'icod-form' ) : null;

			if ( $style ) {
				$href = $style->src . ( $style->ver ? '?ver=' . $style->ver : '' );
				printf(
					'<link rel="stylesheet" id="icod-form-css" href="%s" media="all" />',
					esc_url( $href )
				);
				$style->done = 1;
			}
		}
	}

	/**
	 * Enregistre les assets front.
	 *
	 * Chargement ANTICIPÉ quand la page courante en aura besoin (fiche
	 * produit avec insertion auto ou shortcode dans le contenu) : le style
	 * part ainsi dans wp_head, sans flash de contenu brut.
	 *
	 * @return void
	 */
	public function assets() {
		wp_register_style(
			'icod-form',
			INFINITYCOD_URL . 'assets/front/css/form.css',
			array(),
			INFINITYCOD_VERSION
		);

		wp_register_script(
			'icod-form',
			INFINITYCOD_URL . 'assets/front/js/form.js',
			array(),
			INFINITYCOD_VERSION,
			true
		);

		$needs_form = false;

		if ( function_exists( 'is_product' ) && is_product() ) {
			$needs_form = true; // Insertion automatique.
		} elseif ( function_exists( 'is_singular' ) && is_singular() ) {
			$post = get_post();
			if ( $post && ( has_shortcode( (string) $post->post_content, 'infinitycod_form' ) || has_shortcode( (string) $post->post_content, 'icod_form' ) ) ) {
				$needs_form = true; // Shortcode dans le contenu.
			}
		}

		if ( $needs_form ) {
			wp_enqueue_style( 'icod-form' );
			wp_enqueue_script( 'icod-form' );
		}

		wp_localize_script( 'icod-form', 'icodFront', array(
			'restUrl'  => esc_url_raw( rest_url( 'infinitycod/v1/' ) ),
			'rtl'      => \InfinityCod\Core\I18n::is_rtl(),
			'i18n'     => array(
				'loading'        => __( 'Chargement…', 'infinitycod' ),
				'chooseCommune'  => __( '— Commune —', 'infinitycod' ),
				'chooseDesk'     => __( '— Choisir un bureau —', 'infinitycod' ),
				'noDesks'        => __( 'Aucun bureau disponible pour cette wilaya', 'infinitycod' ),
				'free'           => __( 'Gratuite', 'infinitycod' ),
				'error'          => __( 'Une erreur est survenue, réessayez.', 'infinitycod' ),
				'errorName'      => __( 'Veuillez saisir votre nom complet.', 'infinitycod' ),
				'errorPhone'     => __( 'Numéro algérien invalide (ex. 0555123456).', 'infinitycod' ),
				'errorWilaya'    => __( 'Veuillez choisir votre wilaya.', 'infinitycod' ),
				'errorCommune'   => __( 'Veuillez choisir votre commune.', 'infinitycod' ),
				'errorAttrs'     => __( 'Veuillez choisir les options du produit.', 'infinitycod' ),
				'errorDesk'      => __( 'Veuillez choisir un bureau de retrait.', 'infinitycod' ),
				'sending'        => __( 'Envoi en cours…', 'infinitycod' ),
				'blocked'        => __( 'Commande refusée. Si c‘est une erreur, contactez-nous par téléphone.', 'infinitycod' ),
				'successTitle'   => Settings::get( 'success_title' ),
				'successText'    => Settings::get( 'success_text' ),
				'redirecting'    => __( 'Vous allez être redirigé dans {s} secondes…', 'infinitycod' ),
				'freeBar'        => Settings::get( 'free_amount_message' ),
				'freeTarget'     => (float) Settings::get( 'free_amount_threshold', 0 ),
				'errorEmailFormat' => __( 'Adresse email invalide.', 'infinitycod' ),
				'da'             => __( 'DA', 'infinitycod' ),
			),
		) );
	}
}
