<?php
/**
 * Tema hijo Marbella in Style.
 *
 * Solo diseño: tipos de contenido y lógica van en los plugins propios
 * (maros-connector, mis-contenido), según la constitución.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function () {
		load_child_theme_textdomain( 'marbellainstyle', get_stylesheet_directory() . '/languages' );

		// Menús clásicos: Polylang (gratis) asigna un menú por idioma a cada ubicación.
		register_nav_menus(
			array(
				'primary'        => __( 'Cabecera: principal', 'marbellainstyle' ),
				'overlay'        => __( 'Capa «Menú»: secundario', 'marbellainstyle' ),
				'footer-stay'    => __( 'Pie: alojamiento', 'marbellainstyle' ),
				'footer-services'=> __( 'Pie: servicios y experiencias', 'marbellainstyle' ),
				'footer-company' => __( 'Pie: empresa', 'marbellainstyle' ),
				'footer-legal'   => __( 'Pie: legal', 'marbellainstyle' ),
			)
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		$file = get_stylesheet_directory() . '/assets/css/mis.css';
		wp_enqueue_style( 'marbellainstyle', get_stylesheet_directory_uri() . '/assets/css/mis.css', array(), (string) filemtime( $file ) );
	}
);
add_action(
	'after_setup_theme',
	static function () {
		add_editor_style( 'assets/css/mis.css' );
	}
);

// Categoría propia para los patrones de marca (en /patterns se registran solos).
add_action(
	'init',
	static function () {
		register_block_pattern_category( 'marbellainstyle', array( 'label' => __( 'Marbella in Style', 'marbellainstyle' ) ) );
	}
);

function mis_is_es(): bool {
	return function_exists( 'pll_current_language' ) && 'es' === pll_current_language();
}

/**
 * Texto en el idioma actual: mis_t( 'Stay', 'Alojamiento' ).
 */
function mis_t( string $en, string $es ): string {
	return mis_is_es() ? $es : $en;
}

/**
 * [mis_menu location="primary" class="…"] Menú clásico de una ubicación.
 */
add_shortcode(
	'mis_menu',
	static function ( $atts ) {
		$atts = shortcode_atts( array( 'location' => 'primary', 'class' => '' ), $atts );
		if ( ! has_nav_menu( $atts['location'] ) ) {
			return '';
		}
		return wp_nav_menu(
			array(
				'theme_location' => $atts['location'],
				'container'      => 'nav',
				'container_class'=> 'mis-menu mis-menu--' . sanitize_html_class( $atts['location'] ) . ' ' . sanitize_html_class( $atts['class'] ),
				'depth'          => 2,
				'echo'           => false,
				'fallback_cb'    => false,
			)
		);
	}
);

/**
 * [mis_lang] Selector de idioma EN / ES.
 */
add_shortcode(
	'mis_lang',
	static function () {
		if ( ! function_exists( 'pll_the_languages' ) ) {
			return '';
		}
		$langs = pll_the_languages( array( 'raw' => 1, 'hide_if_empty' => 0 ) );
		$out   = '<nav class="mis-lang" aria-label="Language">';
		foreach ( (array) $langs as $lang ) {
			$out .= sprintf(
				'<a href="%s" hreflang="%s" lang="%s"%s>%s</a>',
				esc_url( $lang['url'] ),
				esc_attr( $lang['slug'] ),
				esc_attr( $lang['slug'] ),
				$lang['current_lang'] ? ' aria-current="true"' : '',
				esc_html( strtoupper( $lang['slug'] ) )
			);
		}
		return $out . '</nav>';
	}
);

/**
 * [mis_t en="…" es="…"] Texto fijo de plantilla en el idioma actual.
 */
add_shortcode(
	'mis_t',
	static fn( $atts ) => esc_html( mis_t( (string) ( $atts['en'] ?? '' ), (string) ( $atts['es'] ?? '' ) ) )
);

/**
 * [mis_link to="contact" en="…" es="…" class="…"] Enlace a una página por su slug inglés,
 * resuelto a la traducción del idioma actual.
 */
add_shortcode(
	'mis_link',
	static function ( $atts ) {
		$atts = shortcode_atts( array( 'to' => '', 'en' => '', 'es' => '', 'class' => '' ), $atts );
		$url  = mis_page_url( $atts['to'] );
		return sprintf( '<a class="%s" href="%s">%s</a>', esc_attr( $atts['class'] ), esc_url( $url ), esc_html( mis_t( $atts['en'], $atts['es'] ) ) );
	}
);

function mis_page_url( string $slug ): string {
	$page = get_page_by_path( $slug );
	if ( ! $page ) {
		return home_url( '/' );
	}
	$id = function_exists( 'pll_get_post' ) ? ( pll_get_post( $page->ID ) ?: $page->ID ) : $page->ID;
	return (string) get_permalink( $id );
}

/**
 * [mis_book] Botón «Reservar» de la cabecera: lleva a Alojamiento.
 */
add_shortcode(
	'mis_book',
	static fn() => sprintf( '<a class="mis-book wp-element-button" href="%s">%s</a>', esc_url( mis_page_url( 'stay' ) ), esc_html( mis_t( 'Book', 'Reservar' ) ) )
);
