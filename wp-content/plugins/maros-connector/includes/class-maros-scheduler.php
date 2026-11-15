<?php
/**
 * Cola y tareas programadas (spec 003, US2 y FR-003).
 *
 * - «maros_tick», cada 5 minutos: lee la lista de marOS cada 15 minutos (encola lo que cambió) y
 *   procesa la cola. En pruebas y producción lo dispara el cron real de IONOS
 *   (`wp cron event run --due-now`), con DISABLE_WP_CRON.
 * - «maros_nightly», a las 03:30 de Madrid: reconciliación completa (relee todo aunque el etag no
 *   haya cambiado) y borra los días pasados del calendario.
 *
 * Un fallo se reintenta con espera creciente (5, 10, 20… minutos, máximo 6 horas); mientras tanto
 * la web sigue con los últimos datos.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

class Maros_Scheduler {

	public const TICK    = 'maros_tick';
	public const NIGHTLY = 'maros_nightly';

	private const QUEUE        = 'maros_queue';
	private const LOCK         = 'maros_lock';
	private const POLL_EVERY   = 15 * MINUTE_IN_SECONDS;
	private const TIME_BUDGET  = 120;
	private const MAX_BACKOFF  = 6 * HOUR_IN_SECONDS;

	public static function init(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		add_action( self::TICK, array( __CLASS__, 'tick' ) );
		add_action( self::NIGHTLY, array( __CLASS__, 'nightly' ) );
		add_action( 'init', array( __CLASS__, 'ensure_scheduled' ), 20 );
	}

	public static function schedules( array $schedules ): array {
		$schedules['maros_5min'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => 'Cada 5 minutos (marOS)',
		);
		return $schedules;
	}

	public static function ensure_scheduled(): void {
		if ( ! wp_next_scheduled( self::TICK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'maros_5min', self::TICK );
		}
		if ( ! wp_next_scheduled( self::NIGHTLY ) ) {
			$next = new DateTimeImmutable( 'today 03:30', new DateTimeZone( 'Europe/Madrid' ) );
			if ( $next->getTimestamp() <= time() ) {
				$next = $next->modify( '+1 day' );
			}
			wp_schedule_event( $next->getTimestamp(), 'daily', self::NIGHTLY );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::TICK );
		wp_clear_scheduled_hook( self::NIGHTLY );
	}

	/** Pone una propiedad en la cola (si ya estaba, la adelanta). */
	public static function enqueue( string $maros_id, string $reason = '' ): void {
		if ( ! Maros_Client::safe_id( $maros_id ) ) {
			return;
		}
		$queue              = self::queue();
		$queue[ $maros_id ] = array(
			'attempts' => $queue[ $maros_id ]['attempts'] ?? 0,
			'next'     => isset( $queue[ $maros_id ] ) && ( $queue[ $maros_id ]['attempts'] ?? 0 ) > 0 ? $queue[ $maros_id ]['next'] : time(),
			'reason'   => $reason,
			'error'    => $queue[ $maros_id ]['error'] ?? '',
		);
		update_option( self::QUEUE, $queue, false );
	}

	public static function queue(): array {
		$queue = get_option( self::QUEUE, array() );
		return is_array( $queue ) ? $queue : array();
	}

	/** Tarea de 5 minutos. */
	public static function tick( bool $force_poll = false ): array {
		$done = array( 'polled' => false, 'processed' => 0, 'failed' => 0 );
		if ( ! self::lock() ) {
			return $done;
		}
		try {
			if ( $force_poll || time() - (int) get_option( 'maros_last_poll', 0 ) >= self::POLL_EVERY ) {
				update_option( 'maros_last_poll', time(), false );
				$polled = Maros_Sync::poll();
				if ( is_wp_error( $polled ) ) {
					Maros_Log::error( 'Lectura de la lista de marOS: ' . $polled->get_error_message() );
				}
				$done['polled'] = true;
			}
			$done = array_merge( $done, self::process() );
		} finally {
			self::unlock();
		}
		return $done;
	}

	/** Procesa las propiedades de la cola que toca reintentar ya. */
	public static function process(): array {
		$start     = time();
		$processed = 0;
		$failed    = 0;
		foreach ( self::queue() as $maros_id => $item ) {
			if ( ( $item['next'] ?? 0 ) > time() ) {
				continue;
			}
			if ( time() - $start > self::TIME_BUDGET ) {
				break;
			}
			// Se saca de la cola antes de sincronizar: si quedan fotos, sync_one la vuelve a encolar.
			$queue = self::queue();
			unset( $queue[ $maros_id ] );
			update_option( self::QUEUE, $queue, false );

			$result = Maros_Sync::sync_property( (string) $maros_id, null );
			if ( is_wp_error( $result ) ) {
				$attempts           = (int) ( $item['attempts'] ?? 0 ) + 1;
				$queue              = self::queue();
				$queue[ $maros_id ] = array(
					'attempts' => $attempts,
					'next'     => time() + min( self::MAX_BACKOFF, 5 * MINUTE_IN_SECONDS * 2 ** ( $attempts - 1 ) ),
					'reason'   => $item['reason'] ?? '',
					'error'    => $result->get_error_message(),
				);
				update_option( self::QUEUE, $queue, false );
				Maros_Log::error( sprintf( '%s (intento %d): %s', $maros_id, $attempts, $result->get_error_message() ) );
				++$failed;
				continue;
			}
			if ( 'skipped' !== $result ) {
				Maros_Log::info( sprintf( '%s: %s (%s).', $maros_id, 'created' === $result ? 'creada' : 'actualizada', $item['reason'] ?? '' ) );
			}
			++$processed;
		}
		return array( 'processed' => $processed, 'failed' => $failed );
	}

	/** Reconciliación nocturna. */
	public static function nightly(): void {
		if ( ! self::lock() ) {
			return;
		}
		try {
			Maros_Sync::sync_all( false, true, true );
			Maros_Calendar::purge_past();
			update_option( 'maros_last_poll', time(), false );
		} finally {
			self::unlock();
		}
	}

	/** Evita dos sincronizaciones a la vez (cron y botón, por ejemplo). Caduca a los 15 minutos. */
	private static function lock(): bool {
		$now = time();
		if ( add_option( self::LOCK, $now, '', false ) ) {
			return true;
		}
		$since = (int) get_option( self::LOCK, 0 );
		if ( $now - $since > 15 * MINUTE_IN_SECONDS ) {
			update_option( self::LOCK, $now, false );
			return true;
		}
		return false;
	}

	private static function unlock(): void {
		delete_option( self::LOCK );
	}

	public static function is_locked(): bool {
		$since = (int) get_option( self::LOCK, 0 );
		return $since && time() - $since <= 15 * MINUTE_IN_SECONDS;
	}

	/** Para el botón «Sincronizar ahora» y `wp maros sync`. */
	public static function run_locked( callable $fn ) {
		if ( ! self::lock() ) {
			return new WP_Error( 'maros_busy', 'Ya hay una sincronización en marcha. Prueba en unos minutos.' );
		}
		try {
			return $fn();
		} finally {
			self::unlock();
		}
	}
}
