<?php
/**
 * Registro corto de la sincronización (spec 003, FR-008): los últimos 50 eventos, sin datos
 * personales ni la clave de marOS. Se ve en Propiedades → marOS y con `wp maros status`.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

class Maros_Log {

	private const OPTION = 'maros_log';
	private const MAX    = 50;

	public static function add( string $level, string $message ): void {
		$log = get_option( self::OPTION, array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift( $log, array( 'time' => time(), 'level' => $level, 'message' => $message ) );
		update_option( self::OPTION, array_slice( $log, 0, self::MAX ), false );
		if ( 'error' === $level && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[maros-connector] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}

	public static function info( string $message ): void {
		self::add( 'info', $message );
	}

	public static function error( string $message ): void {
		self::add( 'error', $message );
	}

	public static function recent( int $limit = 20 ): array {
		$log = get_option( self::OPTION, array() );
		return array_slice( is_array( $log ) ? $log : array(), 0, $limit );
	}

	/** Última vez que marOS respondió bien a la lista de propiedades (aviso de 26 h, US2). */
	public static function mark_ok(): void {
		update_option( 'maros_last_ok', time(), false );
	}

	public static function last_ok(): int {
		return (int) get_option( 'maros_last_ok', 0 );
	}

	public static function is_stale(): bool {
		return time() - self::last_ok() > 26 * HOUR_IN_SECONDS;
	}
}
