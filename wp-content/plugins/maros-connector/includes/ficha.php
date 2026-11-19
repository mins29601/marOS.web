<?php
/**
 * Piezas de la ficha de propiedad con el formato nuevo (maqueta aprobada por Julio el 17-11-2026,
 * con Le Collectionist como referencia): migas de pan, fila de huéspedes, dormitorios y baños,
 * «Read more», key features sacadas de marOS y la línea de precio del pie en móvil.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

// Iconos: includes/icons.php (maros_icon_svg, maros_amenity_icon).

/**
 * [maros_breadcrumbs] All properties › Marbella › Nueva Andalucía, en una línea que se desliza en
 * horizontal si no cabe.
 */
add_shortcode(
	'maros_breadcrumbs',
	static function () {
		$post_id = (int) get_the_ID();
		$es      = maros_is_es( $post_id );
		$all     = function_exists( 'mis_page_url' ) ? mis_page_url( 'stay' ) : home_url( '/stay/' );
		$items   = array( '<a href="' . esc_url( $all ) . '">' . esc_html( $es ? 'Todas las casas' : 'All properties' ) . '</a>' );
		$town    = trim( (string) get_post_meta( $post_id, 'maros_municipality', true ) );
		if ( '' !== $town ) {
			$items[] = '<span>' . esc_html( $town ) . '</span>';
		}
		$terms = get_the_terms( $post_id, 'zona' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$link    = get_term_link( $terms[0] );
			$items[] = is_wp_error( $link ) ? '<span>' . esc_html( $terms[0]->name ) . '</span>' : '<a href="' . esc_url( $link ) . '" aria-current="page">' . esc_html( $terms[0]->name ) . '</a>';
		} elseif ( '' !== (string) get_post_meta( $post_id, 'maros_area', true ) ) {
			$items[] = '<span>' . esc_html( (string) get_post_meta( $post_id, 'maros_area', true ) ) . '</span>';
		}
		return '<nav class="mis-crumbs" aria-label="' . esc_attr( $es ? 'Migas de pan' : 'Breadcrumbs' ) . '">' . implode( '<span class="mis-crumbs__sep" aria-hidden="true">›</span>', $items ) . '</nav>';
	}
);

/** [maros_where] «Nueva Andalucía, Marbella» bajo el título. */
add_shortcode(
	'maros_where',
	static function () {
		$post_id = (int) get_the_ID();
		$terms   = get_the_terms( $post_id, 'zona' );
		$area    = $terms && ! is_wp_error( $terms ) ? $terms[0]->name : (string) get_post_meta( $post_id, 'maros_area', true );
		$parts   = array_filter( array( $area, (string) get_post_meta( $post_id, 'maros_municipality', true ) ) );
		return $parts ? '<p class="mis-where">' . esc_html( implode( ', ', array_unique( $parts ) ) ) . '</p>' : '';
	}
);

/** [maros_facts] Huéspedes, dormitorios y baños en tres columnas: número arriba, texto debajo. */
add_shortcode(
	'maros_facts',
	static function () {
		$post_id = (int) get_the_ID();
		$es      = maros_is_es( $post_id );
		$rows    = array(
			array( (int) get_post_meta( $post_id, 'maros_max_guests', true ), array( 'guest', 'guests' ), array( 'huésped', 'huéspedes' ) ),
			array( (int) get_post_meta( $post_id, 'maros_bedrooms', true ), array( 'bedroom', 'bedrooms' ), array( 'dormitorio', 'dormitorios' ) ),
			array( (int) get_post_meta( $post_id, 'maros_bathrooms', true ), array( 'bathroom', 'bathrooms' ), array( 'baño', 'baños' ) ),
		);
		$html = '';
		foreach ( $rows as list( $n, $en, $sp ) ) {
			if ( $n > 0 ) {
				$words = $es ? $sp : $en;
				$html .= '<li><strong>' . (int) $n . '</strong><span>' . esc_html( 1 === $n ? $words[0] : $words[1] ) . '</span></li>';
			}
		}
		return $html ? '<ul class="mis-facts">' . $html . '</ul>' : '';
	}
);

