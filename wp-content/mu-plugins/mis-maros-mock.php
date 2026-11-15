<?php
/**
 * Plugin Name: MIS marOS mock
 * Description: Imita la API pública v1 de marOS (arquitectura, sección 3.2) con los JSON de
 *              mock/maros-api. Solo se activa con MAROS_ENV=mock y nunca en producción.
 * Version:     0.1.0
 *
 * Rutas (todas bajo /wp-json/maros-mock):
 *   GET  /v1/catalog/properties                 (propiedades publicables en la web)
 *   GET  /v1/catalog/properties/{id}            (ficha)
 *   GET  /v1/catalog/properties/{id}/calendar   (calendario y tarifas por noche, lo que sincroniza WP)
 *   GET  /v1/availability?property_id&from&to&guests
 *   POST /v1/quotes
 *   POST /v1/bookings
 *   GET  /v1/bookings/{id}
 *
 * Exige la cabecera Authorization: Bearer <MAROS_API_KEY> o x-api-key, como la API real.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MAROS_ENV' ) || 'mock' !== MAROS_ENV || 'production' === wp_get_environment_type() ) {
	return;
}

/**
 * Carpeta de los JSON: en wp-env se monta en wp-content/maros-mock; si no, la del repo.
 */
function mis_maros_mock_dir(): string {
	if ( defined( 'MAROS_MOCK_DIR' ) ) {
		return rtrim( MAROS_MOCK_DIR, '/' );
	}
	$candidates = array( WP_CONTENT_DIR . '/maros-mock', dirname( __DIR__, 2 ) . '/mock/maros-api' );
	foreach ( $candidates as $dir ) {
		if ( is_dir( $dir ) ) {
			return $dir;
		}
	}
	return $candidates[0];
}

/**
 * Lee un JSON del mock.
 *
 * @param string $rel Ruta relativa, p. ej. v1/properties.json.
 * @return array|null
 */
function mis_maros_mock_read( string $rel ) {
	$file = mis_maros_mock_dir() . '/' . $rel;
	if ( ! is_readable( $file ) ) {
		return null;
	}
	return json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}

function mis_maros_mock_error( string $code, string $message, int $status ): WP_REST_Response {
	return new WP_REST_Response( array( 'error' => array( 'code' => $code, 'message' => $message ) ), $status );
}

function mis_maros_mock_auth( WP_REST_Request $request ) {
	$key    = defined( 'MAROS_API_KEY' ) ? MAROS_API_KEY : '';
	$bearer = (string) $request->get_header( 'authorization' );
	$given  = 0 === stripos( $bearer, 'Bearer ' ) ? substr( $bearer, 7 ) : (string) $request->get_header( 'x_api_key' );
	if ( '' === $key || ! hash_equals( $key, $given ) ) {
		return new WP_Error( 'unauthorized', 'API key inválida o ausente', array( 'status' => 401 ) );
	}
	return true;
}

function mis_maros_mock_safe_id( string $id ): string {
	return preg_replace( '/[^a-z0-9\-]/', '', strtolower( $id ) );
}

