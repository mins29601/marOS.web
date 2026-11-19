<?php
/**
 * Slugs por idioma: Polylang (gratuito) no deja que dos traducciones compartan slug, y WordPress
 * resuelve el choque con «-2». En su lugar, la traducción lleva el código de su idioma:
 * /es/stay/villa-concha-es/ y no /es/stay/villa-concha-2/ (Julio, 19-11-2026). Es la misma
 * regla que ya siguen los términos (zonas, tipos de servicio: «<slug>-es»).
 *
 * - Al asignar el idioma a una entrada (Polylang guarda el idioma como término de la
 *   taxonomía «language»), si su slug es «<base>-N» y «<base>» ya lo usa otra entrada del mismo
 *   tipo, pasa a «<base>-<idioma>». Cubre el editor, marOS Connector y los scripts de setup.
 * - Lo ya creado se corrige con scripts/setup/slugs-idioma.php. WordPress guarda el slug
 *   anterior (_wp_old_slug) y redirige con 301 la URL vieja a la nueva.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

/**
 * Corrige el slug de una entrada traducida si WordPress le puso «-N» por chocar con otra.
 *
 * @return string El slug nuevo, o '' si no cambia.
 */
function mis_slug_idioma( int $post_id ): string {
	static $running = false;
	if ( $running || ! function_exists( 'pll_get_post_language' ) || ! function_exists( 'pll_default_language' ) ) {
		return '';
	}
	$post = get_post( $post_id );
	$lang = $post ? (string) pll_get_post_language( $post_id ) : '';
	if ( ! $post || ! $lang || pll_default_language() === $lang || 'attachment' === $post->post_type || 'revision' === $post->post_type ) {
		return '';
	}
	if ( ! preg_match( '/^(.+)-(\d+)$/', $post->post_name, $m ) ) {
		return '';
	}
	$base = $m[1];
	// Solo si «-N» viene de un choque: otra entrada del mismo tipo tiene ya el slug base.
	$otra = get_posts(
		array(
			'post_type'        => $post->post_type,
			'name'             => $base,
			'post_status'      => 'any',
			'numberposts'      => 1,
			'fields'           => 'ids',
			'lang'             => '',
			'exclude'          => array( $post_id ),
			'suppress_filters' => true,
		)
	);
	if ( ! $otra ) {
		return '';
	}
	$nuevo = wp_unique_post_slug( $base . '-' . $lang, $post_id, $post->post_status, $post->post_type, $post->post_parent );
	if ( $nuevo === $post->post_name ) {
		return '';
	}
	$running = true;
	wp_update_post( array( 'ID' => $post_id, 'post_name' => $nuevo ) );
	$running = false;
	return $nuevo;
}

add_action(
	'set_object_terms',
	static function ( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( 'language' === $taxonomy ) {
			mis_slug_idioma( (int) $object_id );
		}
	},
	20,
	4
);

// Al guardar una entrada que ya tiene idioma (p. ej. al cambiar el slug a mano en el editor).
add_action( 'wp_insert_post', static fn( $post_id ) => mis_slug_idioma( (int) $post_id ), 99 );
