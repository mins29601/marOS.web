<?php
/**
 * Cambia «-2» (o «-N») por el código del idioma en los slugs de las traducciones que ya existen:
 * /es/stay/villa-concha-2/ pasa a /es/stay/villa-concha-es/ (Julio, 19-11-2026). La regla para
 * lo que se cree a partir de ahora está en mis-contenido/includes/slugs.php. Idempotente.
 *
 * WordPress guarda el slug anterior y redirige con 301 la URL vieja a la nueva, así que los
 * enlaces antiguos siguen funcionando. Los menús enlazan por id y no hay que tocarlos. Al final
 * se listan las entradas cuyo texto enlaza a una URL vieja, para cambiarlas a mano.
 *
 * Uso: wp eval-file scripts/setup/slugs-idioma.php
 *
 * @package MarbellaInStyle
 */

if ( ! function_exists( 'mis_slug_idioma' ) || ! function_exists( 'pll_languages_list' ) ) {
	WP_CLI::error( 'Faltan Polylang o MIS Contenido.' );
}

$mis_tipos   = array_values( get_post_types( array( 'public' => true ) ) );
$mis_tipos   = array_diff( $mis_tipos, array( 'attachment' ) );
$mis_idiomas = array_diff( pll_languages_list(), array( pll_default_language() ) );
$mis_cambios = array();

foreach ( $mis_idiomas as $lang ) {
	$ids = get_posts( array( 'post_type' => $mis_tipos, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'lang' => $lang ) );
	foreach ( $ids as $id ) {
		$antes = get_post_field( 'post_name', $id );
		$url   = get_permalink( $id );
		$nuevo = mis_slug_idioma( (int) $id );
		if ( $nuevo ) {
			$mis_cambios[ wp_make_link_relative( $url ) ] = wp_make_link_relative( get_permalink( $id ) );
			WP_CLI::log( sprintf( '  ~ %s %s: %s → %s', get_post_type( $id ), $lang, $antes, $nuevo ) );
		}
	}
}

if ( ! $mis_cambios ) {
	WP_CLI::success( 'No hay slugs con «-N» que cambiar.' );
	return;
}

// Enlaces internos a las URLs viejas dentro de los textos.
global $wpdb;
foreach ( array_keys( $mis_cambios ) as $viejo ) {
	$like  = '%' . $wpdb->esc_like( $viejo ) . '%';
	$posts = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( $posts as $pid ) {
		WP_CLI::warning( "El texto de «" . get_the_title( $pid ) . "» ($pid) enlaza a $viejo (ahora {$mis_cambios[ $viejo ]}); la 301 lo cubre, pero conviene cambiarlo." );
	}
}
WP_CLI::success( count( $mis_cambios ) . ' slugs cambiados; las URLs viejas redirigen con 301.' );
