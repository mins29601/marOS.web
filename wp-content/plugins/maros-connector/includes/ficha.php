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
 * Municipio de la casa para las migas y «Zona, Municipio»: el de marOS solo si es un municipio de
 * verdad (Maros_Sync::TOWNS); si no, el de su Area (Maros_Sync::ZONE_TOWNS). El campo de marOS es
 * texto libre y a veces trae la urbanización (Casa Coral: «Saladillo», Julio 20-11-2026).
 */
function maros_town( int $post_id ): string {
	$raw  = sanitize_title( remove_accents( trim( (string) get_post_meta( $post_id, 'maros_municipality', true ) ) ) );
	$raw  = preg_replace( '/^(municipio-de-|ayuntamiento-de-)/', '', $raw );
	if ( isset( Maros_Sync::TOWNS[ $raw ] ) ) {
		return Maros_Sync::TOWNS[ $raw ];
	}
	$zone = maros_zone_term( $post_id );
	$slug = $zone ? preg_replace( '/-es$/', '', $zone->slug ) : '';
	return Maros_Sync::ZONE_TOWNS[ $slug ] ?? '';
}

/** Término «zona» de la casa (una de las Areas de la web), o null. */
function maros_zone_term( int $post_id ): ?WP_Term {
	$terms = get_the_terms( $post_id, 'zona' );
	return $terms && ! is_wp_error( $terms ) ? $terms[0] : null;
}

/**
 * Nombre de la Area de la casa: el término «zona» o, si la web aún no lo tiene, el área de marOS
 * solo cuando es una de las Areas de la web. Nunca una urbanización en texto libre.
 */
function maros_area_name( int $post_id ): string {
	$zone = maros_zone_term( $post_id );
	if ( $zone ) {
		return $zone->name;
	}
	$slug = Maros_Sync::zone_slug( array( 'location' => array( 'area' => (string) get_post_meta( $post_id, 'maros_area', true ) ) ) );
	return '' !== $slug ? Maros_Sync::zone_name( $slug, maros_is_es( $post_id ) ) : '';
}

/** true si el municipio y la zona se llaman igual («Estepona» y «Estepona»): se dice una vez. */
function maros_same_place( string $a, string $b ): bool {
	return 0 === strcasecmp( remove_accents( $a ), remove_accents( $b ) );
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
		$town    = maros_town( $post_id );
		$zone    = maros_zone_term( $post_id );
		if ( '' !== $town && ! ( $zone && maros_same_place( $town, $zone->name ) ) ) {
			$items[] = '<span>' . esc_html( $town ) . '</span>';
		}
		// Solo una Area de la web (término «zona»); nunca el área en texto libre de marOS.
		if ( $zone ) {
			$link    = get_term_link( $zone );
			$items[] = is_wp_error( $link ) ? '<span>' . esc_html( $zone->name ) . '</span>' : '<a href="' . esc_url( $link ) . '" aria-current="page">' . esc_html( $zone->name ) . '</a>';
		}
		return '<nav class="mis-crumbs" aria-label="' . esc_attr( $es ? 'Migas de pan' : 'Breadcrumbs' ) . '">' . implode( '<span class="mis-crumbs__sep" aria-hidden="true">›</span>', $items ) . '</nav>';
	}
);

/**
 * [maros_where] «Nueva Andalucía, Marbella». Ya no va bajo el título: ahí va el nombre comercial
 * (Julio 23-11-2026) y la zona está en las migas.
 */
