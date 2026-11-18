<?php
/**
 * Piezas del índice de Zonas y de la ficha de zona (maqueta «Zonas de Marbella in Style», aprobada
 * por Julio el 18-11-2026 con «N properties» en la tarjeta). Las monta el tema con shortcodes:
 *
 *  [mis_zona_portada]       Portada con foto: la zona (ficha) o la página (índice de Zonas).
 *  [mis_zona_migas]         Zonas › <zona>.
 *  [mis_zona_anclas]        Guía · Propiedades · Experiencias · Otras zonas (solo las que hay).
 *  [mis_zona_vistazo]       Recuadro «De un vistazo».
 *  [mis_zona_videos]        Reels de la zona (Instagram…), con el visor de las fichas (D18). Nada si no hay.
 *  [mis_zona_propiedades]   Casas de la zona (misma tarjeta que Alojamiento). Nada si no hay.
 *  [mis_zona_experiencias]  Experiencias de la zona. Nada si no hay.
 *  [mis_zonas_otras]        Franja con otras zonas.
 *  [mis_zonas filtros="si" max="0"]  Tarjetas de zona con filtros por perfil (índice e Inicio).
 *  [mis_zonas_hablar]       «¿No sabes qué zona elegir?» con el botón de contacto.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

function mis_zona_es(): bool {
	return function_exists( 'pll_current_language' ) && 'es' === pll_current_language();
}

function mis_zona_t( string $en, string $es ): string {
	return mis_zona_es() ? $es : $en;
}

/** La zona de la página actual (ficha de zona), o null. */
function mis_zona_actual(): ?WP_Term {
	$term = is_tax( 'zona' ) ? get_queried_object() : null;
	return $term instanceof WP_Term ? $term : null;
}

