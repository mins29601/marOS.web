<?php
/**
 * Comandos WP-CLI de marOS Connector.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

class Maros_CLI {

	/**
	 * Sincroniza las propiedades publicables de marOS (ficha, fotos, calendario y tarifas).
	 *
	 * ## OPTIONS
	 *
	 * [--id=<maros_id>]
	 * : Solo esta propiedad.
	 *
	 * [--publish]
	 * : Publica las propiedades nuevas (por defecto nacen en borrador, spec 003). Solo para el contenido de ejemplo.
	 *
	 * [--force]
	 * : Relee ficha y calendario aunque no hayan cambiado.
	 *
	 * [--no-photos]
	 * : No descarga fotos.
	 *
	 * ## EXAMPLES
	 *
	 *     wp maros sync
	 *     wp maros sync --id=lodgify-405724 --force
	 *
	 * @param array $args       Argumentos.
	 * @param array $assoc_args Opciones.
	 */
	public function sync( $args, $assoc_args ) {
		$publish = ! empty( $assoc_args['publish'] );
		$force   = ! empty( $assoc_args['force'] );
		$photos  = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'photos', true );

		if ( ! empty( $assoc_args['id'] ) ) {
			$id     = (string) $assoc_args['id'];
			$result = Maros_Scheduler::run_locked( static fn() => Maros_Sync::sync_property( $id, null, $publish, $photos, $force ) );
			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
			}
			WP_CLI::success( sprintf( '%s: %s.', $id, $result ) );
			return;
		}

		$result = Maros_Scheduler::run_locked( static fn() => Maros_Sync::sync_all( $publish, $photos, $force ) );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		foreach ( $result['errors'] as $error ) {
			WP_CLI::warning( $error );
		}
		WP_CLI::success( sprintf( 'marOS (%s): %d creadas, %d actualizadas, %d sin cambios, %d retiradas.', Maros_Client::env(), $result['created'], $result['updated'], $result['skipped'], $result['withdrawn'] ) );
	}

	/**
	 * Ejecuta ahora la tarea de 5 minutos (lee la lista de marOS y procesa la cola).
	 */
	public function tick() {
		$result = Maros_Scheduler::tick( true );
		WP_CLI::success( sprintf( 'Lista leída: %s · procesadas %d · con error %d · en cola %d.', $result['polled'] ? 'sí' : 'no (otra sincronización en marcha)', $result['processed'], $result['failed'], count( Maros_Scheduler::queue() ) ) );
	}

	/**
	 * Muestra el estado de la conexión con marOS.
	 */
	public function status() {
		WP_CLI::log( 'MAROS_ENV: ' . Maros_Client::env() . ( Maros_Client::base() ? ' (' . Maros_Client::base() . ')' : '' ) );
		WP_CLI::log( 'Clave: ' . ( Maros_Client::has_key() ? 'configurada' : 'falta' ) . ' · secreto de avisos: ' . ( Maros_Webhook::secret() ? 'configurado' : 'falta' ) );
		WP_CLI::log( 'Última sincronización completa: ' . get_option( 'maros_last_full_sync', 'nunca' ) );
		WP_CLI::log( 'Próxima tarea de 5 min: ' . ( wp_next_scheduled( Maros_Scheduler::TICK ) ? gmdate( 'c', wp_next_scheduled( Maros_Scheduler::TICK ) ) : 'sin programar' ) );
		WP_CLI::log( 'En cola: ' . ( implode( ', ', array_keys( Maros_Scheduler::queue() ) ) ?: 'nada' ) );
		foreach ( Maros_Log::recent( 5 ) as $entry ) {
			WP_CLI::log( sprintf( '  %s [%s] %s', gmdate( 'Y-m-d H:i', $entry['time'] ), $entry['level'], $entry['message'] ) );
		}
		$list = Maros_Client::list_properties();
		if ( is_wp_error( $list ) ) {
			WP_CLI::error( $list->get_error_message() );
		}
		WP_CLI::success( count( $list ) . ' propiedades publicables en marOS.' );
	}
}
