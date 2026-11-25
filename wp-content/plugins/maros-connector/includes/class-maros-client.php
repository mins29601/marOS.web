<?php
/**
 * Cliente de la API de marOS. La clave nunca sale del servidor (principio III).
 *
 * Configuración en wp-config.php (spec 003, FR-007), nunca en el repo ni en la base de datos:
 *   MAROS_ENV       production | staging | mock
 *   MAROS_API_BASE  base sin /v1, p. ej. https://<marOS pruebas>/api
 *   MAROS_API_KEY   clave de la web en marOS (solo lectura de propiedades mientras no haya reservas)
 *
 * Con MAROS_ENV=mock lee los JSON del mock directamente, sin red, así funciona también desde
 * WP-CLI, donde no hay servidor web.
 *
 * Contrato (catálogo publicable de marOS, scope «catalog:read»; el mock lo imita):
 *   GET /v1/catalog/properties               → { properties: [ { id, name, type, updated_at, etag, calendar_etag } ] }
 *   GET /v1/catalog/properties/{id}          → ficha (ver mock/maros-api/v1/properties/*.json)
 *   GET /v1/catalog/properties/{id}/calendar → { currency, etag, days: [ { room_type_id, date, available, closed,
 *                                                check_in, check_out, min_stay, rates: [ { min_stay, max_stay, price } ] } ] }
 *   GET /v1/catalog/properties/{id}/quote?arrival&departure&adults&children&infants[&room_type_id]
 *                                            → presupuesto de la estancia (marOS spec 044, contracts/quote-api.md)
 * Solo salen las propiedades marcadas «publicable en la web» en marOS.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

class Maros_Client {

	/** Reintentos ante 429, 5xx o error de red, con espera creciente (US2, escenario 4). */
	private const RETRIES = 2;

	public static function env(): string {
		return defined( 'MAROS_ENV' ) ? (string) MAROS_ENV : 'mock';
	}

	public static function base(): string {
		return defined( 'MAROS_API_BASE' ) ? rtrim( (string) MAROS_API_BASE, '/' ) : '';
	}

	public static function has_key(): bool {
		return defined( 'MAROS_API_KEY' ) && '' !== (string) MAROS_API_KEY;
	}

	/**
	 * Comprueba que el entorno permite hablar con marOS.
	 *
	 * @return true|WP_Error
	 */
	public static function check_config() {
		$env = self::env();
		if ( ! in_array( $env, array( 'production', 'staging', 'mock' ), true ) ) {
			return new WP_Error( 'maros_config', sprintf( 'MAROS_ENV «%s» no es válido (production, staging o mock).', $env ) );
		}
		if ( 'production' === $env && 'production' !== wp_get_environment_type() ) {
			return new WP_Error( 'maros_env', 'Este entorno no puede usar marOS producción.' );
		}
		if ( 'mock' === $env ) {
			return true;
		}
		if ( ! self::base() ) {
			return new WP_Error( 'maros_config', 'Falta MAROS_API_BASE en wp-config.php.' );
		}
		if ( 'https' !== wp_parse_url( self::base(), PHP_URL_SCHEME ) ) {
			return new WP_Error( 'maros_config', 'MAROS_API_BASE debe ser https.' );
		}
		if ( ! self::has_key() ) {
			return new WP_Error( 'maros_config', 'Falta MAROS_API_KEY en wp-config.php.' );
		}
		return true;
	}

	/**
	 * GET a la API v1.
	 *
	 * @param string $path Ruta tras /v1, p. ej. /properties.
	 * @param string $etag Si se da, se envía If-None-Match; un 304 devuelve array( 'not_modified' => true ).
	 * @return array|WP_Error Cuerpo decodificado; la cabecera ETag, si viene, en la clave «_etag».
	 */
	public static function get( string $path, string $etag = '' ) {
		$ok = self::check_config();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		if ( 'mock' === self::env() ) {
			return self::get_mock( $path );
		}

		$headers = array(
			'Authorization' => 'Bearer ' . MAROS_API_KEY,
			'Accept'        => 'application/json',
		);
		if ( '' !== $etag ) {
			$headers['If-None-Match'] = $etag;
		}

		$attempt = 0;
		do {
			$response = wp_remote_get(
				self::base() . '/v1' . $path,
				array(
					'timeout'     => 20,
					'redirection' => 0,
					'user-agent'  => 'marOS-Connector/' . MAROS_CONNECTOR_VERSION . '; ' . wp_parse_url( home_url(), PHP_URL_HOST ),
					'headers'     => $headers,
				)
			);
			$code  = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			$retry = 0 === $code || 429 === $code || $code >= 500;
			if ( ! $retry || $attempt >= self::RETRIES ) {
				break;
			}
			$wait = (int) wp_remote_retrieve_header( $response, 'retry-after' );
			sleep( min( 10, max( $wait, 2 ** $attempt ) ) );
			++$attempt;
		} while ( true );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'maros_http', sprintf( 'marOS no responde en %s: %s', $path, $response->get_error_message() ) );
		}
		if ( 304 === $code ) {
			return array( 'not_modified' => true, '_etag' => $etag );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 404 === $code ) {
			return new WP_Error( 'maros_not_found', sprintf( 'marOS no encuentra %s', $path ), array( 'status' => 404 ) );
		}
		if ( $code >= 400 || ! is_array( $body ) ) {
			// El mensaje de error de marOS puede ayudar; nunca contiene la clave.
			$detail = is_array( $body ) ? (string) ( $body['error']['message'] ?? '' ) : '';
			return new WP_Error( 'maros_http', trim( sprintf( 'marOS respondió %d en %s. %s', $code, $path, $detail ) ), array( 'status' => $code ) );
		}
		$body['_etag'] = (string) wp_remote_retrieve_header( $response, 'etag' );
		return $body;
	}

	/**
	 * Lista de propiedades publicables, normalizada a array de resúmenes.
	 *
	 * @return array|WP_Error
	 */
	public static function list_properties() {
		$list = self::get( '/catalog/properties' );
		if ( is_wp_error( $list ) ) {
			return $list;
		}
		$items = $list['properties'] ?? null;
		if ( ! is_array( $items ) ) {
			return new WP_Error( 'maros_contract', 'La lista de propiedades de marOS no tiene el formato esperado.' );
		}
		return array_values( array_filter( $items, static fn( $p ) => is_array( $p ) && ! empty( $p['id'] ) && is_string( $p['id'] ) ) );
	}

	/** Ficha de una propiedad. */
	public static function get_property( string $id, string $etag = '' ) {
		return self::safe_id( $id ) ? self::get( '/catalog/properties/' . $id, $etag ) : new WP_Error( 'maros_id', 'Id de marOS no válido.' );
	}

	/** Calendario y tarifas de una propiedad (18 meses, solo fechas futuras). */
	public static function get_calendar( string $id, string $etag = '' ) {
		return self::safe_id( $id ) ? self::get( '/catalog/properties/' . $id . '/calendar', $etag ) : new WP_Error( 'maros_id', 'Id de marOS no válido.' );
	}

	/**
	 * Ficha PDF de una propiedad (marOS spec 057). Con el ETag que ya se tiene, marOS contesta 304 sin
	 * generarla otra vez. Generar tarda unos segundos: un solo intento, con 60 s de margen.
	 *
	 * @return array|WP_Error array( 'not_modified' => true ) o array( 'pdf' => bytes, 'etag' => string ).
	 */
	public static function get_brochure( string $id, string $lang, string $etag = '' ) {
		$ok = self::check_config();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		if ( ! self::safe_id( $id ) || ! in_array( $lang, array( 'es', 'en' ), true ) ) {
			return new WP_Error( 'maros_id', 'Id de marOS o idioma no válido.' );
		}
		if ( 'mock' === self::env() ) {
			return new WP_Error( 'maros_mock', 'La ficha PDF no tiene datos de ejemplo.' );
		}
		$headers = array(
			'Authorization' => 'Bearer ' . MAROS_API_KEY,
			'Accept'        => 'application/pdf',
		);
		if ( '' !== $etag ) {
			$headers['If-None-Match'] = $etag;
		}
		$path     = '/catalog/properties/' . $id . '/brochure?lang=' . $lang;
		$response = wp_remote_get(
			self::base() . '/v1' . $path,
			array(
				'timeout'     => 60,
				'redirection' => 0,
				'user-agent'  => 'marOS-Connector/' . MAROS_CONNECTOR_VERSION . '; ' . wp_parse_url( home_url(), PHP_URL_HOST ),
				'headers'     => $headers,
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'maros_http', sprintf( 'marOS no responde en %s: %s', $path, $response->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 304 === $code ) {
			return array( 'not_modified' => true );
		}
		$body = (string) wp_remote_retrieve_body( $response );
		if ( 200 !== $code || ! str_starts_with( $body, '%PDF-' ) ) {
			$data   = json_decode( $body, true );
			$detail = is_array( $data ) ? (string) ( $data['error']['message'] ?? '' ) : '';
			return new WP_Error( 'maros_http', trim( sprintf( 'marOS respondió %d en %s. %s', $code, $path, $detail ) ), array( 'status' => $code ) );
		}
		return array( 'pdf' => $body, 'etag' => (string) wp_remote_retrieve_header( $response, 'etag' ) );
	}

	/**
	 * Presupuesto de una estancia (marOS spec 044): se calcula con la copia del catálogo de marOS,
	 * sin guardar nada. Devuelve el cuerpo tal cual: { available, exact, nights, lines, total,
	 * deposit, … } o { available: false, reasons }.
	 *
	 * @param array{adults:int,children:int,infants:int} $guests
	 * @return array|WP_Error
	 */
	public static function get_quote( string $id, string $arrival, string $departure, array $guests, string $room_type_id = '' ) {
		if ( ! self::safe_id( $id ) ) {
			return new WP_Error( 'maros_id', 'Id de marOS no válido.' );
		}
		$query = array(
			'arrival'   => $arrival,
			'departure' => $departure,
			'adults'    => (int) $guests['adults'],
			'children'  => (int) ( $guests['children'] ?? 0 ),
			'infants'   => (int) ( $guests['infants'] ?? 0 ),
		);
		if ( '' !== $room_type_id && preg_match( '/^\d{1,20}$/', $room_type_id ) ) {
			$query['room_type_id'] = $room_type_id;
		}
		return self::get( '/catalog/properties/' . $id . '/quote?' . http_build_query( $query ) );
	}

	/**
	 * Solicitud de reserva de la web (marOS spec 026, scope «booking_requests:write»):
	 * POST /v1/catalog/properties/{id}/booking-requests. Un solo intento: marOS no duplica la
	 * reserva con el mismo request_id, y la web deja reintentar al visitante.
	 *
	 * @return array{status:int,body:array}|WP_Error Respuesta de marOS (también 4xx, con su error).
	 */
	public static function post_booking_request( string $id, array $body ) {
		if ( ! self::safe_id( $id ) ) {
			return new WP_Error( 'maros_id', 'Id de marOS no válido.' );
		}
		$ok = self::check_config();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		if ( 'mock' === self::env() ) {
			// Sin red: una solicitud de ejemplo para ver C5 en local.
			return array(
				'status' => 201,
				'body'   => array(
					'reference'  => 'MIS-TEST' . strtoupper( substr( md5( (string) ( $body['request_id'] ?? '' ) ), 0, 2 ) ),
					'status'     => 'requested',
					'property_id' => $id,
					'arrival'    => $body['arrival'] ?? '',
					'departure'  => $body['departure'] ?? '',
					'total'      => $body['quoted_total'] ?? 0,
					'currency'   => 'EUR',
					'deposit'    => null,
					'expires_at' => gmdate( 'c', time() + DAY_IN_SECONDS ),
					'next_steps' => array( 'sent', 'confirm_24h', 'pay_link' ),
				),
			);
		}
		$response = wp_remote_post(
			self::base() . '/v1/catalog/properties/' . $id . '/booking-requests',
			array(
				'timeout'     => 45,
				'redirection' => 0,
				'user-agent'  => 'marOS-Connector/' . MAROS_CONNECTOR_VERSION . '; ' . wp_parse_url( home_url(), PHP_URL_HOST ),
				'headers'     => array(
					'Authorization'   => 'Bearer ' . MAROS_API_KEY,
					'Accept'          => 'application/json',
					'Content-Type'    => 'application/json',
					'Idempotency-Key' => (string) ( $body['request_id'] ?? '' ),
				),
				'body'        => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'maros_http', 'marOS no responde a la solicitud de reserva: ' . $response->get_error_message() );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return array( 'status' => (int) wp_remote_retrieve_response_code( $response ), 'body' => is_array( $data ) ? $data : array() );
	}

	public static function safe_id( string $id ): string {
		return preg_match( '/^[A-Za-z0-9_\-]{1,80}$/', $id ) ? $id : '';
	}

	private static function get_mock( string $path ) {
		if ( ! function_exists( 'mis_maros_mock_read' ) ) {
			return new WP_Error( 'maros_mock', 'El mock de marOS no está cargado (mu-plugin mis-maros-mock).' );
		}
		if ( '/catalog/properties' === $path ) {
			return mis_maros_mock_read( 'v1/properties.json' ) ?? new WP_Error( 'maros_mock', 'Sin properties.json' );
		}
		if ( preg_match( '#^/catalog/properties/([a-z0-9\-]+)/quote\?(.*)$#', $path, $m ) ) {
			if ( ! mis_maros_mock_read( 'v1/properties/' . $m[1] . '.json' ) ) {
				return new WP_Error( 'maros_not_found', 'Propiedad no encontrada: ' . $m[1], array( 'status' => 404 ) );
			}
			parse_str( $m[2], $query );
			return mis_maros_mock_quote( $m[1], $query );
		}
		if ( preg_match( '#^/catalog/properties/([a-z0-9\-]+)/calendar$#', $path, $m ) ) {
			if ( ! mis_maros_mock_read( 'v1/properties/' . $m[1] . '.json' ) ) {
				return new WP_Error( 'maros_not_found', 'Propiedad no encontrada: ' . $m[1], array( 'status' => 404 ) );
			}
			return mis_maros_mock_calendar( $m[1] );
		}
		if ( preg_match( '#^/catalog/properties/([a-z0-9\-]+)$#', $path, $m ) ) {
			return mis_maros_mock_read( 'v1/properties/' . $m[1] . '.json' ) ?? new WP_Error( 'maros_not_found', 'Propiedad no encontrada: ' . $m[1], array( 'status' => 404 ) );
		}
		return new WP_Error( 'maros_mock', 'Ruta no simulada: ' . $path );
	}
}