/** Casas publicadas en la zona (las de su idioma: cada traducción de la zona es un término propio). */
function mis_zona_ids( WP_Term $term, string $type = 'mis_property' ): array {
	static $cache = array();
	$key = $term->term_id . $type;
	if ( ! isset( $cache[ $key ] ) ) {
		$cache[ $key ] = get_posts(
			array(
				'post_type'        => $type,
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'lang'             => '',
				'suppress_filters' => false,
				'tax_query'        => array( array( 'taxonomy' => 'zona', 'terms' => $term->term_id, 'include_children' => false ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);
	}
	return $cache[ $key ];
}

/** «3 properties» / «3 propiedades». */
function mis_zona_n_propiedades( int $n ): string {
	return mis_zona_es()
		? sprintf( 1 === $n ? '%d propiedad' : '%d propiedades', $n )
		: sprintf( 1 === $n ? '%d property' : '%d properties', $n );
}

/** URL de la página de Zonas en el idioma actual. */
function mis_zonas_url(): string {
	if ( function_exists( 'mis_page_url' ) ) {
		return mis_page_url( 'areas' );
	}
	return home_url( mis_zona_es() ? '/es/zonas/' : '/areas/' );
}

/** <img> de la foto de la zona; mientras no tenga, la ilustración generada (Julio, 18-11-2026). */
function mis_zona_img( WP_Term $term, string $size, array $attr = array() ): string {
	$id = (int) get_term_meta( $term->term_id, 'zona_imagen', true );
	if ( $id ) {
		return wp_get_attachment_image( $id, $size, false, $attr + array( 'alt' => '' ) );
	}
	return mis_zona_sintetica( preg_replace( '/-es$/', '', $term->slug ), mis_zona_perfiles_de( $term->term_id ), (string) ( $attr['class'] ?? '' ) );
}

/**
 * Ilustración provisional de una zona: cielo, sol, montes y mar en SVG, con la luz y el paisaje
 * que tocan a sus perfiles (montaña, golf, playa, pueblo). Es la misma en los dos idiomas.
 */
function mis_zona_sintetica( string $seed, array $perfiles = array(), string $class = '' ): string {
	$h      = crc32( $seed );
	$luces  = array(
		array( '#F6E7CF', '#E9B98B', '#F8F1E4', '#2F6F8A', '#1F4E66' ), // mañana
		array( '#CFE3EE', '#F3E9D6', '#FFF8EA', '#2B7A99', '#1C5873' ), // mediodía
		array( '#F2D2B6', '#C98D6B', '#FBE3C4', '#3E6E85', '#24485C' ), // tarde
		array( '#E8DCCB', '#B99A78', '#F6EBDA', '#4A7488', '#2E4E5E' ), // dorada
	);
	list( $sky1, $sky2, $sun, $sea1, $sea2 ) = $luces[ $h % 4 ];
	$monte  = in_array( 'hills', $perfiles, true );
	$golf   = in_array( 'golf', $perfiles, true ) && ! in_array( 'beach', $perfiles, true );
	$pueblo = in_array( 'walk', $perfiles, true );
	$sx     = 300 + ( $h >> 3 ) % 1000;
	$hor    = $monte ? 580 : 520;
	$m1     = $monte ? 230 : 380;
	$m2     = $monte ? 330 : 440;
	$o      = ( $h >> 5 ) % 300;

	$svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid slice">';
	$svg .= '<defs><linearGradient id="c" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' . $sky1 . '"/><stop offset="1" stop-color="' . $sky2 . '"/></linearGradient>';
	$svg .= '<linearGradient id="m" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' . $sea1 . '"/><stop offset="1" stop-color="' . $sea2 . '"/></linearGradient></defs>';
	$svg .= '<rect width="1600" height="900" fill="url(#c)"/>';
	$svg .= '<circle cx="' . $sx . '" cy="' . ( $monte ? $m1 - 70 : $hor - 190 ) . '" r="70" fill="' . $sun . '" opacity=".9"/>';
	// Sierra al fondo y montes delante.
	$svg .= '<path d="M0 ' . $hor . ' L0 ' . ( $m1 + 120 ) . ' C' . ( 200 + $o ) . ' ' . $m1 . ' ' . ( 420 + $o ) . ' ' . ( $m1 + 60 ) . ' ' . ( 640 + $o ) . ' ' . ( $m1 - 20 ) . ' S1100 ' . ( $m1 + 90 ) . ' 1300 ' . ( $m1 + 10 ) . ' S1600 ' . ( $m1 + 80 ) . ' 1600 ' . ( $m1 + 80 ) . ' L1600 ' . $hor . ' Z" fill="#8C8A80" opacity=".45"/>';
	$svg .= '<path d="M0 ' . $hor . ' L0 ' . ( $m2 + 90 ) . ' C' . ( 300 - $o / 2 ) . ' ' . $m2 . ' ' . ( 560 - $o / 2 ) . ' ' . ( $m2 + 110 ) . ' 900 ' . ( $m2 + 50 ) . ' S1400 ' . ( $m2 + 120 ) . ' 1600 ' . ( $m2 + 60 ) . ' L1600 ' . $hor . ' Z" fill="#5F6B57" opacity=".7"/>';
	if ( $monte ) {
		// Valle verde en primer plano.
		$svg .= '<path d="M0 900 L0 560 C400 500 800 620 1200 540 S1600 570 1600 570 L1600 900 Z" fill="#4D5E45"/>';
		$svg .= '<path d="M0 900 L0 760 C500 690 1000 800 1600 720 L1600 900 Z" fill="#3B4A36"/>';
	} else {
		$svg .= '<rect y="' . $hor . '" width="1600" height="' . ( 900 - $hor ) . '" fill="url(#m)"/>';
		$svg .= '<path d="M0 ' . ( $hor + 40 ) . ' H1600 M0 ' . ( $hor + 110 ) . ' H1600 M0 ' . ( $hor + 210 ) . ' H1600" stroke="#fff" stroke-opacity=".12" stroke-width="3"/>';
		if ( $golf ) {
			$svg .= '<path d="M0 900 L0 720 C420 650 900 760 1600 690 L1600 900 Z" fill="#6E8B4E"/><path d="M0 900 L0 810 C600 760 1100 840 1600 790 L1600 900 Z" fill="#587540"/>';
		} else {
			$svg .= '<path d="M0 900 L0 800 C500 760 1100 820 1600 780 L1600 900 Z" fill="#E8D6B5"/>';
		}
	}
	if ( $pueblo ) {
		// Casas blancas en la ladera.
		for ( $i = 0; $i < 9; $i++ ) {
			$x    = 180 + $i * 120 + ( ( $h >> $i ) % 40 );
			$y    = $hor - 40 - ( ( $h >> ( $i + 2 ) ) % 50 );
			$svg .= '<rect x="' . $x . '" y="' . $y . '" width="64" height="40" fill="#FBF8F2"/><path d="M' . ( $x - 4 ) . ' ' . $y . ' L' . ( $x + 32 ) . ' ' . ( $y - 16 ) . ' L' . ( $x + 68 ) . ' ' . $y . ' Z" fill="#C97F5A"/>';
		}
	}
	$svg .= '</svg>';

	return '<img class="' . esc_attr( trim( $class . ' mis-zona-sintetica' ) ) . '" src="data:image/svg+xml;base64,' . base64_encode( $svg ) . '" alt="" width="1600" height="900" decoding="async">'; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

/** Tarjeta de zona: foto vertical con el nombre encima, perfiles y frase. */
function mis_zona_tarjeta( WP_Term $term, string $heading = 'h3' ): string {
	$n        = count( mis_zona_ids( $term ) );
	$perfiles = mis_zona_perfiles_de( $term->term_id );
	$nombres  = mis_zona_perfiles();
	$tags     = array_map( static fn( $k ) => $nombres[ $k ][ mis_zona_es() ? 1 : 0 ], array_filter( $perfiles, static fn( $k ) => isset( $nombres[ $k ] ) ) );
	$resumen  = (string) get_term_meta( $term->term_id, 'zona_resumen', true );
	$link     = get_term_link( $term );

	return sprintf(
		'<a class="mis-zcard" href="%1$s" data-perfiles="%2$s"><span class="mis-zcard__ph">%3$s%4$s<%5$s class="mis-zcard__name">%6$s</%5$s></span>%7$s%8$s</a>',
		esc_url( is_wp_error( $link ) ? '' : $link ),
		esc_attr( implode( ' ', $perfiles ) ),
		mis_zona_img( $term, 'large', array( 'loading' => 'lazy', 'class' => 'mis-zcard__img' ) ),
		$n ? '<span class="mis-zcard__count">' . esc_html( mis_zona_n_propiedades( $n ) ) . '</span>' : '',
		tag_escape( $heading ),
		esc_html( $term->name ),
		$tags ? '<span class="mis-zcard__tags">' . esc_html( implode( ' · ', $tags ) ) . '</span>' : '',
		'' !== $resumen ? '<span class="mis-zcard__text">' . esc_html( $resumen ) . '</span>' : ''
	);
}

/** Zonas del idioma actual: primero las que tienen casas (más casas primero), luego por nombre. */
function mis_zonas_ordenadas( int $excluir = 0 ): array {
	$terms = get_terms( array( 'taxonomy' => 'zona', 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	$terms = array_values( array_filter( $terms, static fn( $t ) => (int) $t->term_id !== $excluir ) );
	usort(
		$terms,
		static function ( $a, $b ) {
			$d = count( mis_zona_ids( $b ) ) <=> count( mis_zona_ids( $a ) );
			return $d ?: strcoll( remove_accents( $a->name ), remove_accents( $b->name ) );
		}
	);
	return $terms;
}

/** Sección a lo ancho, con el mismo relleno que las del resto de la web. */
function mis_zona_seccion( string $inner, string $id = '', bool $tint = false ): string {
	return sprintf(
		'<section class="mis-zsect%s"%s><div class="mis-zsect__in">%s</div></section>',
		$tint ? ' mis-zsect--tint' : '',
		$id ? ' id="' . esc_attr( $id ) . '"' : '',
		$inner
	);
}

function mis_zona_cabecera( string $eyebrow, string $title, string $link = '', string $link_text = '' ): string {
	$head = '<div><p class="mis-eyebrow">' . esc_html( $eyebrow ) . '</p><h2 class="wp-block-heading has-large-font-size">' . esc_html( $title ) . '</h2></div>';
	if ( $link ) {
		$head .= '<a class="mis-link-action" href="' . esc_url( $link ) . '">' . esc_html( $link_text ) . '</a>';
	}
	return '<div class="mis-zsect__head">' . $head . '</div>';
}

// ------------------------------------------------------------------ shortcodes

add_shortcode(
	'mis_zona_portada',
	static function () {
		$term = mis_zona_actual();
		if ( $term ) {
			$img     = mis_zona_img( $term, 'full', array( 'class' => 'mis-zona-cover__img', 'fetchpriority' => 'high', 'sizes' => '100vw' ) );
			$eyebrow = mis_zona_t( 'Area guide', 'Guía de zona' );
			$title   = $term->name;
			$lead    = (string) get_term_meta( $term->term_id, 'zona_resumen', true );
		} else {
			$page    = get_queried_object_id();
			$img     = has_post_thumbnail( $page ) ? get_the_post_thumbnail( $page, 'full', array( 'class' => 'mis-zona-cover__img', 'fetchpriority' => 'high', 'sizes' => '100vw', 'alt' => '' ) ) : mis_zona_sintetica( 'areas', array( 'beach' ), 'mis-zona-cover__img' );
			$eyebrow = mis_zona_t( 'Areas', 'Zonas' );
			$title   = mis_zona_t( 'Where to stay in Marbella', 'Dónde alojarse en Marbella' );
			$lead    = mis_zona_t(
				'Thirteen areas from Marbella to Estepona, each with its own pace. Choose by beach, golf or walking distance, then see the homes we manage there.',
				'Trece zonas de Marbella a Estepona, cada una con su ritmo. Elige por playa, golf o por tenerlo todo a pie, y mira las casas que gestionamos allí.'
			);
		}
		return sprintf(
			'<div class="mis-zona-cover%s">%s<div class="mis-zona-cover__in"><p class="mis-eyebrow">%s</p><h1 class="mis-zona-cover__title">%s</h1>%s</div></div>',
			$img ? '' : ' mis-zona-cover--sin-foto',
			$img,
			esc_html( $eyebrow ),
			esc_html( $title ),
			'' !== $lead ? '<p class="mis-zona-cover__lead">' . esc_html( $lead ) . '</p>' : ''
		);
	}
);

add_shortcode(
	'mis_zona_migas',
	static function () {
		$term = mis_zona_actual();
		if ( ! $term ) {
			return '';
		}
		return '<nav class="mis-crumbs mis-zona-crumbs" aria-label="' . esc_attr( mis_zona_t( 'Breadcrumbs', 'Migas de pan' ) ) . '"><a href="' . esc_url( mis_zonas_url() ) . '">' . esc_html( mis_zona_t( 'Areas', 'Zonas' ) ) . '</a><span class="mis-crumbs__sep" aria-hidden="true">›</span><span aria-current="page">' . esc_html( $term->name ) . '</span></nav>';
	}
);

add_shortcode(
	'mis_zona_anclas',
	static function () {
		$term = mis_zona_actual();
		if ( ! $term ) {
			return '';
		}
		$n     = count( mis_zona_ids( $term ) );
		$items = array( '#guia' => mis_zona_t( 'Guide', 'Guía' ) );
		if ( mis_zona_videos( $term ) ) {
			$items['#videos'] = mis_zona_t( 'Videos', 'Vídeos' );
		}
		if ( $n ) {
			$items['#propiedades'] = mis_zona_t( 'Properties', 'Propiedades' ) . ' · ' . $n;
		}
		if ( mis_zona_ids( $term, 'mis_tour' ) ) {
			$items['#experiencias'] = mis_zona_t( 'Experiences', 'Experiencias' );
		}
		$items['#otras-zonas'] = mis_zona_t( 'Other areas', 'Otras zonas' );
		$links                 = '';
		foreach ( $items as $href => $label ) {
			$links .= '<a href="' . esc_attr( $href ) . '">' . esc_html( $label ) . '</a>';
		}
		return '<nav class="mis-anchors mis-zona-anchors" aria-label="' . esc_attr( mis_zona_t( 'On this page', 'En esta página' ) ) . '">' . $links . '</nav>';
	}
);

add_shortcode(
	'mis_zona_vistazo',
	static function () {
		$term = mis_zona_actual();
		if ( ! $term ) {
			return '';
		}
		$rows = array(
			'zona_ideal'   => mis_zona_t( 'Ideal for', 'Ideal para' ),
			'zona_playas'  => mis_zona_t( 'Beaches', 'Playas' ),
			'zona_golf'    => 'Golf',
			'zona_moverse' => mis_zona_t( 'Getting around', 'Cómo moverse' ),
		);
		$dl = '';
		foreach ( $rows as $key => $label ) {
			$val = (string) get_term_meta( $term->term_id, $key, true );
			if ( '' !== $val ) {
				$dl .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $val ) . '</dd></div>';
			}
		}
		$n   = count( mis_zona_ids( $term ) );
		$cta = $n
			? '<a class="wp-element-button mis-zona-glance__cta" href="#propiedades">' . esc_html( mis_zona_es() ? sprintf( 1 === $n ? 'Ver la propiedad' : 'Ver las %d propiedades', $n ) : sprintf( 1 === $n ? 'See the property' : 'See the %d properties', $n ) ) . '</a>'
			: '';
		$contact = function_exists( 'mis_page_url' ) ? mis_page_url( 'contact' ) : home_url( mis_zona_es() ? '/es/contacto/' : '/contact/' );
		$persona = do_shortcode( '[mis_persona para="guests" estilo="compacto" en="Questions about the area?" es="¿Dudas sobre la zona?"]' );
		if ( '' === $dl && '' === $cta ) {
			return $persona;
		}
		return '<div class="mis-zona-side"><aside class="mis-zona-glance">'
			. ( $dl ? '<p class="mis-zona-glance__title">' . esc_html( mis_zona_t( 'At a glance', 'De un vistazo' ) ) . '</p><dl>' . $dl . '</dl>' : '' )
			. $cta
			. '</aside>'
			. ( '' !== $persona ? $persona : '<p class="mis-zona-glance__help">' . esc_html( mis_zona_t( 'Questions about the area?', '¿Dudas sobre la zona?' ) ) . ' <a href="' . esc_url( $contact ) . '">' . esc_html( mis_zona_t( 'Talk to our team', 'Habla con nuestro equipo' ) ) . '</a></p>' )
			. '</div>';
	}
);

add_shortcode(
	'mis_zona_propiedades',
	static function () {
		$term = mis_zona_actual();
		if ( ! $term || ! mis_zona_ids( $term ) ) {
			return '';
		}
		$stay  = function_exists( 'mis_page_url' ) ? mis_page_url( 'stay' ) : home_url( '/stay/' );
		$query = '<!-- wp:query {"queryId":11,"query":{"perPage":24,"pages":0,"offset":0,"postType":"mis_property","order":"asc","orderBy":"title","inherit":false,"taxQuery":{"zona":[' . (int) $term->term_id . ']}},"align":"wide"} -->
<div class="wp-block-query alignwide"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3,"minimumColumnWidth":"16rem"}} -->
<!-- wp:group {"className":"mis-card mis-card--property","layout":{"type":"default"}} --><div class="wp-block-group mis-card mis-card--property">
<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/5"} /-->
<!-- wp:paragraph {"className":"mis-eyebrow","metadata":{"bindings":{"content":{"source":"maros/property","args":{"key":"area"}}}}} --><p class="mis-eyebrow"></p><!-- /wp:paragraph -->
<!-- wp:post-title {"isLink":true,"level":3} /-->
<!-- wp:paragraph {"className":"mis-muted","metadata":{"bindings":{"content":{"source":"maros/property","args":{"key":"facts"}}}}} --><p class="mis-muted"></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"className":"mis-card__features","metadata":{"bindings":{"content":{"source":"maros/property","args":{"key":"features"}}}}} --><p class="mis-card__features"></p><!-- /wp:paragraph -->
</div><!-- /wp:group -->
<!-- /wp:post-template --></div><!-- /wp:query -->';
		return mis_zona_seccion(
			mis_zona_cabecera( mis_zona_t( 'Stay in this area', 'Alojarse en esta zona' ), sprintf( mis_zona_t( 'Properties in %s', 'Propiedades en %s' ), $term->name ), $stay, mis_zona_t( 'All properties', 'Todas las propiedades' ) ) . do_blocks( $query ),
			'propiedades',
			true
		);
	}
);

add_shortcode(
	'mis_zona_experiencias',
	static function () {
		$term = mis_zona_actual();
		if ( ! $term || ! mis_zona_ids( $term, 'mis_tour' ) ) {
			return '';
		}
		$query = '<!-- wp:query {"queryId":12,"query":{"perPage":6,"pages":0,"offset":0,"postType":"mis_tour","order":"asc","orderBy":"title","inherit":false,"taxQuery":{"zona":[' . (int) $term->term_id . ']}},"align":"wide"} -->
<div class="wp-block-query alignwide"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3,"minimumColumnWidth":"16rem"}} -->
<!-- wp:group {"className":"mis-card","layout":{"type":"default"}} --><div class="wp-block-group mis-card">
<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3"} /-->
<!-- wp:post-title {"isLink":true,"level":3} /-->
<!-- wp:paragraph {"className":"mis-muted","metadata":{"bindings":{"content":{"source":"mis/tour","args":{"key":"facts"}}}}} --><p class="mis-muted"></p><!-- /wp:paragraph -->
</div><!-- /wp:group -->
<!-- /wp:post-template --></div><!-- /wp:query -->';
		return mis_zona_seccion(
			mis_zona_cabecera( mis_zona_t( 'From this area', 'Desde esta zona' ), mis_zona_t( 'Experiences nearby', 'Experiencias cerca' ) ) . do_blocks( $query ),
			'experiencias'
		);
	}
);

