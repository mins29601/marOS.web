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
			return esc_html( $es ? 'Piscina climatizable (el coste se indica para tus fechas)' : 'Pool heating available (cost shown for your dates)' );
		case 'arrival':
			$arrival = (string) $m( 'maros_arrival' );
			if ( 'host' === $arrival ) {
				return esc_html( $es ? 'Te recibimos en persona a tu llegada' : 'We welcome you in person on arrival' );
			}
			if ( 'self_check_in' === $arrival ) {
				return esc_html( $es ? 'Llegada autónoma (self check-in)' : 'Self check-in' );
			}
			return '';
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
