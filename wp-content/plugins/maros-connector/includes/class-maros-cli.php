<?php
/**
 * Comandos WP-CLI de marOS Connector.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

class Maros_CLI {

	/**
	 * Sincroniza las propiedades publicables de marOS.
	 *
	 * ## OPTIONS
	 *
	 * [--publish]
	 * : Publica las propiedades nuevas (por defecto nacen en borrador, spec 003).
	 *
	 * [--no-photos]
	 * : No descarga fotos.
	 *
	 * ## EXAMPLES
	 *
	 *     wp maros sync --publish
	 *
	 * @param array $args       Argumentos.
	 * @param array $assoc_args Opciones.
	 */
	public function sync( $args, $assoc_args ) {
		$result = Maros_Sync::sync_all(
			! empty( $assoc_args['publish'] ),
			(bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'photos', true )
		);
		foreach ( $result['errors'] as $error ) {
			WP_CLI::warning( $error );
		}
		WP_CLI::success( sprintf( 'marOS (%s): %d creadas, %d actualizadas, %d sin cambios.', Maros_Client::env(), $result['created'], $result['updated'], $result['skipped'] ) );
	}

	/**
	 * Muestra el estado de la conexión con marOS.
	 */
	public function status() {
		WP_CLI::log( 'MAROS_ENV: ' . Maros_Client::env() );
		WP_CLI::log( 'Última sincronización completa: ' . get_option( 'maros_last_full_sync', 'nunca' ) );
		$list = Maros_Client::get( '/properties' );
		if ( is_wp_error( $list ) ) {
			WP_CLI::error( $list->get_error_message() );
		}
		WP_CLI::success( count( $list['data'] ?? array() ) . ' propiedades en marOS.' );
	}
}