/** [maros_read_more] Botón «Read more» de la descripción (el bloque anterior con clase mis-desc). */
add_shortcode(
	'maros_read_more',
	static function () {
		$es   = maros_is_es( (int) get_the_ID() );
		$more = $es ? 'Leer más +' : 'Read more +';
		$less = $es ? 'Leer menos −' : 'Read less −';
		return '<button type="button" class="mis-more" aria-expanded="false" data-more="' . esc_attr( $more ) . '" data-less="' . esc_attr( $less ) . '">' . esc_html( $more ) . '</button>'
			. '<script>(function(){var b=document.currentScript.previousElementSibling,d=document.querySelector(".mis-desc");if(!b||!d)return;if(d.scrollHeight<=d.clientHeight+4){b.hidden=true;d.classList.add("is-open");return;}b.addEventListener("click",function(){var o=d.classList.toggle("is-open");b.setAttribute("aria-expanded",o);b.textContent=o?b.dataset.less:b.dataset.more;});})();</script>';
	}
);

/**
 * Key features de la propiedad, sacadas de marOS (Julio 17-11-2026: «Ok» a sacarlas solas):
 * piscina y su climatización, vistas, baños en-suite, self check-in y gimnasio. Como mucho cuatro.
 *
 * @return array<int, array{icon: string, title: string, text: string}>
 */
