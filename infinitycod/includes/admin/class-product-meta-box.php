<?php
/**
 * Métabox produit : offres par quantité personnalisées.
 *
 * @package InfinityCod
 */

namespace InfinityCod\Admin;

use InfinityCod\Form\OffersEngine;

defined( 'ABSPATH' ) || exit;

class ProductMetaBox {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
		add_action( 'save_post_product', array( $this, 'save' ), 10, 1 );
	}

	/**
	 * Déclare la métabox.
	 *
	 * @return void
	 */
	public function add() {
		add_meta_box(
			'icod_offers',
			__( 'InfinityCod — Offres par quantité', 'infinitycod' ),
			array( $this, 'render' ),
			'product',
			'normal',
			'default'
		);
	}

	/**
	 * Affiche la métabox.
	 *
	 * @param \WP_Post $post Produit.
	 * @return void
	 */
	public function render( $post ) {
		$tiers  = OffersEngine::tiers_for_product( $post->ID );
		$custom = get_post_meta( $post->ID, '_icod_offers', true );

		// Format d'édition lisible : « 2=10, 3=15 » (quantité = remise %).
		$pairs = array();
		foreach ( (array) $tiers as $qty => $pct ) {
			$pairs[] = $qty . '=' . round( (float) $pct, 2 );
		}

		wp_nonce_field( 'icod_offers_save', 'icod_offers_nonce' );
		?>
		<p class="description">
			<?php esc_html_e( 'Paliers de remise par quantité pour ce produit. Format : quantité=remise%, séparés par des virgules. Exemple : 2=10, 3=15, 5=20', 'infinitycod' ); ?>
		</p>
		<?php
		$is_custom = '' !== (string) $custom;
		?>
		<p>
			<label>
				<input type="checkbox" name="icod_offers_use_custom" value="1" <?php checked( $is_custom ); ?> />
				<?php esc_html_e( 'Paliers personnalisés actifs', 'infinitycod' ); ?>
			</label>
		</p>
		<p>
			<input type="text" name="icod_offers_tiers" class="regular-text" dir="ltr"
				value="<?php echo esc_attr( implode( ', ', $pairs ) ); ?>"
				placeholder="2=10, 3=15, 5=20" />
		</p>
		<?php
		$global = OffersEngine::global_tiers();
		$hint   = array();
		foreach ( $global as $qty => $pct ) {
			$hint[] = $qty . '→−' . round( (float) $pct, 2 ) . '%';
		}
		?>
		<p class="description">
			<?php
			printf(
				/* translators: %s : paliers globaux. */
				esc_html__( 'Paliers globaux actuels : %s (modifiables via le filtre infinitycod_offers_global_tiers).', 'infinitycod' ),
				esc_html( implode( ' · ', $hint ) )
			);
			?>
		</p>
		<?php
	}

	/**
	 * Sauvegarde.
	 *
	 * @param int $post_id ID du produit.
	 * @return void
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST['icod_offers_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['icod_offers_nonce'] ) ), 'icod_offers_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- vérifié ci-dessus.
		$use_custom = ! empty( $_POST['icod_offers_use_custom'] );
		$raw        = isset( $_POST['icod_offers_tiers'] ) ? sanitize_text_field( wp_unslash( $_POST['icod_offers_tiers'] ) ) : '';
		// phpcs:enable

		if ( ! $use_custom ) {
			delete_post_meta( $post_id, '_icod_offers' );
			return;
		}

		$tiers = array();
		foreach ( explode( ',', $raw ) as $pair ) {
			$parts = array_map( 'trim', explode( '=', $pair ) );
			if ( 2 !== count( $parts ) ) {
				continue;
			}
			$qty = (int) $parts[0];
			$pct = (float) str_replace( ',', '.', $parts[1] );
			if ( $qty >= 2 && $qty <= 99 && $pct > 0 && $pct <= 90 ) {
				$tiers[] = array( 'qty' => $qty, 'pct' => $pct );
			}
		}

		update_post_meta( $post_id, '_icod_offers', wp_json_encode( $tiers ) );
	}
}