add_shortcode(
	'mis_zonas_otras',
	static function () {
		$term = mis_zona_actual();
		$otras = array_slice( mis_zonas_ordenadas( $term ? (int) $term->term_id : 0 ), 0, 8 );
		if ( ! $otras ) {
			return '';
		}
		$cards = implode( '', array_map( static fn( $t ) => mis_zona_tarjeta( $t ), $otras ) );
		return mis_zona_seccion(
			mis_zona_cabecera( mis_zona_t( 'Keep exploring', 'Sigue explorando' ), mis_zona_t( 'Other areas', 'Otras zonas' ), mis_zonas_url(), mis_zona_t( 'All areas', 'Todas las zonas' ) )
			. '<div class="mis-zstrip">' . $cards . '</div>',
			'otras-zonas',
			true
		);
	}
);

add_shortcode(
	'mis_zonas',
	static function ( $atts ) {
		$atts  = shortcode_atts( array( 'filtros' => 'si', 'max' => '0' ), $atts );
		$zonas = mis_zonas_ordenadas();
		$max   = (int) $atts['max'];
		if ( $max > 0 ) {
			$zonas = array_slice( $zonas, 0, $max );
		}
		if ( ! $zonas ) {
			return '';
		}
		$chips = '';
		if ( 'no' !== $atts['filtros'] ) {
			$usados = array();
			foreach ( $zonas as $z ) {
				$usados = array_merge( $usados, mis_zona_perfiles_de( $z->term_id ) );
			}
			$buttons = '<button type="button" data-perfil="" aria-pressed="true">' . esc_html( mis_zona_t( 'All areas', 'Todas' ) ) . '</button>';
			foreach ( mis_zona_perfiles() as $key => $names ) {
				if ( in_array( $key, $usados, true ) ) {
					$buttons .= '<button type="button" data-perfil="' . esc_attr( $key ) . '" aria-pressed="false">' . esc_html( $names[ mis_zona_es() ? 1 : 0 ] ) . '</button>';
				}
			}
			$chips = '<div class="mis-zfilters" role="group" aria-label="' . esc_attr( mis_zona_t( 'Filter areas', 'Filtrar zonas' ) ) . '">' . $buttons . '</div>';
		}
		$grid = '<div class="mis-zgrid">' . implode( '', array_map( static fn( $t ) => mis_zona_tarjeta( $t ), $zonas ) ) . '</div>';
		$js   = $chips ? '<script>(function(s){var f=s.previousElementSibling.previousElementSibling,g=s.previousElementSibling;f.addEventListener("click",function(e){var b=e.target.closest("button");if(!b)return;f.querySelectorAll("button").forEach(function(x){x.setAttribute("aria-pressed",String(x===b));});var p=b.dataset.perfil;g.querySelectorAll(".mis-zcard").forEach(function(c){c.hidden=!!p&&(" "+c.dataset.perfiles+" ").indexOf(" "+p+" ")<0;});});})(document.currentScript);</script>' : '';
		return '<div class="mis-zonas">' . $chips . $grid . $js . '</div>';
	}
);

