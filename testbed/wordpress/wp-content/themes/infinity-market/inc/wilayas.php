<?php
/**
 * ∞ Infinity Coder — Wilayas d'Algérie (58) & frais de livraison.
 *
 * Frais par défaut en DA, modifiables dans le tableau de bord (Réglages → Livraison).
 *
 * @package infinity-market
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Liste par défaut des 58 wilayas : [code] => [fr, ar, home, desk].
 *
 * @return array
 */
function inf_default_wilayas() {
	return array(
		'01' => array( 'fr' => 'Adrar',             'ar' => 'أدرار',          'home' => 1200, 'desk' => 700 ),
		'02' => array( 'fr' => 'Chlef',             'ar' => 'الشلف',          'home' => 700,  'desk' => 400 ),
		'03' => array( 'fr' => 'Laghouat',          'ar' => 'الأغواط',        'home' => 800,  'desk' => 450 ),
		'04' => array( 'fr' => 'Oum El Bouaghi',    'ar' => 'أم البواقي',     'home' => 700,  'desk' => 400 ),
		'05' => array( 'fr' => 'Batna',             'ar' => 'باتنة',          'home' => 700,  'desk' => 400 ),
		'06' => array( 'fr' => 'Béjaïa',            'ar' => 'بجاية',          'home' => 600,  'desk' => 350 ),
		'07' => array( 'fr' => 'Biskra',            'ar' => 'بسكرة',          'home' => 750,  'desk' => 450 ),
		'08' => array( 'fr' => 'Béchar',            'ar' => 'بشار',           'home' => 1200, 'desk' => 700 ),
		'09' => array( 'fr' => 'Blida',             'ar' => 'البليدة',        'home' => 500,  'desk' => 300 ),
		'10' => array( 'fr' => 'Bouira',            'ar' => 'البويرة',        'home' => 600,  'desk' => 350 ),
		'11' => array( 'fr' => 'Tamanrasset',       'ar' => 'تمنراست',        'home' => 1400, 'desk' => 800 ),
		'12' => array( 'fr' => 'Tébessa',           'ar' => 'تبسة',           'home' => 750,  'desk' => 450 ),
		'13' => array( 'fr' => 'Tlemcen',           'ar' => 'تلمسان',         'home' => 700,  'desk' => 400 ),
		'14' => array( 'fr' => 'Tiaret',            'ar' => 'تيارت',          'home' => 700,  'desk' => 400 ),
		'15' => array( 'fr' => 'Tizi Ouzou',        'ar' => 'تيزي وزو',       'home' => 600,  'desk' => 350 ),
		'16' => array( 'fr' => 'Alger',             'ar' => 'الجزائر',        'home' => 400,  'desk' => 250 ),
		'17' => array( 'fr' => 'Djelfa',            'ar' => 'الجلفة',         'home' => 750,  'desk' => 450 ),
		'18' => array( 'fr' => 'Jijel',             'ar' => 'جيجل',           'home' => 700,  'desk' => 400 ),
		'19' => array( 'fr' => 'Sétif',             'ar' => 'سطيف',           'home' => 600,  'desk' => 350 ),
		'20' => array( 'fr' => 'Saïda',             'ar' => 'سعيدة',          'home' => 750,  'desk' => 450 ),
		'21' => array( 'fr' => 'Skikda',            'ar' => 'سكيكدة',         'home' => 700,  'desk' => 400 ),
		'22' => array( 'fr' => 'Sidi Bel Abbès',    'ar' => 'سيدي بلعباس',    'home' => 700,  'desk' => 400 ),
		'23' => array( 'fr' => 'Annaba',            'ar' => 'عنابة',          'home' => 700,  'desk' => 400 ),
		'24' => array( 'fr' => 'Guelma',            'ar' => 'قالمة',          'home' => 700,  'desk' => 400 ),
		'25' => array( 'fr' => 'Constantine',       'ar' => 'قسنطينة',        'home' => 600,  'desk' => 350 ),
		'26' => array( 'fr' => 'Médéa',             'ar' => 'المدية',         'home' => 600,  'desk' => 350 ),
		'27' => array( 'fr' => 'Mostaganem',        'ar' => 'مستغانم',        'home' => 700,  'desk' => 400 ),
		'28' => array( 'fr' => 'M\'Sila',           'ar' => 'المسلة',         'home' => 700,  'desk' => 400 ),
		'29' => array( 'fr' => 'Mascara',           'ar' => 'معسكر',          'home' => 700,  'desk' => 400 ),
		'30' => array( 'fr' => 'Ouargla',           'ar' => 'ورقلة',          'home' => 900,  'desk' => 500 ),
		'31' => array( 'fr' => 'Oran',              'ar' => 'وهران',          'home' => 600,  'desk' => 350 ),
		'32' => array( 'fr' => 'El Bayadh',         'ar' => 'البيض',          'home' => 900,  'desk' => 500 ),
		'33' => array( 'fr' => 'Illizi',            'ar' => 'إليزي',          'home' => 1400, 'desk' => 800 ),
		'34' => array( 'fr' => 'Bordj Bou Arréridj','ar' => 'برج بوعريريج',   'home' => 600,  'desk' => 350 ),
		'35' => array( 'fr' => 'Boumerdès',         'ar' => 'بومرداس',        'home' => 500,  'desk' => 300 ),
		'36' => array( 'fr' => 'El Tarf',           'ar' => 'الطارف',         'home' => 750,  'desk' => 450 ),
		'37' => array( 'fr' => 'Tindouf',           'ar' => 'تندوف',          'home' => 1400, 'desk' => 800 ),
		'38' => array( 'fr' => 'Tissemsilt',        'ar' => 'تيسمسيلت',       'home' => 750,  'desk' => 450 ),
		'39' => array( 'fr' => 'El Oued',           'ar' => 'الوادي',         'home' => 900,  'desk' => 500 ),
		'40' => array( 'fr' => 'Khenchela',         'ar' => 'خنشلة',          'home' => 750,  'desk' => 450 ),
		'41' => array( 'fr' => 'Souk Ahras',        'ar' => 'سوق أهراس',      'home' => 750,  'desk' => 450 ),
		'42' => array( 'fr' => 'Tipaza',            'ar' => 'تيبازة',         'home' => 500,  'desk' => 300 ),
		'43' => array( 'fr' => 'Mila',              'ar' => 'ميلة',           'home' => 700,  'desk' => 400 ),
		'44' => array( 'fr' => 'Aïn Defla',         'ar' => 'عين الدفلى',     'home' => 650,  'desk' => 400 ),
		'45' => array( 'fr' => 'Naâma',             'ar' => 'النعامة',        'home' => 1000, 'desk' => 600 ),
		'46' => array( 'fr' => 'Aïn Témouchent',    'ar' => 'عين تموشنت',     'home' => 700,  'desk' => 400 ),
		'47' => array( 'fr' => 'Ghardaïa',          'ar' => 'غرداية',         'home' => 900,  'desk' => 500 ),
		'48' => array( 'fr' => 'Relizane',          'ar' => 'غليزان',         'home' => 700,  'desk' => 400 ),
		'49' => array( 'fr' => 'El M\'Ghair',       'ar' => 'المغير',         'home' => 900,  'desk' => 500 ),
		'50' => array( 'fr' => 'El Menia',          'ar' => 'المنيعة',        'home' => 1000, 'desk' => 600 ),
		'51' => array( 'fr' => 'Ouled Djellal',     'ar' => 'أولاد جلال',     'home' => 900,  'desk' => 500 ),
		'52' => array( 'fr' => 'Bordj Baji Mokhtar','ar' => 'برج باجي مختار', 'home' => 1400, 'desk' => 800 ),
		'53' => array( 'fr' => 'Béni Abbès',        'ar' => 'بني عباس',       'home' => 1200, 'desk' => 700 ),
		'54' => array( 'fr' => 'Timimoun',          'ar' => 'تيميمون',        'home' => 1200, 'desk' => 700 ),
		'55' => array( 'fr' => 'Touggourt',         'ar' => 'تقرت',           'home' => 900,  'desk' => 500 ),
		'56' => array( 'fr' => 'Djanet',            'ar' => 'جانت',           'home' => 1400, 'desk' => 800 ),
		'57' => array( 'fr' => 'In Salah',          'ar' => 'عين صالح',       'home' => 1200, 'desk' => 700 ),
		'58' => array( 'fr' => 'In Guezzam',        'ar' => 'عين قزام',       'home' => 1400, 'desk' => 800 ),
	);
}