function maros_key_features( int $post_id, bool $es ): array {
	$m        = static fn( string $k ) => (string) get_post_meta( $post_id, $k, true );
	$sleeping = json_decode( $m( 'maros_sleeping' ), true );
	$sleeping = is_array( $sleeping ) ? $sleeping : array();
	$tour     = json_decode( $m( 'maros_tour' ), true );
	$tour     = is_array( $tour ) ? $tour : array();
	$terms    = get_the_terms( $post_id, 'equipamiento' );
	$slugs    = $terms && ! is_wp_error( $terms ) ? wp_list_pluck( $terms, 'slug' ) : array();
	$out      = array();

	// Piscina.
	$pool = $m( 'maros_pool' );
	if ( in_array( $pool, array( 'private', 'both' ), true ) || ( '' === $pool && preg_grep( '/private-pool/', $slugs ) ) ) {
		$heated = 'yes' === $m( 'maros_pool_heating' ) || (bool) preg_grep( '/heated-pool/', $slugs );
		$out[]  = array(
			'icon'  => $heated ? 'heated_pool' : 'pool',
			'title' => $heated ? ( $es ? 'Piscina privada climatizada' : 'Private heated pool' ) : ( $es ? 'Piscina privada' : 'Private pool' ),
			'text'  => $heated
				? ( 'yes' === $m( 'maros_pool_heating_included' ) ? ( $es ? 'Climatización incluida en el precio' : 'Heating included in the price' ) : ( $es ? 'Climatización bajo petición' : 'Heating available on request' ) )
				: ( $es ? 'Solo para tu grupo' : 'Just for your group' ),
		);
	} elseif ( 'communal' === $pool ) {
		$out[] = array( 'icon' => 'pool', 'title' => $es ? 'Piscina comunitaria' : 'Communal pool', 'text' => '' );
	}

	// Vistas: de las estancias (spec 039) y, si no hay, de las comodidades de Lodgify.
	$kinds = array(
		'sea_view'      => array( 'sea', 'al mar' ),
		'mountain_view' => array( 'mountain', 'a la montaña' ),
		'golf_view'     => array( 'golf', 'al golf' ),
		'garden_view'   => array( 'garden', 'al jardín' ),
		'pool_view'     => array( 'pool', 'a la piscina' ),
	);
	$seen  = array();
	$where = array();
	foreach ( $sleeping as $room ) {
		foreach ( (array) ( $room['amenities'] ?? array() ) as $a ) {
			$code = (string) ( $a['code'] ?? '' );
			if ( isset( $kinds[ $code ] ) ) {
				$seen[ $code ] = true;
				$where[]       = sprintf( $es ? 'Dormitorio %d' : 'Bedroom %d', (int) ( $room['number'] ?? 0 ) );
			}
		}
	}
	foreach ( $tour as $room ) {
		if ( ! empty( $room['bedroom'] ) ) {
			continue;
		}
		foreach ( (array) ( $room['amenities'] ?? array() ) as $a ) {
			$code = (string) ( $a['code'] ?? '' );
			if ( isset( $kinds[ $code ] ) ) {
				$seen[ $code ] = true;
				$where[]       = (string) ( $room['name'] ?? '' );
			}
		}
	}
	if ( ! $seen && preg_grep( '/sea-view/', $slugs ) ) {
		$seen['sea_view'] = true;
	}
	if ( $seen ) {
		$words = array_map( static fn( $c ) => $kinds[ $c ][ $es ? 1 : 0 ], array_keys( array_intersect_key( $kinds, $seen ) ) );
		$last  = array_pop( $words );
		$list  = $words ? implode( ', ', $words ) . ( $es ? ' y ' : ' and ' ) . $last : $last;
		$where = array_slice( array_values( array_unique( array_filter( $where ) ) ), 0, 3 );
		$out[] = array(
			'icon'  => 'view',
			'title' => $es ? 'Vistas ' . $list : ucfirst( $list ) . ' views',
			'text'  => $where ? ( $es ? 'Desde ' : 'From ' ) . implode( ' · ', $where ) : '',
		);
	}

	// Baños en-suite.
	$suites = count( array_filter( $sleeping, static fn( $r ) => ! empty( $r['en_suite'] ) ) );
	if ( $suites > 0 ) {
		$all   = $suites === count( $sleeping ) && $suites > 1;
		$out[] = array(
			'icon'  => 'bed',
			'title' => 1 === $suites ? ( $es ? '1 dormitorio con baño en-suite' : '1 en-suite bedroom' ) : sprintf( $es ? '%d dormitorios con baño en-suite' : '%d en-suite bedrooms', $suites ),
			'text'  => $all ? ( $es ? 'Cada dormitorio tiene su propio baño' : 'Every bedroom has its own bathroom' ) : '',
		);
	}

	// Self check-in (es una opción además de la recepción en persona, marOS spec 032).
	if ( 'yes' === $m( 'maros_self_check_in' ) ) {
		$out[] = array(
			'icon'  => 'key',
			'title' => $es ? 'Llegada autónoma' : 'Self check-in',
			'text'  => $es ? 'O recepción en persona con nuestro equipo' : 'Or a personal welcome from our team',
		);
	}

	if ( count( $out ) < 4 && preg_grep( '/gym|fitness/', $slugs ) ) {
		$out[] = array( 'icon' => 'gym', 'title' => $es ? 'Gimnasio' : 'Gym', 'text' => '' );
	}
	return array_slice( $out, 0, 4 );
}

/** [maros_key_features] Bloque «Key features». */
add_shortcode(
	'maros_key_features',
	static function () {
		$post_id = (int) get_the_ID();
		$es      = maros_is_es( $post_id );
		$items   = maros_key_features( $post_id, $es );
		if ( ! $items ) {
			return '';
		}
		$html = '';
		foreach ( $items as $f ) {
			$html .= '<li>' . maros_icon_svg( maros_amenity_icon( $f['icon'] ), 30 ) . '<div><strong>' . esc_html( $f['title'] ) . '</strong>' . ( '' !== $f['text'] ? '<span>' . esc_html( $f['text'] ) . '</span>' : '' ) . '</div></li>';
		}
		return '<section class="mis-keys"><h2 class="wp-block-heading has-large-font-size">' . esc_html( $es ? 'Lo más destacado' : 'Key features' ) . '</h2><ul class="mis-keys__list">' . $html . '</ul></section>';
	}
);

/**
 * [maros_price_line] «From €1,450 / night» en una línea: en el pie fijo del móvil y en la caja de
 * reserva de escritorio (con fallback="0", que no dice nada si no hay precio).
 */