add_action(
	'rest_api_init',
	static function () {
		$ns   = 'maros-mock';
		$auth = 'mis_maros_mock_auth';

		register_rest_route(
			$ns,
			'/v1/catalog/properties',
			array(
				'methods'             => 'GET',
				'permission_callback' => $auth,
				'callback'            => static function () {
					return rest_ensure_response( mis_maros_mock_read( 'v1/properties.json' ) );
				},
			)
		);

		register_rest_route(
			$ns,
			'/v1/catalog/properties/(?P<id>[a-z0-9\-]+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => $auth,
				'callback'            => static function ( WP_REST_Request $r ) {
					$data = mis_maros_mock_read( 'v1/properties/' . mis_maros_mock_safe_id( $r['id'] ) . '.json' );
					return $data ? rest_ensure_response( $data ) : mis_maros_mock_error( 'not_found', 'Propiedad no encontrada', 404 );
				},
			)
		);

		register_rest_route(
			$ns,
			'/v1/catalog/properties/(?P<id>[a-z0-9\-]+)/calendar',
			array(
				'methods'             => 'GET',
				'permission_callback' => $auth,
				'callback'            => static function ( WP_REST_Request $r ) {
					$id = mis_maros_mock_safe_id( $r['id'] );
					if ( ! mis_maros_mock_read( 'v1/properties/' . $id . '.json' ) ) {
						return mis_maros_mock_error( 'not_found', 'Propiedad no encontrada', 404 );
					}
					return rest_ensure_response( mis_maros_mock_calendar( $id ) );
				},
			)
		);

		register_rest_route(
			$ns,
			'/v1/availability',
			array(
				'methods'             => 'GET',
				'permission_callback' => $auth,
				'callback'            => static function ( WP_REST_Request $r ) {
					$id   = mis_maros_mock_safe_id( (string) $r->get_param( 'property_id' ) );
					$from = (string) $r->get_param( 'from' );
					$to   = (string) $r->get_param( 'to' );
					if ( ! $id || ! $from || ! $to ) {
						return mis_maros_mock_error( 'bad_request', 'Faltan property_id, from o to', 400 );
					}
					$cal  = mis_maros_mock_calendar( $id );
					$days = array_values(
						array_filter( $cal['days'], static fn( $d ) => $d['date'] >= $from && $d['date'] < $to )
					);
					return rest_ensure_response(
						array(
							'property_id' => $id,
							'from'        => $from,
							'to'          => $to,
							'available'   => count( $days ) > 0 && ! in_array( false, array_column( $days, 'available' ), true ),
							'days'        => $days,
						)
					);
				},
			)
		);

		register_rest_route(
			$ns,
			'/v1/quotes',
			array(
				'methods'             => 'POST',
				'permission_callback' => $auth,
				'callback'            => static function ( WP_REST_Request $r ) {
					$p        = $r->get_json_params();
					$id       = mis_maros_mock_safe_id( (string) ( $p['property_id'] ?? '' ) );
					$arrival  = (string) ( $p['arrival'] ?? '' );
					$depart   = (string) ( $p['departure'] ?? '' );
					$property = mis_maros_mock_read( 'v1/properties/' . $id . '.json' );
					if ( ! $property || ! $arrival || ! $depart ) {
						return mis_maros_mock_error( 'bad_request', 'property_id, arrival y departure son obligatorios', 400 );
					}
					$nights = (int) ( ( strtotime( $depart ) - strtotime( $arrival ) ) / DAY_IN_SECONDS );
					if ( $nights < 1 ) {
						return mis_maros_mock_error( 'bad_request', 'departure debe ser posterior a arrival', 400 );
					}
					$cal  = mis_maros_mock_calendar( $id );
					$days = array_filter( $cal['days'], static fn( $d ) => $d['date'] >= $arrival && $d['date'] < $depart );
					if ( in_array( false, array_column( $days, 'available' ), true ) ) {
						return mis_maros_mock_error( 'unavailable', 'Fechas no disponibles', 409 );
					}
					$rent     = array_sum( array_map( static fn( $d ) => $d['rates'][0]['price'] ?? 0, $days ) );
					$cleaning = (float) ( $property['fees']['cleaning'] ?? 0 );
					$net      = $rent + $cleaning;
					$vat      = round( $net * 0.10, 2 );
					return rest_ensure_response(
						array(
							'quote_id'            => 'mock-q-' . wp_generate_password( 8, false ),
							'property_id'         => $id,
							'arrival'             => $arrival,
							'departure'           => $depart,
							'nights'              => $nights,
							'guests'              => $p['guests'] ?? null,
							'currency'            => $cal['currency'],
							'breakdown'           => array(
								array( 'type' => 'RoomRate', 'label' => 'Alojamiento', 'amount' => $rent ),
								array( 'type' => 'Fee', 'subtype' => 'Cleaning', 'label' => 'Limpieza', 'amount' => $cleaning ),
								array( 'type' => 'Tax', 'label' => 'IVA 10 %', 'amount' => $vat ),
							),
							'total_excluding_vat' => $net,
							'total_vat'           => $vat,
							'total_including_vat' => $net + $vat,
							'expires_at'          => gmdate( 'c', time() + 15 * MINUTE_IN_SECONDS ),
							'mock'                => true,
						)
					);
				},
			)
		);

		register_rest_route(
			$ns,
			'/v1/bookings',
			array(
				'methods'             => 'POST',
				'permission_callback' => $auth,
				'callback'            => static function ( WP_REST_Request $r ) {
					$p  = $r->get_json_params();
					$id = 'mock-b-' . wp_generate_password( 8, false );
					// No se guardan datos del huésped: solo se devuelve un id y la URL de pago simulada.
					return new WP_REST_Response(
						array(
							'booking_id'  => $id,
							'property_id' => $p['property_id'] ?? null,
							'status'      => 'tentative',
							'payment_url' => home_url( '/?maros_mock_payment=' . $id ),
							'expires_at'  => gmdate( 'c', time() + 30 * MINUTE_IN_SECONDS ),
							'mock'        => true,
						),
						201
					);
				},
			)
		);

		register_rest_route(
			$ns,
			'/v1/bookings/(?P<id>[A-Za-z0-9\-]+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => $auth,
				'callback'            => static function ( WP_REST_Request $r ) {
					return rest_ensure_response( array( 'booking_id' => $r['id'], 'status' => 'tentative', 'mock' => true ) );
				},
			)
		);
	}
);

