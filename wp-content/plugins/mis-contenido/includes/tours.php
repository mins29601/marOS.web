<?php
/**
 * Ficha de experiencia (mis_tour), con la de Granada de la web actual y las de GetYourGuide como
 * modelo (Julio, 24-11-2026): datos rápidos con iconos, lo más destacado, descripción, programa,
 * qué incluye y qué no, extras, punto de encuentro, información importante y cancelación, con la
 * caja de reserva al lado y una barra fija en el móvil.
 *
 *  [mis_tour_head]     Migas, tipo, título, nota media y extracto.
 *  [mis_tour_ficha]    Todas las secciones de la columna principal.
 *  [mis_tour_book]     Caja de reserva (FareHarbor o solicitud).
 *  [mis_tour_sticky]   Barra de reserva fija en el móvil.
 *  [mis_tour_related]  «También te puede gustar»: otras experiencias del mismo idioma.
 *
 * Los textos se cargan con scripts/setup/servicios.php desde los borradores de marOS.content
 * (PC-40). Las listas son una línea por elemento; el programa, «hora | texto».
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

/** Campos de texto de la ficha (se suman a mis_tour_fields() en mis-contenido.php). */
function mis_tour_ficha_fields(): array {
	return array(
		'tour_kind'           => array( 'type' => 'string', 'label' => 'Tipo: tour | activity | multiday' ),
		'tour_meeting_point'  => array( 'type' => 'string', 'label' => 'Punto de encuentro (si no hay recogida)' ),
		'tour_highlights'     => array( 'type' => 'string', 'label' => 'Lo más destacado (una línea por punto)' ),
		'tour_itinerary'      => array( 'type' => 'string', 'label' => 'Programa (una línea por paso: «hora | texto»)' ),
		'tour_itinerary_note' => array( 'type' => 'string', 'label' => 'Nota bajo el programa' ),
		'tour_includes'       => array( 'type' => 'string', 'label' => 'Qué incluye (una línea por punto)' ),
		'tour_excludes'       => array( 'type' => 'string', 'label' => 'No incluye (una línea por punto)' ),
		'tour_extras'         => array( 'type' => 'string', 'label' => 'Extras con precio (una línea por punto)' ),
		'tour_bring'          => array( 'type' => 'string', 'label' => 'Qué llevar (una línea por punto)' ),
		'tour_not_allowed'    => array( 'type' => 'string', 'label' => 'No se permite (una línea por punto)' ),
		'tour_know'           => array( 'type' => 'string', 'label' => 'Bueno saberlo (una línea por punto)' ),
		'tour_cancellation'   => array( 'type' => 'string', 'label' => 'Cancelación (texto)' ),
		'tour_gallery'        => array( 'type' => 'string', 'label' => 'Fotos (ids de Medios separados por comas)' ),
	);
}

function mis_tour_es( int $post_id ): bool {
	return function_exists( 'pll_get_post_language' ) ? 'es' === pll_get_post_language( $post_id ) : false;
}

function mis_tour_lines( int $post_id, string $key ): array {
	$lines = preg_split( '/\r\n|\r|\n/', (string) get_post_meta( $post_id, $key, true ) );
	return array_values( array_filter( array_map( 'trim', $lines ), 'strlen' ) );
}

/** tour | activity | multiday. */
function mis_tour_kind( int $post_id ): string {
	$kind = (string) get_post_meta( $post_id, 'tour_kind', true );
	return in_array( $kind, array( 'tour', 'activity', 'multiday' ), true ) ? $kind : 'tour';
}

function mis_tour_kind_label( string $kind, bool $es ): string {
	$labels = array(
		'tour'     => array( 'Day tour', 'Tour de un día' ),
		'activity' => array( 'Activity', 'Actividad' ),
		'multiday' => array( 'Multi-day trip', 'Viaje de varios días' ),
	);
	return $labels[ $kind ][ $es ? 1 : 0 ];
}

