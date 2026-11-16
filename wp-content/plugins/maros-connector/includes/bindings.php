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
			$terms = get_the_terms( $post_id, 'zona' );
			return esc_html( $terms && ! is_wp_error( $terms ) ? $terms[0]->name : (string) $m( 'maros_area' ) );
		case 'type':
			$terms = get_the_terms( $post_id, 'tipo-propiedad' );
			return esc_html( $terms && ! is_wp_error( $terms ) ? $terms[0]->name : '' );
		case 'checkin':
			return esc_html( sprintf( $es ? 'Entrada desde las %s · salida antes de las %s' : 'Check-in from %s · check-out by %s', $m( 'maros_check_in' ), $m( 'maros_check_out' ) ) );
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
		case 'booking_url':
			return esc_url( MIS_STAY_URL );
		default:
			$value = $m( (string) ( $args['key'] ?? '' ) );
			return is_scalar( $value ) ? esc_html( (string) $value ) : '';
	}
}

/**
 * [maros_gallery] Galería de la propiedad (fotos de marOS, en el orden de marOS).
 */
add_shortcode(
	'maros_gallery',
	static function () {
		$post_id = get_the_ID();
		$ids     = array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $post_id, 'maros_gallery', true ) ) ) );
		if ( ! $ids ) {
			return '';
		}
		$html = '<div class="mis-gallery">';
		foreach ( $ids as $i => $id ) {
			$html .= wp_get_attachment_image( $id, 0 === $i ? 'large' : 'medium_large', false, array( 'class' => 'mis-gallery__img', 'loading' => 0 === $i ? 'eager' : 'lazy' ) );
		}
		return $html . '</div>';
	}
);

/**
 * [maros_amenities] Equipamiento como lista.
 */
add_shortcode(
	'maros_amenities',
	static function () {
		$terms = get_the_terms( get_the_ID(), 'equipamiento' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		$items = array_map( static fn( $t ) => '<li>' . esc_html( $t->name ) . '</li>', $terms );
		return '<ul class="mis-amenities">' . implode( '', $items ) . '</ul>';
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
		$beds = array(
			'single'   => array( 'Single bed', 'Cama individual' ),
			'double'   => array( 'Double bed', 'Cama doble' ),
			'queen'    => array( 'Queen-size bed', 'Cama queen' ),
			'king'     => array( 'King-size bed', 'Cama king' ),
			'sofa_bed' => array( 'Sofa bed', 'Sofá cama' ),
			'bunk'     => array( 'Bunk bed', 'Litera' ),
			'crib'     => array( 'Cot', 'Cuna' ),
		);
		// Lo propio de un dormitorio va primero (lista de Julio, marOS spec 039); el resto, después.
		$first = array( 'walk_in_closet', 'private_terrace', 'balcony', 'sea_view', 'mountain_view', 'garden_view', 'direct_pool_access', 'patio_garden_access', 'independent_access', 'sitting_area', 'desk', 'wardrobe', 'tv', 'air_conditioning' );
		$max   = 5;
		$icon  = static fn( string $d ) => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
		$bed_i = $icon( '<path d="M3 18V7"/><path d="M21 18v-6a3 3 0 0 0-3-3h-8v6"/><path d="M3 15h18"/><circle cx="6.5" cy="11.5" r="1.8"/>' );
		$bath  = $icon( '<path d="M4 12h16v3a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4z"/><path d="M6 12V5a2 2 0 0 1 3.5-1.3"/><path d="M7 19l-1 2M17 19l1 2"/>' );
		$check = $icon( '<path d="M5 12l4 4 10-10"/>' );

		$cards = '';
		foreach ( $rooms as $room ) {
			$n     = (int) ( $room['number'] ?? 0 );
			$title = sprintf( $es ? 'Dormitorio %d' : 'Bedroom %d', $n );
			$img   = ! empty( $room['photo'] ) ? wp_get_attachment_image( (int) $room['photo'], 'medium_large', false, array( 'class' => 'mis-sleeping__img', 'loading' => 'lazy', 'alt' => $title ) ) : '';
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
				$label      = $beds[ $b['type'] ?? '' ][ $es ? 1 : 0 ] ?? ( $es ? 'Cama' : 'Bed' );
				$count      = (int) ( $b['count'] ?? 1 );
				$bed_text[] = $count > 1 ? $count . ' × ' . $label : $label;
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
			$amenities = is_array( $room['amenities'] ?? null ) ? $room['amenities'] : array();
			usort(
				$amenities,
				static function ( $a, $b ) use ( $first ) {
					$x = array_search( $a['code'] ?? '', $first, true );
					$y = array_search( $b['code'] ?? '', $first, true );
					return ( false === $x ? 99 : $x ) <=> ( false === $y ? 99 : $y );
				}
			);
			$shown = array_slice( $amenities, 0, $max );
			foreach ( $shown as $a ) {
				if ( ! empty( $a[ $lang ] ) ) {
					$items[] = '<li>' . $check . '<span>' . esc_html( $a[ $lang ] ) . '</span></li>';
				}
			}
			$rest = count( $amenities ) - count( $shown );
			if ( $rest > 0 ) {
				$items[] = '<li class="mis-sleeping__more"><span>' . esc_html( sprintf( $es ? '+ %d comodidades más' : '+ %d more amenities', $rest ) ) . '</span></li>';
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
