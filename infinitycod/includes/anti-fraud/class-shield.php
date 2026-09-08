<?php
/**
 * Bouclier anti-fraude : honeypot, tempo, fingerprint, rate-limit, blacklist.
 *
 * @package InfinityCod
 */

namespace InfinityCod\AntiFraud;

use InfinityCod\Core\Schema;
use InfinityCod\Core\Settings;
use InfinityCod\Form\Validator;

defined( 'ABSPATH' ) || exit;

class Shield {

	/**
	 * Hooks du module.
	 *
	 * @return void
	 */
	public function register() {}

	/**
	 * Clé secrète de signature du timestamp (régénérée par jour).
	 *
	 * @return string
	 */
	private static function signing_key() {
		return wp_salt( 'nonce' ) . gmdate( 'Ymd' );
	}

	/**
	 * Signe le timestamp d'affichage du formulaire (anti-manipulation).
	 *
	 * @param int $timestamp Unix timestamp du rendu.
	 * @return string
	 */
	public static function sign_timestamp( $timestamp ) {
		return hash_hmac( 'sha256', (string) $timestamp, self::signing_key() );
	}

	/**
	 * Vérifie le timestamp signé renvoyé par le formulaire.
	 *
	 * @param int    $timestamp Unix timestamp renvoyé.
	 * @param string $signature Signature renvoyée.
	 * @return bool Signature valide (le champ n'a pas été falsifié).
	 */
	public static function verify_timestamp( $timestamp, $signature ) {
		return hash_equals( self::sign_timestamp( (int) $timestamp ), (string) $signature );
	}

	/**
	 * Évalue une soumission : score de risque 0-100 + liste de drapeaux.
	 *
	 * @param array $input Données brutes de la soumission :
	 *     name, phone, wilaya, commune, mode, quantity, product_id,
	 *     honeypot, ts, sig, fingerprint.
	 * @return array{score: int, flags: string[], blocked: bool}
	 */
	public function assess( array $input ) {
		$flags     = array();
		$score     = 0;
		$shield_on = (bool) Settings::get( 'shield_enabled', 1 );

		$phone     = Validator::normalize_phone( isset( $input['phone'] ) ? $input['phone'] : '' );
		$ip        = self::client_ip();
		$fingerprint = isset( $input['fingerprint'] ) ? substr( preg_replace( '/[^a-zA-Z0-9]/', '', (string) $input['fingerprint'] ), 0, 64 ) : '';

		if ( ! $shield_on ) {
			return array( 'score' => 0, 'flags' => array(), 'blocked' => false );
		}

		// 1. Honeypot rempli => bot quasi certain.
		if ( ! empty( $input['honeypot'] ) ) {
			$flags[] = 'honeypot';
			$score  += 100;
		}

		// 2. Tempo de remplissage.
		$ts = isset( $input['ts'] ) ? (int) $input['ts'] : 0;
		$sig = isset( $input['sig'] ) ? (string) $input['sig'] : '';
		if ( ! $ts || ! self::verify_timestamp( $ts, $sig ) ) {
			$flags[] = 'ts_invalid';
			$score  += 70; // Signature falsifiée : très suspect.
		} else {
			$elapsed  = time() - $ts;
			$min_time = (int) Settings::get( 'min_submit_seconds', 3 );
			if ( $elapsed < $min_time ) {
				$flags[] = 'too_fast';
				$score  += 40;
			}
		}

		// 3. Blacklist téléphone.
		if ( $phone && $this->is_blacklisted( 'phone', $phone ) ) {
			$flags[] = 'blacklist_phone';
			$score  += 100;
		}

		// 4. Blacklist IP.
		if ( $this->is_blacklisted( 'ip', $ip ) ) {
			$flags[] = 'blacklist_ip';
			$score  += 100;
		}

		// 5. Doublon : même numéro avec commande en attente.
		if ( $phone && Settings::get( 'block_duplicate_phone' ) && $this->has_pending_duplicate( $phone ) ) {
			$flags[] = 'duplicate_phone';
			$score  += 60;
		}

		// 6. Volume par IP sur la dernière heure.
		$max_per_ip = (int) Settings::get( 'max_per_ip_hour', 5 );
		if ( $this->count_ip_last_hour( $ip ) >= $max_per_ip ) {
			$flags[] = 'ip_flood';
			$score  += 50;
		}

		// 7. Fingerprint identique ayant soumis récemment (compte double).
		if ( $fingerprint && $this->count_fingerprint_last_hour( $fingerprint ) >= 2 ) {
			$flags[] = 'fingerprint_repeat';
			$score  += 30;
		}

		$score = min( 100, $score );
		$blocked = $score >= (int) Settings::get( 'min_fraud_score_block', 60 );

		if ( $flags ) {
			$this->log( $ip, (string) $phone, $fingerprint, $flags, $blocked ? 'blocked' : 'flagged' );
		}

		return array(
			'score'   => $score,
			'flags'   => $flags,
			'blocked' => $blocked,
		);
	}

