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
		// Verrou licence : le formulaire est masqué tant qu'aucune licence
		// n'est active (réglage « distribution commerciale », désactivé par
		// défaut). L'administrateur voit une explication à la place.
		if ( Settings::get( 'license_lock_form' ) && ! \InfinityCod\License\LicenseManager::is_premium() ) {
			if ( current_user_can( 'manage_woocommerce' ) ) {
				return '<div class="icod-form-locked-admin">' . esc_html__( '🔒 Le formulaire est masqué pour les visiteurs : « Verrouiller le formulaire sans licence » est actif (Réglages → Avancé). Activez votre licence dans Réglages → Licence pour le réafficher.', 'infinitycod' ) . '</div>';
			}
			return '';
		}

		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product->is_purchasable() ) {
			return '';
		}

		$this->enqueue();

		// Arabe / RTL : la police Cairo est chargée uniquement pour le
		// formulaire (jamais sur tout le site) et sert de 1re police.
		if ( $rtl ) {
			$this->enqueue_cairo();
		}

		$geo     = infinitycod()->module( 'geo' );
		$countries = Settings::active_countries();
		$all_wilayas = $geo ? $geo->wilayas( true ) : array();
		$wilayas   = array_values( array_filter( $all_wilayas, function ( $w ) use ( $countries ) {
			$wc = isset( $w['country_code'] ) ? $w['country_code'] : 'DZ';
			return in_array( $wc, $countries, true );
		} ) );
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
		$payment_online   = infinitycod()->module( 'payment' ) ? \InfinityCod\Payment\PaymentManager::enabled() : false;

		$ts  = time();
		$sig = Shield::sign_timestamp( $ts );

		$wilaya_options = '';
		foreach ( $wilayas as $w ) {
			$wilaya_options .= sprintf(
				'<option value="%1$s" data-country="%3$s">%2$s</option>',
				esc_attr( $w['code'] ),
				esc_html( $geo->wilaya_label( $w ) ),
				esc_attr( isset( $w['country_code'] ) ? $w['country_code'] : 'DZ' )
			);
		}

		// Sélecteur de pays : affiché uniquement si Premium multi-pays actif.
		$country_select = '';
		if ( count( $countries ) > 1 ) {
			$catalog = \InfinityCod\Core\Activator::countries_catalog();
			$default_country = Settings::default_country();
			$country_options = '';
			foreach ( $countries as $cc ) {
				$label = isset( $catalog[ $cc ] ) ? $catalog[ $cc ]['fr'] . ' — ' . $catalog[ $cc ]['ar'] : $cc;
				$country_options .= sprintf(
					'<option value="%1$s"%3$s>%2$s</option>',
					esc_attr( $cc ),
					esc_html( $label ),
					$default_country === $cc ? ' selected' : ''
				);
			}
			$country_select = '<div class="icod-field"><label>' . esc_html__( 'Pays', 'infinitycod' ) . '</label><div class="icod-input-wrap">' . self::field_icon( 'map' ) . '<select id="icod-country" class="icod-input icod-country">' . $country_options . '</select></div></div>';
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
			data-regular-price="<?php echo esc_attr( $product->is_type( 'variable' ) ? '' : $product->get_regular_price() ); ?>"
			data-qty-max="<?php echo esc_attr( (int) Settings::get( 'qty_max', 20 ) ); ?>"
			data-sticky="<?php echo esc_attr( (int) Settings::get( 'sticky_bar', 1 ) ); ?>"
			data-redirect="<?php echo $redirect_on ? esc_attr( Settings::get( 'redirect_url' ) ) : ''; ?>"
			data-redirect-delay="<?php echo $redirect_on ? (int) $redirect_delay : 0; ?>"
			style="max-width:<?php echo (int) $max_width; ?>px;--icod-accent:<?php echo esc_attr( $accent ); ?>"
			dir="<?php echo $rtl ? 'rtl' : 'ltr'; ?>">

			<style>:root{--icod-accent:<?php echo esc_attr( $accent ); ?>;}</style>

			<section class="icod-card" aria-labelledby="icod-form-title">
				<header class="icod-head">
					<?php
					$thumb_url = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
					if ( $thumb_url ) :
						?>
						<img class="icod-head-thumb" src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy" />
					<?php endif; ?>
					<span class="icod-head-icon" aria-hidden="true"><?php echo esc_html( Settings::get( 'form_icon', '🛒' ) ); ?></span>
					<div class="icod-head-text">
						<h2 class="icod-form-title" id="icod-form-title"><?php echo esc_html( $title ); ?></h2>
						<?php if ( Settings::get( 'form_subtitle' ) ) : ?>
							<p class="icod-form-subtitle"><?php echo esc_html( Settings::get( 'form_subtitle' ) ); ?></p>
						<?php endif; ?>
					</div>
					<span class="icod-head-price" data-head-price>
						<?php
						$head_regular = (float) $product->get_regular_price();
						$head_price   = (float) $product->get_price();
						$decimals     = fmod( $head_price, 1 ) ? 2 : 0;
						if ( $head_regular > $head_price && $head_regular > 0 && ! $product->is_type( 'variable' ) ) :
							?>
							<del data-head-price-regular><?php echo esc_html( number_format_i18n( $head_regular, fmod( $head_regular, 1 ) ? 2 : 0 ) . ' ' . __( 'DA', 'infinitycod' ) ); ?></del>
						<?php else : ?>
							<del data-head-price-regular class="icod-hidden"></del>
						<?php endif; ?>
						<ins data-head-price-live><?php echo esc_html( number_format_i18n( $head_price, $decimals ) . ' ' . __( 'DA', 'infinitycod' ) ); ?></ins>
					</span>
				</header>
			<?php if ( ! $product->is_type( 'variable' ) && $product->managing_stock() && $product->get_stock_quantity() !== null ) : ?>
			<div class="icod-stock-badge" data-stock-badge><span class="dot"></span><?php printf( esc_html__( '%d pièces disponibles', 'infinitycod' ), (int) $product->get_stock_quantity() ); ?></div>
			<?php endif; ?>
			<?php if ( Settings::get( 'captcha_enabled' ) ) : ?>
			<?php $c1 = wp_rand( 2, 12 ); $c2 = wp_rand( 2, 12 ); $cap_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''; ?>
			<?php set_transient( 'icod_cap_' . md5( $cap_ip ), $c1 + $c2, 15 * MINUTE_IN_SECONDS ); ?>
			<div class="icod-field icod-captcha-field">
				<label for="icod-captcha"><?php printf( esc_html__( 'Anti-bot : %d + %d = ?', 'infinitycod' ), $c1, $c2 ); ?></label>
				<input type="number" id="icod-captcha" name="icod_captcha" class="icod-input" required inputmode="numeric" />
			</div>
			<?php endif; ?>

			<div class="icod-progress" aria-hidden="true"><div class="icod-progress-fill" data-progress-fill></div></div>

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
									$single          = ( count( $terms ) === 1 );
									?>
									<div class="icod-field icod-variants" data-attribute="<?php echo esc_attr( $taxonomy ); ?>">
										<label><?php echo esc_html( $attribute_label ); ?></label>
										<div class="icod-chipset" role="radiogroup" aria-label="<?php echo esc_attr( $attribute_label ); ?>">
											<?php foreach ( $terms as $term ) : ?>
												<?php $slug = sanitize_title( $term ); ?>
												<label class="icod-chip">
													<input type="radio" class="icod-attr" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>" name="icod_attr_<?php echo esc_attr( $taxonomy ); ?>" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $single ); ?> />
													<span><?php echo esc_html( $term ); ?></span>
												</label>
											<?php endforeach; ?>
										</div>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>

							<div class="icod-duo">
								<div class="icod-field">
									<label for="icod-name-<?php echo esc_attr( $product->get_id() ); ?>"><?php echo esc_html( $label_name ); ?></label>
									<div class="icod-input-wrap">
										<?php echo self::field_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG interne. ?>
										<input type="text" name="icod_name" id="icod-name-<?php echo esc_attr( $product->get_id() ); ?>" class="icod-input icod-input-name" autocomplete="name" required data-icod-field="name" />
									</div>
								</div>

								<div class="icod-field">
									<label for="icod-phone-<?php echo esc_attr( $product->get_id() ); ?>"><?php echo esc_html( $label_phone ); ?></label>
									<div class="icod-input-wrap">
										<?php echo self::field_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG interne. ?>
										<input type="tel" name="icod_phone" id="icod-phone-<?php echo esc_attr( $product->get_id() ); ?>" class="icod-input icod-input-phone" inputmode="tel" autocomplete="tel" placeholder="<?php echo esc_attr( Settings::get( 'phone_placeholder' ) ); ?>" required data-icod-field="phone" />
									</div>
								</div>
							</div>

							<?php if ( $show_email ) : ?>
							<div class="icod-field">
								<label for="icod-email-<?php echo esc_attr( $product->get_id() ); ?>"><?php echo esc_html( Settings::get( 'label_email' ) ); ?></label>
								<div class="icod-input-wrap">
									<?php echo self::field_icon( 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG interne. ?>
									<input type="email" name="icod_email" id="icod-email-<?php echo esc_attr( $product->get_id() ); ?>" class="icod-input" autocomplete="email" />
								</div>
							</div>
							<?php endif; ?>




							<?php echo $country_select; // phpcs:ignore WordPress.Security.EscapeOutput -- construit échappé. ?>

							<div class="icod-row">
								<div class="icod-field">
									<label for="icod-wilaya-<?php echo esc_attr( $product->get_id() ); ?>"><?php echo esc_html( $label_wilaya ); ?></label>
									<div class="icod-input-wrap">
										<?php echo self::field_icon( 'map' ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG interne. ?>
										<select name="icod_wilaya" id="icod-wilaya-<?php echo esc_attr( $product->get_id() ); ?>" class="icod-input icod-wilaya" required data-icod-field="wilaya">
											<option value=""><?php esc_html_e( '— Wilaya —', 'infinitycod' ); ?></option>
											<?php echo $wilaya_options; // phpcs:ignore WordPress.Security.EscapeOutput -- construit échappé. ?>
										</select>
									</div>
								</div>

								<div class="icod-field">
									<label for="icod-commune-<?php echo esc_attr( $product->get_id() ); ?>"><?php echo esc_html( $label_commune ); ?></label>
									<div class="icod-input-wrap">
										<?php echo self::field_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG interne. ?>
										<select name="icod_commune" id="icod-commune-<?php echo esc_attr( $product->get_id() ); ?>" class="icod-input icod-commune" disabled required data-icod-field="commune">
											<option value=""><?php esc_html_e( '— Commune —', 'infinitycod' ); ?></option>
										</select>
										<input type="text" name="icod_commune_text" id="icod-commune-text-<?php echo esc_attr( $product->get_id() ); ?>" class="icod-input icod-commune-text icod-hidden" autocomplete="address-level2" placeholder="<?php esc_attr_e( 'Votre ville', 'infinitycod' ); ?>" />
									</div>
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
									<div class="icod-input-wrap">
										<?php echo self::field_icon( 'building' ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG interne. ?>
										<select name="icod_desk" id="icod-desk-<?php echo esc_attr( $product->get_id() ); ?>" class="icod-input icod-desk">
											<option value=""><?php esc_html_e( '— Choisir un bureau —', 'infinitycod' ); ?></option>
										</select>
									</div>
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

							<?php
				// Champs personnalisés du Checkout Builder.
				foreach ( (array) Settings::get( 'checkout_fields', array() ) as $cf ) {
					if ( empty( $cf['on'] ) || strpos( (string) $cf['key'], 'cf_' ) !== 0 ) { continue; }
					$_lbl = $cf['label'] !== '' ? $cf['label'] : $cf['key'];
					$_req = ! empty( $cf['req'] ) ? ' required' : '';
					$_id  = 'icod-' . esc_attr( $cf['key'] );
					echo '<div class="icod-field"><label for="' . $_id . '">' . esc_html( $_lbl ) . '</label>';
					if ( 'textarea' === $cf['type'] ) {
						echo '<textarea class="icod-input" name="' . esc_attr( $cf['key'] ) . '" rows="2"' . $_req . '></textarea>';
					} elseif ( 'checkbox' === $cf['type'] ) {
						echo '<label style="display:flex;gap:6px;align-items:center;font-weight:400"><input type="checkbox" name="' . esc_attr( $cf['key'] ) . '" value="1"' . $_req . ' /> ' . esc_html( $_lbl ) . '</label>';
					} else {
						echo '<input class="icod-input" type="' . esc_attr( $cf['type'] ) . '" name="' . esc_attr( $cf['key'] ) . '"' . $_req . ' />';
					}
					echo '</div>';
				}
				?>
				<a href="#" class="icod-note-toggle" data-note-toggle>＋ <?php esc_html_e( 'Ajouter une note', 'infinitycod' ); ?></a>
				<?php if ( $show_note ) : ?>
								<div class="icod-field">
									<label for="icod-note-<?php echo esc_attr( $product->get_id() ); ?>"><?php echo esc_html( $label_note ); ?></label>
									<div class="icod-input-wrap icod-input-wrap-area">
										<?php echo self::field_icon( 'note' ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG interne. ?>
										<textarea name="icod_note" id="icod-note-<?php echo esc_attr( $product->get_id() ); ?>" class="icod-input icod-note icod-hidden" rows="2" maxlength="500"></textarea>
									</div>
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
													<span class="icod-paylogos"><?php echo self::payment_logo( 'cash' ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG interne. ?></span>
												</span>
											</label>
											<label class="icod-mode-option">
												<input type="radio" name="icod_payment" value="online" class="icod-pay-radio" />
												<span class="icod-mode-box">
													<span class="icod-mode-title"><?php echo esc_html( Settings::get( 'payment_label' ) ); ?></span>
													<span class="icod-mode-sub"><?php esc_html_e( 'Paiement sécurisé CIB / Edahabia', 'infinitycod' ); ?></span>
													<span class="icod-paylogos"><?php echo self::payment_logo( 'cib' ) . self::payment_logo( 'edahabia' ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG internes. ?></span>
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

						<div class="icod-summary icod-summary-bottom" role="status" aria-live="polite">
								<button type="button" class="icod-summary-head" data-summary-toggle><span>🧾 <?php esc_html_e( 'Récapitulatif', 'infinitycod' ); ?></span><span class="chev">⌃</span></button><div class="icod-summary-body"><?php esc_html_e( '🧾 Récapitulatif', 'infinitycod' ); ?></p>
								<div class="icod-summary-line icod-summary-product">
									<span class="icod-summary-product-name"><?php echo esc_html( wp_trim_words( $product->get_name(), 6 ) ); ?></span>
									<span class="icod-summary-qty" data-summary-qty>×1</span>
								</div>
								<div class="icod-summary-line"><span><?php esc_html_e( 'Prix unitaire', 'infinitycod' ); ?></span><span data-summary-unit>—</span></div>
								<div class="icod-coupon">
									<input type="text" name="icod_coupon" class="icod-coupon-input" data-icod-coupon-input placeholder="<?php esc_attr_e( 'Code promo', 'infinitycod' ); ?>" autocomplete="off" aria-label="<?php esc_attr_e( 'Code promo', 'infinitycod' ); ?>" />
									<button type="button" class="icod-coupon-apply" data-icod-coupon-apply><?php esc_html_e( 'Appliquer', 'infinitycod' ); ?></button>
								</div>
								<div class="icod-coupon-msg icod-hidden" data-coupon-msg role="status"></div>
								<?php if ( Settings::get( 'free_amount_enabled' ) ) : ?>
								<div class="icod-freebar icod-hidden" data-icod-freebar>
									<span data-icod-freebar-text></span>
									<div class="icod-freebar-track"><div class="icod-freebar-fill" data-icod-freebar-fill></div></div>
								</div>
								<?php endif; ?>
								<div class="icod-summary-line"><span><?php esc_html_e( 'Sous-total', 'infinitycod' ); ?></span><span data-summary-subtotal>—</span></div>
								<div class="icod-summary-line icod-hidden" data-summary-discount-row><span data-summary-discount-label><?php esc_html_e( 'Remise', 'infinitycod' ); ?></span><span data-summary-discount>—</span></div>
								<div class="icod-summary-line icod-hidden" data-summary-coupon-row><span data-summary-coupon-label><?php esc_html_e( 'Code promo', 'infinitycod' ); ?></span><span data-summary-coupon>—</span></div>
								<div class="icod-summary-line"><span><?php esc_html_e( 'Livraison', 'infinitycod' ); ?></span><span data-summary-shipping>—</span></div>
								<div class="icod-summary-total"><span><?php esc_html_e( 'Total à payer', 'infinitycod' ); ?></span><span data-summary-total>—</span></div>
							</div>

							<aside class="icod-aside">
							<div class="icod-msg icod-hidden" data-icod-msg role="alert"></div>

							<button type="submit" class="icod-submit">
								<?php echo esc_html( $button ); ?>
							</button>

							<?php if ( $wa_order ) : ?>
								<button type="button" class="icod-submit icod-wa-btn" data-icod-wa>
									💬 <?php echo esc_html( Settings::get( 'wa_order_label' ) ); ?>
								</button>
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

			<div class="icod-success icod-success-<?php echo esc_attr( Settings::get( 'success_style', 'classic' ) ); ?> icod-hidden" data-icod-success hidden>
				<div class="icod-success-icon" aria-hidden="true"><span>✓</span></div>
				<h3 data-icod-success-title><?php echo esc_html( Settings::get( 'success_title' ) ); ?></h3>

				<div class="icod-success-recap">
					<div class="icod-success-num"><span><?php esc_html_e( 'N° de commande', 'infinitycod' ); ?></span><strong data-sd-num>—</strong></div>
					<div class="icod-success-line"><span><?php esc_html_e( 'Produit', 'infinitycod' ); ?></span><strong data-sd-product>—</strong></div>
					<div class="icod-success-line"><span><?php esc_html_e( 'Livraison', 'infinitycod' ); ?></span><strong data-sd-mode>—</strong></div>
					<div class="icod-success-line icod-success-total"><span><?php esc_html_e( 'Total à payer', 'infinitycod' ); ?></span><strong data-sd-total>—</strong></div>
				</div>

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
	 * Icônes SVG des champs (style feather, stroke = couleur courante).
	 *
	 * @param string $name user|phone|email|map|pin|home|building|note.
	 * @return string SVG inline.
	 */
	private static function field_icon( $name ) {
		$paths = array(
			'user'     => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
			'phone'    => '<rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>',
			'email'    => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
			'map'      => '<polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/>',
			'pin'      => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
			'home'     => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
			'building' => '<rect x="4" y="3" width="16" height="18" rx="1.5"/><line x1="9" y1="8" x2="9.01" y2="8"/><line x1="15" y1="8" x2="15.01" y2="8"/><line x1="9" y1="12" x2="9.01" y2="12"/><line x1="15" y1="12" x2="15.01" y2="12"/><path d="M10 21v-4h4v4"/>',
			'note'     => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
		);

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return '<svg class="icod-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	/**
	 * Logos SVG des moyens de paiement algériens (style officiel simplifié).
	 *
	 * @param string $method cash|cib|edahabia|baridimob|ccp.
	 * @return string SVG inline.
	 */
	public static function payment_logo( $method ) {
		// Priorité aux logos officiels : déposez cib.svg/png, edahabia.svg/png, etc.
		// dans assets/front/img/pay/ — ils remplacent automatiquement les visuels par défaut.
		foreach ( array( 'svg', 'png', 'webp' ) as $ext ) {
			$file = INFINITYCOD_PATH . 'assets/front/img/pay/' . $method . '.' . $ext;
			if ( file_exists( $file ) ) {
				return '<img class="icod-paylogo" src="' . esc_url( INFINITYCOD_URL . 'assets/front/img/pay/' . $method . '.' . $ext ) . '" alt="' . esc_attr( $method ) . '" width="52" height="33" loading="lazy" />';
			}
		}
		$common = 'class="icod-paylogo" role="img" width="52" height="33" viewBox="0 0 64 40" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"';

		switch ( $method ) {
			case 'cib':
				return '<svg ' . $common . '>'
					. '<rect x="1" y="1" width="62" height="38" rx="6" fill="#ffffff" stroke="#d8dde3"/>'
					. '<path d="M1 12 L63 4 L63 12 L1 20 Z" fill="#0f4d8f"/>'
					. '<path d="M1 22 L63 14 L63 20 L1 28 Z" fill="#f7b600"/>'
					. '<text x="32" y="35" text-anchor="middle" font-family="Arial, sans-serif" font-weight="bold" font-size="10" fill="#0f4d8f">CIB</text>'
					. '</svg>';

			case 'edahabia':
				return '<svg ' . $common . '>'
					. '<rect x="1" y="1" width="62" height="38" rx="6" fill="#c99a2e"/>'
					. '<rect x="1" y="1" width="62" height="38" rx="6" fill="url(#icod-gold)" stroke="#a67c1e"/>'
					. '<defs><linearGradient id="icod-gold" x1="0" y1="0" x2="0" y2="1">'
					. '<stop offset="0" stop-color="#e8c25a"/><stop offset="1" stop-color="#b8891f"/>'
					. '</linearGradient></defs>'
					. '<path d="M32 8 l2.2 4.4 4.8 .7 -3.5 3.4 .8 4.8 -4.3 -2.3 -4.3 2.3 .8 -4.8 -3.5 -3.4 4.8 -.7 Z" fill="#fff"/>'
					. '<text x="32" y="34" text-anchor="middle" font-family="Arial, sans-serif" font-weight="bold" font-size="9" fill="#ffffff">Edahabia</text>'
					. '</svg>';

			case 'baridimob':
				return '<svg ' . $common . '>'
					. '<rect x="1" y="1" width="62" height="38" rx="6" fill="#ffffff" stroke="#d8dde3"/>'
					. '<rect x="1" y="1" width="62" height="9" rx="6" fill="#f7b600"/>'
					. '<rect x="24" y="13" width="16" height="22" rx="3" fill="none" stroke="#0f4d8f" stroke-width="2.4"/>'
					. '<path d="M29 17 l6 6 M35 17 l-6 6" stroke="#0f4d8f" stroke-width="2"/>'
					. '<text x="13.5" y="29" text-anchor="middle" font-family="Arial, sans-serif" font-weight="bold" font-size="8" fill="#0f4d8f">B</text>'
					. '<text x="50" y="29" text-anchor="middle" font-family="Arial, sans-serif" font-weight="bold" font-size="8" fill="#0f4d8f">M</text>'
					. '</svg>';

			case 'ccp':
				return '<svg ' . $common . '>'
					. '<rect x="1" y="1" width="62" height="38" rx="6" fill="#ffffff" stroke="#d8dde3"/>'
					. '<rect x="1" y="26" width="62" height="13" rx="6" fill="#0f4d8f"/>'
					. '<text x="32" y="18" text-anchor="middle" font-family="Arial, sans-serif" font-weight="bold" font-size="12" fill="#0f4d8f">CCP</text>'
					. '<text x="32" y="35" text-anchor="middle" font-family="Arial, sans-serif" font-size="7" fill="#ffffff">Algérie Poste</text>'
					. '</svg>';

			case 'cash':
			default:
				return '<svg ' . $common . '>'
					. '<rect x="1" y="1" width="62" height="38" rx="6" fill="#e5f2e9" stroke="#0e7a4f"/>'
					. '<rect x="10" y="11" width="44" height="22" rx="3" fill="#ffffff" stroke="#0e7a4f" stroke-width="1.5"/>'
					. '<circle cx="32" cy="22" r="6.5" fill="#e5f2e9" stroke="#0e7a4f" stroke-width="1.5"/>'
					. '<text x="32" y="25.5" text-anchor="middle" font-family="Arial, sans-serif" font-weight="bold" font-size="7.5" fill="#0e7a4f">DA</text>'
					. '</svg>';
		}
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
	 * Charge la police Cairo pour le rendu arabe du formulaire.
	 *
	 * Même logique que enqueue() : si wp_head est déjà passé, le <link>
	 * est écrit inline faute de pouvoir être mis en file.
	 *
	 * @return void
	 */
	private function enqueue_cairo() {
		$handle = 'icod-font-cairo';
		$src    = 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap';

		if ( wp_style_is( $handle, 'registered' ) ) {
			wp_enqueue_style( $handle );
		} else {
			wp_register_style( $handle, $src, array(), null );
			wp_enqueue_style( $handle );
		}

		// Fallback identique au CSS du formulaire si wp_head est passé.
		if ( function_exists( 'did_action' ) && did_action( 'wp_head' ) && ! wp_style_is( $handle, 'done' ) ) {
			printf(
				'<link rel="stylesheet" id="%1$s-css" href="%2$s" media="all" />',
				esc_attr( $handle ),
				esc_url( $src )
			);
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
			'currency' => Settings::currency(),
			'da'       => Settings::currency_label(),
			'defaultCountry' => Settings::default_country(),
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
				'coupon'         => __( 'Code promo', 'infinitycod' ),
				'couponOk'       => __( 'Code promo appliqué !', 'infinitycod' ),
				'couponBad'      => __( 'Code promo invalide.', 'infinitycod' ),
				'couponExpired'  => __( 'Code promo expiré.', 'infinitycod' ),
				'couponUsed'     => __( 'Code promo déjà utilisé.', 'infinitycod' ),
				'couponMin'      => __( 'Montant minimum non atteint pour ce code.', 'infinitycod' ),
				'successTitle'   => Settings::get( 'success_title' ),
				'successText'    => Settings::get( 'success_text' ),
				'redirecting'    => __( 'Vous allez être redirigé dans {s} secondes…', 'infinitycod' ),
				'freeBar'        => Settings::get( 'free_amount_message' ),
				'freeTarget'     => (float) Settings::get( 'free_amount_threshold', 0 ),
				'errorEmailFormat' => __( 'Adresse email invalide.', 'infinitycod' ),
				'da'             => Settings::currency_label(),
			),
		) );
	}
}
