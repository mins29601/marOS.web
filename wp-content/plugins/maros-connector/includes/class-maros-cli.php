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
	 * Rellena frase clave, título SEO y meta descripción de Yoast en todas las propiedades.
	 *
	 * Respeta lo editado a mano en Yoast salvo con --force.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Sobrescribe también los campos editados a mano.
	 *
	 * [--dry-run]
	 * : Solo muestra lo que se generaría.
	 */
	public function seo( $args, $assoc_args ) {
		$force = ! empty( $assoc_args['force'] );
		$dry   = ! empty( $assoc_args['dry-run'] );
		$ids   = get_posts( array( 'post_type' => 'mis_property', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'lang' => '', 'orderby' => 'ID', 'order' => 'ASC' ) );
		$rows  = array();
		foreach ( $ids as $id ) {
			$values  = maros_seo_values( (int) $id );
			$changed = $dry ? array() : maros_seo_apply( (int) $id, $force );
			$rows[]  = array(
				'id'       => $id,
				'lang'     => maros_is_es( (int) $id ) ? 'es' : 'en',
				'focuskw'  => $values['focuskw'],
				'title'    => $values['title'],
				'metadesc' => mb_strlen( $values['metadesc'] ) . ': ' . $values['metadesc'],
				'cambiado' => $dry ? '-' : ( implode( ',', $changed ) ?: 'nada' ),
			);
		}
		WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'lang', 'focuskw', 'title', 'metadesc', 'cambiado' ) );
	}

	/**
	 * Comprueba que cada propiedad exista en todos los idiomas (en, es) y con las traducciones vinculadas.
	 *
	 * Lista cada propiedad con su entrada en cada idioma (id, estado y slug) y las demás entradas con su
	 * id de marOS (duplicadas o en la papelera); avisa también del contenido publicado sin traducción.
	 * Con --fix vuelve a sincronizar desde marOS las propiedades incompletas: vincula la traducción
	 * suelta si existe y, si no, la crea.
	 *
	 * ## OPTIONS
	 *
	 * [--fix]
	 * : Repara las propiedades incompletas.
	 *
	 * ## EXAMPLES
	 *
	 *     wp maros idiomas
	 *     wp maros idiomas --fix
	 */
	public function idiomas( $args, $assoc_args ) {
		if ( ! function_exists( 'pll_get_post_language' ) ) {
			WP_CLI::error( 'Polylang no está activo.' );
		}
		$fix      = ! empty( $assoc_args['fix'] );
		$statuses = array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' );
		$all      = get_posts( array( 'post_type' => 'mis_property', 'post_status' => $statuses, 'posts_per_page' => -1, 'fields' => 'ids', 'lang' => '', 'orderby' => 'ID', 'order' => 'ASC' ) );
		$ids      = array_values( array_unique( array_filter( array_map( static fn( $id ) => (string) get_post_meta( $id, 'maros_id', true ), $all ) ) ) );
		$describe = static fn( int $id ): string => sprintf( '%d %s %s %s', $id, pll_get_post_language( $id ) ?: '?', get_post_status( $id ), get_post_field( 'post_name', $id ) );
		$rows     = array();
		$bad      = array();
		foreach ( $ids as $maros_id ) {
			$found = Maros_Sync::translations_of( $maros_id );
			$ok    = Maros_Sync::has_all_languages( $maros_id );
			$row   = array( 'maros_id' => $maros_id, 'nombre' => get_the_title( (int) ( $found['en'] ?? reset( $found ) ?: 0 ) ), 'ok' => $ok ? 'sí' : 'NO' );
			foreach ( Maros_Sync::LANGS as $lang ) {
				$row[ $lang ] = empty( $found[ $lang ] ) ? '—' : $describe( $found[ $lang ] );
			}
			$same         = array_map( 'intval', get_posts( array( 'post_type' => 'mis_property', 'post_status' => $statuses, 'posts_per_page' => -1, 'fields' => 'ids', 'lang' => '', 'meta_key' => 'maros_id', 'meta_value' => $maros_id ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			$row['otras'] = implode( ', ', array_map( $describe, array_diff( $same, array_values( $found ) ) ) );
			$rows[]       = $row;
			if ( ! $ok ) {
				$bad[] = $maros_id;
			}
		}
		WP_CLI\Utils\format_items( 'table', $rows, array_merge( array( 'maros_id', 'nombre', 'ok' ), Maros_Sync::LANGS, array( 'otras' ) ) );

		// Resto del contenido traducible publicado sin todas sus traducciones (solo aviso).
		$types = array_filter( get_post_types( array( 'public' => true ) ), static fn( $t ) => 'mis_property' !== $t && 'attachment' !== $t && pll_is_translated_post_type( $t ) );
		$langs = (array) pll_languages_list();
		foreach ( get_posts( array( 'post_type' => array_values( $types ), 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'lang' => '' ) ) as $id ) {
			$missing = array_diff( $langs, array_keys( (array) pll_get_post_translations( $id ) ) );
			if ( $missing ) {
				WP_CLI::warning( sprintf( '%s %d «%s» (%s) no tiene traducción en %s.', get_post_type( $id ), $id, get_the_title( $id ), pll_get_post_language( $id ) ?: 'sin idioma', implode( ', ', $missing ) ) );
			}
		}

		if ( ! $bad ) {
			WP_CLI::success( 'Todas las propiedades existen en ' . implode( ' y ', Maros_Sync::LANGS ) . ' y están vinculadas.' );
			return;
		}
		if ( ! $fix ) {
			WP_CLI::warning( 'Incompletas: ' . implode( ', ', $bad ) . '. Se reparan con: wp maros idiomas --fix' );
			return;
		}
		foreach ( $bad as $maros_id ) {
			$result = Maros_Scheduler::run_locked( static fn() => Maros_Sync::sync_property( $maros_id, null, false, true, true ) );
			$now    = Maros_Sync::translations_of( $maros_id );
			$text   = implode( ' · ', array_map( static fn( $id ) => $describe( $id ) . ' ' . wp_make_link_relative( (string) get_permalink( $id ) ), $now ) );
			if ( is_wp_error( $result ) ) {
				WP_CLI::warning( "$maros_id: " . $result->get_error_message() );
			} elseif ( Maros_Sync::has_all_languages( $maros_id ) ) {
				WP_CLI::log( "  ✓ $maros_id reparada: $text" );
			} else {
				WP_CLI::warning( "$maros_id sigue incompleta ($result): $text" );
			}
		}
	}

	/**
	 * Publica, en todos los idiomas, las propiedades que marOS da como publicables.
	 *
	 * Las que ya no están en la lista de marOS siguen en borrador (la sincronización las volvería a
	 * retirar). Pensado para pruebas; en producción las publica un editor (spec 003).
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Solo muestra lo que haría.
	 *
	 * ## EXAMPLES
	 *
	 *     wp maros publicar
	 */
	public function publicar( $args, $assoc_args ) {
		$dry  = ! empty( $assoc_args['dry-run'] );
		$list = Maros_Client::list_properties();
		if ( is_wp_error( $list ) ) {
			WP_CLI::error( $list->get_error_message() );
		}
		$publishable = array_map( 'strval', array_column( $list, 'id' ) );
		$known       = array_unique( array_filter( array_map( static fn( $id ) => (string) get_post_meta( $id, 'maros_id', true ), Maros_Sync::all_posts() ) ) );
		$count       = 0;
		foreach ( $publishable as $maros_id ) {
			$found = Maros_Sync::translations_of( $maros_id );
			if ( ! $found ) {
				WP_CLI::warning( "$maros_id es publicable en marOS pero aún no está en la web (wp maros sync --id=$maros_id)." );
				continue;
			}
			foreach ( $found as $lang => $post_id ) {
				if ( 'publish' === get_post_status( $post_id ) ) {
					continue;
				}
				if ( ! $dry ) {
					wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
				}
				++$count;
				WP_CLI::log( sprintf( '  ✓ %s %s «%s» %s', $maros_id, $lang, get_the_title( $post_id ), wp_make_link_relative( (string) get_permalink( $post_id ) ) ) );
			}
		}
		foreach ( array_diff( $known, $publishable ) as $maros_id ) {
			$found = Maros_Sync::translations_of( $maros_id );
			WP_CLI::log( sprintf( '  · %s «%s» sigue en borrador: no es publicable en marOS.', $maros_id, get_the_title( (int) ( reset( $found ) ?: 0 ) ) ) );
		}
		WP_CLI::success( sprintf( '%d traducciones %s.', $count, $dry ? 'se publicarían' : 'publicadas' ) );
	}

	/**
	 * Lista las zonas que no son una de las Areas de la web (las que creó la sincronización con el
	 * nombre libre de marOS, p. ej. una urbanización) y, con --borrar, las borra. Las casas que las
	 * tenían recuperan su Area en la siguiente sincronización.
	 *
	 * ## OPTIONS
	 *
	 * [--borrar]
	 * : Borra las zonas que sobran. Sin esta opción solo las muestra.
	 *
	 * ## EXAMPLES
	 *
	 *     wp maros zonas-sobrantes
	 *     wp maros zonas-sobrantes --borrar && wp maros sync
	 *
	 * @subcommand zonas-sobrantes
	 */
	public function zonas_sobrantes( $args, $assoc_args ) {
		$terms = get_terms( array( 'taxonomy' => 'zona', 'hide_empty' => false, 'lang' => '' ) );
		if ( is_wp_error( $terms ) ) {
			WP_CLI::error( $terms->get_error_message() );
		}
		$extra = array_filter( $terms, static fn( $t ) => '' === Maros_Sync::zone_name( preg_replace( '/-es$/', '', $t->slug ), false ) );
		foreach ( $extra as $t ) {
			WP_CLI::log( sprintf( '  · %s (%s): %d casas', $t->name, $t->slug, $t->count ) );
			if ( ! empty( $assoc_args['borrar'] ) ) {
				wp_delete_term( $t->term_id, 'zona' );
			}
		}
		WP_CLI::success( sprintf( '%d zonas que no son Areas de la web%s.', count( $extra ), $extra && ! empty( $assoc_args['borrar'] ) ? ', borradas' : '' ) );
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
