<?php
/**
 * [maros_gallery] Galería de la propiedad (marOS spec 041, maqueta B aprobada por Julio el 16-11-2026).
 *
 * La cabecera enseña las cinco primeras fotos y un botón «Ver las N fotos». El botón abre una capa a
 * pantalla completa con una tira de estancias arriba y, debajo, cada estancia con su nombre, una
 * línea con planta, camas y comodidades, y sus fotos (como el recorrido de fotos de Airbnb). Las
 * fotos que no están en ninguna estancia van primero, en «La villa». Cualquier foto abre el visor.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

/**
 * Secciones de la galería en el orden en que se enseñan: «La villa» primero y cada baño en-suite
 * justo después de su dormitorio.
 *
 * @return array<int, array{title: string, line: string, photos: int[]}>
 */
function maros_gallery_sections( int $post_id, bool $es ): array {
	$gallery  = array_values( array_unique( array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $post_id, 'maros_gallery', true ) ) ) ) ) );
	$tour     = json_decode( (string) get_post_meta( $post_id, 'maros_tour', true ), true );
	$tour     = is_array( $tour ) ? array_values( array_filter( $tour, static fn( $r ) => is_array( $r ) && ! empty( $r['photos'] ) ) ) : array();
	$sleeping = json_decode( (string) get_post_meta( $post_id, 'maros_sleeping', true ), true );
	$beds     = array();
	foreach ( is_array( $sleeping ) ? $sleeping : array() as $room ) {
		$beds[ (int) ( $room['number'] ?? 0 ) ] = $room;
	}
	$lang = $es ? 'es' : 'en';

	if ( ! $tour ) {
		return $gallery ? array( array( 'title' => '', 'line' => '', 'photos' => $gallery ) ) : array();
	}

	// Cada baño en-suite pasa detrás de su dormitorio, si ese dormitorio tiene fotos.
	$bedrooms = array_filter( array_map( static fn( $r ) => (int) ( $r['bedroom'] ?? 0 ), $tour ) );
	$suites   = array();
	$rooms    = array();
	foreach ( $tour as $room ) {
		$of = (int) ( $room['en_suite_of'] ?? 0 );
		if ( $of && in_array( $of, $bedrooms, true ) ) {
			$suites[ $of ][] = $room;
		} else {
			$rooms[] = $room;
		}
	}
	$ordered = array();
	foreach ( $rooms as $room ) {
		$ordered[] = $room;
		$n         = (int) ( $room['bedroom'] ?? 0 );
		foreach ( $n ? ( $suites[ $n ] ?? array() ) : array() as $bath ) {
			$ordered[] = $bath;
		}
	}

	$sections = array();
	$in_rooms = array();
	foreach ( $ordered as $room ) {
		$n     = (int) ( $room['bedroom'] ?? 0 );
		$of    = (int) ( $room['en_suite_of'] ?? 0 );
		$parts = array();
		if ( $n ) {
			$title = sprintf( $es ? 'Dormitorio %d' : 'Bedroom %d', $n );
			if ( ! empty( $beds[ $n ]['master'] ) ) {
				$parts[] = $es ? 'Dormitorio principal' : 'Master bedroom';
			}
		} elseif ( $of ) {
			$title = sprintf( $es ? 'Baño en-suite · Dormitorio %d' : 'Bedroom %d en-suite bathroom', $of );
		} else {
			$title = (string) ( $room['name'] ?? '' );
		}
		if ( ! empty( $room['floor'][ $lang ] ) ) {
			$parts[] = $room['floor'][ $lang ];
		}
		foreach ( $n && is_array( $beds[ $n ]['beds'] ?? null ) ? $beds[ $n ]['beds'] : array() as $bed ) {
			$parts[] = maros_bed_label( (array) $bed, $es );
		}
		foreach ( maros_sort_amenities( is_array( $room['amenities'] ?? null ) ? $room['amenities'] : array() ) as $a ) {
			if ( ! empty( $a[ $lang ] ) ) {
				$parts[] = $a[ $lang ];
			}
		}
		$photos     = array_values( array_unique( array_filter( array_map( 'intval', (array) $room['photos'] ) ) ) );
		$in_rooms   = array_merge( $in_rooms, $photos );
		$sections[] = array( 'title' => $title, 'line' => implode( ' · ', $parts ), 'photos' => $photos );
	}

	$rest = array_values( array_diff( $gallery, $in_rooms ) );
	if ( $rest ) {
		array_unshift( $sections, array( 'title' => $es ? 'La villa' : 'The villa', 'line' => '', 'photos' => $rest ) );
	}
	return $sections;
}