add_shortcode(
	'maros_price_line',
	static function ( $atts = array() ) {
		$post_id = (int) get_the_ID();
		$es      = maros_is_es( $post_id );
		$price   = (float) get_post_meta( $post_id, 'maros_price_from', true );
		if ( $price <= 0 ) {
			if ( '0' === (string) ( $atts['fallback'] ?? '1' ) ) {
				return '';
			}
			return '<p class="mis-price-line">' . esc_html( $es ? 'Reserva directa · mejor precio' : 'Direct booking · best price' ) . '</p>';
		}
		$cur    = (string) get_post_meta( $post_id, 'maros_currency', true ) ?: 'EUR';
		$amount = number_format_i18n( $price );
		$money  = 'EUR' === $cur ? ( $es ? $amount . ' €' : '€' . $amount ) : $amount . ' ' . $cur;
		return '<p class="mis-price-line"><small>' . esc_html( $es ? 'Desde' : 'From' ) . '</small> <strong>' . esc_html( $money ) . '</strong> <small>' . esc_html( $es ? '/ noche' : '/ night' ) . '</small></p>';
	}
);

/**
 * [maros_anchors] Subnavegación fija por anclas (spec 006: Resumen · Vídeos · Dormitorios ·
 * Comodidades · Zona · Opiniones · FAQ). Solo enlaza las secciones que tiene esta casa y marca la
 * que se está leyendo.
 */
add_shortcode(
	'maros_anchors',
	static function () {
		$post_id = (int) get_the_ID();
		$es      = maros_is_es( $post_id );
		$rooms   = json_decode( (string) get_post_meta( $post_id, 'maros_sleeping', true ), true );
		$terms   = get_the_terms( $post_id, 'equipamiento' );
		$items   = array(
			'overview'  => array( true, $es ? 'Resumen' : 'Overview' ),
			'videos'    => array( function_exists( 'maros_videos' ) && maros_videos( $post_id ), $es ? 'Vídeos' : 'Videos' ),
			'bedrooms'  => array( is_array( $rooms ) && $rooms, $es ? 'Dormitorios' : 'Bedrooms' ),
			'amenities' => array( $terms && ! is_wp_error( $terms ), $es ? 'Comodidades' : 'Amenities' ),
			'location'  => array( true, $es ? 'Ubicación' : 'Location' ),
			// Las opiniones solo se pintan si hay: al cargar la página se quita el enlace de toda sección que no esté.
			'reviews'   => array( function_exists( 'glsr_get_reviews' ), $es ? 'Opiniones' : 'Reviews' ),
			'faq'       => array( true, 'FAQ' ),
		);
		$html = '';
		foreach ( $items as $id => list( $show, $label ) ) {
			if ( $show ) {
				$html .= '<a href="#' . esc_attr( $id ) . '">' . esc_html( $label ) . '</a>';
			}
		}
		$js = '<script>document.addEventListener("DOMContentLoaded",function(){var n=document.querySelector(".mis-anchors");if(!n)return;var l=[].slice.call(n.querySelectorAll("a")).filter(function(a){var s=document.getElementById(a.hash.slice(1));if(!s){a.remove();return false;}a._s=s;return true;});if(!("IntersectionObserver" in window))return;var v=new Map();var o=new IntersectionObserver(function(es){es.forEach(function(e){v.set(e.target,e.isIntersecting);});var c=null;l.forEach(function(a){if(!c&&v.get(a._s))c=a;});l.forEach(function(a){if(a===c){a.setAttribute("aria-current","true");}else{a.removeAttribute("aria-current");}});if(c&&n.scrollWidth>n.clientWidth){n.scrollLeft=c.offsetLeft-24;}},{rootMargin:"-40% 0px -55% 0px"});l.forEach(function(a){o.observe(a._s);});});</script>';
		return '<nav class="mis-anchors" aria-label="' . esc_attr( $es ? 'Secciones de la casa' : 'Property sections' ) . '">' . $html . '</nav>' . $js;
	}
);