add_shortcode(
	'mis_zonas_hablar',
	static function () {
		$persona = do_shortcode( '[mis_persona para="guests" en="Not sure which area suits you?" es="¿No sabes qué zona elegir?"]' );
		if ( '' !== $persona ) {
			return mis_zona_seccion( $persona, '', true );
		}
		$contact = function_exists( 'mis_page_url' ) ? mis_page_url( 'contact' ) : home_url( mis_zona_es() ? '/es/contacto/' : '/contact/' );
		return mis_zona_seccion(
			'<p class="mis-eyebrow">' . esc_html( mis_zona_t( 'Talk to us', 'Habla con nosotros' ) ) . '</p>'
			. '<h2 class="wp-block-heading has-large-font-size">' . esc_html( mis_zona_t( 'Not sure which area suits you?', '¿No sabes qué zona elegir?' ) ) . '</h2>'
			. '<p class="mis-muted">' . esc_html( mis_zona_t( 'Tell us who is coming and what you want to do, and our team will suggest the area and the home.', 'Cuéntanos quién viene y qué queréis hacer, y nuestro equipo te propondrá la zona y la casa.' ) ) . '</p>'
			. '<div class="wp-block-buttons"><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $contact ) . '">' . esc_html( mis_zona_t( 'Contact us', 'Contactar' ) ) . '</a></div></div>',
			'',
			true
		);
	}
);

