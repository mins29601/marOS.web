<?php
/**
 * Avisos firmados de marOS (spec 003, US2): POST /wp-json/maros/v1/webhook.
 *
 * Firma igual que las entradas de n8n en marOS: cabeceras X-Maros-Timestamp (segundos o ms) y
 * X-Maros-Signature = HMAC-SHA256 hex de «timestamp.cuerpo» con MAROS_WEBHOOK_SECRET.
 * Más de 5 minutos de diferencia, firma mala o aviso repetido → se rechaza y se registra.
 * Un aviso válido solo encola la propiedad y responde 202; el trabajo lo hace el cron de 5 minutos.
 *
 * Cuerpo: { "id": "<id único del aviso>", "type": "property.updated" | "availability.changed" |
 *           "property.removed", "property_id": "<id de marOS>" }
 *
 * Mientras marOS no envíe avisos, la lectura de la lista cada 15 minutos hace el mismo trabajo.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

class Maros_Webhook {

	private const TYPES     = array( 'property.updated', 'availability.changed', 'property.removed' );
	private const SEEN      = 'maros_webhook_seen';
	private const TOLERANCE = 300;
	private const MAX_BODY  = 65536;

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes(): void {
		register_rest_route(
			'maros/v1',
			'/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle' ),
				// La autenticación es la firma HMAC, que se comprueba en handle().
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function secret(): string {
		return defined( 'MAROS_WEBHOOK_SECRET' ) ? (string) MAROS_WEBHOOK_SECRET : '';
	}

	public static function sign( string $timestamp, string $body, string $secret ): string {
		return hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
	}

	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$secret = self::secret();
		if ( '' === $secret ) {
			return self::reply( 404, 'not_found', 'Avisos de marOS no configurados.' );
		}
		$body = (string) $request->get_body();
		if ( strlen( $body ) > self::MAX_BODY ) {
			return self::reply( 413, 'too_large', 'Aviso demasiado grande.' );
		}
		$timestamp = (string) $request->get_header( 'x_maros_timestamp' );
		$signature = strtolower( (string) $request->get_header( 'x_maros_signature' ) );
		$signature = str_starts_with( $signature, 'sha256=' ) ? substr( $signature, 7 ) : $signature;

		$seconds = ctype_digit( $timestamp ) ? (int) $timestamp : 0;
		if ( $seconds > 9999999999 ) {
			$seconds = intdiv( $seconds, 1000 );
		}
		if ( ! $seconds || abs( time() - $seconds ) > self::TOLERANCE ) {
			Maros_Log::error( 'Aviso de marOS rechazado: hora ausente o fuera de margen.' );
			return self::reply( 401, 'unauthorized', 'Firma no válida.' );
		}
		if ( '' === $signature || ! hash_equals( self::sign( $timestamp, $body, $secret ), $signature ) ) {
			Maros_Log::error( 'Aviso de marOS rechazado: firma no válida.' );
			return self::reply( 401, 'unauthorized', 'Firma no válida.' );
		}

		$data     = json_decode( $body, true );
		$event_id = is_array( $data ) ? (string) ( $data['id'] ?? '' ) : '';
		$type     = is_array( $data ) ? (string) ( $data['type'] ?? '' ) : '';
		$maros_id = is_array( $data ) ? Maros_Client::safe_id( (string) ( $data['property_id'] ?? '' ) ) : '';
		if ( '' === $event_id || strlen( $event_id ) > 100 || ! in_array( $type, self::TYPES, true ) || '' === $maros_id ) {
			Maros_Log::error( 'Aviso de marOS rechazado: formato no válido.' );
			return self::reply( 400, 'invalid', 'Aviso con formato no válido.' );
		}

		$seen = get_option( self::SEEN, array() );
		$seen = is_array( $seen ) ? $seen : array();
		if ( isset( $seen[ $event_id ] ) ) {
			// Repetido: no se hace nada la segunda vez (US1, escenario 4).
			return new WP_REST_Response( array( 'result' => 'duplicate' ), 200 );
		}
		$seen[ $event_id ] = time();
		$seen              = array_filter( $seen, static fn( $t ) => $t > time() - DAY_IN_SECONDS );
		update_option( self::SEEN, array_slice( $seen, -500, null, true ), false );

		Maros_Scheduler::enqueue( $maros_id, 'aviso ' . $type );
		Maros_Log::info( sprintf( 'Aviso de marOS: %s %s.', $type, $maros_id ) );
		return new WP_REST_Response( array( 'result' => 'queued' ), 202 );
	}

	private static function reply( int $status, string $code, string $message ): WP_REST_Response {
		return new WP_REST_Response( array( 'error' => array( 'code' => $code, 'message' => $message ) ), $status );
	}
}
