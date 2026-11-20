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
 * - POST /wp-json/maros/v1/booking-request: solicitud de reserva (spec 026 de marOS, fase C,
 *   pantallas C4 y C5). Con fechas y precio, «Reservar» abre el formulario en la misma pantalla; la
 *   web vuelve a pedir el presupuesto, comprueba los datos y envía la solicitud a marOS con el total
 *   que vio el huésped. Sin la solicitud activa (filtro «maros_booking_requests», apagado en
 *   producción hasta que se apruebe), el botón sigue llevando al motor de Lodgify (`stay.`, D15).
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

	/** Solicitudes de reserva por visitante cada diez minutos (marOS admite 10 por minuto en total). */
	private const REQUESTS_PER_WINDOW = 5;

	/** Versión de las condiciones y la privacidad que acepta el huésped (marOS la guarda, FR-001). */
	private const TERMS_VERSION = 'web-2026-11';

	/** Franjas de llegada de marOS (ARRIVAL_SLOTS). */
	private const ARRIVAL_SLOTS = array( 'unknown', 'before_16', '16_20', 'after_20' );

	/**
	 * ¿«Reservar» abre la solicitud de reserva? Sí fuera de producción; en producción, cuando se
	 * apruebe (add_filter( 'maros_booking_requests', '__return_true' )).
	 */
	public static function requests_enabled(): bool {
		return (bool) apply_filters( 'maros_booking_requests', 'production' !== Maros_Client::env() );
	}

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
		register_rest_route(
			'maros/v1',
			'/booking-request',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_request' ),
				// Público, como el presupuesto: límite por visitante en rest_request() y en marOS.
				'permission_callback' => '__return_true',
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

		if ( ! self::within_limit( 'q', self::PER_MINUTE, MINUTE_IN_SECONDS ) ) {
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

	/* ------------------------------------------------------------------ Solicitud de reserva (C4, C5) */

	/**
	 * POST /wp-json/maros/v1/booking-request. Errores para el navegador: «fields» (campo → texto,
	 * junto a cada campo), «price_changed» con el presupuesto nuevo, «not_available» con los motivos,
	 * o «message» para el resto. Nunca se guarda nada del huésped en WordPress.
	 */
	public static function rest_request( WP_REST_Request $r ): WP_REST_Response {
		$in      = (array) $r->get_json_params();
		$post_id = isset( $in['post'] ) ? absint( $in['post'] ) : 0;
		$post    = $post_id ? get_post( $post_id ) : null;
		$maros   = $post && 'mis_property' === $post->post_type && 'publish' === $post->post_status ? (string) get_post_meta( $post_id, 'maros_id', true ) : '';
		$es      = $post_id ? maros_is_es( $post_id ) : false;
		if ( '' === $maros || get_post_meta( $post_id, 'maros_withdrawn', true ) || ! self::requests_enabled() ) {
			return self::reply( array( 'error' => 'not_found', 'message' => self::t( $es, 'This home can’t be booked online. Talk to our team and we’ll help.', 'Esta casa no se puede reservar en la web. Habla con el equipo y te ayudamos.' ) ), 404 );
		}

		$str       = static fn( string $k, int $max ) => isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) ? mb_substr( trim( (string) $in[ $k ] ), 0, $max ) : '';
		$int       = static fn( string $k ) => isset( $in[ $k ] ) && is_numeric( $in[ $k ] ) ? (int) $in[ $k ] : 0;
		$arrival   = $str( 'arrival', 10 );
		$departure = $str( 'departure', 10 );
		$nights    = self::nights( $arrival, $departure );
		if ( $nights < 1 || $nights > self::MAX_NIGHTS || $arrival < wp_date( 'Y-m-d' ) ) {
			return self::reply( array( 'error' => 'bad_dates', 'message' => self::t( $es, 'Choose a check-in date from today and a check-out date after it.', 'Elige una llegada a partir de hoy y una salida posterior.' ) ), 400 );
		}
		$max    = (int) get_post_meta( $post_id, 'maros_max_guests', true ) ?: 50;
		$guests = array(
			'adults'   => max( 1, min( 50, $int( 'adults' ) ) ),
			'children' => max( 0, min( 50, $int( 'children' ) ) ),
			'infants'  => max( 0, min( 20, $int( 'infants' ) ) ),
		);

		$fields = array();
		$msg    = self::request_strings( $es )['fields'];
		$guest  = array(
			'first_name' => $str( 'first_name', 80 ),
			'last_name'  => $str( 'last_name', 80 ),
			'email'      => strtolower( $str( 'email', 200 ) ),
			'phone'      => preg_replace( '/\s+/', ' ', $str( 'phone', 30 ) ),
			'country'    => strtoupper( $str( 'country', 2 ) ),
			'language'   => $es ? 'es' : 'en',
		);
		if ( '' === $guest['first_name'] ) {
			$fields['first_name'] = $msg['first_name'];
		}
		if ( '' === $guest['last_name'] ) {
			$fields['last_name'] = $msg['last_name'];
		}
		if ( ! preg_match( '/^[^\s@]{1,100}@[^\s@]{1,100}\.[^\s@]{2,40}$/', $guest['email'] ) ) {
			$fields['email'] = $msg['email'];
		}
		if ( ! preg_match( '/^\+\d[\d\s().-]{5,24}$/', $guest['phone'] ) ) {
			$fields['phone'] = $msg['phone'];
		}
		if ( ! preg_match( '/^[A-Z]{2}$/', $guest['country'] ) ) {
			$fields['country'] = $msg['country'];
		}
		$slot = $str( 'arrival_time', 12 ) ?: 'unknown';
		if ( ! in_array( $slot, self::ARRIVAL_SLOTS, true ) ) {
			$slot = 'unknown';
		}
		$message = $str( 'message', 1000 );
		if ( empty( $in['terms'] ) ) {
			$fields['terms'] = $msg['terms'];
		}
		$request_id = strtolower( $str( 'request_id', 36 ) );
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $request_id ) ) {
			return self::reply( array( 'error' => 'bad_request', 'message' => self::t( $es, 'Reload the page and try again.', 'Vuelve a cargar la página e inténtalo de nuevo.' ) ), 400 );
		}
		if ( $guests['adults'] + $guests['children'] > $max ) {
			return self::reply( array( 'error' => 'bad_guests', 'message' => sprintf( $es ? 'La casa admite hasta %d huéspedes (sin contar bebés).' : 'The home sleeps up to %d guests (infants not included).', $max ) ), 400 );
		}
		if ( $fields ) {
			return self::reply( array( 'error' => 'invalid', 'fields' => $fields, 'message' => self::request_strings( $es )['check'] ), 400 );
		}

		if ( ! self::within_limit( 'r', self::REQUESTS_PER_WINDOW, 10 * MINUTE_IN_SECONDS ) ) {
			return self::reply( array( 'error' => 'rate_limited', 'message' => self::t( $es, 'Too many requests from this connection. Wait a few minutes or talk to our team.', 'Demasiadas solicitudes desde esta conexión. Espera unos minutos o habla con el equipo.' ) ), 429 );
		}

		// El presupuesto de ahora, el mismo que ve la ficha: si ha cambiado, el huésped lo confirma.
		$quote = self::quote( $post_id, $maros, $arrival, $departure, $guests );
		if ( is_wp_error( $quote ) ) {
			Maros_Log::add( 'error', 'Presupuesto de ' . $maros . ' para una solicitud: ' . $quote->get_error_message() );
			return self::reply( array( 'error' => 'unavailable', 'message' => self::request_failed( $es ) ), 502 );
		}
		$seen = isset( $in['total'] ) && is_numeric( $in['total'] ) ? round( (float) $in['total'], 2 ) : -1.0;
		if ( empty( $quote['available'] ) ) {
			return self::reply( array( 'error' => 'not_available', 'message' => self::t( $es, 'These dates are no longer available.', 'Estas fechas ya no están disponibles.' ), 'quote' => self::present( $quote, $post_id, $es, $guests ) ), 409 );
		}
		if ( abs( (float) $quote['total'] - $seen ) > 0.01 ) {
			return self::price_changed( $quote, $post_id, $es, $guests );
		}

		$body = array(
			'request_id'        => $request_id,
			'arrival'           => $arrival,
			'departure'         => $departure,
			'adults'            => $guests['adults'],
			'children'          => $guests['children'],
			'infants'           => $guests['infants'],
			'guest'             => $guest,
			'arrival_time'      => $slot,
			'accepted_terms'    => array( 'version' => self::TERMS_VERSION ),
			'marketing_consent' => ! empty( $in['marketing'] ),
			'quoted_total'      => round( (float) $quote['total'], 2 ),
		);
		if ( ! empty( $quote['room_type_id'] ) ) {
			$body['room_type_id'] = (string) $quote['room_type_id'];
		}
		if ( '' !== $message ) {
			$body['message'] = $message;
		}

		$res = Maros_Client::post_booking_request( $maros, $body );
		if ( is_wp_error( $res ) ) {
			Maros_Log::add( 'error', 'Solicitud de reserva de ' . $maros . ': ' . $res->get_error_message() );
			return self::reply( array( 'error' => 'unavailable', 'message' => self::request_failed( $es ) ), 502 );
		}
		$data = $res['body'];
		if ( $res['status'] >= 200 && $res['status'] < 300 && ! empty( $data['reference'] ) ) {
			$currency = (string) ( $data['currency'] ?? 'EUR' );
			$out      = array(
				'reference'  => (string) $data['reference'],
				'first_name' => $guest['first_name'],
				'email'      => $guest['email'],
				'total'      => self::money( (float) ( $data['total'] ?? $quote['total'] ), $currency, $es ),
			);
			if ( isset( $data['deposit'] ) && is_numeric( $data['deposit'] ) && (float) $data['deposit'] > 0 ) {
				$out['deposit'] = sprintf( self::t( $es, 'Refundable security deposit: %s', 'Fianza reembolsable: %s' ), self::money( (float) $data['deposit'], $currency, $es ) );
			}
			return self::reply( $out, 201 );
		}

		// marOS ha dicho que no: se explica con sus datos y no se guarda el presupuesto viejo.
		$error = is_array( $data['error'] ?? null ) ? $data['error'] : array();
		$code  = (string) ( $error['code'] ?? '' );
		if ( in_array( $code, array( 'price_changed', 'not_available' ), true ) ) {
			delete_transient( self::quote_key( $post_id, $maros, $arrival, $departure, $guests ) );
		}
		if ( 'price_changed' === $code && is_array( $error['quote'] ?? null ) ) {
			return self::price_changed( $error['quote'], $post_id, $es, $guests );
		}
		if ( 'not_available' === $code ) {
			$gone = array( 'available' => false, 'arrival' => $arrival, 'departure' => $departure, 'nights' => $nights, 'reasons' => (array) ( $error['reasons'] ?? array() ) );
			return self::reply( array( 'error' => 'not_available', 'message' => self::t( $es, 'These dates are no longer available.', 'Estas fechas ya no están disponibles.' ), 'quote' => self::present( $gone, $post_id, $es, $guests ) ), 409 );
		}
		if ( 'invalid_request' === $code && isset( $error['field'] ) ) {
			$name = str_replace( 'guest.', '', (string) $error['field'] );
			if ( isset( $guest[ $name ] ) ) {
				return self::reply( array( 'error' => 'invalid', 'fields' => array( $name => self::t( $es, 'Check this detail.', 'Revisa este dato.' ) ), 'message' => self::t( $es, 'Check the highlighted details.', 'Revisa los datos marcados.' ) ), 400 );
			}
		}
		if ( 429 === $res['status'] ) {
			return self::reply( array( 'error' => 'rate_limited', 'message' => self::t( $es, 'We’re receiving a lot of requests. Try again in a minute.', 'Estamos recibiendo muchas solicitudes. Vuelve a intentarlo en un minuto.' ) ), 429 );
		}
		Maros_Log::add( 'error', 'Solicitud de reserva de ' . $maros . ' rechazada por marOS (' . $res['status'] . ' ' . $code . '): ' . (string) ( $error['message'] ?? '' ) );
		return self::reply( array( 'error' => 'unavailable', 'message' => self::request_failed( $es ) ), 502 );
	}

	private static function price_changed( array $quote, int $post_id, bool $es, array $guests ): WP_REST_Response {
		return self::reply(
			array(
				'error'   => 'price_changed',
				'message' => self::t( $es, 'The price for these dates has just changed. Check the new total and send the request again.', 'El precio de estas fechas acaba de cambiar. Revisa el total nuevo y vuelve a enviar la solicitud.' ),
				'quote'   => self::present( $quote, $post_id, $es, $guests ),
			),
			409
		);
	}

	private static function request_failed( bool $es ): string {
		return self::t( $es, 'We couldn’t send your request. Nothing has been booked or charged. Try again in a moment, or talk to our team.', 'No hemos podido enviar tu solicitud. No se ha reservado ni cobrado nada. Vuelve a intentarlo en un momento o habla con el equipo.' );
	}

	/**
	 * Presupuesto de marOS para una casa, con caché por la huella del calendario. Si la casa tiene
	 * varios tipos de alojamiento, el más barato que esté libre.
	 *
	 * @return array|WP_Error
	 */
	private static function quote( int $post_id, string $maros, string $arrival, string $departure, array $guests ) {
		$key = self::quote_key( $post_id, $maros, $arrival, $departure, $guests );
		$hit = get_transient( $key );
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

	private static function quote_key( int $post_id, string $maros, string $arrival, string $departure, array $guests ): string {
		$etag = (string) get_post_meta( $post_id, 'maros_calendar_etag', true );
		return 'maros_q_' . md5( wp_json_encode( array( $maros, $arrival, $departure, $guests, $etag ) ) );
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
		// El importe tal cual, para enviarlo con la solicitud (marOS comprueba que no ha cambiado).
		$out['amount'] = round( (float) ( $q['total'] ?? 0 ), 2 );
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
		$requests = self::requests_enabled();
		if ( $requests ) {
			$config['request'] = rest_url( 'maros/v1/booking-request' );
			$config['form']    = self::request_strings( $es );
		}

		$stepper = static function ( string $key, string $label, string $hint ) use ( $es ): string {
			return '<div class="mis-resv__guest"><div><span class="mis-resv__guest-label">' . esc_html( $label ) . '</span>' . ( '' !== $hint ? '<small>' . esc_html( $hint ) . '</small>' : '' ) . '</div>'
				. '<div class="mis-resv__stepper"><button type="button" data-step="' . esc_attr( $key ) . '" data-by="-1" aria-label="' . esc_attr( ( $es ? 'Menos: ' : 'Fewer: ' ) . $label ) . '">−</button>'
				. '<output data-count="' . esc_attr( $key ) . '" aria-live="polite">0</output>'
				. '<button type="button" data-step="' . esc_attr( $key ) . '" data-by="1" aria-label="' . esc_attr( ( $es ? 'Más: ' : 'More: ' ) . $label ) . '">+</button></div></div>';
		};
		$close = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>';

		$rating = self::rating( $post_id, $es );
		$area   = maros_area_name( $post_id );
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
		$html .= '<div class="mis-resv__after" data-after></div>';
		$html .= self::included( $post_id, $es );
		$html .= '<div class="mis-resv__help">' . $talk . '</div>';
		if ( $requests ) {
			$html .= self::request_markup( $es, (string) $photo, $name );
		}
		$html .= '<script type="application/json" class="mis-resv__data">' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . '</script>';
		$html .= '</div>';

		$file = dirname( __DIR__ ) . '/assets/booking.js';
		wp_enqueue_script( 'maros-booking', plugins_url( 'assets/booking.js', __DIR__ ), array( 'maros-calendar' ), (string) filemtime( $file ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		return $html;
	}

	/* ------------------------------------------------------------------ Pantallas C4 y C5 */

	/** Una página del sitio por su slug en inglés (en el idioma de la ficha), o '' si no existe. */
	private static function page( string $slug ): string {
		return function_exists( 'mis_page_url' ) && get_page_by_path( $slug ) ? mis_page_url( $slug ) : '';
	}

	/** Textos de la solicitud que usa el navegador (los avisos de campo son los mismos que da el servidor). */
	private static function request_strings( bool $es ): array {
		return array(
			'fields'  => array(
				'first_name' => self::t( $es, 'Enter your first name.', 'Escribe tu nombre.' ),
				'last_name'  => self::t( $es, 'Enter your surname.', 'Escribe tus apellidos.' ),
				'email'      => self::t( $es, 'Enter a valid email address, such as name@example.com.', 'Escribe un email válido, por ejemplo nombre@ejemplo.com.' ),
				'phone'      => self::t( $es, 'Enter your phone number with the country code, such as +44 7700 900123.', 'Escribe tu teléfono con el prefijo del país, por ejemplo +34 600 123 456.' ),
				'country'    => self::t( $es, 'Choose your country of residence.', 'Elige tu país de residencia.' ),
				'terms'      => self::t( $es, 'To send the request, accept the booking conditions and the privacy policy.', 'Para enviar la solicitud, acepta las condiciones de reserva y la política de privacidad.' ),
			),
			'check'   => self::t( $es, 'Check the highlighted details.', 'Revisa los datos marcados.' ),
			'sending' => self::t( $es, 'Sending…', 'Enviando…' ),
			'send'    => self::t( $es, 'Send request', 'Enviar solicitud' ),
			'failed'  => self::request_failed( $es ),
			'thanks'  => self::t( $es, 'Thank you, %1$s. We’ve received your request and we’ll write to you at %2$s.', 'Gracias, %1$s. Hemos recibido tu solicitud y te escribiremos a %2$s.' ),
		);
	}

	private static function request_markup( bool $es, string $photo, string $name ): string {
		$f     = self::request_strings( $es );
		$input = static function ( string $key, string $label, string $type, string $auto, string $extra = '' ): string {
			return '<div class="mis-req__field" data-field="' . esc_attr( $key ) . '"><label for="mis-req-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>'
				. '<input id="mis-req-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" type="' . esc_attr( $type ) . '" autocomplete="' . esc_attr( $auto ) . '" aria-describedby="mis-req-' . esc_attr( $key ) . '-error"' . $extra . '>'
				. '<p class="mis-req__error" id="mis-req-' . esc_attr( $key ) . '-error" hidden></p></div>';
		};
		$back  = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7"/></svg>';
		$tick  = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
		$house = '<div class="mis-req__house">' . ( $photo ? '<img src="' . esc_url( $photo ) . '" alt="" width="64" height="64" loading="lazy">' : '' )
			. '<div><p class="mis-req__name">' . esc_html( $name ) . '</p><p data-req-dates></p><p data-req-guests></p></div></div>';

		$terms   = self::page( 'terms-and-conditions' );
		$privacy = self::page( 'privacy-policy' );
		$link    = static fn( string $url, string $text ) => '' !== $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $text ) . '</a>' : esc_html( $text );
		$accept  = $es
			? 'Acepto las ' . $link( $terms, 'condiciones de reserva' ) . ' y la ' . $link( $privacy, 'política de privacidad' ) . '.'
			: 'I accept the ' . $link( $terms, 'booking conditions' ) . ' and the ' . $link( $privacy, 'privacy policy' ) . '.';
		$slots   = array(
			'unknown'   => self::t( $es, 'Not sure yet', 'Aún no lo sé' ),
			'before_16' => self::t( $es, 'Before 4 pm', 'Antes de las 16:00' ),
			'16_20'     => self::t( $es, '4 pm – 8 pm', '16:00 – 20:00' ),
			'after_20'  => self::t( $es, 'After 8 pm', 'Después de las 20:00' ),
		);
		$radios = '';
		foreach ( $slots as $value => $label ) {
			$radios .= '<label class="mis-req__chip"><input type="radio" name="arrival_time" value="' . esc_attr( $value ) . '"' . checked( 'unknown', $value, false ) . '><span>' . esc_html( $label ) . '</span></label>';
		}
		$home     = function_exists( 'pll_home_url' ) ? pll_home_url() : home_url( '/' );
		$services = self::page( 'services' );
		$contact  = self::page( 'contact' );

		$html  = '<div class="mis-req" data-req hidden role="dialog" aria-modal="true" aria-labelledby="mis-req-title">';
		$html .= '<div class="mis-req__panel">';
		$html .= '<div class="mis-req__top"><button type="button" class="mis-req__back" data-req-close aria-label="' . esc_attr( self::t( $es, 'Back to dates and price', 'Volver a fechas y precio' ) ) . '">' . $back . '</button><b id="mis-req-title" tabindex="-1">' . esc_html( self::t( $es, 'Request to book', 'Solicitar reserva' ) ) . '</b><span></span></div>';

		// C4: la solicitud.
		$html .= '<form class="mis-req__form" data-req-form novalidate>';
		$html .= '<div class="mis-req__summary">' . $house . '<button type="button" class="mis-req__change" data-req-close>' . esc_html( self::t( $es, 'Change', 'Cambiar' ) ) . '</button></div>';
		$html .= '<div class="mis-req__total"><span>' . esc_html( 'Total' ) . ' <small data-req-nights></small></span><b data-req-total></b></div><p class="mis-req__deposit" data-req-deposit hidden></p>';
		$html .= '<div class="mis-req__alert" data-req-alert role="alert" hidden></div>';
		$html .= '<fieldset class="mis-req__group"><legend>' . esc_html( self::t( $es, 'Your details', 'Tus datos' ) ) . '</legend><p class="mis-req__hint">' . esc_html( self::t( $es, 'We only use them for this booking.', 'Solo los usamos para esta reserva.' ) ) . '</p>';
		$html .= '<div class="mis-req__row">' . $input( 'first_name', self::t( $es, 'First name', 'Nombre' ), 'text', 'given-name', ' maxlength="80" required' ) . $input( 'last_name', self::t( $es, 'Surname', 'Apellidos' ), 'text', 'family-name', ' maxlength="80" required' ) . '</div>';
		$html .= $input( 'email', 'Email', 'email', 'email', ' maxlength="200" required inputmode="email"' );
		$html .= '<div class="mis-req__field" data-field="phone"><label for="mis-req-phone">' . esc_html( self::t( $es, 'Phone (WhatsApp)', 'Teléfono (WhatsApp)' ) ) . '</label>'
			. '<div class="mis-req__phone"><input id="mis-req-prefix" name="prefix" type="tel" autocomplete="tel-country-code" inputmode="tel" maxlength="5" value="+" aria-label="' . esc_attr( self::t( $es, 'Country code', 'Prefijo' ) ) . '">'
			. '<input id="mis-req-phone" name="phone" type="tel" autocomplete="tel-national" inputmode="tel" maxlength="24" required aria-describedby="mis-req-phone-error"></div>'
			. '<p class="mis-req__error" id="mis-req-phone-error" hidden></p></div>';
		$html .= '<div class="mis-req__field" data-field="country"><label for="mis-req-country">' . esc_html( self::t( $es, 'Country of residence', 'País de residencia' ) ) . '</label>'
			. '<select id="mis-req-country" name="country" autocomplete="country" required aria-describedby="mis-req-country-error"><option value="">' . esc_html( self::t( $es, 'Choose…', 'Elige…' ) ) . '</option></select>'
			. '<p class="mis-req__error" id="mis-req-country-error" hidden></p></div>';
		$html .= '</fieldset>';
		$html .= '<fieldset class="mis-req__group"><legend>' . esc_html( self::t( $es, 'Approximate arrival time (optional)', 'Llegada aproximada (opcional)' ) ) . '</legend><div class="mis-req__chips">' . $radios . '</div></fieldset>';
		$html .= '<div class="mis-req__field"><label for="mis-req-message">' . esc_html( self::t( $es, 'Anything we should know? (optional)', '¿Algo que debamos saber? (opcional)' ) ) . '</label><textarea id="mis-req-message" name="message" rows="3" maxlength="1000"></textarea></div>';
		$html .= '<div class="mis-req__next"><p>' . esc_html( self::t( $es, 'What happens now', 'Qué pasa ahora' ) ) . '</p><ol>'
			. '<li>' . esc_html( self::t( $es, 'Nothing is charged when you send the request.', 'Al enviar la solicitud no se cobra nada.' ) ) . '</li>'
			. '<li>' . esc_html( self::t( $es, 'We confirm within 24 hours. Meanwhile, the dates are held for you.', 'Te confirmamos en 24 horas. Mientras, las fechas quedan apartadas para ti.' ) ) . '</li>'
			. '<li>' . esc_html( self::t( $es, 'You pay with a secure link we send you.', 'Pagas con un enlace seguro que te enviamos.' ) ) . '</li>'
			. '</ol></div>';
		$html .= '<div class="mis-req__field mis-req__check" data-field="terms"><label><input type="checkbox" name="terms" value="1" required aria-describedby="mis-req-terms-error"><span>' . $accept . '</span></label><p class="mis-req__error" id="mis-req-terms-error" hidden></p></div>';
		$html .= '<div class="mis-req__field mis-req__check"><label><input type="checkbox" name="marketing" value="1"><span>' . esc_html( self::t( $es, 'I’d like to receive news and offers from Marbella in Style by email (optional).', 'Quiero recibir novedades y ofertas de Marbella in Style por email (opcional).' ) ) . '</span></label></div>';
		$html .= '<div class="mis-req__foot"><div><b data-req-total></b><small data-req-nights></small></div><button type="submit" class="wp-element-button" data-req-send>' . esc_html( $f['send'] ) . '</button></div>';
		$html .= '</form>';

		// C5: solicitud enviada.
		$html .= '<div class="mis-req__done" data-req-done hidden>';
		$html .= '<div class="mis-req__ok">' . $tick . '</div><h2 class="mis-req__title" tabindex="-1" data-req-done-title>' . esc_html( self::t( $es, 'Request sent', 'Solicitud enviada' ) ) . '</h2>';
		$html .= '<p data-req-thanks></p><p class="mis-req__ref">' . esc_html( self::t( $es, 'Reference', 'Referencia' ) ) . ' <b data-req-ref></b></p>';
		$html .= '<div class="mis-req__summary">' . $house . '</div><div class="mis-req__total"><span>Total</span><b data-req-total></b></div><p class="mis-req__deposit" data-req-deposit hidden></p>';
		$html .= '<ol class="mis-req__steps">'
			. '<li class="is-done">' . $tick . '<span>' . esc_html( self::t( $es, 'Request received. Your dates are held for 24 hours.', 'Solicitud recibida. Tus fechas quedan apartadas 24 horas.' ) ) . '</span></li>'
			. '<li><span>' . esc_html( self::t( $es, 'We confirm your booking within 24 hours.', 'Te confirmamos la reserva en 24 horas como máximo.' ) ) . '</span></li>'
			. '<li><span>' . esc_html( self::t( $es, 'Then we send you a secure link to pay.', 'Después te enviamos un enlace seguro para pagar.' ) ) . '</span></li>'
			. '</ol>';
		$html .= '<p class="mis-req__hint">' . esc_html( self::t( $es, 'Any questions? Write to us and mention your reference.', '¿Dudas? Escríbenos y menciona tu referencia.' ) ) . ( '' !== $contact ? ' <a href="' . esc_url( $contact ) . '">' . esc_html( self::t( $es, 'Talk to our team', 'Habla con el equipo' ) ) . '</a>' : '' ) . '</p>';
		$html .= '<div class="mis-req__actions">' . ( '' !== $services ? '<a class="wp-element-button" href="' . esc_url( $services ) . '">' . esc_html( self::t( $es, 'Add services to your stay', 'Añadir servicios a tu estancia' ) ) . '</a>' : '' )
			. '<a class="mis-req__home" href="' . esc_url( $home ) . '">' . esc_html( self::t( $es, 'Back to home', 'Volver al inicio' ) ) . '</a></div>';
		$html .= '</div>';

		$html .= '</div></div>';
		return $html;
	}

	/**
	 * Disponibilidad de la copia local para pintar el calendario, de hoy al último día cargado.
	 * Un carácter por día (bits: 1 noche libre, 2 se puede llegar, 4 se puede salir) y la estancia
	 * mínima por tramos [día desde hoy, noches]. Con varios tipos de alojamiento, basta uno libre;
	 * el presupuesto de marOS es el que manda.
	 */
	/**
	 * Lista «Incluido», común a todas las casas (Julio, 18-11-2026: «Lista común de la empresa»,
	 * con iconos). La recepción dice también «self check-in» en las casas que lo ofrecen (marOS
	 * spec 032, meta maros_self_check_in). El filtro «maros_booking_included» permite cambiarla.
	 *
	 * @return array<int, array{icon:string,text:string}>
	 */
	private static function included_items( int $post_id, bool $es ): array {
		$self  = 'yes' === get_post_meta( $post_id, 'maros_self_check_in', true );
		$items = array(
			array(
				'icon' => 'phosphor/key',
				'text' => $self
					? ( $es ? 'Recepción en persona o self check-in, como prefieras' : 'Personal welcome or self check-in, as you prefer' )
					: ( $es ? 'Recepción en persona' : 'Personal welcome' ),
			),
			array(
				'icon' => 'phosphor/bed',
				'text' => $es ? 'Limpieza final, ropa de cama y toallas' : 'Final cleaning, bed linen and towels',
			),
			array(
				'icon' => 'phosphor/wifi-high',
				'text' => $es ? 'Wifi y suministros' : 'Wi-Fi and utilities',
			),
			array(
				'icon' => 'propios/campana-recepcion',
				'text' => $es ? 'Concierge durante la estancia' : 'Concierge throughout your stay',
			),
		);
		return (array) apply_filters( 'maros_booking_included', $items, $post_id, $es );
	}

	private static function included( int $post_id, bool $es ): string {
		$li = '';
		foreach ( self::included_items( $post_id, $es ) as $item ) {
			$text = is_array( $item ) ? (string) ( $item['text'] ?? '' ) : '';
			if ( '' !== $text ) {
				$icon = function_exists( 'maros_icon_svg' ) ? maros_icon_svg( (string) ( $item['icon'] ?? '' ), 20 ) : '';
				$li  .= '<li>' . $icon . '<span>' . esc_html( $text ) . '</span></li>';
			}
		}
		if ( '' === $li ) {
			return '';
		}
		return '<div class="mis-resv__incl"><small>' . esc_html( $es ? 'Incluido' : 'Included' ) . '</small><ul>' . $li . '</ul></div>';
	}

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

	/** Límite por visitante; la IP solo se usa con hash y durante la ventana. */
	private static function within_limit( string $kind, int $max, int $window ): bool {
		$ip  = (string) ( $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$key = 'maros_' . $kind . 'l_' . md5( wp_salt( 'nonce' ) . $ip . (int) floor( time() / $window ) );
		$n   = (int) get_transient( $key );
		if ( $n >= $max ) {
			return false;
		}
		set_transient( $key, $n + 1, 2 * $window );
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