/** Resumen de una línea de la cancelación (condiciones de FareHarbor, cláusulas 56 y 63). */
function mis_tour_cancel_short( string $kind, bool $es ): string {
	if ( 'activity' === $kind ) {
		return $es ? 'Cancelación gratuita hasta 48 horas antes' : 'Free cancellation up to 48 hours before';
	}
	if ( 'tour' === $kind ) {
		return $es ? 'Cancela con más de 15 días: reembolso menos un 10 %' : 'Cancel 15+ days before: refund minus 10%';
	}
	return '';
}

/**
 * Icono de trazo (estilo Phosphor Light de la web): los que hay en maros-connector y unos pocos
 * propios.
 */
function mis_tour_icon( string $name, int $size = 22 ): string {
	$files = array( 'pin' => 'phosphor/map-pin', 'car' => 'phosphor/car', 'calendar' => 'phosphor/calendar-check', 'shield' => 'phosphor/shield-check', 'guide' => 'phosphor/user-check' );
	if ( isset( $files[ $name ] ) && function_exists( 'maros_icon_svg' ) ) {
		$svg = maros_icon_svg( $files[ $name ], $size );
		if ( '' !== $svg ) {
			return $svg;
		}
	}
	$paths = array(
		'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
		'hourglass' => '<path d="M7 3h10M7 21h10M8 3v3.5a4 4 0 0 0 8 0V3M8 21v-3.5a4 4 0 0 1 8 0V21"/>',
		'users'     => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6.5 6.5-6.5s6.5 2.9 6.5 6.5"/><path d="M15.5 4.8a3.5 3.5 0 0 1 0 6.4M18 13.9c2 .9 3.5 3.2 3.5 6.1"/>',
		'globe'     => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.4 2.5 3.6 5.5 3.6 9s-1.2 6.5-3.6 9c-2.4-2.5-3.6-5.5-3.6-9S9.6 5.5 12 3z"/>',
		'check'     => '<path d="M4.5 12.5l4.5 4.5 10.5-10.5"/>',
		'x'         => '<path d="M6 6l12 12M18 6L6 18"/>',
		'plus'      => '<path d="M12 5v14M5 12h14"/>',
		'pin'       => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
		'car'       => '<path d="M3 13l2-6h14l2 6v5h-3v-2H6v2H3z"/><circle cx="7.5" cy="13.5" r="1"/><circle cx="16.5" cy="13.5" r="1"/>',
		'shield'    => '<path d="M12 3l8 3v6c0 4.5-3.4 8-8 9-4.6-1-8-4.5-8-9V6z"/><path d="M8.5 12l2.5 2.5 4.5-4.5"/>',
		'calendar'  => '<rect x="3.5" y="5" width="17" height="15.5" rx="1"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/>',
		'guide'     => '<circle cx="10" cy="8" r="4"/><path d="M3 20c0-3.9 3.1-7 7-7 1.4 0 2.7.4 3.8 1.1M15 18l2 2 4-4"/>',
		'star'      => '<path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8L3.5 9.7l5.9-.9z" fill="currentColor" stroke="none"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return '<svg class="mis-icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/** Nota media y número de opiniones (Site Reviews), o null si no tiene. */
function mis_tour_rating( int $post_id ): ?array {
	if ( ! function_exists( 'glsr_get_reviews' ) ) {
		return null;
	}
	$reviews = glsr_get_reviews( array( 'assigned_posts' => $post_id, 'per_page' => 200, 'status' => 'approved' ) );
	$ratings = array();
	foreach ( $reviews as $review ) {
		$ratings[] = (int) $review->rating;
	}
	if ( ! $ratings ) {
		return null;
	}
	return array( 'average' => array_sum( $ratings ) / count( $ratings ), 'count' => max( count( $ratings ), (int) ( $reviews->total ?? 0 ) ) );
}

function mis_tour_rating_html( int $post_id, bool $es, string $class = 'mis-tour-rating' ): string {
	$r = mis_tour_rating( $post_id );
	if ( ! $r ) {
		return '';
	}
	return sprintf(
		'<a class="%s" href="#reviews">%s<strong>%s</strong> <span>%s</span></a>',
		esc_attr( $class ),
		mis_tour_icon( 'star', 16 ),
		esc_html( number_format_i18n( $r['average'], 1 ) ),
		esc_html( sprintf( $es ? _n( '%d opinión', '%d opiniones', $r['count'], 'mis-contenido' ) : _n( '%d review', '%d reviews', $r['count'], 'mis-contenido' ), $r['count'] ) )
	);
}

/** Datos rápidos: [icono, título, detalle]. */
function mis_tour_facts( int $post_id, bool $es ): array {
	$kind  = mis_tour_kind( $post_id );
	$get   = static fn( string $k ) => trim( (string) get_post_meta( $post_id, $k, true ) );
	$facts = array();
	if ( $get( 'tour_duration' ) ) {
		$facts[] = array( 'hourglass', $es ? 'Duración' : 'Duration', $get( 'tour_duration' ) );
	}
	if ( $get( 'tour_start_time' ) ) {
		$facts[] = array( 'clock', $es ? 'Salida' : 'Starting time', $get( 'tour_start_time' ) );
	}
	$group = $get( 'tour_group_size' );
	if ( get_post_meta( $post_id, 'tour_private', true ) ) {
		$facts[] = array( 'users', $es ? 'Privado' : 'Private', $group ? $group : ( $es ? 'Solo tu grupo' : 'Just your group' ) );
	} elseif ( $group ) {
		$facts[] = array( 'users', $es ? 'Grupo reducido' : 'Small group', $group );
	}
	if ( $get( 'tour_languages' ) ) {
		$facts[] = array( 'globe', $es ? 'Guía en' : 'Live guide in', $get( 'tour_languages' ) );
	}
	$pickup = $get( 'tour_pickup' );
	$meet   = $get( 'tour_meeting_point' );
	if ( $meet ) {
		$facts[] = array( 'pin', $es ? 'Punto de encuentro' : 'Meeting point', $meet );
	} elseif ( $pickup ) {
		$facts[] = array( 'car', $es ? 'Recogida' : 'Pick-up', $pickup );
	}
	$cancel = mis_tour_cancel_short( $kind, $es );
	if ( $cancel ) {
		$facts[] = array( 'shield', $es ? 'Cancelación' : 'Cancellation', $cancel );
	}
	return $facts;
}

/** ¿Se reserva en FareHarbor? */
function mis_tour_fareharbor( int $post_id ): string {
	$item = (string) get_post_meta( $post_id, 'tour_fareharbor_item', true );
	return ( $item && 'formulario' !== get_post_meta( $post_id, 'tour_booking_mode', true ) ) ? $item : '';
}

function mis_tour_list( array $items, string $icon, string $class ): string {
	if ( ! $items ) {
		return '';
	}
	$out = '<ul class="mis-tour-list ' . esc_attr( $class ) . '">';
	foreach ( $items as $item ) {
		$out .= '<li>' . mis_tour_icon( $icon, 20 ) . '<span>' . esc_html( $item ) . '</span></li>';
	}
	return $out . '</ul>';
}

function mis_tour_section( string $id, string $title, string $body ): string {
	if ( '' === trim( $body ) ) {
		return '';
	}
	return '<section class="mis-tour-sec" id="' . esc_attr( $id ) . '"><h2 class="wp-block-heading has-large-font-size">' . esc_html( $title ) . '</h2>' . $body . '</section>';
}

add_shortcode(
	'mis_tour_head',
	static function () {
		$post_id = (int) get_the_ID();
		if ( 'mis_tour' !== get_post_type( $post_id ) ) {
			return '';
		}
		$es    = mis_tour_es( $post_id );
		$kind  = mis_tour_kind( $post_id );
		$index = get_page_by_path( 'experiences' );
		$index = $index && function_exists( 'pll_get_post' ) ? ( pll_get_post( $index->ID, $es ? 'es' : 'en' ) ?: $index->ID ) : ( $index ? $index->ID : 0 );
		$out   = '<nav class="mis-crumbs mis-muted" aria-label="' . esc_attr( $es ? 'Migas de pan' : 'Breadcrumb' ) . '">';
		$out  .= '<a href="' . esc_url( home_url( $es ? '/es/' : '/' ) ) . '">' . esc_html( $es ? 'Inicio' : 'Home' ) . '</a><span class="mis-crumbs__sep" aria-hidden="true">›</span>';
		if ( $index ) {
			$out .= '<a href="' . esc_url( get_permalink( $index ) ) . '">' . esc_html( get_the_title( $index ) ) . '</a><span class="mis-crumbs__sep" aria-hidden="true">›</span>';
		}
		$out .= '<span>' . esc_html( mis_tour_kind_label( $kind, $es ) ) . '</span></nav>';
		$out .= '<h1 class="wp-block-post-title mis-ficha-title mis-tour-title">' . esc_html( get_the_title( $post_id ) ) . '</h1>';
		$rating = mis_tour_rating_html( $post_id, $es );
		if ( $rating ) {
			$out .= '<p class="mis-tour-meta">' . $rating . '</p>';
		}
		$excerpt = (string) get_post_field( 'post_excerpt', $post_id, 'raw' );
		if ( $excerpt ) {
			$out .= '<p class="mis-tour-lead has-medium-font-size">' . esc_html( $excerpt ) . '</p>';
		}
		return $out;
	}
);

add_shortcode(
	'mis_tour_ficha',
	static function () {
		$post_id = (int) get_the_ID();
		if ( 'mis_tour' !== get_post_type( $post_id ) ) {
			return '';
		}
		$es   = mis_tour_es( $post_id );
		$kind = mis_tour_kind( $post_id );
		$out  = '';

		// Datos rápidos.
		$facts = mis_tour_facts( $post_id, $es );
		if ( $facts ) {
			$list = '<ul class="mis-keys__list mis-tour-facts">';
			foreach ( $facts as $f ) {
				$list .= '<li>' . mis_tour_icon( $f[0], 26 ) . '<div><strong>' . esc_html( $f[1] ) . '</strong><span>' . esc_html( $f[2] ) . '</span></div></li>';
			}
			$list .= '</ul>';
			$out  .= mis_tour_section( 'about', 'multiday' === $kind ? ( $es ? 'Sobre este viaje' : 'About this trip' ) : ( $es ? 'Sobre esta experiencia' : 'About this experience' ), $list );
		}

		$out .= mis_tour_section( 'highlights', $es ? 'Lo más destacado' : 'Highlights', mis_tour_list( mis_tour_lines( $post_id, 'tour_highlights' ), 'check', 'mis-tour-list--highlights' ) );

		// Descripción (el contenido de la entrada).
		$content = (string) get_post_field( 'post_content', $post_id, 'raw' );
		if ( '' !== trim( wp_strip_all_tags( $content ) ) ) {
			$html = apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$out .= mis_tour_section( 'description', $es ? 'Descripción' : 'Full description', '<div class="mis-tour-desc">' . $html . '</div>' );
		}

		// Programa.
		$steps = mis_tour_lines( $post_id, 'tour_itinerary' );
		if ( $steps ) {
			$list = '<ol class="mis-tour-steps">';
			foreach ( $steps as $step ) {
				$parts = array_map( 'trim', explode( '|', $step, 2 ) );
				$time  = 2 === count( $parts ) ? $parts[0] : '';
				$text  = 2 === count( $parts ) ? $parts[1] : $parts[0];
				$list .= '<li>' . ( $time ? '<strong>' . esc_html( $time ) . '</strong>' : '' ) . '<span>' . esc_html( $text ) . '</span></li>';
			}
			$list .= '</ol>';
			$note  = (string) get_post_meta( $post_id, 'tour_itinerary_note', true );
			if ( $note ) {
				$list .= '<p class="mis-muted mis-tour-note">' . esc_html( $note ) . '</p>';
			}
			$out .= mis_tour_section( 'itinerary', $es ? 'Programa' : 'Itinerary', $list );
		}

		// Qué incluye, qué no y extras.
		$inc = mis_tour_list( mis_tour_lines( $post_id, 'tour_includes' ), 'check', 'mis-tour-list--yes' );
		$exc = mis_tour_list( mis_tour_lines( $post_id, 'tour_excludes' ), 'x', 'mis-tour-list--no' );
		$ext = mis_tour_list( mis_tour_lines( $post_id, 'tour_extras' ), 'plus', 'mis-tour-list--extra' );
		if ( $inc || $exc || $ext ) {
			$body = '<div class="mis-tour-incl">';
			$body .= $inc ? '<div><h3>' . esc_html( $es ? 'Incluye' : 'Included' ) . '</h3>' . $inc . '</div>' : '';
			$body .= $exc ? '<div><h3>' . esc_html( $es ? 'No incluye' : 'Not included' ) . '</h3>' . $exc . '</div>' : '';
			$body .= '</div>';
			$body .= $ext ? '<div class="mis-tour-extras"><h3>' . esc_html( $es ? 'Extras opcionales' : 'Optional extras' ) . '</h3>' . $ext . '</div>' : '';
			$out  .= mis_tour_section( 'included', $es ? 'Qué incluye' : 'What’s included', $body );
		}

		// Punto de encuentro o recogida.
		$meet   = trim( (string) get_post_meta( $post_id, 'tour_meeting_point', true ) );
		$pickup = trim( (string) get_post_meta( $post_id, 'tour_pickup', true ) );
		if ( $meet ) {
			$maps = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $meet );
			$body = '<div class="mis-tour-meet">' . mis_tour_icon( 'pin', 26 ) . '<div><p>' . esc_html( $meet ) . '</p>';
			if ( $pickup ) {
				$body .= '<p class="mis-muted">' . esc_html( ( $es ? 'Recogida: ' : 'Pick-up: ' ) . $pickup ) . '</p>';
			}
			$body .= '<p><a class="mis-link-action" href="' . esc_url( $maps ) . '" target="_blank" rel="noopener">' . esc_html( $es ? 'Abrir en Google Maps' : 'Open in Google Maps' ) . '</a></p></div></div>';
			$out  .= mis_tour_section( 'meeting', $es ? 'Punto de encuentro' : 'Meeting point', $body );
		} elseif ( $pickup ) {
			$body = '<div class="mis-tour-meet">' . mis_tour_icon( 'car', 26 ) . '<div><p>' . esc_html( $pickup ) . '</p></div></div>';
			$out .= mis_tour_section( 'meeting', $es ? 'Recogida' : 'Pick-up', $body );
		}

		// Información importante.
		$groups = array(
			array( 'tour_bring', $es ? 'Qué llevar' : 'What to bring', 'check' ),
			array( 'tour_not_allowed', $es ? 'No se permite' : 'Not allowed', 'x' ),
			array( 'tour_know', $es ? 'Bueno saberlo' : 'Know before you go', 'check' ),
		);
		$body = '';
		foreach ( $groups as $g ) {
			$items = mis_tour_lines( $post_id, $g[0] );
			if ( $items ) {
				$body .= '<div><h3>' . esc_html( $g[1] ) . '</h3>' . mis_tour_list( $items, $g[2], 'mis-tour-list--info' ) . '</div>';
			}
		}
		$out .= mis_tour_section( 'important', $es ? 'Información importante' : 'Important information', $body ? '<div class="mis-tour-info">' . $body . '</div>' : '' );

		// Cancelación.
		$cancel = (string) get_post_meta( $post_id, 'tour_cancellation', true );
		$out   .= mis_tour_section( 'cancellation', $es ? 'Cancelación' : 'Cancellation policy', $cancel ? '<p>' . esc_html( $cancel ) . '</p>' : '' );

		return $out;
	}
);

/** URL del botón: FareHarbor (Lightframe) o el contacto con el nombre de la experiencia. */
function mis_tour_book_url( int $post_id ): string {
	$item = mis_tour_fareharbor( $post_id );
	if ( $item ) {
		return 'https://fareharbor.com/embeds/book/marbella-in-style/items/' . rawurlencode( $item ) . '/?full-items=yes&flow=no';
	}
	// El título sin pasar por los filtros de get_the_title(), que convierten «&» en «&#038;».
	return home_url( mis_tour_es( $post_id ) ? '/es/contacto/' : '/contact/' ) . '?tour=' . rawurlencode( (string) get_post_field( 'post_title', $post_id, 'raw' ) );
}

function mis_tour_price_html( int $post_id, bool $es ): string {
	$price = (float) get_post_meta( $post_id, 'tour_price_from', true );
	if ( $price ) {
		return '<p class="mis-tour-price"><small>' . esc_html( $es ? 'Desde' : 'From' ) . '</small> <strong>' . esc_html( number_format_i18n( $price ) . ' €' ) . '</strong></p>';
	}
	if ( mis_tour_fareharbor( $post_id ) ) {
		return '<p class="mis-tour-price"><strong>' . esc_html( $es ? 'Reserva online' : 'Book online' ) . '</strong></p>';
	}
	return '<p class="mis-tour-price"><strong>' . esc_html( $es ? 'Precio a consultar' : 'Price on request' ) . '</strong></p>';
}

add_shortcode(
	'mis_tour_book',
	static function () {
		$post_id = (int) get_the_ID();
		if ( 'mis_tour' !== get_post_type( $post_id ) ) {
			return '';
		}
		$es    = mis_tour_es( $post_id );
		$kind  = mis_tour_kind( $post_id );
		$fh    = mis_tour_fareharbor( $post_id );
		$label = $fh ? ( $es ? 'Ver disponibilidad' : 'Check availability' ) : ( $es ? 'Solicitar' : 'Request this experience' );
		$out   = '<div class="mis-tour-book">' . mis_tour_price_html( $post_id, $es );
		$out  .= mis_tour_rating_html( $post_id, $es, 'mis-tour-rating mis-tour-rating--small' );
		$out  .= '<a class="wp-block-button__link wp-element-button mis-tour-book__cta" href="' . esc_url( mis_tour_book_url( $post_id ) ) . '">' . esc_html( $label ) . '</a>';
		$points = array();
		$cancel = mis_tour_cancel_short( $kind, $es );
		if ( $cancel ) {
			$points[] = array( 'shield', $cancel );
		}
		if ( get_post_meta( $post_id, 'tour_private', true ) ) {
			$points[] = array( 'users', $es ? 'Privado: solo tu grupo' : 'Private: just your group' );
		}
		$pickup = (string) get_post_meta( $post_id, 'tour_pickup', true );
		if ( $pickup && ! get_post_meta( $post_id, 'tour_meeting_point', true ) ) {
			$points[] = array( 'car', $pickup );
		}
		if ( ! $fh ) {
			$points[] = array( 'calendar', $es ? 'Te respondemos con precio y disponibilidad' : 'We reply with price and availability' );
		}
		if ( $points ) {
			$out .= '<ul class="mis-tour-book__points">';
			foreach ( $points as $p ) {
				$out .= '<li>' . mis_tour_icon( $p[0], 20 ) . '<span>' . esc_html( $p[1] ) . '</span></li>';
			}
			$out .= '</ul>';
		}
		return $out . '</div>';
	}
);

add_shortcode(
	'mis_tour_sticky',
	static function () {
		$post_id = (int) get_the_ID();
		if ( 'mis_tour' !== get_post_type( $post_id ) ) {
			return '';
		}
		$es    = mis_tour_es( $post_id );
		$fh    = mis_tour_fareharbor( $post_id );
		$label = $fh ? ( $es ? 'Disponibilidad' : 'Availability' ) : ( $es ? 'Solicitar' : 'Request' );
		$dur   = (string) get_post_meta( $post_id, 'tour_duration', true );
		$price = (float) get_post_meta( $post_id, 'tour_price_from', true );
		$line  = $price ? sprintf( $es ? 'Desde <strong>%s €</strong>' : 'From <strong>€%s</strong>', esc_html( number_format_i18n( $price ) ) ) : '<strong>' . esc_html( $dur ) . '</strong>';
		$sub   = 'activity' === mis_tour_kind( $post_id ) ? ( $es ? 'Cancelación gratuita hasta 48 h' : 'Free cancellation up to 48 h' ) : ( 'tour' === mis_tour_kind( $post_id ) ? ( $es ? 'Cancelación flexible' : 'Flexible cancellation' ) : '' );
		$sub   = $price && $dur ? trim( $dur . ' · ' . $sub, ' ·' ) : $sub;
		return '<div class="mis-sticky-cta mis-tour-sticky"><p class="mis-price-line">' . $line . ( $sub ? '<br><small>' . esc_html( $sub ) . '</small>' : '' ) . '</p><a class="wp-element-button" href="' . esc_url( mis_tour_book_url( $post_id ) ) . '">' . esc_html( $label ) . '</a></div>';
	}
);

add_shortcode(
	'mis_tour_related',
	static function () {
		$post_id = (int) get_the_ID();
		if ( 'mis_tour' !== get_post_type( $post_id ) ) {
			return '';
		}
		$es    = mis_tour_es( $post_id );
		$kind  = mis_tour_kind( $post_id );
		$ids   = get_posts(
			array(
				'post_type'        => 'mis_tour',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'post__not_in'     => array( $post_id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
				'lang'             => $es ? 'es' : 'en',
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);
		// Primero las del mismo tipo (tours con tours, actividades con actividades).
		usort( $ids, static fn( $a, $b ) => (int) ( mis_tour_kind( $b ) === $kind ) <=> (int) ( mis_tour_kind( $a ) === $kind ) );
		$ids = array_slice( $ids, 0, 4 );
		if ( ! $ids ) {
			return '';
		}
		$out = '<section class="mis-tour-related" id="related"><h2 class="wp-block-heading has-large-font-size">' . esc_html( $es ? 'También te puede gustar' : 'You might also like' ) . '</h2><div class="mis-tour-related__grid">';
		foreach ( $ids as $id ) {
			$url   = get_permalink( $id );
			$out  .= '<div class="mis-card">';
			$thumb = get_post_thumbnail_id( $id );
			if ( $thumb ) {
				$out .= '<a class="mis-card__foto" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . wp_get_attachment_image( $thumb, 'medium_large', false, array( 'alt' => '', 'loading' => 'lazy' ) ) . '</a>';
			}
			$out .= '<p class="mis-eyebrow">' . esc_html( mis_tour_kind_label( mis_tour_kind( $id ), $es ) ) . '</p>';
			$out .= '<h3 class="wp-block-post-title"><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $id ) ) . '</a></h3>';
			$dur  = (string) get_post_meta( $id, 'tour_duration', true );
			$out .= $dur ? '<p class="mis-muted">' . esc_html( $dur ) . '</p>' : '';
			$out .= '</div>';
		}
		return $out . '</div></section>';
	}
);