/**
 * Wilayas fusionnées avec les réglages enregistrés (frais + activation).
 *
 * @return array
 */
function inf_get_wilayas() {
	$defaults = inf_default_wilayas();
	$saved    = get_option( 'inf_shipping', array() );

	foreach ( $defaults as $code => $wilaya ) {
		$defaults[ $code ]['active'] = true;
		if ( isset( $saved[ $code ] ) && is_array( $saved[ $code ] ) ) {
			$defaults[ $code ]['home']   = isset( $saved[ $code ]['home'] ) ? max( 0, (int) $saved[ $code ]['home'] ) : $defaults[ $code ]['home'];
			$defaults[ $code ]['desk']   = isset( $saved[ $code ]['desk'] ) ? max( 0, (int) $saved[ $code ]['desk'] ) : $defaults[ $code ]['desk'];
			$defaults[ $code ]['active'] = ! empty( $saved[ $code ]['active'] );
		}
	}
	return $defaults;
}

/**
 * Frais de livraison pour une wilaya et un type (home|desk).
 *
 * @param string $code Code wilaya (01-58).
 * @param string $type Type de livraison.
 * @return int Frais (0 si wilaya inactive ou inconnue).
 */
function inf_delivery_fee( $code, $type ) {
	$wilayas = inf_get_wilayas();
	if ( ! isset( $wilayas[ $code ] ) || empty( $wilayas[ $code ]['active'] ) ) {
		return -1;
	}
	return 'desk' === $type ? (int) $wilayas[ $code ]['desk'] : (int) $wilayas[ $code ]['home'];
}
