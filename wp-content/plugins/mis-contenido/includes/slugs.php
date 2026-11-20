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
	if ( ! $post || ! $lang || 'attachment' === $post->post_type || 'revision' === $post->post_type || '' === $post->post_name ) {
		return '';
	}
	$default = (string) pll_default_language();
	$set     = static function ( WP_Post $p, string $slug ) use ( &$running ): string {
		$slug = wp_unique_post_slug( $slug, $p->ID, $p->post_status, $p->post_type, $p->post_parent );
		if ( $slug === $p->post_name ) {
			return '';
		}
		$running = true;
		wp_update_post( array( 'ID' => $p->ID, 'post_name' => $slug ) );
		$running = false;
		return $slug;
	};
	$holder = static fn( string $name ): int => (int) ( get_posts(
		array(
			'post_type'        => $post->post_type,
			'name'             => $name,
			'post_status'      => 'any',
			'numberposts'      => 1,
			'fields'           => 'ids',
			'lang'             => '',
			'exclude'          => array( $post_id ),
			'suppress_filters' => true,
		)
	)[0] ?? 0 );
	$translations = function_exists( 'pll_get_post_translations' ) ? array_map( 'intval', (array) pll_get_post_translations( $post_id ) ) : array();

	if ( $default === $lang ) {
		// El idioma principal lleva el slug limpio: si quedó en «<base>-N» porque <base> lo tiene una
		// traducción suya (p. ej. al publicar la inglesa con la española ya publicada), la traducción
		// pasa a «<base>-<idioma>» y esta recupera <base>.
		if ( ! preg_match( '/^(.+)-(\d+)$/', $post->post_name, $m ) ) {
			return '';
		}
		$otra = $holder( $m[1] );
		$tr   = $otra ? array_search( $otra, $translations, true ) : false;
		if ( ! $tr || $tr === $default ) {
			return '';
		}
		$set( get_post( $otra ), $m[1] . '-' . $tr );
		return $holder( $m[1] ) ? '' : $set( get_post( $post_id ), $m[1] );
	}

	// Traducción: «<base>-N» por choque, o el mismo slug que su versión en el idioma principal
	// (los borradores pueden repetirlo y chocarían al publicar), pasa a «<base>-<idioma>».
	$principal = (int) ( $translations[ $default ] ?? 0 );
	$base      = '';
	if ( $principal && preg_replace( '/-\d+$/', '', (string) get_post_field( 'post_name', $principal ) ) === $post->post_name ) {
		$base = $post->post_name;
	} elseif ( preg_match( '/^(.+)-(\d+)$/', $post->post_name, $m ) && $holder( $m[1] ) ) {
		$base = $m[1];
	}
	if ( '' === $base ) {
		return '';
	}
	$nuevo = $set( $post, $base . '-' . $lang );
	// Si la principal se quedó con «-N» por culpa de esta, recupera ahora el slug limpio.
	if ( $nuevo && $principal && preg_match( '/^(.+)-(\d+)$/', (string) get_post_field( 'post_name', $principal ), $m ) && $m[1] === $base && ! $holder( $base ) ) {
		$set( get_post( $principal ), $base );
	}
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
