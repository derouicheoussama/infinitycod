<?php
/**
 * ∞ Infinity Coder — Personnalisation de l'apparence (Customizer + CSS dynamique).
 *
 * Panel « ∞ Apparence » : palette / couleur d'accent, polices (FR + arabe),
 * arrière-plan, images d'accueil, largeur du site, arrondis, styles
 * d'en-tête et de pied de page. Les réglages sont stockés dans inf_settings
 * (une seule source de vérité, partagée avec le tableau de bord).
 *
 * @package infinity-market
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------
 * Données : palettes & polices
 * ----------------------------------------------------------------- */

/**
 * Palettes prédéfinies (l'accent du thème est la première).
 *
 * @return array
 */
function inf_palette_presets() {
	return array(
		'blue'   => array( 'label' => __( 'Bleu Infinity (défaut)', 'infinity-market' ), 'accent' => '#1e40af' ),
		'ocean'  => array( 'label' => __( 'Océan', 'infinity-market' ), 'accent' => '#0ea5e9' ),
		'green'  => array( 'label' => __( 'Vert', 'infinity-market' ), 'accent' => '#16a34a' ),
		'orange' => array( 'label' => __( 'Orange', 'infinity-market' ), 'accent' => '#ea580c' ),
		'violet' => array( 'label' => __( 'Violet', 'infinity-market' ), 'accent' => '#7c3aed' ),
		'red'    => array( 'label' => __( 'Rouge', 'infinity-market' ), 'accent' => '#dc2626' ),
	);
}

/**
 * Polices proposées (avec support arabe pour le marché algérien).
 *
 * @return array
 */
function inf_fonts() {
	return array(
		'system'    => array( 'label' => __( 'Police système (la plus rapide)', 'infinity-market' ), 'family' => '' ),
		'poppins'   => array( 'label' => 'Poppins', 'family' => 'Poppins', 'weights' => '400;600;700;800' ),
		'inter'     => array( 'label' => 'Inter', 'family' => 'Inter', 'weights' => '400;600;700;800' ),
		'roboto'    => array( 'label' => 'Roboto', 'family' => 'Roboto', 'weights' => '400;500;700' ),
		'montserrat'=> array( 'label' => 'Montserrat', 'family' => 'Montserrat', 'weights' => '400;600;700;800' ),
		'cairo'     => array( 'label' => 'Cairo — عربي', 'family' => 'Cairo', 'weights' => '400;600;700;800' ),
		'tajawal'   => array( 'label' => 'Tajawal — عربي', 'family' => 'Tajawal', 'weights' => '400;500;700;800' ),
		'notokufi'  => array( 'label' => 'Noto Kufi Arabic — عربي', 'family' => 'Noto Kufi Arabic', 'weights' => '400;600;700' ),
		'amiri'     => array( 'label' => 'Amiri — عربي (serif)', 'family' => 'Amiri', 'weights' => '400;700' ),
		'rubik'     => array( 'label' => 'Rubik (FR + عربي)', 'family' => 'Rubik', 'weights' => '400;600;700;800' ),
	);
}

/**
 * URL Google Fonts d'une police.
 *
 * @param string $id Identifiant de police.
 * @return string URL vide si police système.
 */
function inf_font_url( $id ) {
	$inf_fonts = inf_fonts();
	if ( ! isset( $inf_fonts[ $id ] ) || empty( $inf_fonts[ $id ]['family'] ) ) {
		return '';
	}
	$inf_family  = str_replace( ' ', '+', $inf_fonts[ $id ]['family'] );
	$inf_weights = isset( $inf_fonts[ $id ]['weights'] ) ? $inf_fonts[ $id ]['weights'] : '400;600;700';
	return 'https://fonts.googleapis.com/css2?family=' . rawurlencode( $inf_family ) . ':wght@' . $inf_weights . '&display=swap';
}

/**
 * Accent par défaut (palette du thème).
 *
 * @return string
 */
function inf_default_accent() {
	$inf_presets = inf_palette_presets();
	$inf_palette = inf_get_setting( 'palette', 'blue' );
	return isset( $inf_presets[ $inf_palette ] ) ? $inf_presets[ $inf_palette ]['accent'] : '#1e40af';
}