/**
 * Calendario de 18 meses generado a partir de la plantilla v1/calendar/{id}.json:
 * tarifa base por temporada, estancia mínima y bloques de fechas ocupadas (relativos a hoy).
 */
function mis_maros_mock_calendar( string $id ): array {
	$tpl = mis_maros_mock_read( 'v1/calendar/' . $id . '.json' ) ?? mis_maros_mock_read( 'v1/calendar/_default.json' );

	$booked = array();
	foreach ( $tpl['booked_offsets'] ?? array() as $block ) {
		for ( $i = 0; $i < $block['nights']; $i++ ) {
			$booked[ $block['from_day'] + $i ] = true;
		}
	}

	$days  = array();
	$today = strtotime( gmdate( 'Y-m-d' ) );
	$room  = (int) ( mis_maros_mock_read( 'v1/properties/' . $id . '.json' )['room_types'][0]['id'] ?? 1 );
	for ( $i = 0; $i < 548; $i++ ) {
		$ts       = $today + $i * DAY_IN_SECONDS;
		$month    = (int) gmdate( 'n', $ts );
		$high     = in_array( $month, $tpl['high_season_months'] ?? array( 6, 7, 8 ), true );
		$min_stay = $high ? ( $tpl['min_stay_high'] ?? 7 ) : ( $tpl['min_stay'] ?? 3 );
		$rate     = (float) ( $high ? $tpl['rate_high'] : $tpl['rate'] );
		$days[]   = array(
			'room_type_id' => $room,
			'date'         => gmdate( 'Y-m-d', $ts ),
			'available'    => empty( $booked[ $i ] ),
			'closed'       => false,
			'check_in'     => true,
			'check_out'    => true,
			'min_stay'     => $min_stay,
			// Como Lodgify: un precio por tramo de estancia; las estancias largas, algo más baratas.
			'rates'        => array(
				array( 'min_stay' => $min_stay, 'max_stay' => 13, 'price' => $rate ),
				array( 'min_stay' => 14, 'max_stay' => null, 'price' => round( $rate * 0.9, 2 ) ),
			),
		);
	}

	return array(
		'property_id' => $id,
		'currency'    => $tpl['currency'] ?? 'EUR',
		'from'        => $days[0]['date'],
		'to'          => $days[ count( $days ) - 1 ]['date'],
		'etag'        => '"mock-cal-' . $id . '-1"',
		'days'        => $days,
	);
}
