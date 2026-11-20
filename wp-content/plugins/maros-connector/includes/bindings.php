<?php
/**
 * Vinculaciones de bloques y shortcodes de la Propiedad.
 *
 * «maros/property» devuelve datos ya formateados y traducidos para usarlos en párrafos,
 * títulos y botones del editor (Block Bindings), sin bloques a medida.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MIS_STAY_URL' ) ) {
	// Hasta la fase 3 el botón Reservar lleva a la web de reservas de Lodgify (D15).
	define( 'MIS_STAY_URL', 'https://stay.marbellainstyle.com/' );
}

/**
 * Enlace de reserva de una propiedad en la web de Lodgify: /<idioma>/<id de Lodgify>, que Lodgify
 * redirige a la ficha con su slug (comprobado el 17-11-2026). Si la visita llega del buscador
 * (?checkin, ?checkout, ?guests) se pasan como arrival, departure y adults. Sin id de Lodgify,
 * la portada de reservas.
 */
function maros_booking_url( int $post_id ): string {
	$lodgify = (int) get_post_meta( $post_id, 'maros_lodgify_id', true );
	if ( ! $lodgify ) {
		return MIS_STAY_URL;
	}
	$url  = trailingslashit( MIS_STAY_URL ) . ( maros_is_es( $post_id ) ? 'es' : 'en' ) . '/' . $lodgify;
	$args = array();
	foreach ( array( 'checkin' => 'arrival', 'checkout' => 'departure' ) as $from => $to ) {
		$date = sanitize_text_field( wp_unslash( $_GET[ $from ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$args[ $to ] = $date;
		}
	}
	$guests = absint( $_GET['guests'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( $guests ) {
		$args['adults'] = $guests;
	}
	return $args ? add_query_arg( $args, $url ) : $url;
}

/**
 * Los enlaces a las fichas desde el listado conservan la búsqueda (fechas y huéspedes) para que
 * «Check availability» la lleve a Lodgify.
 */
add_filter(
	'post_type_link',
	static function ( string $link, WP_Post $post ): string {
		// En la propia ficha no: el canónico y los hreflang deben quedar limpios.
		if ( 'mis_property' !== $post->post_type || is_admin() || is_singular( 'mis_property' ) ) {
			return $link;
		}
		$args = array();
		foreach ( array( 'checkin', 'checkout' ) as $k ) {
			$v = sanitize_text_field( wp_unslash( $_GET[ $k ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) {
				$args[ $k ] = $v;
			}
		}
		$guests = absint( $_GET['guests'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $guests ) {
			$args['guests'] = $guests;
		}
		return $args ? add_query_arg( $args, $link ) : $link;
	},
	10,
	2
);

/**
 * Comodidades de una estancia en el orden de la web: lo propio de la estancia primero (lista de
 * Julio, marOS spec 039) y lo básico que tiene cualquier dormitorio (perchas, ropa de cama…) al final.
 * Lo que no está en ninguna lista queda en medio, en el orden de marOS.
 */
function maros_sort_amenities( array $amenities ): array {
	$first = array( 'sea_view', 'mountain_view', 'garden_view', 'private_terrace', 'balcony', 'patio_balcony', 'direct_pool_access', 'patio_garden_access', 'independent_access', 'private_entrance', 'walk_in_closet', 'bathtub', 'shower', 'sitting_area', 'desk', 'dedicated_workspace', 'tv', 'indoor_fireplace', 'air_conditioning', 'heating', 'safe', 'wardrobe' );
	$last  = array( 'hangers', 'bed_linens', 'extra_pillows_blankets', 'clothing_storage', 'blackout_shades', 'iron', 'hair_dryer' );
	$rank  = static function ( $a ) use ( $first, $last ): int {
		$code = (string) ( $a['code'] ?? '' );
		$i    = array_search( $code, $first, true );
		if ( false !== $i ) {
			return $i;
		}
		return in_array( $code, $last, true ) ? 200 : 100;
	};
	usort( $amenities, static fn( $a, $b ) => $rank( $a ) <=> $rank( $b ) );
	return $amenities;
}

/** Comodidades de un dormitorio que se enseñan en su tarjeta: lo que lo distingue de los demás. */
const MAROS_SPECIAL_ROOM_AMENITIES = array( 'sea_view', 'mountain_view', 'golf_view', 'garden_view', 'pool_view', 'private_terrace', 'balcony', 'patio_balcony', 'direct_pool_access', 'patio_garden_access', 'independent_access', 'private_entrance', 'walk_in_closet', 'sitting_area', 'dedicated_workspace', 'indoor_fireplace' );

/** «Cama king», «2 × Cama individual»… para una cama de marOS (spec 040). */
function maros_bed_label( array $bed, bool $es ): string {
	$beds  = array(
		'single'   => array( 'Single bed', 'Cama individual' ),
		'double'   => array( 'Double bed', 'Cama doble' ),
		'queen'    => array( 'Queen-size bed', 'Cama queen' ),
		'king'     => array( 'King-size bed', 'Cama king' ),
		'sofa_bed' => array( 'Sofa bed', 'Sofá cama' ),
		'bunk'     => array( 'Bunk bed', 'Litera' ),
		'crib'     => array( 'Cot', 'Cuna' ),
	);
	$label = $beds[ $bed['type'] ?? '' ][ $es ? 1 : 0 ] ?? ( $es ? 'Cama' : 'Bed' );
	$count = (int) ( $bed['count'] ?? 1 );
	return $count > 1 ? $count . ' × ' . $label : $label;
}

function maros_is_es( int $post_id = 0 ): bool {
	if ( function_exists( 'pll_get_post_language' ) && $post_id ) {
		return 'es' === pll_get_post_language( $post_id );
	}
	return function_exists( 'pll_current_language' ) && 'es' === pll_current_language();
}

add_action(
	'init',
	static function () {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}
		register_block_bindings_source(
			'maros/property',
			array(
				'label'              => __( 'Property data (marOS)', 'maros-connector' ),
				'get_value_callback' => 'maros_property_binding_value',
				'uses_context'       => array( 'postId', 'postType' ),
			)
		);
	}
);

function maros_property_binding_value( array $args, $block_instance ) {
	$post_id = (int) ( $block_instance->context['postId'] ?? get_the_ID() );
	$es      = maros_is_es( $post_id );
	$m       = static fn( string $k ) => get_post_meta( $post_id, $k, true );

	switch ( $args['key'] ?? '' ) {
		case 'facts':
			return esc_html(
				sprintf(
					$es ? '%d huéspedes · %d dormitorios · %d baños' : '%d guests · %d bedrooms · %d bathrooms',
					(int) $m( 'maros_max_guests' ),
					(int) $m( 'maros_bedrooms' ),
					(int) $m( 'maros_bathrooms' )
				)
			);
		case 'area':
			return esc_html( maros_area_name( $post_id ) );
		case 'type':
			$terms = get_the_terms( $post_id, 'tipo-propiedad' );
			return esc_html( $terms && ! is_wp_error( $terms ) ? $terms[0]->name : '' );
		case 'checkin':
			// Sin horas en marOS no se dice nada (antes salía «Check-in from · check-out by»).
			$in    = trim( (string) $m( 'maros_check_in' ) );
			$out   = trim( (string) $m( 'maros_check_out' ) );
			$parts = array();
			if ( '' !== $in ) {
				$parts[] = sprintf( $es ? 'Entrada desde las %s' : 'Check-in from %s', $in );
			}
			if ( '' !== $out ) {
				$parts[] = sprintf( $es ? 'salida antes de las %s' : 'check-out by %s', $out );
			}
			return $parts ? esc_html( ucfirst( implode( ' · ', $parts ) ) ) : '';
		case 'deposit':
			$deposit = (float) $m( 'maros_deposit' );
			return $deposit ? esc_html( sprintf( $es ? 'Fianza: %s €' : 'Security deposit: €%s', number_format_i18n( $deposit ) ) ) : '';
		case 'pool':
			$labels = array(
				'private'  => array( 'Private pool', 'Piscina privada' ),
				'communal' => array( 'Communal pool', 'Piscina comunitaria' ),
				'both'     => array( 'Private and communal pool', 'Piscina privada y comunitaria' ),
				'none'     => array( 'No pool', 'Sin piscina' ),
			);
			$label = $labels[ (string) $m( 'maros_pool' ) ] ?? null;
			return $label ? esc_html( $label[ $es ? 1 : 0 ] ) : '';
		case 'pool_heating':
			// El precio exacto se da en el presupuesto para las fechas del huésped (PC-15 §2.3, I2).
			if ( 'yes' !== $m( 'maros_pool_heating' ) ) {
				return '';
			}
			if ( 'yes' === $m( 'maros_pool_heating_included' ) ) {
				return esc_html( $es ? 'Piscina climatizada, incluida en el precio' : 'Heated pool, included in the price' );
			}
			return esc_html( $es ? 'Piscina climatizable (el coste se indica para tus fechas)' : 'Pool heating available (cost shown for your dates)' );
		case 'self_check_in':
			// Es una opción además de la recepción en persona (marOS spec 032): solo se dice si la hay.
			return 'yes' === $m( 'maros_self_check_in' ) ? esc_html( $es ? 'Opción de llegada autónoma (self check-in)' : 'Self check-in available' ) : '';
		case 'pets':
			$pets = (string) $m( 'maros_pets' );
			if ( 'no' === $pets ) {
				return esc_html( $es ? 'No se admiten mascotas' : 'No pets allowed' );
			}
			if ( 'yes' !== $pets ) {
				return '';
			}
			$fee = (float) $m( 'maros_pets_fee' );
			if ( ! $fee ) {
				return esc_html( $es ? 'Se admiten mascotas' : 'Pets welcome' );
			}
			return esc_html( sprintf( $es ? 'Se admiten mascotas (suplemento de %s €)' : 'Pets welcome (€%s supplement)', number_format_i18n( $fee ) ) );
		case 'events':
			$events = (string) $m( 'maros_events' );
			if ( '' === $events ) {
				return '';
			}
			return esc_html( 'yes' === $events ? ( $es ? 'Se admiten eventos, consúltanos' : 'Events allowed on request' ) : ( $es ? 'No se admiten fiestas ni eventos' : 'No parties or events' ) );
		case 'license':
			$license = (string) $m( 'maros_license' );
			return $license ? esc_html( sprintf( $es ? 'Registro turístico: %s' : 'Tourist licence: %s', $license ) ) : '';
		case 'features':
			// Tarjeta del listado: las tres key features principales, en breve (Julio, 18-11-2026).
			$keys = function_exists( 'maros_key_features' ) ? array_slice( maros_key_features( $post_id, $es ), 0, 3 ) : array();
			return esc_html( implode( ' · ', wp_list_pluck( $keys, 'title' ) ) );
		case 'stay_price':
			// Solo con fechas buscadas (spec 005, conclusión 4): el total de la estancia, orientativo.
			$stay = class_exists( 'Maros_Search' ) ? Maros_Search::stay_for( $post_id ) : null;
			if ( ! $stay || $stay['total'] <= 0 ) {
				return '';
			}
			return esc_html(
				sprintf(
					'%1$s · %2$s',
					Maros_Search::money( $stay['total'], $stay['currency'], $es ),
					1 === $stay['nights'] ? ( $es ? '1 noche' : '1 night' ) : sprintf( $es ? '%d noches' : '%d nights', $stay['nights'] )
				)
			);
		case 'booking_url':
			return esc_url( maros_booking_url( (int) $post_id ) );
		default:
			$value = $m( (string) ( $args['key'] ?? '' ) );
			return is_scalar( $value ) ? esc_html( (string) $value ) : '';
	}
}

/**
 * Quita de la lista de comodidades lo que Lodgify da como recuento («4 King bed», «5 Bathroom»):
 * ya está en los datos y en la distribución, y a menudo no coincide. Si una etiqueta empieza por
 * otra («Parking» y «Parking Included») queda solo la más larga.
 *
 * @param WP_Term[] $terms
 * @return WP_Term[]
 */
function maros_clean_amenities( array $terms ): array {
	$terms = array_values( array_filter( $terms, static fn( $t ) => ! preg_match( '/^\d+\s/', (string) $t->name ) ) );
	$names = array_map( static fn( $t ) => strtolower( trim( (string) $t->name ) ), $terms );
	return array_values(
		array_filter(
			$terms,
			static function ( $t ) use ( $names ): bool {
				$n = strtolower( trim( (string) $t->name ) );
				foreach ( $names as $other ) {
					if ( $other !== $n && str_starts_with( $other, $n . ' ' ) ) {
						return false;
					}
				}
				return true;
			}
		)
	);
}

/**
 * Etiqueta en mayúscula solo al principio («Fire Extinguisher» → «Fire extinguisher»). Las siglas
 * (TV, A/C, Wi-Fi) se dejan como vienen.
 */
function maros_amenity_label( string $name ): string {
	$words = explode( ' ', trim( $name ) );
	foreach ( $words as $i => $w ) {
		if ( $i > 0 && preg_match( '/^\p{Lu}\p{Ll}+$/u', $w ) ) {
			$words[ $i ] = mb_strtolower( $w );
		}
	}
	return implode( ' ', $words );
}

/**
 * [maros_amenities] «Features and amenities»: comodidades de Lodgify con su icono, en dos columnas.
 * Se ven diez; el resto, con «Show all N amenities».
 */
add_shortcode(
	'maros_amenities',
	static function () {
		$terms = get_the_terms( get_the_ID(), 'equipamiento' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		$es = maros_is_es( (int) get_the_ID() );
		$terms = maros_clean_amenities( $terms );
		// Lo que más pregunta el huésped va entre las diez primeras (la secadora, Julio 17-11-2026).
		$first = array( 'private_pool', 'heated_pool', 'shared_pool', 'sea_view', 'air_conditioning', 'wifi', 'parking', 'dryer', 'washer', 'dishwasher', 'bbq', 'gym', 'terrace' );
		$rank  = static function ( $t ) use ( $first ) {
			$pos = array_search( preg_replace( '/_es$/', '', str_replace( '-', '_', $t->slug ) ), $first, true );
			return false === $pos ? count( $first ) : $pos;
		};
		usort( $terms, static fn( $a, $b ) => array( $rank( $a ), $a->name ) <=> array( $rank( $b ), $b->name ) );
		$items = array_map( static fn( $t ) => '<li>' . maros_icon_svg( maros_amenity_icon( $t->slug ), 22 ) . '<span>' . esc_html( maros_amenity_label( $t->name ) ) . '</span></li>', $terms );
		$html  = '<ul class="mis-amenities">' . implode( '', $items ) . '</ul>';
		if ( count( $items ) > 10 ) {
			$html .= '<button type="button" class="mis-amenities__all" onclick="this.previousElementSibling.classList.add(\'is-open\');this.remove();">' . esc_html( sprintf( $es ? 'Ver las %d comodidades' : 'Show all %d amenities', count( $items ) ) ) . '</button>';
		}
		return $html;
	}
);

/**
 * [maros_sleeping] Distribución de dormitorios (marOS spec 040): una fila de tarjetas con
 * desplazamiento horizontal, una por dormitorio, con foto, planta, camas, baño en-suite y
 * comodidades. Maqueta aprobada por Julio el 16-11-2026.
 */
add_shortcode(
	'maros_sleeping',
	static function () {
		$post_id = get_the_ID();
		$rooms   = json_decode( (string) get_post_meta( $post_id, 'maros_sleeping', true ), true );
		if ( ! is_array( $rooms ) || ! $rooms ) {
			return '';
		}
		$es   = maros_is_es( (int) $post_id );
		$lang = $es ? 'es' : 'en';
		$max   = 3;
		$icon  = static fn( string $d ) => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
		$bed_i = maros_icon_svg( maros_amenity_icon( 'bed' ), 18 );
		$bath  = maros_icon_svg( maros_amenity_icon( 'ensuite' ), 18 );

		$cards = '';
		foreach ( $rooms as $room ) {
			$n     = (int) ( $room['number'] ?? 0 );
			$title = sprintf( $es ? 'Dormitorio %d' : 'Bedroom %d', $n );
			$img   = ! empty( $room['photo'] ) ? wp_get_attachment_image( (int) $room['photo'], 'medium_large', false, array( 'class' => 'mis-sleeping__img', 'loading' => 'lazy', 'alt' => $title ) ) : '';
			if ( $img ) {
				// Abre la galería por estancias en el bloque de este dormitorio (Julio 19-11-2026), no el visor.
				$img = '<button type="button" class="mis-sleeping__open" data-mis-room-photo="' . (int) $room['photo'] . '" aria-label="' . esc_attr( sprintf( $es ? 'Ver fotos: %s' : 'View photos: %s', $title ) ) . '">' . $img . '</button>';
			}
			$html  = '<li class="mis-sleeping__card"><div class="mis-sleeping__media">' . ( $img ?: '<div class="mis-sleeping__img mis-sleeping__img--empty" aria-hidden="true"></div>' );
			if ( ! empty( $room['master'] ) ) {
				$html .= '<span class="mis-sleeping__badge">' . esc_html( $es ? 'Principal' : 'Master' ) . '</span>';
			}
			$html .= '</div><div class="mis-sleeping__body"><h3 class="mis-sleeping__title">' . esc_html( $title ) . '</h3>';
			if ( ! empty( $room['floor'][ $lang ] ) ) {
				$html .= '<p class="mis-sleeping__floor">' . esc_html( $room['floor'][ $lang ] ) . '</p>';
			}
			$bed_text = array();
			foreach ( is_array( $room['beds'] ?? null ) ? $room['beds'] : array() as $b ) {
				$bed_text[] = maros_bed_label( $b, $es );
			}
			if ( $bed_text ) {
				$html .= '<p class="mis-sleeping__beds">' . $bed_i . '<span>' . esc_html( implode( ' · ', $bed_text ) ) . '</span></p>';
			}
			$items = array();
			if ( is_array( $room['en_suite'] ?? null ) ) {
				$tub     = ! empty( $room['en_suite']['bathtub'] );
				$shower  = ! empty( $room['en_suite']['shower'] );
				$text    = $tub && $shower
					? ( $es ? 'Baño en-suite con bañera y ducha' : 'En-suite bathroom with bathtub and shower' )
					: ( $tub
						? ( $es ? 'Baño en-suite con bañera' : 'En-suite bathroom with bathtub' )
						: ( $shower
							? ( $es ? 'Baño en-suite con ducha' : 'En-suite bathroom with shower' )
							: ( $es ? 'Baño en-suite' : 'En-suite bathroom' ) ) );
				$items[] = '<li>' . $bath . '<span>' . esc_html( $text ) . '</span></li>';
			}
			// Solo lo especial del dormitorio (Julio 17-11-2026): vistas, terraza, accesos, vestidor…
			// Lo que tiene cualquier dormitorio (aire, perchas, ropa de cama…) no se enseña aquí.
			$special = array_values(
				array_filter(
					maros_sort_amenities( is_array( $room['amenities'] ?? null ) ? $room['amenities'] : array() ),
					static fn( $a ) => in_array( (string) ( $a['code'] ?? '' ), MAROS_SPECIAL_ROOM_AMENITIES, true )
				)
			);
			foreach ( array_slice( $special, 0, $max ) as $a ) {
				if ( ! empty( $a[ $lang ] ) ) {
					$items[] = '<li>' . maros_icon_svg( maros_amenity_icon( (string) ( $a['code'] ?? '' ) ), 18 ) . '<span>' . esc_html( $a[ $lang ] ) . '</span></li>';
				}
			}
			if ( $items ) {
				$html .= '<ul class="mis-sleeping__list">' . implode( '', $items ) . '</ul>';
			}
			$cards .= $html . '</div></li>';
		}

		$id   = 'mis-sleeping-' . $post_id;
		$prev = $es ? 'Dormitorios anteriores' : 'Previous bedrooms';
		$next = $es ? 'Más dormitorios' : 'More bedrooms';
		$nav  = count( $rooms ) > 1
			? '<div class="mis-sleeping__nav"><button type="button" class="mis-sleeping__btn" data-dir="-1" aria-controls="' . esc_attr( $id ) . '" aria-label="' . esc_attr( $prev ) . '">' . $icon( '<path d="M15 6l-6 6 6 6"/>' ) . '</button><button type="button" class="mis-sleeping__btn" data-dir="1" aria-controls="' . esc_attr( $id ) . '" aria-label="' . esc_attr( $next ) . '">' . $icon( '<path d="M9 6l6 6-6 6"/>' ) . '</button></div>'
			: '';
		$js   = '<script>document.querySelectorAll(".mis-sleeping__btn").forEach(function(b){b.addEventListener("click",function(){var t=document.getElementById(b.getAttribute("aria-controls"));if(!t)return;var c=t.querySelector(".mis-sleeping__card");t.scrollBy({left:(c?c.offsetWidth+24:300)*Number(b.dataset.dir),behavior:"smooth"});});});</script>';

		$head = '<div class="mis-sleeping__head"><h2 class="wp-block-heading has-large-font-size">' . esc_html( $es ? 'Distribución' : 'Sleeping arrangements' ) . '</h2>' . $nav . '</div>';
		return '<section id="bedrooms" class="mis-sleeping">' . $head . '<ul class="mis-sleeping__track" id="' . esc_attr( $id ) . '" tabindex="0" aria-label="' . esc_attr( $es ? 'Dormitorios' : 'Bedrooms' ) . '">' . $cards . '</ul></section>' . ( $nav ? $js : '' );
	}
);