// ------------------------------------------------------------------ vídeos

/**
 * Vídeos de la zona (term meta `zona_videos`, el mismo JSON que `mis_videos` de las fichas). Usa
 * las funciones y el visor de maros-connector (includes/videos.php): sin ellas, no hay vídeos.
 */
function mis_zona_videos( WP_Term $term ): array {
	if ( ! function_exists( 'maros_video_embed' ) ) {
		return array();
	}
	$list = json_decode( (string) get_term_meta( $term->term_id, 'zona_videos', true ), true );
	$out  = array();
	foreach ( is_array( $list ) ? $list : array() as $v ) {
		$url      = (string) ( $v['url'] ?? '' );
		$platform = (string) ( $v['platform'] ?? '' );
		if ( '' === $url || '' === $platform ) {
			continue;
		}
		$embed = maros_video_embed( $url, $platform );
		$out[] = array( 'url' => $url, 'platform' => $platform, 'cover' => (int) ( $v['cover'] ?? 0 ), 'embed' => $embed['src'], 'vertical' => $embed['vertical'] );
	}
	return $out;
}

add_shortcode(
	'mis_zona_videos',
	static function () {
		$term   = mis_zona_actual();
		$videos = $term ? mis_zona_videos( $term ) : array();
		if ( ! $videos ) {
			return '';
		}
		$inline = (bool) apply_filters( 'maros_videos_inline', true, 0 );
		$total  = count( $videos );
		// Sin carátula: la foto de la zona.
		$fallback = (int) get_term_meta( $term->term_id, 'zona_imagen', true );
		$cards    = '';
		foreach ( $videos as $i => $v ) {
			$label  = maros_video_platform_label( $v['platform'] );
			$cover  = $v['cover'] && wp_attachment_is_image( $v['cover'] ) ? $v['cover'] : $fallback;
			$img    = $cover ? wp_get_attachment_image( $cover, 'medium_large', false, array( 'class' => 'mis-video__img', 'alt' => '', 'loading' => 'lazy', 'sizes' => '(min-width: 768px) 260px, 62vw' ) ) : '<span class="mis-video__img mis-video__img--empty"></span>';
			$aria   = sprintf( mis_zona_t( 'Watch video %1$d of %2$d of %3$s on %4$s', 'Ver el vídeo %1$d de %2$d de %3$s en %4$s' ), $i + 1, $total, $term->name, $label );
			$data   = $inline && '' !== $v['embed'] ? ' data-mis-video="' . esc_url( $v['embed'] ) . '" data-mis-video-platform="' . esc_attr( $label ) . '"' . ( $v['vertical'] ? ' data-mis-video-vertical' : '' ) : '';
			$cards .= '<li class="mis-videos__item"><a class="mis-video" href="' . esc_url( $v['url'] ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( $aria ) . '"' . $data . '>' . $img . '<span class="mis-video__play">' . maros_video_play_svg() . '</span><span class="mis-video__platform">' . esc_html( $label ) . '</span></a></li>';
		}

		$insta = function_exists( 'mis_contact' ) ? mis_contact( 'instagram' ) : '';
		$html  = mis_zona_cabecera( mis_zona_t( 'Videos', 'Vídeos' ), sprintf( mis_zona_t( '%s on video', '%s en vídeo' ), $term->name ), $insta, mis_zona_t( 'Follow us on Instagram', 'Síguenos en Instagram' ) );
		$html .= '<div class="mis-videos"><ul class="mis-videos__track">' . $cards . '</ul></div>';
		if ( $inline ) {
			$html .= '<dialog class="mis-video-lb" aria-label="' . esc_attr( sprintf( mis_zona_t( 'Video of %s', 'Vídeo de %s' ), $term->name ) ) . '">';
			$html .= '<div class="mis-video-lb__bar"><a class="mis-video-lb__out" href="#" target="_blank" rel="noopener"></a>';
			$html .= '<button type="button" class="mis-video-lb__close" data-mis-video-close aria-label="' . esc_attr( mis_zona_t( 'Close video', 'Cerrar vídeo' ) ) . '"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>';
			$html .= '<div class="mis-video-lb__stage" data-out-label="' . esc_attr( mis_zona_t( 'Watch on %s', 'Ver en %s' ) ) . '"></div>';
			$html .= '</dialog>';
			$js = WP_PLUGIN_DIR . '/maros-connector/assets/videos.js';
			if ( is_readable( $js ) ) {
				wp_enqueue_script( 'maros-videos', plugins_url( 'maros-connector/assets/videos.js' ), array(), (string) filemtime( $js ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
			}
		}
		return mis_zona_seccion( $html, 'videos' );
	}
);


// ------------------------------------------------------------------ ficha de propiedad

/**
 * [mis_zona_ficha] Apartado «Location» de la ficha de propiedad: la zona de la casa con su foto (o
 * la ilustración), la frase corta, «De un vistazo» y el enlace a la guía. Nada si la casa no tiene zona.
 */
add_shortcode(
	'mis_zona_ficha',
	static function () {
		$terms = get_the_terms( (int) get_the_ID(), 'zona' );
		$term  = $terms && ! is_wp_error( $terms ) ? $terms[0] : null;
		if ( ! $term instanceof WP_Term ) {
			return '';
		}
		$link    = get_term_link( $term );
		$link    = is_wp_error( $link ) ? '' : $link;
		$resumen = (string) get_term_meta( $term->term_id, 'zona_resumen', true );
		$rows    = array(
			'zona_ideal'   => mis_zona_t( 'Ideal for', 'Ideal para' ),
			'zona_playas'  => mis_zona_t( 'Beaches', 'Playas' ),
			'zona_golf'    => 'Golf',
			'zona_moverse' => mis_zona_t( 'Getting around', 'Cómo moverse' ),
		);
		$dl = '';
		foreach ( $rows as $key => $label ) {
			$val = (string) get_term_meta( $term->term_id, $key, true );
			if ( '' !== $val ) {
				$dl .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $val ) . '</dd></div>';
			}
		}
		return '<section id="area" class="mis-ficha-seccion">'
			. '<h2 class="wp-block-heading has-large-font-size">' . esc_html( mis_zona_t( 'Area Guide', 'Guía de la zona' ) ) . '</h2>'
			. '<div class="mis-zona-ficha">'
			. '<a class="mis-zona-ficha__ph" href="' . esc_url( $link ) . '" tabindex="-1" aria-hidden="true">' . mis_zona_img( $term, 'large', array( 'loading' => 'lazy', 'class' => 'mis-zona-ficha__img' ) ) . '</a>'
			. '<div class="mis-zona-ficha__body">'
			. '<h3 class="mis-zona-ficha__name"><a href="' . esc_url( $link ) . '">' . esc_html( $term->name ) . '</a></h3>'
			. ( '' !== $resumen ? '<p class="mis-zona-ficha__text">' . esc_html( $resumen ) . '</p>' : '' )
			. ( $dl ? '<dl class="mis-zona-ficha__dl">' . $dl . '</dl>' : '' )
			. '<a class="mis-link-action" href="' . esc_url( $link ) . '">' . esc_html( mis_zona_t( 'Read the area guide', 'Leer la guía de la zona' ) ) . '</a>'
			. '</div></div></section>';
	}
);

/**
 * [mis_cerca] Apartado «Close to the Villa / Apartment» de la ficha (Julio, 18-11-2026): lo que tiene
 * cerca la casa, en coche, con las distancias que marOS mide con OpenStreetMap (maros_nearby; las de
 * Google no se pueden publicar), y la nota de la dirección. Sin medidas, queda «Location» con la nota.
 */
add_shortcode(
	'mis_cerca',
	static function () {
		$post_id = (int) get_the_ID();
		$list    = mis_zona_cerca( $post_id );
		$types   = get_the_terms( $post_id, 'tipo-propiedad' );
		$slug    = $types && ! is_wp_error( $types ) ? preg_replace( '/-es$/', '', $types[0]->slug ) : '';
		$title   = ! $list ? mis_zona_t( 'Location', 'Ubicación' )
			: ( 'villa' === $slug ? mis_zona_t( 'Close to the Villa', 'Cerca de la villa' )
			: ( 'apartment' === $slug ? mis_zona_t( 'Close to the Apartment', 'Cerca del apartamento' )
			: mis_zona_t( 'Close to the Property', 'Cerca de la casa' ) ) );
		$lat = (float) get_post_meta( $post_id, 'maros_lat', true );
		$lng = (float) get_post_meta( $post_id, 'maros_lng', true );
		$map = '';
		if ( $lat && $lng ) {
			// marOS solo manda coordenadas propias (no de Google) y redondeadas a 2 decimales (~1 km).
			$base = plugins_url( 'assets/', dirname( __DIR__ ) . '/mis-contenido.php' );
			$dir  = dirname( __DIR__ ) . '/assets/';
			wp_enqueue_style( 'leaflet', $base . 'leaflet/leaflet.css', array(), '1.9.4' );
			wp_enqueue_script( 'leaflet', $base . 'leaflet/leaflet.js', array(), '1.9.4', array( 'in_footer' => true, 'strategy' => 'defer' ) );
			wp_enqueue_script( 'mis-mapa-cerca', $base . 'mapa-cerca.js', array( 'leaflet' ), (string) filemtime( $dir . 'mapa-cerca.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
			$map = '<div class="mis-cerca-mapa" data-lat="' . esc_attr( (string) $lat ) . '" data-lng="' . esc_attr( (string) $lng ) . '" role="img" aria-label="'
				. esc_attr( mis_zona_t( 'Map of the approximate area of the house', 'Mapa de la zona aproximada de la casa' ) ) . '"></div>';
		}
		$body = $list . '<p class="mis-muted">' . esc_html( mis_zona_t( 'The exact address is shared after booking.', 'La dirección exacta se envía tras la reserva.' ) ) . '</p>';
		return '<section id="location" class="mis-ficha-seccion">'
			. '<h2 class="wp-block-heading has-large-font-size">' . esc_html( $title ) . '</h2>'
			. ( $map ? '<div class="mis-cerca' . ( $list ? '' : ' mis-cerca--solo-mapa' ) . '"><div class="mis-cerca__datos">' . $body . '</div>' . $map . '</div>' : $body )
			. '</section>';
	}
);

/** Lista de distancias en coche (maros_nearby) con la fecha y la atribución, o '' si no hay. */
function mis_zona_cerca( int $post_id ): string {
	$rows = json_decode( (string) get_post_meta( $post_id, 'maros_nearby', true ), true );
	if ( ! is_array( $rows ) || ! $rows ) {
		return '';
	}
	$li     = '';
	$latest = '';
	foreach ( $rows as $r ) {
		if ( ! is_array( $r ) || empty( $r['name'] ) || ! isset( $r['distance_km'], $r['duration_minutes'] ) ) {
			continue;
		}
		$latest = max( $latest, (string) ( $r['measured_on'] ?? '' ) );
		$li    .= '<li class="mis-zona-cerca__item"><span class="mis-zona-cerca__name">' . esc_html( $r['name'] ) . '</span>'
			. '<span class="mis-zona-cerca__val"><strong>' . esc_html( sprintf( mis_zona_t( '%d min', '%d min' ), (int) $r['duration_minutes'] ) ) . '</strong>'
			. ' · ' . esc_html( number_format_i18n( (float) $r['distance_km'], 1 ) ) . ' km</span></li>';
	}
	if ( '' === $li ) {
		return '';
	}
	$when = $latest ? date_i18n( 'F Y', strtotime( $latest . ' 12:00:00' ) ) : '';
	return '<div class="mis-zona-cerca">'
		. '<ul class="mis-zona-cerca__list">' . $li . '</ul>'
		. '<p class="mis-zona-cerca__src">'
		. esc_html( $when ? sprintf( mis_zona_t( 'By car, without traffic. Measured in %s.', 'En coche, sin tráfico. Medido en %s.' ), $when ) : mis_zona_t( 'By car, without traffic.', 'En coche, sin tráfico.' ) )
		. ' <a href="https://www.openstreetmap.org/copyright" rel="noopener" target="_blank">© OpenStreetMap contributors</a>'
		. ' · <a href="https://openrouteservice.org/" rel="noopener" target="_blank">openrouteservice</a></p>'
		. '</div>';
}