add_shortcode(
	'maros_where',
	static function () {
		$post_id = (int) get_the_ID();
		$zone    = maros_zone_term( $post_id );
		$town    = maros_town( $post_id );
		$parts   = array_filter( array( $zone ? $zone->name : '', $zone && maros_same_place( $town, $zone->name ) ? '' : $town ) );
		return $parts ? '<p class="mis-where">' . esc_html( implode( ', ', $parts ) ) . '</p>' : '';
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
 * Key features de la propiedad, sacadas de marOS (Julio 17-11-2026: «Ok» a sacarlas solas). Como
 * mucho cuatro, por orden de lo que más pesa al elegir casa (Julio 20-11-2026: «lo más destacable es
 * que está junto a la playa», y «2 en-suite bedrooms» de 3 no dice nada):
 *
 * 1. La playa: primera línea o acceso directo (comodidad de marOS o el nombre comercial, que escribe
 *    el equipo), o una playa medida a 2 km o menos (maros_nearby, OpenStreetMap; guía editorial §4:
 *    solo distancias medidas).
 * 2. Piscina privada (y su climatización). 3. Vistas al mar, al golf o a la montaña.
 * 4. Golf medido a 2 km o menos. 5. Todos los dormitorios en-suite (solo si son todos).
 * 6. Comedor exterior, barbacoa o jacuzzi de una estancia exterior. 7. Piscina comunitaria.
 * 8. Vistas al jardín o a la piscina. 9. Self check-in. 10. Gimnasio.
 *
 * @return array<int, array{icon: string, title: string, text: string, phrase?: string}>
 */
function maros_key_features( int $post_id, bool $es ): array {
	$m        = static fn( string $k ) => (string) get_post_meta( $post_id, $k, true );
	$json     = static fn( string $k ) => is_array( $v = json_decode( $m( $k ), true ) ) ? $v : array();
	// marOS spec 053: si el agente de «Lo más destacado» ya los ha escrito, mandan los suyos.
	$ai = array();
	foreach ( $json( 'maros_key_features_ai' ) as $item ) {
		$lang  = $es ? 'es' : 'en';
		$title = is_array( $item ) ? trim( (string) ( $item['title'][ $lang ] ?? '' ) ) : '';
		if ( '' !== $title ) {
			$ai[] = array( 'icon' => (string) ( $item['icon'] ?? 'check' ), 'title' => $title, 'text' => trim( (string) ( $item['text'][ $lang ] ?? '' ) ) );
		}
	}
	if ( $ai ) {
		return $ai;
	}
	$sleeping = $json( 'maros_sleeping' );
	$tour     = $json( 'maros_tour' );
	$nearby   = array_values( array_filter( $json( 'maros_nearby' ), 'is_array' ) );
	$terms    = get_the_terms( $post_id, 'equipamiento' );
	$slugs    = $terms && ! is_wp_error( $terms ) ? wp_list_pluck( $terms, 'slug' ) : array();
	$codes    = array();
	foreach ( array_merge( $sleeping, $tour ) as $room ) {
		foreach ( (array) ( $room['amenities'] ?? array() ) as $a ) {
			$codes[ (string) ( $a['code'] ?? '' ) ] = true;
		}
	}
	$near = static function ( string $icon ) use ( $nearby ): ?array {
		foreach ( $nearby as $r ) {
			if ( $icon === ( $r['icon'] ?? '' ) && isset( $r['distance_km'] ) ) {
				return $r;
			}
		}
		return null;
	};
	$by_road = static fn( array $r ) => sprintf( $es ? '%1$s, a %2$s km por carretera' : '%1$s, %2$s km by road', $r['name'], number_format_i18n( (float) $r['distance_km'], 1 ) );
	$out     = array();

	// 1. La playa.
	$beach      = $near( 'beach' );
	$beach_km   = $beach ? (float) $beach['distance_km'] : INF;
	$front      = isset( $codes['waterfront'] ) || (bool) preg_match( '/beach\s*-?front|front\s*-?line|primera\s+l[ií]nea/i', $m( 'maros_subtitle' ) );
	$access     = isset( $codes['beach_access'] ) || (bool) preg_grep( '/beach-access|acceso-a-la-playa/', $slugs );
	if ( $front || $access || $beach_km <= 2 ) {
		$out[] = array(
			'icon'  => 'beach_access',
			'title' => $front ? ( $es ? 'En primera línea de playa' : 'Beachfront' )
				: ( $access ? ( $es ? 'Acceso directo a la playa' : 'Direct beach access' )
				: ( $beach_km <= 0.5 ? ( $es ? 'Junto a la playa' : 'By the beach' ) : ( $es ? 'Cerca de la playa' : 'Close to the beach' ) ) ),
			'text'  => $beach && $beach_km <= 2 ? $by_road( $beach ) : '',
			// Para el extracto: «…, featuring a beachfront setting, …».
			'phrase' => $front ? ( $es ? 'ubicación en primera línea de playa' : 'a beachfront setting' )
				: ( $access ? ( $es ? 'acceso directo a la playa' : 'direct beach access' ) : ( $es ? 'la playa cerca' : 'the beach nearby' ) ),
		);
	}

	// 2. Piscina privada.
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
	}

	// 3 y 8. Vistas: de las estancias (spec 039) y, si no hay, de las comodidades de Lodgify.
	$views = maros_key_views( $sleeping, $tour, $slugs, $es );
	if ( $views['major'] ) {
		$out[] = $views['major'];
	}

	// 4. Golf cerca, medido.
	$golf = $near( 'golf' );
	if ( $golf && (float) $golf['distance_km'] <= 2 ) {
		$out[] = array( 'icon' => 'golf_view', 'title' => $es ? 'Cerca del golf' : 'Close to golf', 'text' => $by_road( $golf ), 'phrase' => $es ? 'el golf cerca' : 'golf nearby' );
	}

	// 5. Todos los dormitorios con su baño (si solo lo tienen algunos, no se destaca).
	$suites = count( array_filter( $sleeping, static fn( $r ) => ! empty( $r['en_suite'] ) ) );
	if ( $suites > 1 && $suites === count( $sleeping ) ) {
		$out[] = array(
			'icon'  => 'bed',
			'title' => $es ? 'Todos los dormitorios en-suite' : 'Every bedroom en-suite',
			'text'  => $es ? 'Cada dormitorio tiene su propio baño' : 'Each bedroom has its own bathroom',
		);
	}

	// 6. Vida exterior: comedor, barbacoa o jacuzzi de una estancia exterior (la primera que los tenga).
	foreach ( $tour as $room ) {
		$has = array();
		foreach ( (array) ( $room['amenities'] ?? array() ) as $a ) {
			$has[ (string) ( $a['code'] ?? '' ) ] = true;
		}
		if ( empty( $room['outdoor'] ) || ! array_intersect_key( $has, array( 'bbq_grill' => 1, 'outdoor_dining_area' => 1, 'hot_tub' => 1 ) ) ) {
			continue;
		}
		$bbq   = isset( $has['bbq_grill'] );
		$dine  = isset( $has['outdoor_dining_area'] );
		$out[] = isset( $has['hot_tub'] )
			? array( 'icon' => 'hot_tub', 'title' => $es ? 'Jacuzzi exterior' : 'Outdoor hot tub', 'text' => (string) ( $room['name'] ?? '' ) )
			: array(
				'icon'  => $bbq ? 'bbq_grill' : 'outdoor_dining_area',
				'title' => $bbq && $dine ? ( $es ? 'Comedor exterior con barbacoa' : 'Al fresco dining with barbecue' )
					: ( $bbq ? ( $es ? 'Barbacoa' : 'Barbecue' ) : ( $es ? 'Comedor exterior' : 'Al fresco dining' ) ),
				'text'  => (string) ( $room['name'] ?? '' ),
			);
		break;
	}

	// 7. Piscina comunitaria.
	if ( 'communal' === $pool ) {
		$out[] = array( 'icon' => 'pool', 'title' => $es ? 'Piscina comunitaria' : 'Communal pool', 'text' => '' );
	}

	if ( $views['minor'] ) {
		$out[] = $views['minor'];
	}

	// 9. Self check-in (es una opción además de la recepción en persona, marOS spec 032).
	if ( 'yes' === $m( 'maros_self_check_in' ) ) {
		$out[] = array(
			'icon'  => 'key',
			'title' => $es ? 'Llegada autónoma' : 'Self check-in',
			'text'  => $es ? 'O recepción en persona con nuestro equipo' : 'Or a personal welcome from our team',
		);
	}

	if ( preg_grep( '/gym|fitness/', $slugs ) ) {
		$out[] = array( 'icon' => 'gym', 'title' => $es ? 'Gimnasio' : 'Gym', 'text' => '' );
	}
	return array_slice( $out, 0, 4 );
}

/**
 * Vistas de la casa en dos grupos: las que pesan (mar, golf, montaña) y las menores (jardín,
 * piscina), cada una con las estancias desde donde se ven.
 *
 * @return array{major: ?array, minor: ?array}
 */
function maros_key_views( array $sleeping, array $tour, array $slugs, bool $es ): array {
	$kinds = array(
		'sea_view'      => array( 'sea', 'al mar', true ),
		'golf_view'     => array( 'golf', 'al golf', true ),
		'mountain_view' => array( 'mountain', 'a la montaña', true ),
		'garden_view'   => array( 'garden', 'al jardín', false ),
		'pool_view'     => array( 'pool', 'a la piscina', false ),
	);
	$seen = array( true => array(), false => array() );
	$from = array( true => array(), false => array() );
	$add  = static function ( array $room, string $where ) use ( $kinds, &$seen, &$from ) {
		foreach ( (array) ( $room['amenities'] ?? array() ) as $a ) {
			$code = (string) ( $a['code'] ?? '' );
			if ( isset( $kinds[ $code ] ) ) {
				$major                    = $kinds[ $code ][2];
				$seen[ $major ][ $code ] = true;
				$from[ $major ][]        = $where;
			}
		}
	};
	foreach ( $sleeping as $room ) {
		$add( $room, sprintf( $es ? 'Dormitorio %d' : 'Bedroom %d', (int) ( $room['number'] ?? 0 ) ) );
	}
	foreach ( $tour as $room ) {
		if ( empty( $room['bedroom'] ) ) {
			$add( $room, (string) ( $room['name'] ?? '' ) );
		}
	}
	if ( ! $seen[ true ] && ! $seen[ false ] && preg_grep( '/sea-view/', $slugs ) ) {
		$seen[ true ]['sea_view'] = true;
	}
	$out = array();
	foreach ( array( 'major' => true, 'minor' => false ) as $key => $major ) {
		if ( ! $seen[ $major ] ) {
			$out[ $key ] = null;
			continue;
		}
		$words       = array_map( static fn( $c ) => $kinds[ $c ][ $es ? 1 : 0 ], array_keys( array_intersect_key( $kinds, $seen[ $major ] ) ) );
		$last        = array_pop( $words );
		$list        = $words ? implode( ', ', $words ) . ( $es ? ' y ' : ' and ' ) . $last : $last;
		$where       = array_slice( array_values( array_unique( array_filter( $from[ $major ] ) ) ), 0, 3 );
		$out[ $key ] = array(
			'icon'  => isset( $seen[ $major ]['golf_view'] ) && 1 === count( $seen[ $major ] ) ? 'golf_view' : ( $major ? 'view' : 'garden_view' ),
			'title' => $es ? 'Vistas ' . $list : ucfirst( $list ) . ' views',
			'text'  => $where ? ( $es ? 'Desde ' : 'From ' ) . implode( ' · ', $where ) : '',
		);
	}
	return $out;
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
 * [maros_cta_line] Texto del pie fijo del móvil (Julio 24-11-2026, con el de Airbnb de modelo): sin
 * precio, «Añade fechas para ver el precio» y debajo la nota media con estrellas, que lleva a las
 * opiniones. Sin opiniones, solo la primera línea. Al elegir fechas, booking.js pone el total.
 */
add_shortcode(
	'maros_cta_line',
	static function () {
		$post_id = (int) get_the_ID();
		$es      = maros_is_es( $post_id );
		$rating  = class_exists( 'Maros_Reviews' ) ? Maros_Reviews::rating( $post_id ) : null;
		$html    = '<span class="mis-cta-line__dates">' . esc_html( $es ? 'Añade fechas para ver el precio' : 'Add dates to see prices' ) . '</span>';
		if ( $rating ) {
			$label = sprintf(
				$es ? 'Nota media %1$s de 5 en %2$d opiniones' : 'Rated %1$s out of 5 in %2$d reviews',
				number_format_i18n( $rating['average'], 2 ),
				$rating['total']
			);
			$html .= '<a class="mis-cta-line__rating" href="#reviews" aria-label="' . esc_attr( $label ) . '"><span aria-hidden="true">★ ' . esc_html( number_format_i18n( $rating['average'], 2 ) ) . ' · ' . esc_html( sprintf( $es ? _n( '%d opinión', '%d opiniones', $rating['total'], 'maros-connector' ) : _n( '%d review', '%d reviews', $rating['total'], 'maros-connector' ), $rating['total'] ) ) . '</span></a>';
		}
		return '<p class="mis-cta-line">' . $html . '</p>';
	}
);

/**
 * [maros_managed] «Gestionada por Marbella in Style» (Julio 24-11-2026, como el anfitrión de
 * Airbnb): solo si en marOS consta que la casa la gestionamos nosotros (managed_by_us). Textos de
 * marOS.content (PC-14 «Nosotros»: equipo local en la Costa del Sol desde 2008).
 */
add_shortcode(
	'maros_managed',
	static function () {
		$post_id = (int) get_the_ID();
		if ( 'yes' !== get_post_meta( $post_id, 'maros_managed', true ) ) {
			return '';
		}
		$es   = maros_is_es( $post_id );
		$logo = get_stylesheet_directory_uri() . '/assets/logo/marbellainstyle-simbolo-oro.svg';
		return '<div class="mis-managed"><span class="mis-managed__mark"><img src="' . esc_url( $logo ) . '" width="28" height="28" alt=""></span><p><strong>'
			. esc_html( $es ? 'Gestionada por Marbella in Style' : 'Managed by Marbella in Style' ) . '</strong><span>'
			. esc_html( $es ? 'Equipo local en la Costa del Sol desde 2008' : 'Local team on the Costa del Sol since 2008' )
			. '</span></p></div>';
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
			'pool'      => array( function_exists( 'maros_pools' ) && maros_pools( $post_id ), $es ? 'Piscina' : 'Pool' ),
			'amenities' => array( $terms && ! is_wp_error( $terms ), $es ? 'Comodidades' : 'Amenities' ),
			// Las opiniones solo se pintan si hay: al cargar la página se quita el enlace de toda sección que no esté.
			// Van antes que la ubicación, como en la página (Julio 23-11-2026).
			'reviews'   => array( function_exists( 'glsr_get_reviews' ), $es ? 'Opiniones' : 'Reviews' ),
			// «Close to the Villa» y «Area Guide» (Julio, 18-11-2026); el script quita «Zona» si la casa no tiene.
			'location'  => array( true, $es ? 'Cerca' : 'Nearby' ),
			'area'      => array( true, $es ? 'Zona' : 'Area' ),
			'faq'       => array( true, 'FAQ' ),
		);
		$html = '';
		foreach ( $items as $id => list( $show, $label ) ) {
			if ( $show ) {
				$html .= '<a href="#' . esc_attr( $id ) . '">' . esc_html( $label ) . '</a>';
			}
		}
		// En móvil la barra no aparece hasta pasar la foto de cabecera (Julio 24-11-2026, como Airbnb):
		// .is-shown la deja ver; sin galería se ve siempre.
		$js = '<script>document.addEventListener("DOMContentLoaded",function(){var n=document.querySelector(".mis-anchors");if(!n)return;var g=document.querySelector(".mis-hero-gallery");function sh(){n.classList.toggle("is-shown",!g||g.getBoundingClientRect().bottom<=0);}addEventListener("scroll",sh,{passive:true});addEventListener("resize",sh);sh();var l=[].slice.call(n.querySelectorAll("a")).filter(function(a){var s=document.getElementById(a.hash.slice(1));if(!s){a.remove();return false;}a._s=s;return true;});if(!("IntersectionObserver" in window))return;var v=new Map();var o=new IntersectionObserver(function(es){es.forEach(function(e){v.set(e.target,e.isIntersecting);});var c=null;l.forEach(function(a){if(!c&&v.get(a._s))c=a;});l.forEach(function(a){if(a===c){a.setAttribute("aria-current","true");}else{a.removeAttribute("aria-current");}});if(c&&n.scrollWidth>n.clientWidth){n.scrollLeft=c.offsetLeft-24;}},{rootMargin:"-40% 0px -55% 0px"});l.forEach(function(a){o.observe(a._s);});});</script>';
		return '<nav class="mis-anchors mis-anchors--ficha" aria-label="' . esc_attr( $es ? 'Secciones de la casa' : 'Property sections' ) . '">' . $html . '</nav>' . $js;
	}
);

/**
 * [maros_subtitle] El nombre comercial de Lodgify bajo el título, que es el nombre interno de marOS
 * (Julio 19-11-2026: «Casa Coral» de título y «Beachfront 3BR Townhouse with Garden» de subtítulo).
 * Ocupa el sitio de «Zona, Municipio» (Julio 23-11-2026).
 */
add_shortcode(
	'maros_subtitle',
	static function () {
		$sub = trim( (string) get_post_meta( (int) get_the_ID(), 'maros_subtitle', true ) );
		return '' !== $sub ? '<p class="mis-ficha-subtitle">' . esc_html( $sub ) . '</p>' : '';
	}
);

/**
 * Piscinas de la casa para el bloque «Piscina» (Julio 19-11-2026): las de marOS (spec 032/047) con
 * su uso, si es interior o exterior, la climatización y sus comodidades, y la foto de su estancia.
 * Si marOS solo trae el resumen de siempre (`maros_pool`), una tarjeta por tipo, sin más datos.
 *
 * @return array<int, array{access: string, covered: ?bool, heated: ?bool, heating: ?array, amenities: array, photo: int}>
 */
function maros_pools( int $post_id ): array {
	$pools = json_decode( (string) get_post_meta( $post_id, 'maros_pools', true ), true );
	$pools = is_array( $pools ) ? array_values( array_filter( $pools, static fn( $p ) => is_array( $p ) && in_array( $p['access'] ?? '', array( 'private', 'communal' ), true ) ) ) : array();
	if ( ! $pools ) {
		$summary = (string) get_post_meta( $post_id, 'maros_pool', true );
		$kinds   = array( 'private' => array( 'private' ), 'communal' => array( 'communal' ), 'both' => array( 'private', 'communal' ) )[ $summary ] ?? array();
		$pools   = array_map( static fn( $k ) => array( 'access' => $k, 'covered' => null, 'heated' => null, 'heating' => null, 'amenities' => array() ), $kinds );
	}
	// La foto: la de la estancia de tipo piscina de la galería, en el mismo orden (solo las que tienen foto).
	$tour  = json_decode( (string) get_post_meta( $post_id, 'maros_tour', true ), true );
	$shots = array();
	foreach ( is_array( $tour ) ? $tour : array() as $room ) {
		if ( is_array( $room ) && 'pool' === ( $room['type'] ?? '' ) && ! empty( $room['photos'] ) ) {
			$shots[] = (int) reset( $room['photos'] );
		}
	}
	$paired = count( $shots ) === count( $pools );
	foreach ( $pools as $i => &$pool ) {
		$pool['amenities'] = array_values( array_filter( is_array( $pool['amenities'] ?? null ) ? $pool['amenities'] : array(), 'is_array' ) );
		$pool['photo']     = $paired ? $shots[ $i ] : ( 1 === count( $pools ) && $shots ? $shots[0] : 0 );
	}
	unset( $pool );
	return $pools;
}

/** [maros_pools] Bloque «Piscina» de la ficha, con ancla #pool para la barra de secciones. */
add_shortcode(
	'maros_pools',
	static function () {
		$post_id = (int) get_the_ID();
		$pools   = maros_pools( $post_id );
		if ( ! $pools ) {
			return '';
		}
		$es    = maros_is_es( $post_id );
		$lang  = $es ? 'es' : 'en';
		$cards = '';
		foreach ( $pools as $pool ) {
			$private = 'private' === $pool['access'];
			$title   = $private ? ( $es ? 'Piscina privada' : 'Private pool' ) : ( $es ? 'Piscina comunitaria' : 'Communal pool' );
			$facts   = array();
			$facts[] = array( $private ? 'phosphor/lock-key' : 'phosphor/buildings', $private ? ( $es ? 'Solo para tu grupo' : 'Just for your group' ) : ( $es ? 'Compartida con la comunidad' : 'Shared with the community' ) );
			if ( null !== ( $pool['covered'] ?? null ) ) {
				$facts[] = array( $pool['covered'] ? 'phosphor/house-simple' : 'phosphor/sun', $pool['covered'] ? ( $es ? 'Interior, cubierta' : 'Indoor, covered' ) : ( $es ? 'Exterior' : 'Outdoor' ) );
			}
			if ( true === ( $pool['heated'] ?? null ) ) {
				// El precio exacto se da en el presupuesto para las fechas del huésped (PC-15 §2.3, I2).
				$heating = is_array( $pool['heating'] ?? null ) ? $pool['heating'] : array();
				$facts[] = array(
					'propios/piscina-climatizada',
					! empty( $heating['included'] )
						? ( $es ? 'Climatizada, incluida en el precio' : 'Heated, included in the price' )
						: ( ! empty( $heating['mandatory'] )
							? ( $es ? 'Climatizada (el coste se indica para tus fechas)' : 'Heated (cost shown for your dates)' )
							: ( $es ? 'Climatizable bajo petición (el coste se indica para tus fechas)' : 'Heating available on request (cost shown for your dates)' ) ),
				);
			} elseif ( false === ( $pool['heated'] ?? null ) ) {
				$facts[] = array( 'phosphor/swimming-pool', $es ? 'Sin climatizar' : 'Not heated' );
			}
			foreach ( maros_sort_amenities( $pool['amenities'] ) as $a ) {
				if ( ! empty( $a[ $lang ] ) ) {
					$facts[] = array( maros_amenity_icon( (string) ( $a['code'] ?? '' ) ), (string) $a[ $lang ] );
				}
			}
			$items = '';
			foreach ( $facts as list( $icon, $text ) ) {
				$items .= '<li>' . maros_icon_svg( $icon, 18 ) . '<span>' . esc_html( $text ) . '</span></li>';
			}
			$img = $pool['photo'] ? wp_get_attachment_image( $pool['photo'], 'medium_large', false, array( 'class' => 'mis-pools__img', 'loading' => 'lazy', 'alt' => $title ) ) : '';
			if ( $img ) {
				// Abre la galería por estancias en el bloque de esta piscina, como los dormitorios.
				$img = '<button type="button" class="mis-pools__open" data-mis-room-photo="' . (int) $pool['photo'] . '" aria-label="' . esc_attr( sprintf( $es ? 'Ver fotos: %s' : 'View photos: %s', $title ) ) . '">' . $img . '</button>';
			}
			$cards .= '<li class="mis-pools__card' . ( $img ? '' : ' mis-pools__card--text' ) . '">' . $img . '<div class="mis-pools__body"><h3 class="mis-pools__title">' . esc_html( $title ) . '</h3><ul class="mis-pools__list">' . $items . '</ul></div></li>';
		}
		$heading = count( $pools ) > 1 ? ( $es ? 'Piscinas' : 'Pools' ) : ( $es ? 'Piscina' : 'Pool' );
		return '<section id="pool" class="mis-pools"><h2 class="wp-block-heading has-large-font-size">' . esc_html( $heading ) . '</h2><ul class="mis-pools__cards">' . $cards . '</ul></section>';
	}
);
