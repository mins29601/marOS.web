<?php
/**
 * Caja de reserva de la ficha (spec 003 US3b; spec 006: «Caja de reserva» en escritorio y
 * «Pantalla de reserva» del flujo C en móvil, T28).
 *
 * - [maros_booking]: fechas (calendario con la copia local de disponibilidad), huéspedes y el
 *   presupuesto en vivo con noches, desglose, total y depósito.
 * - GET /wp-json/maros/v1/quote: proxy del presupuesto de marOS (spec 044 de marOS,
 *   GET /v1/catalog/properties/{id}/quote, scope «catalog:read»). La clave no sale del servidor
 *   (principio III); se valida todo, se guarda en caché por la huella del calendario (FR-009 de la
 *   spec 044) y se limita por visitante.
 *
 * El botón sigue llevando al motor de Lodgify (`stay.`) con las fechas y los huéspedes (D15) hasta
 * que marOS tenga la ruta para crear reservas desde la web (spec 026 de marOS).
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

final class Maros_Booking {

	/** Presupuestos por visitante y minuto (spec 003 US3b, escenario 3). */
	private const PER_MINUTE = 20;

	/** Igual que la API de marOS (MAX_QUOTE_NIGHTS). */
	private const MAX_NIGHTS = 90;

	/** Caché de un presupuesto mientras no cambie el calendario de la casa. */
	private const TTL = 15 * MINUTE_IN_SECONDS;

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_shortcode( 'maros_booking', array( __CLASS__, 'shortcode' ) );
	}

	public static function routes(): void {
		register_rest_route(
			'maros/v1',
			'/quote',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_quote' ),
				// Público: lo pide la ficha a cualquier visitante. Límite por visitante en rest_quote().
				'permission_callback' => '__return_true',
				'args'                => array(
					'post'      => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ),
					'arrival'   => array( 'required' => true, 'type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
					'departure' => array( 'required' => true, 'type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
					'adults'    => array( 'required' => true, 'type' => 'integer', 'minimum' => 1, 'maximum' => 40 ),
					'children'  => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 40, 'default' => 0 ),
					'infants'   => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 20, 'default' => 0 ),
				),
			)
		);
	}

	/* ------------------------------------------------------------------ Proxy del presupuesto */

	public static function rest_quote( WP_REST_Request $r ): WP_REST_Response {
		$post_id = (int) $r['post'];
		$post    = get_post( $post_id );
		$maros   = $post && 'mis_property' === $post->post_type && 'publish' === $post->post_status ? (string) get_post_meta( $post_id, 'maros_id', true ) : '';
		$es      = maros_is_es( $post_id );
		if ( '' === $maros || get_post_meta( $post_id, 'maros_withdrawn', true ) ) {
			return self::reply( array( 'error' => 'not_found' ), 404 );
		}

		$arrival   = (string) $r['arrival'];
		$departure = (string) $r['departure'];
		$nights    = self::nights( $arrival, $departure );
		if ( $nights < 1 || $nights > self::MAX_NIGHTS || $arrival < wp_date( 'Y-m-d' ) ) {
			return self::reply( array( 'error' => 'bad_dates', 'message' => self::t( $es, 'Choose a check-in date from today and a check-out date after it.', 'Elige una llegada a partir de hoy y una salida posterior.' ) ), 400 );
		}
		$guests = array(
			'adults'   => (int) $r['adults'],
			'children' => (int) $r['children'],
			'infants'  => (int) $r['infants'],
		);

		if ( ! self::within_limit() ) {
			return self::reply( array( 'error' => 'rate_limited', 'message' => self::t( $es, 'Too many requests. Please wait a minute and try again.', 'Demasiadas consultas. Espera un minuto y vuelve a intentarlo.' ) ), 429 );
		}

		$quote = self::quote( $post_id, $maros, $arrival, $departure, $guests );
		if ( is_wp_error( $quote ) ) {
			Maros_Log::add( 'error', 'Presupuesto de ' . $maros . ': ' . $quote->get_error_message() );
			return self::reply(
				array(
					'error'   => 'unavailable',
					'message' => self::t( $es, 'We can’t show the price right now. Try again in a moment, or talk to our team.', 'Ahora no podemos darte el precio. Vuelve a intentarlo en un momento o habla con el equipo.' ),
				),
				502
			);
		}
		return self::reply( self::present( $quote, $post_id, $es, $guests ) );
	}

	/**
	 * Presupuesto de marOS para una casa, con caché por la huella del calendario. Si la casa tiene
	 * varios tipos de alojamiento, el más barato que esté libre.
	 *
	 * @return array|WP_Error
	 */
	private static function quote( int $post_id, string $maros, string $arrival, string $departure, array $guests ) {
		$etag = (string) get_post_meta( $post_id, 'maros_calendar_etag', true );
		$key  = 'maros_q_' . md5( wp_json_encode( array( $maros, $arrival, $departure, $guests, $etag ) ) );
		$hit  = get_transient( $key );
		if ( is_array( $hit ) ) {
			return $hit;
		}

		$rooms = json_decode( (string) get_post_meta( $post_id, 'maros_room_types', true ), true );
		$ids   = is_array( $rooms ) ? array_values( array_filter( array_map( static fn( $t ) => is_array( $t ) ? (string) ( $t['id'] ?? '' ) : '', $rooms ) ) ) : array();
		// Con un solo tipo, marOS usa ese sin que se lo digamos.
		$ids = count( $ids ) > 1 ? array_slice( $ids, 0, 4 ) : array( '' );

		$best = null;
		foreach ( $ids as $room ) {
			$q = Maros_Client::get_quote( $maros, $arrival, $departure, $guests, $room );
			if ( is_wp_error( $q ) ) {
				return $q;
			}
			if ( ! is_array( $q ) || ! array_key_exists( 'available', $q ) ) {
				return new WP_Error( 'maros_contract', 'El presupuesto de marOS no tiene el formato esperado.' );
			}
			if ( ! $best || ( ! empty( $q['available'] ) && ( empty( $best['available'] ) || (float) $q['total'] < (float) $best['total'] ) ) ) {
				$best = $q;
			}
		}
		unset( $best['_etag'] );
		set_transient( $key, $best, self::TTL );
		return $best;
	}

	/** Lo que necesita el navegador, ya traducido y con los importes escritos. */
	private static function present( array $q, int $post_id, bool $es, array $guests ): array {
		$nights   = (int) ( $q['nights'] ?? 0 );
		$currency = (string) ( $q['currency'] ?? get_post_meta( $post_id, 'maros_currency', true ) ?: 'EUR' );
		$out      = array(
			'available' => ! empty( $q['available'] ),
			'arrival'   => (string) ( $q['arrival'] ?? '' ),
			'departure' => (string) ( $q['departure'] ?? '' ),
			'nights'    => $nights,
			'nights_label' => sprintf( $es ? _n( '%d noche', '%d noches', $nights ) : ( 1 === $nights ? '%d night' : '%d nights' ), $nights ),
			'book_url'  => self::stay_url( $post_id, (string) ( $q['arrival'] ?? '' ), (string) ( $q['departure'] ?? '' ), $guests ),
		);
		if ( ! $out['available'] ) {
			$out['reasons'] = array_values( array_filter( array_map( static fn( $r ) => is_array( $r ) ? self::reason( $r, $es ) : '', (array) ( $q['reasons'] ?? array() ) ) ) );
			if ( ! $out['reasons'] ) {
				$out['reasons'] = array( self::t( $es, 'These dates aren’t available.', 'Estas fechas no están disponibles.' ) );
			}
			return $out;
		}
		$lines = array();
		foreach ( (array) ( $q['lines'] ?? array() ) as $l ) {
			if ( ! is_array( $l ) || ! isset( $l['amount'] ) || ! is_numeric( $l['amount'] ) ) {
				continue;
			}
			$kind     = (string) ( $l['kind'] ?? '' );
			$included = ! empty( $l['included'] );
			$lines[]  = array(
				'kind'     => $kind,
				'label'    => self::line_label( $kind, (string) ( $l['label'] ?? '' ), $nights, $included, $es ),
				'amount'   => self::money( ( 'discount' === $kind ? -1 : 1 ) * abs( (float) $l['amount'] ), $currency, $es ),
				'included' => $included,
			);
		}
		$out['lines'] = $lines;
		$out['total'] = self::money( (float) ( $q['total'] ?? 0 ), $currency, $es );
		$out['short'] = $out['total'] . ' · ' . $out['nights_label'];
		$deposit      = isset( $q['deposit'] ) && is_numeric( $q['deposit'] ) ? (float) $q['deposit'] : 0.0;
		if ( $deposit > 0 ) {
			$out['deposit'] = sprintf( self::t( $es, 'Refundable security deposit: %s (not included in the total)', 'Fianza reembolsable: %s (no incluida en el total)' ), self::money( $deposit, $currency, $es ) );
		}
		$out['note'] = empty( $q['exact'] )
			? self::t( $es, 'Estimated price. We confirm the final price and availability with you before you pay.', 'Precio orientativo. Te confirmamos el precio final y la disponibilidad antes de pagar.' )
			: self::t( $es, 'We confirm availability with you before you pay.', 'Te confirmamos la disponibilidad antes de pagar.' );
		return $out;
	}

	private static function line_label( string $kind, string $label, int $nights, bool $included, bool $es ): string {
		if ( 'accommodation' === $kind ) {
			return $es ? sprintf( _n( 'Alojamiento (%d noche)', 'Alojamiento (%d noches)', $nights ), $nights ) : sprintf( 1 === $nights ? 'Stay (%d night)' : 'Stay (%d nights)', $nights );
		}
		if ( 'tax' === $kind && preg_match( '/(\d+(?:[.,]\d+)?)\s*%/', $label, $m ) ) {
			$rate = str_replace( '.', $es ? ',' : '.', $m[1] );
			if ( $included ) {
				return $es ? sprintf( 'IVA incluido (%s %%)', $rate ) : sprintf( 'VAT included (%s%%)', $rate );
			}
			return $es ? sprintf( 'IVA (%s %%)', $rate ) : sprintf( 'VAT (%s%%)', $rate );
		}
		// Los gastos llegan con el nombre que tienen en Lodgify, que suele estar en español.
		if ( ! $es ) {
			$en = array(
				'limpieza'             => 'Cleaning',
				'limpieza final'       => 'Final cleaning',
				'servicio'             => 'Service',
				'gastos de servicio'   => 'Service fee',
				'climatización piscina' => 'Pool heating',
				'calefacción piscina'  => 'Pool heating',
				'tasa turística'       => 'Tourist tax',
				'mascotas'             => 'Pets',
				'descuento'            => 'Discount',
			);
			$label = $en[ mb_strtolower( trim( $label ) ) ] ?? $label;
		}
		return '' !== $label ? $label : ( 'discount' === $kind ? self::t( $es, 'Discount', 'Descuento' ) : self::t( $es, 'Fee', 'Gasto' ) );
	}

	/** Motivo de marOS (spec 044, FR-007) en palabras del visitante. */
	private static function reason( array $r, bool $es ): string {
		$n     = isset( $r['required'] ) ? (int) $r['required'] : 0;
		$dates = array_map( static fn( $d ) => self::day( (string) $d, $es ), array_slice( (array) ( $r['dates'] ?? array() ), 0, 3 ) );
		switch ( (string) ( $r['code'] ?? '' ) ) {
			case 'not_available':
				return self::t( $es, 'Some of these nights are already booked.', 'Algunas de estas noches ya están reservadas.' );
			case 'check_in_not_allowed':
				return sprintf( self::t( $es, 'Check-in isn’t possible on %s.', 'No se puede llegar el %s.' ), $dates[0] ?? '' );
			case 'check_out_not_allowed':
				return sprintf( self::t( $es, 'Check-out isn’t possible on %s.', 'No se puede salir el %s.' ), $dates[0] ?? '' );
			case 'min_stay':
				return sprintf( $es ? 'La estancia mínima para esa llegada es de %d noches.' : 'The minimum stay for that check-in date is %d nights.', $n );
			case 'max_stay':
				return sprintf( $es ? 'La estancia máxima es de %d noches.' : 'The maximum stay is %d nights.', $n );
			case 'too_many_guests':
				return sprintf( $es ? 'La casa admite hasta %d huéspedes (sin contar bebés).' : 'The home sleeps up to %d guests (infants not included).', $n );
			case 'advance_notice':
				return sprintf( $es ? 'Hay que reservar con %d días de antelación como mínimo.' : 'Bookings need at least %d days’ notice.', $n );
			case 'beyond_calendar':
				return self::t( $es, 'We can’t take bookings that far ahead yet.', 'Todavía no aceptamos reservas para esas fechas.' );
			case 'no_rate':
				return self::t( $es, 'We don’t have a price for these dates yet. Talk to our team and we’ll help.', 'Aún no tenemos precio para estas fechas. Habla con el equipo y te ayudamos.' );
		}
		return '';
	}

	/* ------------------------------------------------------------------ Caja de reserva */

	public static function shortcode(): string {
		$post_id = (int) get_the_ID();
		if ( ! $post_id || 'mis_property' !== get_post_type( $post_id ) || ! get_post_meta( $post_id, 'maros_id', true ) ) {
			return '';
		}
		$es     = maros_is_es( $post_id );
		$max    = (int) get_post_meta( $post_id, 'maros_max_guests', true );
		$params = class_exists( 'Maros_Search' ) ? Maros_Search::params() : array();
		$photo  = get_the_post_thumbnail_url( $post_id, 'medium' );
		$name   = get_the_title( $post_id );
		$config = array(
			'post'     => $post_id,
			'lang'     => $es ? 'es' : 'en',
			'endpoint' => rest_url( 'maros/v1/quote' ),
			'stay'     => self::stay_url( $post_id, '', '', array() ),
			'max'      => $max > 0 ? $max : 30,
			'arrival'  => (string) ( $params['checkin'] ?? '' ),
			'departure' => (string) ( $params['checkout'] ?? '' ),
			'adults'   => max( 1, min( $max > 0 ? $max : 30, (int) ( $params['guests'] ?? 0 ) ?: 2 ) ),
			'calendar' => self::calendar( $post_id ),
			'i18n'     => self::strings( $es ),
		);

		$stepper = static function ( string $key, string $label, string $hint ) use ( $es ): string {
			return '<div class="mis-resv__guest"><div><span class="mis-resv__guest-label">' . esc_html( $label ) . '</span>' . ( '' !== $hint ? '<small>' . esc_html( $hint ) . '</small>' : '' ) . '</div>'
				. '<div class="mis-resv__stepper"><button type="button" data-step="' . esc_attr( $key ) . '" data-by="-1" aria-label="' . esc_attr( ( $es ? 'Menos: ' : 'Fewer: ' ) . $label ) . '">−</button>'
				. '<output data-count="' . esc_attr( $key ) . '" aria-live="polite">0</output>'
				. '<button type="button" data-step="' . esc_attr( $key ) . '" data-by="1" aria-label="' . esc_attr( ( $es ? 'Más: ' : 'More: ' ) . $label ) . '">+</button></div></div>';
		};
		$close = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>';

		$rating = self::rating( $post_id, $es );
		$area   = (string) get_post_meta( $post_id, 'maros_area', true );
		$zones  = get_the_terms( $post_id, 'zona' );
		if ( $zones && ! is_wp_error( $zones ) ) {
			$area = $zones[0]->name;
		}
		$talk = shortcode_exists( 'mis_persona' ) ? do_shortcode( '[mis_persona para="guests" estilo="compacto" en="Prefer to talk it through?" es="¿Prefieres hablarlo?"]' ) : '';
		if ( '' === $talk ) {
			$talk = '<p class="mis-resv__talk">' . esc_html( $es ? '¿Prefieres hablarlo?' : 'Prefer to talk it through?' ) . ' ' . do_shortcode( '[mis_link to="contact" en="Talk to our team" es="Habla con el equipo"]' ) . '</p>';
		}

		// Maqueta «Mockups web Marbella in Style»: E3 (caja de escritorio) y M4 C1–C3 (pantalla del móvil).
		$html  = '<div class="mis-resv" data-mis-resv>';
		// Pantalla de reserva del móvil: barra «✕ Reservar» y cabecera de la casa.
		$html .= '<div class="mis-resv__top"><button type="button" class="mis-resv__close" data-book-close aria-label="' . esc_attr( $es ? 'Cerrar' : 'Close' ) . '">' . $close . '</button><b>' . esc_html( $es ? 'Reservar' : 'Book' ) . '</b><span></span></div>';
		$html .= '<div class="mis-resv__house">' . ( $photo ? '<img src="' . esc_url( $photo ) . '" alt="" width="84" height="84" loading="lazy">' : '' )
			. '<div>' . ( '' !== $area ? '<small>' . esc_html( $area ) . '</small>' : '' ) . '<p class="mis-resv__name">' . esc_html( $name ) . '</p>' . $rating . '</div></div>';
		// Caja de escritorio: «Elige tus fechas» y la valoración.
		$html .= '<div class="mis-resv__title"><p>' . esc_html( $es ? 'Elige tus fechas' : 'Choose your dates' ) . '</p>' . $rating . '</div>';
		$html .= '<div class="mis-resv__dates" role="group" aria-label="' . esc_attr( $es ? 'Fechas' : 'Dates' ) . '">'
			. '<button type="button" class="mis-resv__date" data-pick="arrival"><small>' . esc_html( $es ? 'Llegada' : 'Check-in' ) . '</small><span data-show="arrival">' . esc_html( $es ? 'Añadir' : 'Add date' ) . '</span></button>'
			. '<button type="button" class="mis-resv__date" data-pick="departure"><small>' . esc_html( $es ? 'Salida' : 'Check-out' ) . '</small><span data-show="departure">' . esc_html( $es ? 'Añadir' : 'Add date' ) . '</span></button>'
			. '</div>';
		$html .= '<div class="mis-resv__cal" hidden></div>';
		$html .= '<details class="mis-resv__guests"><summary><small>' . esc_html( $es ? 'Huéspedes' : 'Guests' ) . '</small><span data-show="guests"></span></summary>'
			. $stepper( 'adults', $es ? 'Adultos' : 'Adults', $es ? '13 años o más' : 'Age 13+' )
			. $stepper( 'children', $es ? 'Niños' : 'Children', $es ? 'De 2 a 12 años' : 'Ages 2–12' )
			. $stepper( 'infants', $es ? 'Bebés' : 'Infants', $es ? 'Menos de 2 años' : 'Under 2' )
			. ( $max > 0 ? '<p class="mis-resv__cap">' . esc_html( sprintf( $es ? 'Hasta %d huéspedes, sin contar bebés.' : 'Up to %d guests, not counting infants.', $max ) ) . '</p>' : '' )
			. '</details>';
		$html .= '<div class="mis-resv__quote" aria-live="polite"><p class="mis-resv__empty">' . esc_html( $es ? 'Elige fechas para ver el desglose y el precio total.' : 'Choose your dates to see the breakdown and the total price.' ) . '</p></div>';
		// Pie: en el móvil, barra fija con el total, las fechas y «Reservar».
		$html .= '<div class="mis-resv__foot"><div class="mis-resv__sum" data-sum><span class="mis-resv__sum-none">' . esc_html( $es ? 'Sin fechas todavía' : 'No dates yet' ) . '</span></div>'
			. '<a class="wp-element-button mis-resv__cta" href="' . esc_url( $config['stay'] ) . '" data-book-cta>' . esc_html( $es ? 'Ver disponibilidad' : 'Check availability' ) . '</a></div>';
		$html .= '<div class="mis-resv__help">' . $talk . '</div>';
		$html .= '<script type="application/json" class="mis-resv__data">' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . '</script>';
		$html .= '</div>';

		$file = dirname( __DIR__ ) . '/assets/booking.js';
		wp_enqueue_script( 'maros-booking', plugins_url( 'assets/booking.js', __DIR__ ), array( 'maros-calendar' ), (string) filemtime( $file ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		return $html;
	}

	/**
	 * Disponibilidad de la copia local para pintar el calendario, de hoy al último día cargado.
	 * Un carácter por día (bits: 1 noche libre, 2 se puede llegar, 4 se puede salir) y la estancia
	 * mínima por tramos [día desde hoy, noches]. Con varios tipos de alojamiento, basta uno libre;
	 * el presupuesto de marOS es el que manda.
	 */
	private static function calendar( int $post_id ): array {
		global $wpdb;
		$maros = (string) get_post_meta( $post_id, 'maros_id', true );
		$today = wp_date( 'Y-m-d' );
		// phpcs:ignore WordPress.DB.PreparedSQL
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT date, MAX(available) AS free, MAX(available AND NOT closed_arrival) AS arrive, MAX(NOT closed_departure) AS leave_ok, MIN(CASE WHEN available AND NOT closed_arrival THEN min_stay END) AS min_stay FROM ' . Maros_Calendar::calendar_table() . ' WHERE property_id = %s AND date >= %s GROUP BY date ORDER BY date', $maros, $today ), ARRAY_A );
		if ( ! $rows ) {
			return array( 'start' => $today, 'days' => '', 'min' => array() );
		}
		$by = array();
		foreach ( $rows as $r ) {
			$by[ $r['date'] ] = $r;
		}
		$last = (string) end( $rows )['date'];
		$days = '';
		$min  = array();
		$prev = null;
		$i    = 0;
		for ( $ts = strtotime( $today . ' 12:00:00 UTC' ), $end = strtotime( $last . ' 12:00:00 UTC' ); $ts <= $end; $ts += DAY_IN_SECONDS, $i++ ) {
			$r     = $by[ gmdate( 'Y-m-d', $ts ) ] ?? null;
			$days .= (string) ( $r ? ( (int) $r['free'] | ( (int) $r['arrive'] << 1 ) | ( (int) $r['leave_ok'] << 2 ) ) : 0 );
			$m     = $r ? (int) $r['min_stay'] : 0;
			if ( $m !== $prev ) {
				$min[] = array( $i, $m );
				$prev  = $m;
			}
		}
		// El día siguiente al último cargado solo sirve de salida si no sabemos lo contrario.
		return array( 'start' => $today, 'days' => $days . '4', 'min' => $min );
	}

	private static function strings( bool $es ): array {
		return $es
			? array(
				'add'      => 'Elige fecha',
				'pickIn'   => 'Elige el día de llegada',
				'pickOut'  => 'Elige el día de salida',
				'minStay'  => 'Estancia mínima: %d noches',
				'clear'    => 'Borrar fechas',
				'prev'     => 'Mes anterior',
				'next'     => 'Mes siguiente',
				'loading'  => 'Calculando el precio…',
				'total'    => 'Total',
				'book'     => 'Reservar',
				'check'    => 'Ver disponibilidad',
				'none'     => 'Sin fechas todavía',
				'empty'    => 'Elige fechas para ver el desglose y el precio total.',
				'other'    => 'Cambiar fechas',
				'adult'    => array( '%d adulto', '%d adultos' ),
				'child'    => array( '%d niño', '%d niños' ),
				'infant'   => array( '%d bebé', '%d bebés' ),
				'weekdays' => array( 'L', 'M', 'X', 'J', 'V', 'S', 'D' ),
				'locale'   => 'es-ES',
			)
			: array(
				'add'      => 'Add date',
				'pickIn'   => 'Choose your check-in date',
				'pickOut'  => 'Choose your check-out date',
				'minStay'  => 'Minimum stay: %d nights',
				'clear'    => 'Clear dates',
				'prev'     => 'Previous month',
				'next'     => 'Next month',
				'loading'  => 'Working out the price…',
				'total'    => 'Total',
				'book'     => 'Book',
				'check'    => 'Check availability',
				'none'     => 'No dates yet',
				'empty'    => 'Choose your dates to see the breakdown and the total price.',
				'other'    => 'Change dates',
				'adult'    => array( '%d adult', '%d adults' ),
				'child'    => array( '%d child', '%d children' ),
				'infant'   => array( '%d infant', '%d infants' ),
				'weekdays' => array( 'M', 'T', 'W', 'T', 'F', 'S', 'S' ),
				'locale'   => 'en-GB',
			);
	}

	/* ------------------------------------------------------------------ Utilidades */

	/** «★ 4,92 (38)» con las opiniones publicadas de la casa (Site Reviews), o nada si no hay. */
	private static function rating( int $post_id, bool $es ): string {
		if ( ! function_exists( 'glsr_get_reviews' ) ) {
			return '';
		}
		$reviews = glsr_get_reviews( array( 'assigned_posts' => $post_id, 'per_page' => 100, 'status' => 'approved' ) );
		$stars   = array();
		foreach ( $reviews as $r ) {
			$stars[] = (int) $r->rating;
		}
		if ( ! $stars ) {
			return '';
		}
		$avg   = number_format( array_sum( $stars ) / count( $stars ), 2, $es ? ',' : '.', '' );
		$total = max( count( $stars ), (int) ( $reviews->total ?? 0 ) );
		return '<a class="mis-resv__rating" href="#reviews">★ ' . esc_html( $avg ) . ' <span>(' . (int) $total . ')</span></a>';
	}

	/** Motor de Lodgify (`stay.`) con fechas y huéspedes, como «Check availability» (D15). */
	public static function stay_url( int $post_id, string $arrival, string $departure, array $guests ): string {
		$lodgify = (int) get_post_meta( $post_id, 'maros_lodgify_id', true );
		if ( ! $lodgify ) {
			return MIS_STAY_URL;
		}
		$url  = trailingslashit( MIS_STAY_URL ) . ( maros_is_es( $post_id ) ? 'es' : 'en' ) . '/' . $lodgify;
		$args = array();
		if ( '' !== $arrival && '' !== $departure ) {
			$args = array( 'arrival' => $arrival, 'departure' => $departure );
		}
		if ( ! empty( $guests['adults'] ) ) {
			$args['adults'] = (int) $guests['adults'];
			if ( ! empty( $guests['children'] ) ) {
				$args['children'] = (int) $guests['children'];
			}
			if ( ! empty( $guests['infants'] ) ) {
				$args['infants'] = (int) $guests['infants'];
			}
		}
		return $args ? add_query_arg( $args, $url ) : $url;
	}

	private static function nights( string $a, string $b ): int {
		$valid = static fn( string $d ) => checkdate( (int) substr( $d, 5, 2 ), (int) substr( $d, 8, 2 ), (int) substr( $d, 0, 4 ) );
		if ( ! $valid( $a ) || ! $valid( $b ) ) {
			return 0;
		}
		return (int) round( ( strtotime( $b . ' 00:00:00 UTC' ) - strtotime( $a . ' 00:00:00 UTC' ) ) / DAY_IN_SECONDS );
	}

	/** Límite por visitante; la IP solo se usa con hash y durante un minuto. */
	private static function within_limit(): bool {
		$ip  = (string) ( $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$key = 'maros_ql_' . md5( wp_salt( 'nonce' ) . $ip . gmdate( 'YmdHi' ) );
		$n   = (int) get_transient( $key );
		if ( $n >= self::PER_MINUTE ) {
			return false;
		}
		set_transient( $key, $n + 1, 2 * MINUTE_IN_SECONDS );
		return true;
	}

	public static function money( float $amount, string $currency, bool $es ): string {
		$dec = abs( $amount - round( $amount ) ) >= 0.005 ? 2 : 0;
		$n   = number_format( abs( $amount ), $dec, $es ? ',' : '.', $es ? '.' : ',' );
		$s   = $amount < 0 ? '−' : '';
		if ( 'EUR' === $currency ) {
			return $es ? $s . $n . ' €' : $s . '€' . $n;
		}
		return $s . $n . ' ' . $currency;
	}

	private static function day( string $date, bool $es ): string {
		if ( ! preg_match( '/^\d{4}-(\d{2})-(\d{2})$/', $date, $m ) ) {
			return $date;
		}
		// Sin depender del idioma de WordPress, que en la API REST puede no ser el de la ficha.
		$months = $es
			? array( 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre' )
			: array( 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' );
		$month  = $months[ (int) $m[1] - 1 ] ?? '';
		return $es ? (int) $m[2] . ' de ' . $month : (int) $m[2] . ' ' . $month;
	}

	private static function t( bool $es, string $en, string $es_text ): string {
		return $es ? $es_text : $en;
	}

	private static function reply( array $data, int $status = 200 ): WP_REST_Response {
		$res = new WP_REST_Response( $data, $status );
		// Ni Cloudflare ni el navegador deben guardar un precio: puede cambiar con el calendario.
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}
}

Maros_Booking::init();