	/**
	 * IP cliente (derrière CDN éventuels, en-têtes les plus courants).
	 *
	 * @return string
	 */
	public static function client_ip() {
		$candidates = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
		foreach ( $candidates as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = trim( explode( ',', (string) $_SERVER[ $key ] )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '0.0.0.0';
	}

	/**
	 * Une valeur (téléphone normalisé ou IP) est-elle en liste noire ?
	 *
	 * @param string $kind  'phone' ou 'ip'.
	 * @param string $value Valeur exacte.
	 * @return bool
	 */
	public function is_blacklisted( $kind, $value ) {
		global $wpdb;
		$table = Schema::table( 'blacklist' );
		return (bool) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE kind = %s AND value = %s LIMIT 1", $kind, $value ) // phpcs:ignore WordPress.DB.PreparedSQL
		);
	}

	/**
	 * Ajoute une entrée en liste noire.
	 *
	 * @param string $kind   'phone' ou 'ip'.
	 * @param string $value  Valeur.
	 * @param string $reason Motif.
	 * @return bool
	 */
	public function blacklist( $kind, $value, $reason = '' ) {
		global $wpdb;
		return false !== $wpdb->insert(
			Schema::table( 'blacklist' ),
			array(
				'kind'       => $kind,
				'value'      => $value,
				'reason'     => $reason,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Une commande en attente existe-t-elle déjà pour ce numéro ?
	 *
	 * @param string $phone Téléphone normalisé.
	 * @return bool
	 */
	public function has_pending_duplicate( $phone ) {
		global $wpdb;
		$table = Schema::table( 'orders' );
		return (bool) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE phone = %s AND status IN ('pending','confirmed') LIMIT 1", $phone ) // phpcs:ignore WordPress.DB.PreparedSQL
		);
	}

	/**
	 * Nombre de soumissions/rejets de cette IP sur la dernière heure.
	 *
	 * @param string $ip Adresse IP.
	 * @return int
	 */
	public function count_ip_last_hour( $ip ) {
		global $wpdb;
		$orders = Schema::table( 'orders' );
		$logs   = Schema::table( 'fraud_logs' );
		$since  = current_time( 'mysql', time() - HOUR_IN_SECONDS );

		$n  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$orders} WHERE ip = %s AND created_at >= %s", $ip, $since ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$n += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$logs} WHERE ip = %s AND created_at >= %s", $ip, $since ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		return $n;
	}

	/**
	 * Soumissions récentes de ce fingerprint.
	 *
	 * @param string $fingerprint Empreinte navigateur.
	 * @return int
	 */
	public function count_fingerprint_last_hour( $fingerprint ) {
		global $wpdb;
		$orders = Schema::table( 'orders' );
		$since  = current_time( 'mysql', time() - HOUR_IN_SECONDS );

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$orders} WHERE fingerprint = %s AND created_at >= %s", $fingerprint, $since ) // phpcs:ignore WordPress.DB.PreparedSQL
		);
	}

	/**
	 * Journalise un événement anti-fraude.
	 *
	 * @param string $ip          IP.
	 * @param string $phone       Téléphone.
	 * @param string $fingerprint Empreinte.
	 * @param array  $flags       Drapeaux détectés.
	 * @param string $action      'flagged' ou 'blocked'.
	 * @return void
	 */
	public function log( $ip, $phone, $fingerprint, array $flags, $action ) {
		global $wpdb;
		$wpdb->insert(
			Schema::table( 'fraud_logs' ),
			array(
				'created_at'  => current_time( 'mysql' ),
				'ip'          => $ip,
				'phone'       => $phone,
				'fingerprint' => $fingerprint,
				'flags'       => implode( ',', $flags ),
				'action'      => $action,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}
