<?php
/**
 * Activation / désactivation : tables, seed géographique, réglages.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Core;

defined( 'ABSPATH' ) || exit;

class Activator {

	/**
	 * Hook d'activation : crée les tables et charge les données de base.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		self::create_tables();
		self::seed_geo();
		self::seed_countries();
		self::seed_settings();
		self::apply_detected_defaults();

		set_transient( 'icod_welcome', 1, 7 * DAY_IN_SECONDS );

		update_option( 'infinitycod_db_version', INFINITYCOD_DB_VERSION );
		update_option( 'infinitycod_installed_at', current_time( 'mysql' ) );
	}

	/**
	 * Crée / met à jour les tables via dbDelta.
	 *
	 * Le require est conditionné : dans le harnais de test, dbDelta est
	 * déjà défini et le fichier wp-admin n'existe pas.
	 *
	 * @return void
	 */
	public static function create_tables() {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		foreach ( Schema::create_tables() as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Insère wilayas et communes si les tables sont vides.
	 *
	 * @return void
	 */
	public static function seed_geo() {
		global $wpdb;

		$wilayas_table  = Schema::table( 'wilayas' );
		$communes_table = Schema::table( 'communes' );

		// Wilayas.
		if ( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wilayas_table}" ) ) {
			$wilayas = self::read_json( 'wilayas.json' );
			if ( $wilayas ) {
				$rows = array();
				foreach ( $wilayas as $w ) {
					$code    = isset( $w['code'] ) ? $w['code'] : '';
					$name_fr = isset( $w['name_fr'] ) ? $w['name_fr'] : '';
					$name_ar = isset( $w['name_ar'] ) ? $w['name_ar'] : '';
					if ( '' === $code ) {
						continue;
					}
					$rows[] = array( $code, $name_fr, $name_ar, 1, -1, -1, 0 );
				}
				if ( $rows ) {
					$placeholders = implode( ',', array_fill( 0, count( $rows ), '( %s, %s, %s, %d, %d, %d, %d )' ) );
					$values       = call_user_func_array( 'array_merge', $rows );
					$wpdb->query(
						$wpdb->prepare(
							"INSERT INTO {$wilayas_table} (code, name_fr, name_ar, active, price_home, price_desk, free_shipping) VALUES $placeholders", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders generes.
							$values
						)
					);
				}
			}
		}

		// Communes : par lots de 200 pour respecter max_allowed_packet.
		if ( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$communes_table}" ) ) {
			$communes = self::read_json( 'communes.json' );
			if ( $communes ) {
				$batch = array();
				$total = 0;
				foreach ( $communes as $c ) {
					$wilaya = isset( $c['wilaya_code'] ) ? $c['wilaya_code'] : '';
					$fr     = isset( $c['name_fr'] ) ? $c['name_fr'] : '';
					$ar     = isset( $c['name_ar'] ) ? $c['name_ar'] : '';
					if ( '' === $wilaya || '' === $fr ) {
						continue;
					}
					$batch[] = array( $wilaya, $fr, $ar, 1, 0, -1, -1 );
					if ( count( $batch ) >= 200 ) {
						$placeholders = implode( ',', array_fill( 0, count( $batch ), '( %s, %s, %s, %d, %d, %d, %d )' ) );
						$values       = call_user_func_array( 'array_merge', $batch );
						$wpdb->query( $wpdb->prepare( "INSERT INTO {$communes_table} (wilaya_code, name_fr, name_ar, active, has_desk, price_home, price_desk) VALUES $placeholders", $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders generes.
						$total += count( $batch );
						$batch  = array();
					}
				}
				if ( $batch ) {
					$placeholders = implode( ',', array_fill( 0, count( $batch ), '( %s, %s, %s, %d, %d, %d, %d )' ) );
					$values       = call_user_func_array( 'array_merge', $batch );
					$wpdb->query( $wpdb->prepare( "INSERT INTO {$communes_table} (wilaya_code, name_fr, name_ar, active, has_desk, price_home, price_desk) VALUES $placeholders", $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders generes.
					$total += count( $batch );
				}
			}
		}
	}

	/**
	 * Écrit les réglages par défaut à la première activation.
	 *
	 * @return void
	 */
	public static function seed_settings() {
		if ( false === get_option( Settings::OPTION, false ) ) {
			add_option( Settings::OPTION, Settings::defaults(), '', true );
		}
	}

	/**
	 * Catalogue des pays du marché arabe (régions/wilayas équivalentes).
	 *
	 * L'Algérie reste complète (58 wilayas + 1541 communes). Les autres pays
	 * sont livrés au niveau région ; la commune y devient un champ libre.
	 * L'Algérie est le pays par défaut ; un pays unique libre est détecté à
	 * l'activation ; les multi-pays simultanés sont une option Premium.
	 *
	 * dial    : indicatif téléphonique international.
	 * currency: devise proposée par défaut.
	 * phone   : exemple de numéro local (placeholder du formulaire).
	 *
	 * @return array<string, array{fr:string, ar:string, dial:string, currency:string, phone:string, regions:array<string,string>}>
	 */
	public static function countries_catalog() {
		return array(
			'DZ' => array( 'fr' => 'Algérie', 'ar' => 'الجزائر', 'dial' => '213', 'currency' => 'DZD', 'phone' => '0555 12 34 56', 'regions' => array() ), // 58 wilayas déjà seedées.
			'MA' => array( 'fr' => 'Maroc', 'ar' => 'المغرب', 'dial' => '212', 'currency' => 'MAD', 'phone' => '0612 34 56 78', 'regions' => array(
				'Tanger-Tétouan-Al Hoceïma' => 'طنجة تطوان الحسيمة',
				"L'Oriental"                => 'الشرق',
				'Fès-Meknès'                => 'فاس مكناس',
				'Rabat-Salé-Kénitra'        => 'الرباط سلا القنيطرة',
				'Béni Mellal-Khénifra'      => 'بني ملال خنيفرة',
				'Casablanca-Settat'         => 'الدار البيضاء سطات',
				'Marrakech-Safi'            => 'مراكش آسفي',
				'Drâa-Tafilalet'            => 'درعة تافيلالت',
				'Souss-Massa'               => 'سوس ماسة',
				'Guelmim-Oued Noun'         => 'كلميم واد نون',
				'Laâyoune-Sakia El Hamra'   => 'العيون الساقية الحمراء',
				'Dakhla-Oued Ed-Dahab'      => 'الداخلة وادي الذهب',
			) ),
			'TN' => array( 'fr' => 'Tunisie', 'ar' => 'تونس', 'dial' => '216', 'currency' => 'TND', 'phone' => '20 123 456', 'regions' => array(
				'Tunis' => 'تونس', 'Ariana' => 'أريانة', 'Ben Arous' => 'بن عروس', 'La Manouba' => 'منوبة',
				'Nabeul' => 'نابل', 'Zaghouan' => 'زغوان', 'Bizerte' => 'بنزرت', 'Béja' => 'باجة',
				'Jendouba' => 'جندوبة', 'Le Kef' => 'الكاف', 'Siliana' => 'سليانة', 'Sousse' => 'سوسة',
				'Monastir' => 'المنستير', 'Mahdia' => 'المهدية', 'Sfax' => 'صفاقس', 'Kairouan' => 'القيروان',
				'Kasserine' => 'القصرين', 'Sidi Bouzid' => 'سيدي بوزيد', 'Gabès' => 'قابس', 'Médenine' => 'مدنين',
				'Tataouine' => 'تطاوين', 'Gafsa' => 'قفصة', 'Tozeur' => 'توزر', 'Kébili' => 'قبلي',
			) ),
			'EG' => array( 'fr' => 'Égypte', 'ar' => 'مصر', 'dial' => '20', 'currency' => 'EGP', 'phone' => '0100 123 4567', 'regions' => array(
				'Le Caire' => 'القاهرة', 'Alexandrie' => 'الإسكندرية', 'Gizeh' => 'الجيزة', 'Port-Saïd' => 'بورسعيد',
				'Suez' => 'السويس', 'Louxor' => 'الأقصر', 'Assouan' => 'أسوان', 'Assiout' => 'أسيوط',
				'Beni Souef' => 'بني سويف', 'Fayoum' => 'الفيوم', 'Menoufia' => 'المنوفية', 'Minya' => 'المنيا',
				'New Valley' => 'الوادي الجديد', 'Sharqiya' => 'الشرقية', 'Dakahlia' => 'الدقهلية', 'Gharbiya' => 'الغربية',
				'Qalyubia' => 'القليوبية', 'Kafr El Sheikh' => 'كفر الشيخ', 'Damiette' => 'دمياط', 'Ismailia' => 'الإسماعيلية',
				'Sinai Nord' => 'شمال سيناء', 'Sinai Sud' => 'جنوب سيناء', 'Sohag' => 'سوهاج', 'Mer Rouge' => 'البحر الأحمر',
				'Matrouh' => 'مطروح', 'Qena' => 'قنا', 'Beheira' => 'البحيرة',
			) ),
			'SA' => array( 'fr' => 'Arabie Saoudite', 'ar' => 'المملكة العربية السعودية', 'dial' => '966', 'currency' => 'SAR', 'phone' => '0501 234 567', 'regions' => array(
				'Riyad' => 'الرياض', 'La Mecque' => 'مكة المكرمة', 'Médine' => 'المدينة المنورة', 'Charqiya (Orientale)' => 'الشرقية',
				'Asir' => 'عسير', 'Tabuk' => 'تبوك', 'Qassim' => 'القصيم', 'Haïl' => 'حائل',
				'Jouf' => 'الجوف', 'Najran' => 'نجران', 'Bahah' => 'الباحة', 'Jizan' => 'جازان',
				'Frontières du Nord' => 'الحدود الشمالية',
			) ),
			'AE' => array( 'fr' => 'Émirats Arabes Unis', 'ar' => 'الإمارات العربية المتحدة', 'dial' => '971', 'currency' => 'AED', 'phone' => '050 123 4567', 'regions' => array(
				'Abu Dhabi' => 'أبوظبي', 'Dubaï' => 'دبي', 'Charjah' => 'الشارقة', 'Ajman' => 'عجمان',
				'Umm Al Quwain' => 'أم القيوين', 'Ras Al Khaïmah' => 'رأس الخيمة', 'Foujairah' => 'الفجيرة',
			) ),
		);
	}

	/**
	 * Détecte le pays du site : adresse WooCommerce, puis locale WordPress,
	 * puis fuseau horaire. Retourne un code du catalogue, sinon 'DZ'.
	 *
	 * @return string
	 */
	public static function detect_site_country() {
		$catalog = self::countries_catalog();

		// 1. Adresse boutique WooCommerce (ex. « MA :Casablanca-Settat » ou « DZ »).
		$wc_country = (string) get_option( 'woocommerce_default_country', '' );
		if ( preg_match( '/^([A-Za-z]{2})/', $wc_country, $m ) ) {
			$code = strtoupper( $m[1] );
			if ( isset( $catalog[ $code ] ) ) {
				return $code;
			}
		}

		// 2. Locale WordPress (ex. « fr_MA », « ar_DZ », « ar_EG »).
		$locale = (string) get_locale();
		if ( preg_match( '/_([A-Za-z]{2})$/', $locale, $m ) ) {
			$code = strtoupper( $m[1] );
			if ( isset( $catalog[ $code ] ) ) {
				return $code;
			}
		}

		// 3. Fuseau horaire (ex. « Africa/Casablanca »).
		$city_to_country = array(
			'algiers'     => 'DZ', 'casablanca' => 'MA', 'tunis' => 'TN', 'cairo' => 'EG',
			'riyadh'      => 'SA', 'dubai'      => 'AE', 'abu_dhabi' => 'AE', 'kuwait' => 'KW',
			'baghdad'     => 'IQ', 'tripoli'    => 'LY', 'khartoum' => 'SD', 'doha' => 'QA',
			'muscat'      => 'OM', 'manama'     => 'BH', 'amman' => 'JO', 'damascus' => 'SY',
			'sanaa'       => 'YE', 'nouakchott' => 'MR',
		);
		$tz = function_exists( 'wp_timezone_string' ) ? (string) wp_timezone_string() : '';
		if ( false !== strpos( $tz, '/' ) ) {
			$city = strtolower( substr( $tz, strrpos( $tz, '/' ) + 1 ) );
			if ( isset( $city_to_country[ $city ] ) && isset( $catalog[ $city_to_country[ $city ] ] ) ) {
				return $city_to_country[ $city ];
			}
		}

		return 'DZ';
	}

	/**
	 * Applique les défauts détectés une seule fois (pays, devise) — sans
	 * jamais écraser une configuration existante.
	 *
	 * @return void
	 */
	public static function apply_detected_defaults() {
		if ( get_option( 'infinitycod_detection_done', false ) ) {
			return;
		}

		$code = self::detect_site_country();
		$catalog = self::countries_catalog();

		if ( isset( $catalog[ $code ] ) ) {
			Settings::set( 'default_country', $code );

			// Devise uniquement si jamais personnalisée (valeur par défaut).
			if ( 'DZD' === Settings::get( 'currency', 'DZD' ) && 'DZD' !== $catalog[ $code ]['currency'] ) {
				Settings::set( 'currency', $catalog[ $code ]['currency'] );
			}

			\InfinityCod\Logging\Logger::log( 'migration', 'Pays détecté à l‘installation : ' . $code . ' — devise ' . Settings::currency() );
		}

		update_option( 'infinitycod_detection_done', 1, true );
	}

	/**
	 * Insère les régions des pays du marché arabe (une fois par pays).
	 * Les prix restent à -1 (à configurer) ; inactives par défaut.
	 *
	 * @return void
	 */
	public static function seed_countries() {
		global $wpdb;

		$table = Schema::table( 'wilayas' );

		foreach ( self::countries_catalog() as $code => $country ) {
			if ( 'DZ' === $code || empty( $country['regions'] ) ) {
				continue;
			}

			$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE country_code = %s", $code ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $existing > 0 ) {
				continue;
			}

			$rows = array();
			$i    = 1;
			foreach ( $country['regions'] as $fr => $ar ) {
				$region_code = sprintf( '%s-%02d', $code, $i++ );
				$rows[]      = array( $region_code, $code, $fr, $ar, 0, -1, -1, 0 );
			}
			if ( $rows ) {
				$placeholders = implode( ',', array_fill( 0, count( $rows ), '( %s, %s, %s, %s, %d, %d, %d, %d )' ) );
				$values       = call_user_func_array( 'array_merge', $rows );
				$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (code, country_code, name_fr, name_ar, active, price_home, price_desk, free_shipping) VALUES $placeholders", $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- placeholders generes.
			}
		}
	}

	/**
	 * Lit un fichier JSON du dossier data/.
	 *
	 * @param string $file Nom du fichier (ex. 'wilayas.json').
	 * @return array|null
	 */
	private static function read_json( $file ) {
		$path = INFINITYCOD_PATH . 'data/' . $file;
		if ( ! is_readable( $path ) ) {
			return null;
		}
		$decoded = json_decode( (string) file_get_contents( $path ), true );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Met à jour les tables quand INFINITYCOD_DB_VERSION change.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'infinitycod_db_version' ) !== INFINITYCOD_DB_VERSION ) {
			self::create_tables();
			self::seed_geo();
			self::seed_countries();
			update_option( 'infinitycod_db_version', INFINITYCOD_DB_VERSION, true );
		}

		// Migrations versionnées (idempotentes, exécutées une seule fois).
		self::run_migrations();
	}

	/**
	 * Registry des migrations versionnées.
	 *
	 * Chaque migration : clé unique => callback. Idempotente, exécutée
	 * une seule fois (option infinitycod_migrations), loggée.
	 *
	 * @return array<string, callable>
	 */
	private static function migrations() {
		return array(
			'5.27.0_form_full_width' => function () {
				// Les sites qui n'ont jamais choisi de position quittent la colonne
				// résumé (étroite) pour la pleine largeur : vrai checkout 2 colonnes sur PC.
				if ( 'after_summary' === Settings::get( 'form_position', 'after_summary' ) ) {
					Settings::set( 'form_position', 'full_width' );
				}
			},
			'1.7.1_license_server' => function () {
				$license_server = Settings::get( 'license_server', '' );
				if ( $license_server && false !== strpos( (string) $license_server, 'factexpert.online' ) ) {
					Settings::set( 'license_server', 'https://infinitycoder.app/api.php' );
				}
			},
		);
	}

	/**
	 * Exécute les migrations non encore appliquées.
	 *
	 * @return void
	 */
	public static function run_migrations() {
		$done = get_option( 'infinitycod_migrations', array() );
		$done = is_array( $done ) ? $done : array();

		$ran = false;
		foreach ( self::migrations() as $key => $callback ) {
			if ( in_array( $key, $done, true ) ) {
				continue;
			}

			call_user_func( $callback );
			$done[] = $key;
			$ran    = true;

			\InfinityCod\Logging\Logger::log( 'migration', 'Migration appliquée : ' . $key );
		}

		update_option( 'infinitycod_migrations', $done, false );

		// Une migration peut changer la position du formulaire (etc.) : purge
		// des caches de pages pour que le public voie le nouveau rendu aussitôt.
		if ( $ran ) {
			\InfinityCod\Core\CachePurge::purge_all();
		}
	}

	/**
	 * Alias historique (compatibilité interne).
	 *
	 * @return void
	 */
	public static function migrate_settings() {
		self::run_migrations();
	}

	/**
	 * Hook de désactivation : nettoie les événements planifiés.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'infinitycod_sync_tracking' );
		wp_clear_scheduled_hook( 'infinitycod_recover_abandoned' );
		wp_clear_scheduled_hook( 'infinitycod_update_check' );
	}
}
