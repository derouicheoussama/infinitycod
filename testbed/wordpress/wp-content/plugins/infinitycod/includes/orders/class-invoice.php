<?php
/**
 * Facture PDF — générateur minimal sans dépendance (polices core, A4).
 *
 * Produit une facture d'une page : en-tête boutique, code facture, client,
 * ligne produit, totaux et mention de paiement. Attachable à l'e-mail de
 * confirmation et téléchargeable depuis la fiche commande.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Orders;

use InfinityCod\Core\Schema;
use InfinityCod\Core\Settings;

defined( 'ABSPATH' ) || exit;

class Invoice {

	/**
	 * Hooks : téléchargement admin + e-mail automatique à la confirmation.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_icod_invoice', array( $this, 'download' ) );
		add_action( 'infinitycod_order_status_changed', array( $this, 'maybe_email_invoice' ), 20, 3 );
	}

	/**
	 * Téléchargement admin (PDF en pièce jointe HTTP).
	 *
	 * @return void
	 */
	public function download() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}
		check_admin_referer( 'icod_invoice' );

		$icod_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce vérifié ci-dessus.
		if ( $icod_id < 1 ) {
			wp_die( esc_html__( 'Commande introuvable.', 'infinitycod' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( 'facture-' . $icod_id . '.pdf' ) );
		echo self::build( $icod_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- PDF binaire volontaire.
		exit;
	}

	/**
	 * Joint la facture à l'e-mail de confirmation (si activé + e-mail connu).
	 *
	 * @param int    $icod_id Ligne commande.
	 * @param string $status  Nouveau statut.
	 * @param array  $row     Ligne complète.
	 * @return void
	 */
	public function maybe_email_invoice( $icod_id, $status, $row ) {
		if ( 'confirmed' !== $status || ! Settings::get( 'invoice_enabled', 1 ) || ! Settings::get( 'invoice_email', 1 ) ) {
			return;
		}
		$to = isset( $row['email'] ) && is_email( (string) $row['email'] ) ? (string) $row['email'] : '';
		if ( '' === $to ) {
			return;
		}

		$pdf = self::build( (int) $icod_id );
		if ( '' === $pdf ) {
			return;
		}

		$upload = wp_upload_dir();
		$tmp    = trailingslashit( $upload['basedir'] ) . 'icod-facture-' . (int) $icod_id . '.pdf';
		file_put_contents( $tmp, $pdf ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- pièce jointe temporaire supprimée après envoi.

		$subject = sprintf(
			/* translators: 1 : nom du site, 2 : numéro de commande. */
			__( '[%1$s] Votre facture — commande #%2$d', 'infinitycod' ),
			get_bloginfo( 'name' ),
			(int) $icod_id
		);
		$message = '<p>' . sprintf(
			/* translators: %d : numéro de commande. */
			esc_html__( 'Merci pour votre commande #%d. Votre facture est jointe à cet e-mail.', 'infinitycod' ),
			(int) $icod_id
		) . '</p>';

		wp_mail( $to, $subject, $message, array( 'Content-Type: text/html; charset=utf-8' ), array( $tmp ) );
		wp_delete_file( $tmp );
	}

	/**
	 * Convertit UTF-8 → Windows-1252 (polices core du PDF).
	 *
	 * @param string $s Texte.
	 * @return string
	 */
	private static function to_ansi( $s ) {
		$s = str_replace( array( '’', '‘', '…', '—', '–' ), array( "'", "'", '...', '-', '-' ), (string) $s );
		$out = iconv( 'UTF-8', 'windows-1252//TRANSLIT', $s );
		return false === $out ? preg_replace( '/[^[:ascii:]]/', '', $s ) : $out;
	}

	/**
	 * Échappe une chaîne pour un littéral PDF.
	 *
	 * @param string $s Texte CP1252.
	 * @return string
	 */
	private static function esc( $s ) {
		return str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), (string) $s );
	}

	/**
	 * Construit le PDF de la facture.
	 *
	 * @param int $icod_id Ligne commande.
	 * @return string Octets du PDF ('' si commande introuvable).
	 */
	public static function build( $icod_id ) {
		global $wpdb;
		$table = Schema::table( 'orders' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $icod_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB
		if ( ! is_array( $row ) ) {
			return '';
		}

		$geo    = infinitycod()->module( 'geo' );
		$wrow   = $geo ? $geo->wilaya( (string) $row['wilaya_code'] ) : null;
		$wilaya = is_array( $wrow ) && isset( $wrow['name_fr'] ) ? (string) $wrow['name_fr'] : (string) $row['wilaya_code'];
		$mode   = 'desk' === $row['delivery_mode'] ? __( 'Stopdesk', 'infinitycod' ) : __( 'Domicile', 'infinitycod' );
		$paid   = (int) $row['paid'] ? __( 'PAYÉE', 'infinitycod' ) : __( 'À ENCAISSER', 'infinitycod' );
		$product = $row['product_id'] ? get_the_title( (int) $row['product_id'] ) : '';
		$cur     = Settings::currency_label();

		// ----- Flux de contenu (coord. PDF : origine en bas à gauche) -----
		$c  = "0.06 0.36 0.25 rg\n"; // bandeau vert
		$c .= "0 742 595 50 re f\n";
		$c .= "1 1 1 rg\nBT /F2 17 Tf 36 758 Td (" . self::esc( self::to_ansi( get_bloginfo( 'name' ) ) ) . ") Tj ET\n";
		$facture_lbl = 'Facture FAC-' . (int) $icod_id;
		$c .= "BT /F1 10 Tf 36 748 Td (" . self::esc( self::to_ansi( $facture_lbl ) ) . ") Tj ET" + chr(92) + "n";
		$c .= "0.09 0.14 0.19 rg\n";
		$c .= "BT /F2 13 Tf 36 706 Td (" . self::esc( self::to_ansi( $row['customer_name'] ) ) . ") Tj ET\n";
		$c .= "BT /F1 10 Tf 36 690 Td (" . self::esc( self::to_ansi( 'Tel : ' . $row['phone'] ) ) . ") Tj ET\n";
		$c .= "BT /F1 10 Tf 36 675 Td (" . self::esc( self::to_ansi( $mode . ' - ' . $wilaya . ( $row['commune'] ? ' / ' . $row['commune'] : '' ) . ( $row['stopdesk'] ? ' / ' . $row['stopdesk'] : '' ) ) ) . ") Tj ET\n";
		$c .= "BT /F1 10 Tf 36 660 Td (" . self::esc( self::to_ansi( mysql2date( 'd/m/Y H:i', (string) $row['created_at'] ) ) ) . ") Tj ET\n";
		$c .= "0.88 0.91 0.94 RG 1 w\n36 645 m 559 645 l S\n";
		$c .= "BT /F2 10 Tf 36 628 Td (Produit) Tj 200 0 Td (Qte) Tj 70 0 Td (P.U.) Tj 90 0 Td (Total) Tj ET\n";
		$c .= "BT /F1 10 Tf 36 610 Td (" . self::esc( self::to_ansi( mb_substr( $product, 0, 46 ) ) ) . ") Tj 200 0 Td x" . (int) $row['quantity'] . " Tj 70 0 Td (" . self::esc( number_format( (float) $row['subtotal'] / max( 1, (int) $row['quantity'] ), 0, '.', ' ' ) ) . ") Tj 90 0 Td (" . self::esc( number_format( (float) $row['subtotal'], 0, '.', ' ' ) ) . ") Tj ET\n";
		$c .= "BT /F1 10 Tf 36 588 Td (Livraison) Tj 460 0 Td (" . self::esc( number_format( (float) $row['shipping'], 0, '.', ' ' ) ) . ") Tj ET\n";
		if ( (float) $row['discount'] > 0 ) {
			$c .= "BT /F1 10 Tf 36 572 Td (Remise) Tj 460 0 Td (-" . self::esc( number_format( (float) $row['discount'], 0, '.', ' ' ) ) . ") Tj ET\n";
		}
		$c .= "BT /F2 12 Tf 36 540 Td (" . self::esc( self::to_ansi( $paid ) ) . ") Tj 380 0 Td (" . self::esc( number_format( (float) $row['total'], 0, '.', ' ' ) . ' ' . $cur ) . ") Tj ET\n";
		$c .= "BT /F1 8 Tf 36 40 Td (" . self::esc( self::to_ansi( __( 'Merci pour votre confiance — InfinityCod', 'infinitycod' ) ) ) . ") Tj ET\n";

		return self::assemble( $c );
	}

	/**
	 * Assemble les objets PDF (catalogue, page A4, Helvetica).
	 *
	 * @param string $content Flux de contenu de la page.
	 * @return string
	 */
	private static function assemble( $content ) {
		$objects = array(
			"<< /Type /Catalog /Pages 2 0 R >>",
			"<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
			"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>",
			"<< /Length " . strlen( $content ) . " >>\nstream\n" . $content . "endstream",
			"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
			"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>",
		);

		$pdf = "%PDF-1.4\n";
		$offsets = array();
		foreach ( $objects as $i => $body ) {
			$offsets[ $i + 1 ] = strlen( $pdf );
			$pdf .= ( $i + 1 ) . " 0 obj\n" . $body . "\nendobj\n";
		}

		$xref_pos = strlen( $pdf );
		$count    = count( $objects ) + 1;
		$pdf     .= "xref\n0 " . $count . "\n0000000000 65535 f \n";
		for ( $i = 1; $i < $count; $i++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
		}
		$pdf .= "trailer\n<< /Size " . $count . " /Root 1 0 R >>\nstartxref\n" . $xref_pos . "\n%%EOF";

		return $pdf;
	}
}
