<?php
/**
 * ∞ Infinity Coder — Gabarit du formulaire COD (shortcode [infinity_cod]).
 *
 * Variables disponibles : $inf_a (attributs du shortcode).
 *
 * @package infinity-market
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$inf_price     = max( 0, (float) $inf_a['price'] );
$inf_old_price = max( 0, (float) $inf_a['old_price'] );
$inf_product   = $inf_a['product'];
$inf_token     = inf_price_token( $inf_product, $inf_price );
$inf_time      = time();
?>
<section class="inf-cod" id="inf-cod" data-unit-price="<?php echo esc_attr( (string) $inf_price ); ?>">
	<div class="inf-cod__card">
		<header class="inf-cod__head">
			<h3 class="inf-cod__title"><?php echo esc_html( $inf_a['title'] ); ?></h3>
			<p class="inf-cod__pay">
				<?php inf_icon( 'cash' ); ?>
				<?php esc_html_e( 'Payez en espèces à la livraison — partout en Algérie (58 wilayas)', 'infinity-market' ); ?>
			</p>
		</header>

		<?php if ( $inf_product ) : ?>
		<div class="inf-cod__product">
			<?php if ( $inf_a['image'] ) : ?>
				<img class="inf-cod__thumb" src="<?php echo esc_url( $inf_a['image'] ); ?>" alt="<?php echo esc_attr( $inf_product ); ?>">
			<?php endif; ?>
			<div>
				<p class="inf-cod__product-name"><?php echo esc_html( $inf_product ); ?></p>
				<p class="inf-cod__product-price">
					<strong><?php echo esc_html( inf_price( $inf_price ) ); ?></strong>
					<?php if ( $inf_old_price > 0 && $inf_old_price > $inf_price ) : ?>
						<del><?php echo esc_html( inf_price( $inf_old_price ) ); ?></del>
					<?php endif; ?>
				</p>
			</div>
		</div>
		<?php endif; ?>

		<form class="inf-cod__form" method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
			<input type="hidden" name="action" value="inf_cod_submit">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'inf_cod' ) ); ?>">
			<input type="hidden" name="inf_product" value="<?php echo esc_attr( $inf_product ); ?>">
			<input type="hidden" name="inf_price" value="<?php echo esc_attr( (string) $inf_price ); ?>">
			<input type="hidden" name="inf_token" value="<?php echo esc_attr( $inf_token ); ?>">
			<input type="hidden" name="inf_form_time" value="<?php echo esc_attr( (string) $inf_time ); ?>">

			<!-- Honeypot : à laisser vide (robots) -->
			<p class="inf-hp" aria-hidden="true">
				<label>Site web <input type="text" name="inf_website" tabindex="-1" autocomplete="off"></label>
			</p>

			<div class="inf-cod__grid">
				<label class="inf-field">
					<span class="inf-field__label"><?php esc_html_e( 'Nom complet *', 'infinity-market' ); ?></span>
					<input type="text" name="inf_name" class="inf-field__input" required minlength="3" placeholder="<?php esc_attr_e( 'Votre nom et prénom', 'infinity-market' ); ?>">
					<span class="inf-field__error"></span>
				</label>

				<label class="inf-field">
					<span class="inf-field__label"><?php esc_html_e( 'Téléphone *', 'infinity-market' ); ?></span>
					<input type="tel" name="inf_phone" class="inf-field__input" required inputmode="tel" pattern="0[5-7][0-9]{8}" placeholder="0555 12 34 56" title="<?php esc_attr_e( 'Format : 0555123456', 'infinity-market' ); ?>">
					<span class="inf-field__error"></span>
				</label>

				<label class="inf-field">
					<span class="inf-field__label"><?php esc_html_e( 'Wilaya *', 'infinity-market' ); ?></span>
					<select name="inf_wilaya" class="inf-field__input inf-cod__wilaya" required>
						<option value=""><?php esc_html_e( '— Choisissez votre wilaya —', 'infinity-market' ); ?></option>
						<?php foreach ( inf_get_wilayas() as $inf_code => $inf_w ) : ?>
							<?php if ( empty( $inf_w['active'] ) ) { continue; } ?>
							<option value="<?php echo esc_attr( $inf_code ); ?>" data-home="<?php echo esc_attr( (string) $inf_w['home'] ); ?>" data-desk="<?php echo esc_attr( (string) $inf_w['desk'] ); ?>">
								<?php echo esc_html( (int) $inf_code . ' — ' . $inf_w['fr'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<span class="inf-field__error"></span>
				</label>

				<label class="inf-field">
					<span class="inf-field__label"><?php esc_html_e( 'Commune *', 'infinity-market' ); ?></span>
					<input type="text" name="inf_commune" class="inf-field__input" required placeholder="<?php esc_attr_e( 'Ex. : Bab Ezzouar', 'infinity-market' ); ?>">
					<span class="inf-field__error"></span>
				</label>

				<label class="inf-field inf-field--full">
					<span class="inf-field__label"><?php esc_html_e( 'Adresse (facultatif)', 'infinity-market' ); ?></span>
					<input type="text" name="inf_address" class="inf-field__input" placeholder="<?php esc_attr_e( 'Rue, immeuble, point de repère…', 'infinity-market' ); ?>">
				</label>

				<div class="inf-field inf-field--full">
					<span class="inf-field__label"><?php esc_html_e( 'Mode de livraison', 'infinity-market' ); ?></span>
					<div class="inf-delivery">
						<label class="inf-delivery__opt">
							<input type="radio" name="inf_delivery" value="home" checked>
							<span class="inf-delivery__box"><?php inf_icon( 'pin' ); ?><strong><?php esc_html_e( 'À domicile', 'infinity-market' ); ?></strong></span>
						</label>
						<label class="inf-delivery__opt">
							<input type="radio" name="inf_delivery" value="desk">
							<span class="inf-delivery__box"><?php inf_icon( 'box' ); ?><strong><?php esc_html_e( 'Bureau (Stopdesk)', 'infinity-market' ); ?></strong></span>
						</label>
					</div>
				</div>

				<div class="inf-field">
					<span class="inf-field__label"><?php esc_html_e( 'Quantité', 'infinity-market' ); ?></span>
					<div class="inf-qty">
						<button type="button" class="inf-qty__btn" data-qty="-1" aria-label="<?php esc_attr_e( 'Moins', 'infinity-market' ); ?>">−</button>
						<input type="number" name="inf_qty" class="inf-qty__input inf-cod__qty" value="1" min="1" max="99">
						<button type="button" class="inf-qty__btn" data-qty="1" aria-label="<?php esc_attr_e( 'Plus', 'infinity-market' ); ?>">+</button>
					</div>
				</div>

				<label class="inf-field">
					<span class="inf-field__label"><?php esc_html_e( 'Remarques (facultatif)', 'infinity-market' ); ?></span>
					<input type="text" name="inf_notes" class="inf-field__input" placeholder="<?php esc_attr_e( 'Couleur, taille, horaire d\'appel…', 'infinity-market' ); ?>">
				</label>
			</div>

			<div class="inf-cod__summary">
				<p><span><?php echo esc_html( __( 'Sous-total', 'infinity-market' ) ); ?></span><span class="inf-cod__subtotal"><?php echo esc_html( inf_price( $inf_price ) ); ?></span></p>
				<p><span><?php echo esc_html( __( 'Livraison', 'infinity-market' ) ); ?></span><span class="inf-cod__fee"><?php esc_html_e( '—', 'infinity-market' ); ?></span></p>
				<p class="inf-cod__summary-total"><span><?php echo esc_html( __( 'Total à payer', 'infinity-market' ) ); ?></span><span class="inf-cod__total"><?php echo esc_html( inf_price( $inf_price ) ); ?></span></p>
			</div>

			<button type="submit" class="inf-btn inf-btn--primary inf-btn--big inf-cod__submit">
				<?php inf_icon( 'cash' ); ?> <?php echo esc_html( $inf_a['button'] ); ?>
			</button>

			<div class="inf-cod__result" role="status" aria-live="polite"></div>

			<p class="inf-cod__trust">
				<?php inf_icon( 'shield' ); ?>
				<?php esc_html_e( 'Aucun paiement en ligne : vous réglez le livreur à la réception du colis.', 'infinity-market' ); ?>
			</p>
		</form>
	</div>
</section>
