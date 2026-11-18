<?php
/**
 * Extracto automático de cada propiedad (Julio 18-11-2026: «debería actualizarse automáticamente en
 * base a la zona, capacidad y features principales»).
 *
 * Se escribe con los datos que ya hay en WordPress: tipo y zona (sus términos, con el nombre que les
 * dé el equipo), huéspedes, dormitorios y baños (marOS) y lo más destacado de la ficha
 * (maros_key_features). Se rehace en cada sincronización y al guardar la propiedad.
 *
 * Un extracto escrito a mano se respeta: el último texto automático se guarda en maros_excerpt_auto
 * y, si el extracto ya no es ese, es que alguien lo cambió. Para volver al automático basta con
 * vaciar el extracto y guardar. Las fichas anteriores a este cambio no tienen maros_excerpt_auto y
 * pasan al automático una vez.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

/** Meta con el último extracto automático de la ficha. */
const MAROS_EXCERPT_AUTO = 'maros_excerpt_auto';

/**
 * Extracto automático de una propiedad en su idioma.
 *
 * Ej.: «Villa de 4 dormitorios y 4 baños para 8 huéspedes en Sierra Blanca, con piscina privada
 * climatizada, vistas al mar y gimnasio.»
 */
function maros_auto_excerpt( int $post_id, bool $es ): string {
	$num = static fn( string $k ) => (int) get_post_meta( $post_id, $k, true );
	$beds  = $num( 'maros_bedrooms' );
	$baths = $num( 'maros_bathrooms' );
	$max   = $num( 'maros_max_guests' );

	$type = maros_excerpt_term( $post_id, 'tipo-propiedad' );
	$type = '' !== $type ? $type : ( $es ? 'Propiedad' : 'Property' );
	$zone = maros_excerpt_term( $post_id, 'zona' );
	if ( '' === $zone ) {
		$zone = (string) get_post_meta( $post_id, 'maros_area', true );
	}

	$features = array();
	foreach ( maros_key_features( $post_id, $es ) as $f ) {
		// Los dormitorios en-suite repetirían el número de dormitorios de la frase.
		if ( 'bed' === ( $f['icon'] ?? '' ) ) {
			continue;
		}
		$title = (string) ( $f['title'] ?? '' );
		// «Vistas al mar» → «vistas al mar»; los que empiezan por número se quedan igual.
		$features[] = preg_match( '/^\d/', $title ) ? $title : mb_strtolower( mb_substr( $title, 0, 1 ) ) . mb_substr( $title, 1 );
	}
	$features = array_slice( array_values( array_filter( $features ) ), 0, 3 );

	if ( $es ) {
		$sizes = array_filter(
			array(
				$beds > 0 ? sprintf( 1 === $beds ? '%d dormitorio' : '%d dormitorios', $beds ) : '',
				$baths > 0 ? sprintf( 1 === $baths ? '%d baño' : '%d baños', $baths ) : '',
			)
		);
		$text = $type . ( $sizes ? ' de ' . implode( ' y ', $sizes ) : '' );
		$text .= $max > 0 ? sprintf( 1 === $max ? ' para %d huésped' : ' para %d huéspedes', $max ) : '';
		$text .= '' !== $zone ? ' en ' . $zone : '';
		$text .= $features ? ', con ' . maros_excerpt_list( $features, ' y ' ) : '';
		return $text . '.';
	}

	$text = $beds > 0 ? sprintf( '%d-bedroom %s', $beds, mb_strtolower( $type ) ) : $type;
	$text .= $baths > 0 ? sprintf( 1 === $baths ? ' with %d bathroom' : ' with %d bathrooms', $baths ) : '';
	$text .= $max > 0 ? sprintf( 1 === $max ? ' for %d guest' : ' for %d guests', $max ) : '';
	$text .= '' !== $zone ? ' in ' . $zone : '';
	$text .= $features ? ', featuring ' . maros_excerpt_list( $features, ' and ' ) : '';
	return $text . '.';
}

/** Nombre del primer término de la propiedad en esa taxonomía, o ''. */
function maros_excerpt_term( int $post_id, string $tax ): string {
	$terms = get_the_terms( $post_id, $tax );
	return $terms && ! is_wp_error( $terms ) ? (string) $terms[0]->name : '';
}

/** «a, b y c». */
function maros_excerpt_list( array $items, string $and ): string {
	$last = array_pop( $items );
	return $items ? implode( ', ', $items ) . $and . $last : $last;
}

/**
 * Pone el extracto automático si el actual está vacío, es el automático anterior o la ficha aún no
 * tiene ninguno guardado. Un extracto escrito a mano no se toca.
 *
 * @param bool|null $es Idioma; null lo deduce de la propiedad (Polylang).
 * @return bool Si el extracto cambió.
 */
function maros_refresh_excerpt( int $post_id, ?bool $es = null ): bool {
	$post = get_post( $post_id );
	if ( ! $post || 'mis_property' !== $post->post_type ) {
		return false;
	}
	$current = trim( (string) $post->post_excerpt );
	$last    = get_post_meta( $post_id, MAROS_EXCERPT_AUTO, true );
	$manual  = '' !== $current && '' !== (string) $last && $current !== $last;
	if ( $manual ) {
		return false;
	}
	$auto = maros_auto_excerpt( $post_id, $es ?? maros_is_es( $post_id ) );
	update_post_meta( $post_id, MAROS_EXCERPT_AUTO, $auto );
	if ( $auto === $current ) {
		return false;
	}
	// Sin pasar por save_post: no crea revisión ni vuelve a llamar a este gancho.
	global $wpdb;
	$wpdb->update( $wpdb->posts, array( 'post_excerpt' => $auto ), array( 'ID' => $post_id ) );
	clean_post_cache( $post_id );
	return true;
}

/** Al guardar en el editor (p. ej. un cambio de zona o un extracto vaciado), se rehace. */
add_action(
	'save_post_mis_property',
	static function ( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		maros_refresh_excerpt( $post_id );
	},
	20
);

/** El editor de bloques guarda zona y tipo después de save_post: se repasa cuando ya están. */
add_action(
	'rest_after_insert_mis_property',
	static function ( WP_Post $post ): void {
		maros_refresh_excerpt( $post->ID );
	}
);
