<?php
/**
 * Calendario y tarifas de marOS en tablas propias (spec 003, FR-003b; data-model «Calendario y
 * tarifas»). La búsqueda y el calendario de la web leen de aquí, nunca de marOS.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

class Maros_Calendar {

	public const DB_VERSION = '1';

	public static function calendar_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'maros_calendar';
	}

	public static function rates_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'maros_rates';
	}

	/** Crea o actualiza las tablas (activación y cada vez que cambia DB_VERSION). */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$cal     = self::calendar_table();
		$rates   = self::rates_table();

		dbDelta(
			"CREATE TABLE {$cal} (
				property_id varchar(80) NOT NULL,
				room_type_id bigint(20) unsigned NOT NULL DEFAULT 0,
				date date NOT NULL,
				available tinyint(1) NOT NULL DEFAULT 0,
				min_stay smallint(5) unsigned NOT NULL DEFAULT 0,
				closed_arrival tinyint(1) NOT NULL DEFAULT 0,
				closed_departure tinyint(1) NOT NULL DEFAULT 0,
				synced_at datetime NOT NULL,
				PRIMARY KEY  (property_id,room_type_id,date),
				KEY date_available (date,available)
			) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$rates} (
				property_id varchar(80) NOT NULL,
				room_type_id bigint(20) unsigned NOT NULL DEFAULT 0,
				date date NOT NULL,
				min_stay smallint(5) unsigned NOT NULL DEFAULT 0,
				max_stay smallint(5) unsigned DEFAULT NULL,
				amount decimal(10,2) NOT NULL,
				currency char(3) NOT NULL,
				synced_at datetime NOT NULL,
				PRIMARY KEY  (property_id,room_type_id,date,min_stay),
				KEY date_amount (date,amount)
			) {$charset};"
		);
		update_option( 'maros_db_version', self::DB_VERSION, false );
	}

	public static function maybe_install(): void {
		if ( get_option( 'maros_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Sustituye el calendario y las tarifas de una propiedad, todo o nada (data-model).
	 *
	 * @param string $maros_id Id de marOS.
	 * @param array  $data     Respuesta de /catalog/properties/{id}/calendar.
	 * @return int|WP_Error Días guardados.
	 */
	public static function replace( string $maros_id, array $data ) {
		global $wpdb;
		$days = $data['days'] ?? null;
		if ( ! is_array( $days ) ) {
			return new WP_Error( 'maros_contract', 'El calendario de ' . $maros_id . ' no trae días.' );
		}
		$currency = strtoupper( substr( (string) ( $data['currency'] ?? 'EUR' ), 0, 3 ) );
		$today    = wp_date( 'Y-m-d' );
		$now      = current_time( 'mysql', true );

		$cal_rows  = array();
		$rate_rows = array();
		foreach ( $days as $d ) {
			$date = (string) ( $d['date'] ?? '' );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || $date < $today ) {
				continue;
			}
			$room  = (int) ( $d['room_type_id'] ?? 0 );
			$rates = is_array( $d['rates'] ?? null ) ? $d['rates'] : array();
			$min   = isset( $d['min_stay'] ) ? (int) $d['min_stay'] : ( $rates ? min( array_map( static fn( $r ) => (int) ( $r['min_stay'] ?? 0 ), $rates ) ) : 0 );

			$cal_rows[] = $wpdb->prepare(
				'(%s,%d,%s,%d,%d,%d,%d,%s)',
				$maros_id,
				$room,
				$date,
				! empty( $d['available'] ) && empty( $d['closed'] ) ? 1 : 0,
				max( 0, $min ),
				array_key_exists( 'check_in', $d ) && ! $d['check_in'] ? 1 : 0,
				array_key_exists( 'check_out', $d ) && ! $d['check_out'] ? 1 : 0,
				$now
			);
			foreach ( $rates as $r ) {
				if ( ! isset( $r['price'] ) || ! is_numeric( $r['price'] ) || $r['price'] < 0 ) {
					continue;
				}
				$max         = isset( $r['max_stay'] ) && is_numeric( $r['max_stay'] ) ? (string) (int) $r['max_stay'] : 'NULL';
				$rate_rows[] = $wpdb->prepare( '(%s,%d,%s,%d,', $maros_id, $room, $date, (int) ( $r['min_stay'] ?? 0 ) )
					. $max
					. $wpdb->prepare( ',%f,%s,%s)', (float) $r['price'], $currency, $now );
			}
		}

		$cal   = self::calendar_table();
		$rtab  = self::rates_table();
		$wpdb->query( 'START TRANSACTION' );
		$ok = false !== $wpdb->query( $wpdb->prepare( "DELETE FROM {$cal} WHERE property_id = %s", $maros_id ) ) // phpcs:ignore WordPress.DB.PreparedSQL
			&& false !== $wpdb->query( $wpdb->prepare( "DELETE FROM {$rtab} WHERE property_id = %s", $maros_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		foreach ( array_chunk( $cal_rows, 500 ) as $chunk ) {
			$ok = $ok && false !== $wpdb->query( "INSERT INTO {$cal} (property_id,room_type_id,date,available,min_stay,closed_arrival,closed_departure,synced_at) VALUES " . implode( ',', $chunk ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		foreach ( array_chunk( $rate_rows, 500 ) as $chunk ) {
			$ok = $ok && false !== $wpdb->query( "INSERT INTO {$rtab} (property_id,room_type_id,date,min_stay,max_stay,amount,currency,synced_at) VALUES " . implode( ',', $chunk ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		if ( ! $ok ) {
			$error = $wpdb->last_error;
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'maros_db', 'No se pudo guardar el calendario de ' . $maros_id . ': ' . $error );
		}
		$wpdb->query( 'COMMIT' );
		return count( $cal_rows );
	}

	/** Borra el calendario de una propiedad que deja de publicarse. */
	public static function delete( string $maros_id ): void {
		global $wpdb;
		$wpdb->delete( self::calendar_table(), array( 'property_id' => $maros_id ) );
		$wpdb->delete( self::rates_table(), array( 'property_id' => $maros_id ) );
	}

	/** Quita los días pasados (lo hace la tarea diaria). */
	public static function purge_past(): void {
		global $wpdb;
		$today = wp_date( 'Y-m-d' );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::calendar_table() . ' WHERE date < %s', $today ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::rates_table() . ' WHERE date < %s', $today ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/** Días y última carga de una propiedad, para la pantalla «marOS». */
	public static function summary( string $maros_id ): array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) AS days, SUM(available) AS free, MAX(synced_at) AS synced_at FROM ' . self::calendar_table() . ' WHERE property_id = %s', $maros_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		return array(
			'days'      => (int) ( $row['days'] ?? 0 ),
			'free'      => (int) ( $row['free'] ?? 0 ),
			'synced_at' => $row['synced_at'] ?? null,
		);
	}
}
