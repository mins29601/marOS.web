<?php
/**
 * Cliente de la API de marOS. La clave nunca sale del servidor (principio III).
 *
 * Con MAROS_ENV=mock lee los JSON del mock directamente (spec 003, US «entorno»), así funciona
 * también desde WP-CLI, donde no hay servidor web.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

class Maros_Client {

	public static function env(): string {
		return defined( 'MAROS_ENV' ) ? (string) MAROS_ENV : 'mock';
	}

	/**
	 * GET a la API v1.
	 *
	 * @param string $path Ruta tras /v1, p. ej. /properties.
	 * @return array|WP_Error
	 */
	public static function get( string $path ) {
		if ( 'production' !== wp_get_environment_type() && 'production' === self::env() ) {
			return new WP_Error( 'maros_env', 'Este entorno no puede usar marOS producción.' );
		}

		if ( 'mock' === self::env() ) {
			return self::get_mock( $path );
		}

		$base = defined( 'MAROS_API_BASE' ) ? rtrim( MAROS_API_BASE, '/' ) : '';
		if ( ! $base ) {
			return new WP_Error( 'maros_config', 'Falta MAROS_API_BASE.' );
		}
		$response = wp_remote_get(
			$base . '/v1' . $path,
			array(
				'timeout' => 15,
				'headers' => array( 'Authorization' => 'Bearer ' . ( defined( 'MAROS_API_KEY' ) ? MAROS_API_KEY : '' ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code >= 400 || ! is_array( $body ) ) {
			return new WP_Error( 'maros_http', sprintf( 'marOS respondió %d en %s', $code, $path ) );
		}
		return $body;
	}

	private static function get_mock( string $path ) {
		if ( ! function_exists( 'mis_maros_mock_read' ) ) {
			return new WP_Error( 'maros_mock', 'El mock de marOS no está cargado (mu-plugin mis-maros-mock).' );
		}
		if ( '/properties' === $path ) {
			return mis_maros_mock_read( 'v1/properties.json' ) ?? new WP_Error( 'maros_mock', 'Sin properties.json' );
		}
		if ( preg_match( '#^/properties/([a-z0-9\-]+)$#', $path, $m ) ) {
			return mis_maros_mock_read( 'v1/properties/' . $m[1] . '.json' ) ?? new WP_Error( 'maros_not_found', 'Propiedad no encontrada: ' . $m[1] );
		}
		return new WP_Error( 'maros_mock', 'Ruta no simulada: ' . $path );
	}
}
