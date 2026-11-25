<?php
/**
 * Ficha PDF de la propiedad (marOS spec 057; Julio 25-11-2026: «En la web, hará falta añadir en la
 * ficha de la propiedad un enlace para descargar el pdf»).
 *
 * marOS genera el PDF con el diseño aprobado (GET /v1/catalog/properties/{id}/brochure?lang=es|en).
 * La web lo guarda en uploads/maros-fichas/ y lo sirve desde la ficha en «?maros_brochure=1». Antes
 * de servirlo pregunta a marOS, como mucho una vez por hora y casa, con el ETag guardado: si nada ha
 * cambiado, marOS contesta 304 sin generarlo otra vez. Si marOS no responde y hay copia, sirve la copia.
 *
 * El QR del PDF lleva a «/?maros_property={id}&lang=es|en», que aquí se redirige a la ficha de esa
 * casa en ese idioma.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

/** Minutos entre dos comprobaciones con marOS de la misma ficha. */
const MAROS_BROCHURE_CHECK = HOUR_IN_SECONDS;

/** Carpeta de los PDF, dentro de uploads. */
function maros_brochure_dir(): string {
	return trailingslashit( wp_upload_dir()['basedir'] ) . 'maros-fichas';
}

/** Ruta del PDF guardado de una casa e idioma. */
function maros_brochure_path( string $maros_id, string $lang ): string {
	return maros_brochure_dir() . '/' . $maros_id . '-' . $lang . '.pdf';
}

/**
 * El PDF al día de una casa, o WP_Error si no hay ninguno.
 *
 * @return string|WP_Error Ruta del archivo.
 */
function maros_brochure_file( string $maros_id, string $lang ) {
	$maros_id = Maros_Client::safe_id( $maros_id );
	if ( '' === $maros_id || ! in_array( $lang, array( 'es', 'en' ), true ) ) {
		return new WP_Error( 'maros_brochure', 'Ficha no válida.' );
	}
	$path    = maros_brochure_path( $maros_id, $lang );
	$option  = 'maros_brochure_etag_' . $maros_id . '_' . $lang;
	$checked = 'maros_brochure_checked_' . md5( $maros_id . $lang );
	$have    = is_readable( $path );
	if ( $have && get_transient( $checked ) ) {
		return $path;
	}

	$result = Maros_Client::get_brochure( $maros_id, $lang, $have ? (string) get_option( $option, '' ) : '' );
	if ( is_wp_error( $result ) ) {
		Maros_Log::add( 'error', 'Ficha PDF ' . $maros_id . ' (' . $lang . '): ' . $result->get_error_message() );
		// Mejor una ficha de hace un rato que ninguna; se vuelve a intentar en 5 minutos.
		if ( $have ) {
			set_transient( $checked, 1, 5 * MINUTE_IN_SECONDS );
			return $path;
		}
		return $result;
	}
	if ( empty( $result['not_modified'] ) ) {
		wp_mkdir_p( maros_brochure_dir() );
		$tmp = $path . '.' . wp_generate_password( 8, false ) . '.tmp';
		if ( false === file_put_contents( $tmp, $result['pdf'] ) || ! rename( $tmp, $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			wp_delete_file( $tmp );
			return $have ? $path : new WP_Error( 'maros_brochure', 'No se ha podido guardar la ficha PDF.' );
		}
		update_option( $option, (string) $result['etag'], false );
	}
	set_transient( $checked, 1, MAROS_BROCHURE_CHECK );
	return $path;
}

/** URL de descarga de la ficha de una propiedad (la de su idioma). */
function maros_brochure_url( int $post_id ): string {
	return add_query_arg( 'maros_brochure', '1', get_permalink( $post_id ) );
}

add_action(
	'template_redirect',
	static function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- descargas y redirecciones públicas, sin cambios.
		if ( isset( $_GET['maros_property'] ) ) {
			$id   = Maros_Client::safe_id( sanitize_text_field( wp_unslash( $_GET['maros_property'] ) ) );
			$lang = isset( $_GET['lang'] ) && 'es' === $_GET['lang'] ? 'es' : 'en';
			$post = $id ? Maros_Sync::find_by_maros_id( $id, $lang ) : 0;
			if ( ! $post && $id ) {
				$post = Maros_Sync::find_by_maros_id( $id, 'es' === $lang ? 'en' : 'es' );
			}
			$url = $post && 'publish' === get_post_status( $post ) ? get_permalink( $post ) : home_url( '/' );
			wp_safe_redirect( $url, 302, 'marOS' );
			exit;
		}
		if ( ! isset( $_GET['maros_brochure'] ) || ! is_singular( 'mis_property' ) ) {
			return;
		}
		// phpcs:enable
		$post_id  = (int) get_queried_object_id();
		$es       = maros_is_es( $post_id );
		$maros_id = (string) get_post_meta( $post_id, 'maros_id', true );
		$file     = get_post_meta( $post_id, 'maros_withdrawn', true ) ? new WP_Error( 'maros_brochure', 'Retirada.' ) : maros_brochure_file( $maros_id, $es ? 'es' : 'en' );
		if ( is_wp_error( $file ) ) {
			wp_die(
				esc_html( $es ? 'La ficha en PDF no está disponible ahora mismo. Vuelve a intentarlo en unos minutos.' : 'The PDF brochure is not available right now. Please try again in a few minutes.' ),
				esc_html( $es ? 'Ficha no disponible' : 'Brochure unavailable' ),
				array( 'response' => 503, 'back_link' => true )
			);
		}
		$name = ( $es ? 'ficha-' : 'brochure-' ) . sanitize_title( get_the_title( $post_id ) ) . '.pdf';
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . filesize( $file ) );
		header( 'X-Robots-Tag: noindex' );
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	},
	1
);

/** [maros_brochure] Enlace «Descargar ficha (PDF)» en la ficha de la propiedad. */
add_shortcode(
	'maros_brochure',
	static function () {
		$post_id = (int) get_the_ID();
		if ( ! $post_id || 'mis_property' !== get_post_type( $post_id ) || ! get_post_meta( $post_id, 'maros_id', true ) || get_post_meta( $post_id, 'maros_withdrawn', true ) ) {
			return '';
		}
		$es = maros_is_es( $post_id );
		return '<p class="mis-brochure"><a href="' . esc_url( maros_brochure_url( $post_id ) ) . '" rel="nofollow">'
			. maros_icon_svg( 'phosphor/file-pdf', 22 )
			. '<span>' . esc_html( $es ? 'Descargar ficha (PDF)' : 'Download brochure (PDF)' ) . '</span></a></p>';
	}
);