add_shortcode(
	'maros_gallery',
	static function () {
		$post_id  = (int) get_the_ID();
		$es       = maros_is_es( $post_id );
		$sections = maros_gallery_sections( $post_id, $es );
		if ( ! $sections ) {
			return '';
		}
		$name  = get_the_title( $post_id );
		$order = array_values( array_unique( array_merge( ...array_map( static fn( $s ) => $s['photos'], $sections ) ) ) );
		$total = count( $order );
		$lodgify = array_values( array_unique( array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $post_id, 'maros_gallery', true ) ) ) ) ) );
		$hero    = array_values( array_unique( array_merge( array_intersect( $lodgify, $order ), $order ) ) );
		$id    = 'mis-tour-' . $post_id;
		$icon  = static fn( string $d, int $s = 18 ) => '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
		$alt   = static fn( int $att ) => trim( (string) get_post_meta( $att, '_wp_attachment_image_alt', true ) ) ?: $name;

		// Carrusel a sangre (formato Le Collectionist, Julio 17-11-2026): todas las fotos en el orden
		// de Lodgify, flechas, deslizar y «1/N», que abre la galería por estancias.
		$html = '<div class="mis-gallery-wrap mis-hero-gallery" data-mis-tour="' . esc_attr( $id ) . '"><div class="mis-carousel" tabindex="0" aria-roledescription="carousel" aria-label="' . esc_attr( sprintf( $es ? 'Fotos de %s' : 'Photos of %s', $name ) ) . '">';
		foreach ( $hero as $i => $att ) {
			$img   = wp_get_attachment_image( $att, 'large', false, array( 'class' => 'mis-carousel__img', 'loading' => 0 === $i ? 'eager' : 'lazy', 'fetchpriority' => 0 === $i ? 'high' : 'auto', 'sizes' => '100vw' ) );
			$html .= '<button type="button" class="mis-carousel__slide" data-mis-photo="' . (int) $att . '" aria-label="' . esc_attr( sprintf( $es ? 'Ver foto %1$d de %2$d' : 'View photo %1$d of %2$d', $i + 1, $total ) ) . '">' . $img . '</button>';
		}
		$html .= '</div>';
		if ( $total > 1 ) {
			$html .= '<button type="button" class="mis-carousel__nav mis-carousel__nav--prev" data-mis-slide="-1" aria-label="' . esc_attr( $es ? 'Foto anterior' : 'Previous photo' ) . '">' . $icon( '<path d="M15 6l-6 6 6 6"/>', 22 ) . '</button>';
			$html .= '<button type="button" class="mis-carousel__nav mis-carousel__nav--next" data-mis-slide="1" aria-label="' . esc_attr( $es ? 'Foto siguiente' : 'Next photo' ) . '">' . $icon( '<path d="M9 6l6 6-6 6"/>', 22 ) . '</button>';
			$html .= '<button type="button" class="mis-carousel__count" data-mis-open aria-label="' . esc_attr( sprintf( $es ? 'Ver las %d fotos' : 'Show all %d photos', $total ) ) . '"><span class="mis-carousel__index">1</span>/' . $total . '</button>';
		}

		// Capa a pantalla completa: tira de estancias, secciones y visor.
		$html .= '<dialog class="mis-tour" id="' . esc_attr( $id ) . '" aria-label="' . esc_attr( sprintf( $es ? 'Fotos de %s' : 'Photos of %s', $name ) ) . '">';
		$html .= '<div class="mis-tour__bar"><button type="button" class="mis-tour__close" data-mis-close aria-label="' . esc_attr( $es ? 'Cerrar' : 'Close' ) . '">' . $icon( '<path d="M15 6l-6 6 6 6"/>' ) . '</button><p class="mis-tour__title">' . esc_html( $name ) . ' <span>· ' . esc_html( sprintf( $es ? '%d fotos' : '%d photos', $total ) ) . '</span></p></div>';
		$html .= '<div class="mis-tour__scroll">';
		$named = array_filter( $sections, static fn( $s ) => '' !== $s['title'] );
		if ( count( $named ) > 1 ) {
			$html .= '<nav class="mis-tour__chips" aria-label="' . esc_attr( $es ? 'Estancias' : 'Rooms' ) . '">';
			foreach ( $named as $i => $s ) {
				$n     = count( $s['photos'] );
				$html .= '<button type="button" class="mis-tour__chip" data-mis-goto="' . esc_attr( $id . '-' . $i ) . '">' . wp_get_attachment_image( $s['photos'][0], 'medium', false, array( 'class' => 'mis-tour__thumb', 'loading' => 'lazy', 'alt' => '' ) ) . '<span>' . esc_html( $s['title'] ) . '</span><small>' . esc_html( sprintf( 1 === $n ? ( $es ? '%d foto' : '%d photo' ) : ( $es ? '%d fotos' : '%d photos' ), $n ) ) . '</small></button>';
			}
			$html .= '</nav>';
		}
		foreach ( $sections as $i => $s ) {
			$html .= '<section class="mis-tour__sec" id="' . esc_attr( $id . '-' . $i ) . '">';
			if ( '' !== $s['title'] ) {
				$html .= '<div class="mis-tour__head"><h3>' . esc_html( $s['title'] ) . '</h3>' . ( '' !== $s['line'] ? '<p>' . esc_html( $s['line'] ) . '</p>' : '' ) . '</div>';
			}
			$html .= '<div class="mis-tour__grid">';
			foreach ( $s['photos'] as $att ) {
				$full  = wp_get_attachment_image_url( $att, 'large' );
				$set   = wp_get_attachment_image_srcset( $att, 'large' );
				$html .= '<button type="button" class="mis-tour__item" data-mis-photo="' . (int) $att . '" data-full="' . esc_url( (string) $full ) . '" data-srcset="' . esc_attr( (string) $set ) . '" data-section="' . esc_attr( $s['title'] ) . '" aria-label="' . esc_attr( $alt( $att ) ) . '">' . wp_get_attachment_image( $att, 'medium_large', false, array( 'class' => 'mis-tour__img', 'loading' => 'lazy' ) ) . '</button>';
			}
			$html .= '</div></section>';
		}
		$html .= '</div>';
		$html .= '<div class="mis-lb" hidden><div class="mis-lb__bar"><span class="mis-lb__count" aria-live="polite"></span><button type="button" class="mis-lb__close" data-mis-lb-close aria-label="' . esc_attr( $es ? 'Cerrar visor' : 'Close viewer' ) . '">' . $icon( '<path d="M6 6l12 12M18 6L6 18"/>' ) . '</button></div>';
		$html .= '<div class="mis-lb__stage"><button type="button" class="mis-lb__nav" data-mis-step="-1" aria-label="' . esc_attr( $es ? 'Foto anterior' : 'Previous photo' ) . '">' . $icon( '<path d="M15 6l-6 6 6 6"/>', 22 ) . '</button><img class="mis-lb__img" alt="" sizes="100vw"><button type="button" class="mis-lb__nav" data-mis-step="1" aria-label="' . esc_attr( $es ? 'Foto siguiente' : 'Next photo' ) . '">' . $icon( '<path d="M9 6l6 6-6 6"/>', 22 ) . '</button></div>';
		$html .= '<p class="mis-lb__caption"></p></div>';
		$html .= '</dialog></div>';

		wp_enqueue_script( 'maros-gallery', plugins_url( 'assets/gallery.js', __DIR__ ), array(), (string) filemtime( dirname( __DIR__ ) . '/assets/gallery.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		return $html;
	}
);
