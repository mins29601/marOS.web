<?php
/**
 * Importa las opiniones que muestra cada ficha de la web actual (marbellainstyle.com, Site Reviews)
 * y las asigna a la propiedad nueva (Julio, 18-11-2026).
 *
 * Los datos están en opiniones-web-actual.json, sacados de las 18 fichas de /services/villas/ y
 * /services/apartments/ que tienen propiedad en marOS según redirects / inventario-urls.csv. Se
 * guardan con la misma Ability que usa el agente de Airbnb (Maros_Reviews::publish_review), con
 * origen «direct», así que:
 * - no se duplican si se vuelve a ejecutar (clave autor + fecha + texto);
 * - tampoco se crea una que ya esté en la ficha con el mismo texto desde otro portal (Airbnb…).
 *
 * El nombre del huésped va como nombre de pila con la inicial del apellido.
 *
 * Uso: wp eval-file scripts/setup/opiniones-web-actual.php [simular]
 * (en local: npm run wp -- eval-file wp-content/mis-scripts/setup/opiniones-web-actual.php)
 *
 * @package MarbellaInStyle
 */

if ( ! class_exists( 'Maros_Reviews' ) || ! function_exists( 'glsr_create_review' ) ) {
	WP_CLI::error( 'Faltan marOS Connector o Site Reviews.' );
}

$mis_simular = in_array( 'simular', $args ?? array(), true );
$mis_datos   = json_decode( (string) file_get_contents( __DIR__ . '/opiniones-web-actual.json' ), true );
if ( ! is_array( $mis_datos ) ) {
	WP_CLI::error( 'No puedo leer opiniones-web-actual.json.' );
}

// El registro de marOS apunta quién guardó cada opinión.
$mis_admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( $mis_admins ) {
	wp_set_current_user( (int) $mis_admins[0] );
}

$mis_norm = static fn( string $t ): string => trim( (string) preg_replace( '/\s+/u', ' ', mb_strtolower( wp_strip_all_tags( $t ) ) ) );

/** Textos de las opiniones que ya tiene la propiedad (cualquier origen). */
$mis_existentes = static function ( int $property ) use ( $mis_norm ): array {
	$out     = array();
	$reviews = glsr_get_reviews( array( 'assigned_posts' => $property, 'per_page' => 1000, 'status' => 'all' ) );
	foreach ( $reviews as $review ) {
		$out[ $mis_norm( (string) $review->content ) ] = (int) $review->ID;
	}
	return $out;
};

$mis_cuenta = array();
$mis_cache  = array();
foreach ( $mis_datos as $mis_r ) {
	$mid = (string) $mis_r['maros_id'];
	$mis_cuenta[ $mid ] = $mis_cuenta[ $mid ] ?? array( 'nuevas' => 0, 'ya_estaban' => 0, 'error' => '' );

	$found = get_posts(
		array(
			'post_type'      => 'mis_property',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => 'maros_id', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $mid, // phpcs:ignore WordPress.DB.SlowDBQuery
			'lang'           => function_exists( 'pll_default_language' ) ? (string) pll_default_language() : '',
		)
	);
	$property = (int) ( $found[0] ?? 0 );
	if ( ! $property ) {
		$mis_cuenta[ $mid ]['error'] = 'sin propiedad en la web';
		continue;
	}

	if ( ! isset( $mis_cache[ $property ] ) ) {
		$mis_cache[ $property ] = $mis_existentes( $property );
	}
	if ( isset( $mis_cache[ $property ][ $mis_norm( (string) $mis_r['content'] ) ] ) ) {
		++$mis_cuenta[ $mid ]['ya_estaban'];
		continue;
	}
	if ( $mis_simular ) {
		++$mis_cuenta[ $mid ]['nuevas'];
		continue;
	}

	$input = array_intersect_key(
		$mis_r,
		array_flip( array( 'source', 'author', 'author_location', 'date', 'rating', 'title', 'content', 'language' ) )
	);
	$input['property_id'] = $property;
	$res = Maros_Reviews::publish_review( $input );
	if ( is_wp_error( $res ) ) {
		WP_CLI::warning( sprintf( '%s · %s (%s): %s', $mid, $mis_r['author'], $mis_r['date'], $res->get_error_message() ) );
		$mis_cuenta[ $mid ]['error'] = $res->get_error_message();
		continue;
	}
	$res['created'] ? ++$mis_cuenta[ $mid ]['nuevas'] : ++$mis_cuenta[ $mid ]['ya_estaban'];
	$mis_cache[ $property ][ $mis_norm( (string) $mis_r['content'] ) ] = (int) $res['review_id'];
}

WP_CLI::log( $mis_simular ? 'Simulación (no se guarda nada):' : 'Opiniones de la web actual:' );
foreach ( $mis_cuenta as $mid => $c ) {
	WP_CLI::log( sprintf( '  %-16s nuevas %2d · ya estaban %2d%s', $mid, $c['nuevas'], $c['ya_estaban'], $c['error'] ? ' · ' . $c['error'] : '' ) );
}
WP_CLI::success(
	sprintf(
		'%d nuevas, %d ya estaban, de %d.',
		array_sum( array_column( $mis_cuenta, 'nuevas' ) ),
		array_sum( array_column( $mis_cuenta, 'ya_estaban' ) ),
		count( $mis_datos )
	)
);
