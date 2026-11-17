<?php
/**
 * Piezas de la ficha de propiedad con el formato nuevo (maqueta aprobada por Julio el 17-11-2026,
 * con Le Collectionist como referencia): migas de pan, fila de huéspedes, dormitorios y baños,
 * «Read more», key features sacadas de marOS y la línea de precio del pie en móvil.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

/** Icono de trazo fino (24×24) con el tamaño dado. */
function maros_icon( string $paths, int $size = 22 ): string {
	return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths . '</svg>';
}

/** Trazos de los iconos de la ficha. */
function maros_icon_paths( string $name ): string {
	$icons = array(
		'pool'    => '<path d="M2 17c2 0 2-1.5 4-1.5S8 17 10 17s2-1.5 4-1.5 2 1.5 4 1.5 2-1.5 4-1.5M2 21c2 0 2-1.5 4-1.5S8 21 10 21s2-1.5 4-1.5 2 1.5 4 1.5 2-1.5 4-1.5"/><path d="M8 14V5a2 2 0 0 1 4 0M16 14V5a2 2 0 0 0-4 0M8 9h8"/>',
		'view'    => '<path d="M2 19l6-9 4 5 3-4 7 8z"/><circle cx="17" cy="6" r="2"/>',
		'bed'     => '<path d="M3 18V7"/><path d="M21 18v-6a3 3 0 0 0-3-3h-8v6"/><path d="M3 15h18"/><circle cx="6.5" cy="11.5" r="1.8"/>',
		'key'     => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3M14 9l2 2"/>',
		'gym'     => '<path d="M6 7v10M18 7v10M3 10v4M21 10v4M6 12h12"/>',
		'wifi'    => '<path d="M2 9a15 15 0 0 1 20 0M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0"/><circle cx="12" cy="19" r="1"/>',
		'snow'    => '<path d="M12 3v18M4.2 7.5l15.6 9M4.2 16.5l15.6-9"/>',
		'parking' => '<rect x="3" y="3" width="18" height="18"/><path d="M10 17V7h3a3 3 0 0 1 0 6h-3"/>',
		'bbq'     => '<path d="M4 10h16a8 8 0 0 1-16 0zM8 18l-2 3M16 18l2 3M9 6c0-1 1-1 1-2M14 6c0-1 1-1 1-2"/>',
		'terrace' => '<path d="M3 10h18M6 10v10M18 10v10M12 3v7"/>',
		'sauna'   => '<path d="M4 20h16M6 20V9h12v11M9 6c0-1 1-1 1-2M14 6c0-1 1-1 1-2"/>',
		'tv'      => '<rect x="3" y="5" width="18" height="12"/><path d="M8 21h8"/>',
		'kitchen' => '<path d="M5 3v8a2 2 0 0 0 4 0V3M7 11v10M17 3c-2 0-3 2-3 5s1 4 3 4v9"/>',
		'washer'  => '<rect x="4" y="3" width="16" height="18"/><circle cx="12" cy="13" r="4"/><path d="M7 6h2"/>',
		'heat'    => '<path d="M12 3c3 4 5 6 5 10a5 5 0 0 1-10 0c0-2 1-3 2-4 0 2 1 3 2 3 0-3-1-5 1-9z"/>',
		'safe'    => '<rect x="3" y="4" width="18" height="16"/><circle cx="12" cy="12" r="3"/>',
		'garden'  => '<path d="M12 21v-8M12 13c-4 0-6-3-6-7 4 0 6 3 6 7zM12 13c4 0 6-3 6-7-4 0-6 3-6 7z"/>',
		'check'   => '<path d="M5 12l4 4 10-10"/>',
	);
	return $icons[ $name ] ?? $icons['check'];
}

/** Icono para una comodidad de Lodgify según su código («private-pool», «wifi»…). */
function maros_amenity_icon( string $slug ): string {
	$rules = array(
		'pool'    => '/pool|piscina/',
		'wifi'    => '/wifi|internet/',
		'snow'    => '/air|aire|cooling/',
		'parking' => '/parking|garage|aparcamiento/',
		'bbq'     => '/bbq|barbecue|grill|barbacoa/',
		'gym'     => '/gym|fitness|gimnasio/',
		'terrace' => '/terrace|balcon|patio|terraza|dining/',
		'view'    => '/view|vistas/',
		'sauna'   => '/sauna|jacuzzi|hot-tub|spa/',
		'tv'      => '/tv|television|cable/',
		'kitchen' => '/kitchen|cocina|oven|microwave|coffee/',
		'washer'  => '/wash|laundry|dryer|lavadora/',
		'heat'    => '/heat|fireplace|calefaccion/',
		'safe'    => '/safe|caja-fuerte/',
		'garden'  => '/garden|jardin/',
	);
	foreach ( $rules as $icon => $re ) {
		if ( preg_match( $re, $slug ) ) {
			return $icon;
		}
	}
	return 'check';
}

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
			'icon'  => 'pool',
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
			$html .= '<li>' . maros_icon( maros_icon_paths( $f['icon'] ), 30 ) . '<div><strong>' . esc_html( $f['title'] ) . '</strong>' . ( '' !== $f['text'] ? '<span>' . esc_html( $f['text'] ) . '</span>' : '' ) . '</div></li>';
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