/**
 * Assombrit une couleur hexadécimale.
 *
 * @param string $hex    Couleur (#rgb ou #rrggbb).
 * @param float  $factor Facteur (0-1).
 * @return string
 */
function inf_darken_hex( $hex, $factor = 0.76 ) {
	$inf_hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $inf_hex ) ) {
		$inf_hex = $inf_hex[0] . $inf_hex[0] . $inf_hex[1] . $inf_hex[1] . $inf_hex[2] . $inf_hex[2];
	}
	if ( 6 !== strlen( $inf_hex ) ) {
		return '#1e3a8a';
	}
	$inf_r = max( 0, min( 255, (int) round( hexdec( substr( $inf_hex, 0, 2 ) ) * $factor ) ) );
	$inf_g = max( 0, min( 255, (int) round( hexdec( substr( $inf_hex, 2, 2 ) ) * $factor ) ) );
	$inf_b = max( 0, min( 255, (int) round( hexdec( substr( $inf_hex, 4, 2 ) ) * $factor ) ) );
	return sprintf( '#%02x%02x%02x', $inf_r, $inf_g, $inf_b );
}

/**
 * Liste blanche générique.
 *
 * @param string $value  Valeur.
 * @param array  $allowed Valeurs autorisées.
 * @param string $default Défaut.
 * @return string
 */
function inf_sanitize_choice( $value, $allowed, $default ) {
	return in_array( (string) $value, $allowed, true ) ? (string) $value : $default;
}

/**
 * Sanitise la palette.
 *
 * @param string $value Valeur.
 * @return string
 */
function inf_sanitize_palette( $value ) {
	return inf_sanitize_choice( $value, array_merge( array_keys( inf_palette_presets() ), array( 'custom' ) ), 'blue' );
}

/**
 * Sanitise une police.
 *
 * @param string $value Valeur.
 * @return string
 */
function inf_sanitize_font( $value ) {
	return inf_sanitize_choice( $value, array_keys( inf_fonts() ), 'system' );
}

/* -----------------------------------------------------------------
 * Customizer : panel « ∞ Apparence »
 * ----------------------------------------------------------------- */

add_action( 'customize_register', 'inf_customize_register' );
function inf_customize_register( $wp_customize ) {
	$wp_customize->add_panel(
		'inf_appearance',
		array(
			'title'    => '∞ ' . INF_THEME_LABEL . ' — ' . __( 'Apparence', 'infinity-market' ),
			'priority' => 29,
		)
	);

	/* ----- Couleurs & style ----- */
	$wp_customize->add_section( 'inf_colors', array( 'title' => __( 'Couleurs & style', 'infinity-market' ), 'panel' => 'inf_appearance' ) );

	$inf_choices = array();
	foreach ( inf_palette_presets() as $inf_id => $inf_p ) {
		$inf_choices[ $inf_id ] = $inf_p['label'];
	}
	$inf_choices['custom'] = __( 'Personnalisée (couleur ci-dessous)', 'infinity-market' );

	$wp_customize->add_setting( 'inf_settings[palette]', array( 'type' => 'option', 'default' => 'blue', 'sanitize_callback' => 'inf_sanitize_palette' ) );
	$wp_customize->add_control(
		'inf_palette',
		array(
			'label'   => __( 'Palette de couleurs', 'infinity-market' ),
			'section' => 'inf_colors',
			'type'    => 'select',
			'choices' => $inf_choices,
		)
	);

	$wp_customize->add_setting( 'inf_settings[accent]', array( 'type' => 'option', 'default' => '', 'sanitize_callback' => 'sanitize_hex_color' ) );
	$wp_customize->add_control(
		new WP_Customize_Color_Control(
			$wp_customize,
			'inf_accent',
			array(
				'label'       => __( 'Couleur d\'accent personnalisée', 'infinity-market' ),
				'description' => __( 'Prioritaire sur la palette si renseignée.', 'infinity-market' ),
				'section'     => 'inf_colors',
			)
		)
	);

	$wp_customize->add_setting( 'inf_settings[header_style]', array( 'type' => 'option', 'default' => 'gradient', 'sanitize_callback' => 'inf_sanitize_header_style' ) );
	$wp_customize->add_control(
		'inf_header_style',
		array(
			'label'   => __( 'Style du menu', 'infinity-market' ),
			'section' => 'inf_colors',
			'type'    => 'select',
			'choices' => array(
				'gradient' => __( 'Dégradé (défaut)', 'infinity-market' ),
				'flat'     => __( 'Uni', 'infinity-market' ),
				'dark'     => __( 'Bleu nuit', 'infinity-market' ),
			),
		)
	);

	$wp_customize->add_setting( 'inf_settings[footer_style]', array( 'type' => 'option', 'default' => 'dark', 'sanitize_callback' => 'inf_sanitize_footer_style' ) );
	$wp_customize->add_control(
		'inf_footer_style',
		array(
			'label'   => __( 'Style du pied de page', 'infinity-market' ),
			'section' => 'inf_colors',
			'type'    => 'select',
			'choices' => array(
				'dark'  => __( 'Sombre (défaut)', 'infinity-market' ),
				'light' => __( 'Clair', 'infinity-market' ),
			),
		)
	);

	$wp_customize->add_setting( 'inf_settings[radius_style]', array( 'type' => 'option', 'default' => 'round', 'sanitize_callback' => 'inf_sanitize_radius_style' ) );
	$wp_customize->add_control(
		'inf_radius_style',
		array(
			'label'   => __( 'Arrondi des blocs', 'infinity-market' ),
			'section' => 'inf_colors',
			'type'    => 'select',
			'choices' => array(
				'square' => __( 'Angles droits', 'infinity-market' ),
				'soft'   => __( 'Légèrement arrondi', 'infinity-market' ),
				'round'  => __( 'Arrondi (défaut)', 'infinity-market' ),
				'pill'   => __( 'Très arrondi', 'infinity-market' ),
			),
		)
	);

	$wp_customize->add_setting( 'inf_settings[container_width]', array( 'type' => 'option', 'default' => 1200, 'sanitize_callback' => 'inf_sanitize_container' ) );
	$wp_customize->add_control(
		'inf_container_width',
		array(
			'label'       => __( 'Largeur du site (px)', 'infinity-market' ),
			'description' => __( 'Entre 960 et 1440 px.', 'infinity-market' ),
			'section'     => 'inf_colors',
			'type'        => 'number',
			'input_attrs' => array( 'min' => 960, 'max' => 1440, 'step' => 10 ),
		)
	);

	/* ----- Typographie ----- */
	$wp_customize->add_section( 'inf_typo', array( 'title' => __( 'Typographie', 'infinity-market' ), 'panel' => 'inf_appearance' ) );

	$inf_font_choices = array();
	foreach ( inf_fonts() as $inf_id => $inf_f ) {
		$inf_font_choices[ $inf_id ] = $inf_f['label'];
	}

	$wp_customize->add_setting( 'inf_settings[body_font]', array( 'type' => 'option', 'default' => 'system', 'sanitize_callback' => 'inf_sanitize_font' ) );
	$wp_customize->add_control(
		'inf_body_font',
		array(
			'label'       => __( 'Police du texte', 'infinity-market' ),
			'description' => __( 'Les polices « عربي » couvrent l\'arabe et le français.', 'infinity-market' ),
			'section'     => 'inf_typo',
			'type'        => 'select',
			'choices'     => $inf_font_choices,
		)
	);

	$wp_customize->add_setting( 'inf_settings[heading_font]', array( 'type' => 'option', 'default' => 'system', 'sanitize_callback' => 'inf_sanitize_font' ) );
	$wp_customize->add_control(
		'inf_heading_font',
		array(
			'label'   => __( 'Police des titres', 'infinity-market' ),
			'section' => 'inf_typo',
			'type'    => 'select',
			'choices' => $inf_font_choices,
		)
	);

	$wp_customize->add_setting( 'inf_settings[font_size]', array( 'type' => 'option', 'default' => 16, 'sanitize_callback' => 'inf_sanitize_font_size' ) );
	$wp_customize->add_control(
		'inf_font_size',
		array(
			'label'       => __( 'Taille du texte (px)', 'infinity-market' ),
			'section'     => 'inf_typo',
			'type'        => 'range',
			'input_attrs' => array( 'min' => 14, 'max' => 20, 'step' => 1 ),
		)
	);

	/* ----- Arrière-plan ----- */
	$wp_customize->add_section( 'inf_background', array( 'title' => __( 'Arrière-plan', 'infinity-market' ), 'panel' => 'inf_appearance' ) );

	$wp_customize->add_setting( 'inf_settings[bg_color]', array( 'type' => 'option', 'default' => '', 'sanitize_callback' => 'sanitize_hex_color' ) );
	$wp_customize->add_control(
		new WP_Customize_Color_Control(
			$wp_customize,
			'inf_bg_color',
			array(
				'label'   => __( 'Couleur de fond du site', 'infinity-market' ),
				'section' => 'inf_background',
			)
		)
	);

	$wp_customize->add_setting( 'inf_settings[bg_image]', array( 'type' => 'option', 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
	$wp_customize->add_control(
		new WP_Customize_Image_Control(
			$wp_customize,
			'inf_bg_image',
			array(
				'label'       => __( 'Image de fond', 'infinity-market' ),
				'description' => __( 'Un voile clair est appliqué automatiquement pour garder le texte lisible.', 'infinity-market' ),
				'section'     => 'inf_background',
			)
		)
	);

	/* ----- Images d'accueil ----- */
	$wp_customize->add_section( 'inf_images', array( 'title' => __( 'Images d\'accueil', 'infinity-market' ), 'panel' => 'inf_appearance' ) );

	$wp_customize->add_setting( 'inf_settings[hero_image]', array( 'type' => 'option', 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
	$wp_customize->add_control(
		new WP_Customize_Image_Control(
			$wp_customize,
			'inf_hero_image',
			array(
				'label'       => __( 'Image du hero', 'infinity-market' ),
				'description' => __( 'Grande image de la page d\'accueil (InfinityMarket / InfinityMarket).', 'infinity-market' ),
				'section'     => 'inf_images',
			)
		)
	);

	$wp_customize->add_setting( 'inf_settings[product_image]', array( 'type' => 'option', 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
	$wp_customize->add_control(
		new WP_Customize_Image_Control(
			$wp_customize,
			'inf_product_image',
			array(
				'label'       => __( 'Image du produit phare', 'infinity-market' ),
				'description' => __( 'Photo du produit (InfinityLanding + formulaire COD).', 'infinity-market' ),
				'section'     => 'inf_images',
			)
		)
	);
}

/* Sanitises dédiées (noms explicites pour le Customizer). */
function inf_sanitize_header_style( $value ) {
	return inf_sanitize_choice( $value, array( 'gradient', 'flat', 'dark' ), 'gradient' );
}
function inf_sanitize_footer_style( $value ) {
	return inf_sanitize_choice( $value, array( 'dark', 'light' ), 'dark' );
}
function inf_sanitize_radius_style( $value ) {
	return inf_sanitize_choice( $value, array( 'square', 'soft', 'round', 'pill' ), 'round' );
}
function inf_sanitize_container( $value ) {
	return max( 960, min( 1440, (int) $value ) );
}
function inf_sanitize_font_size( $value ) {
	return max( 14, min( 20, (int) $value ) );
}

/* -----------------------------------------------------------------
 * Front : polices + CSS dynamique
 * ----------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', 'inf_customizer_fonts', 20 );
function inf_customizer_fonts() {
	$inf_body = inf_get_setting( 'body_font', 'system' );
	$inf_head = inf_get_setting( 'heading_font', 'system' );

	$inf_url_body = inf_font_url( $inf_body );
	if ( $inf_url_body ) {
		wp_enqueue_style( 'inf-font-body', $inf_url_body, array(), null );
	}
	if ( $inf_head !== $inf_body ) {
		$inf_url_head = inf_font_url( $inf_head );
		if ( $inf_url_head ) {
			wp_enqueue_style( 'inf-font-head', $inf_url_head, array(), null );
		}
	}
}

add_action( 'wp_head', 'inf_customizer_css', 99 );
function inf_customizer_css() {
	$inf_presets = inf_palette_presets();
	$inf_palette = inf_get_setting( 'palette', 'blue' );
	$inf_custom  = inf_get_setting( 'accent' );

	if ( $inf_custom ) {
		$inf_accent = $inf_custom;
		$inf_dark   = inf_darken_hex( $inf_custom );
	} else {
		$inf_p      = isset( $inf_presets[ $inf_palette ] ) ? $inf_presets[ $inf_palette ] : reset( $inf_presets );
		$inf_accent = $inf_p['accent'];
		$inf_dark   = inf_darken_hex( $inf_p['accent'] );
	}

	$inf_fonts     = inf_fonts();
	$inf_stack     = "-apple-system, 'Segoe UI', Tahoma, sans-serif";
	$inf_body_id   = inf_get_setting( 'body_font', 'system' );
	$inf_head_id   = inf_get_setting( 'heading_font', 'system' );
	$inf_font_body = ( isset( $inf_fonts[ $inf_body_id ]['family'] ) && $inf_fonts[ $inf_body_id ]['family'] ) ? "'" . $inf_fonts[ $inf_body_id ]['family'] . "', " . $inf_stack : $inf_stack;
	$inf_font_head = ( isset( $inf_fonts[ $inf_head_id ]['family'] ) && $inf_fonts[ $inf_head_id ]['family'] ) ? "'" . $inf_fonts[ $inf_head_id ]['family'] . "', " . $inf_stack : $inf_stack;

	$inf_radii     = array( 'square' => '2px', 'soft' => '8px', 'round' => '14px', 'pill' => '22px' );
	$inf_radius    = $inf_radii[ inf_get_setting( 'radius_style', 'round' ) ];
	$inf_container = (int) inf_get_setting( 'container_width', 1200 );
	$inf_font_size = (int) inf_get_setting( 'font_size', 16 );

	$inf_css  = ':root{--inf-accent:' . $inf_accent . ';--inf-accent-dark:' . $inf_dark . ';--inf-radius:' . $inf_radius . ';--inf-container:' . $inf_container . 'px;--inf-font:' . $inf_font_body . ';--inf-font-head:' . $inf_font_head . ';}';
	$inf_css .= 'body.inf-body{font-size:' . $inf_font_size . 'px;}';
	$inf_css .= 'h1,h2,h3,h4,h5,h6,.inf-hero__title,.inf-section__title,.inf-article__title,.inf-entry__title{font-family:var(--inf-font-head);}';

	$inf_bg = inf_get_setting( 'bg_color' );
	if ( $inf_bg ) {
		$inf_css .= 'body.inf-body{background-color:' . $inf_bg . ';}';
	}
	$inf_bg_image = inf_get_setting( 'bg_image' );
	if ( $inf_bg_image ) {
		$inf_css .= 'body.inf-body{background-image:linear-gradient(rgba(246,248,252,.9),rgba(246,248,252,.9)),url(' . esc_url( $inf_bg_image ) . ');background-size:cover;background-position:center;background-attachment:fixed;}';
	}

	switch ( inf_get_setting( 'header_style', 'gradient' ) ) {
		case 'flat':
			$inf_css .= '.inf-nav{background:' . $inf_accent . ';}';
			break;
		case 'dark':
			$inf_css .= '.inf-nav{background:linear-gradient(90deg,#0f1f4b,#16307c);}';
			break;
	}

	if ( 'light' === inf_get_setting( 'footer_style', 'dark' ) ) {
		$inf_css .= '.inf-footer{background:#f1f5fb;color:#475569;border-top:1px solid #e2e8f4;}';
		$inf_css .= '.inf-footer a{color:#334155;}.inf-footer a:hover{color:' . $inf_accent . ';}';
		$inf_css .= '.inf-footer .inf-widget__title{color:#0f172a;border-color:#e2e8f4;}';
		$inf_css .= '.inf-footer .inf-widget li{border-color:#e2e8f4;}';
		$inf_css .= '.inf-footer__badges{background:#e8eefb;color:#475569;}';
		$inf_css .= '.inf-socials__link{background:#e2e8f4;color:#334155;}';
	}

	echo '<style id="inf-customizer-css">' . $inf_css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- valeurs toutes sanitées ci-dessus.
}
