<?php
/**
 * Importa las opiniones de las fichas de experiencias de la web actual (marbellainstyle.com,
 * Site Reviews, /services/tours/) y las asigna a la experiencia nueva en inglés y en español
 * (Julio, 24-11-2026: «nuestras actividades tienen información que todavía no está aquí»).
 *
 * Los datos están en opiniones-tours.json (todas las páginas de opiniones de cada ficha). Como en
 * opiniones-web-actual.php, el nombre va como nombre de pila con la inicial del apellido y no se
 * duplican si se vuelve a ejecutar (clave: experiencia + autor + fecha + texto).
 *
 * Uso: wp eval-file scripts/setup/opiniones-tours.php [simular]
 * (en local: npm run wp -- eval-file wp-content/mis-scripts/setup/opiniones-tours.php)
 * Antes: servicios.php, que crea las experiencias.
 *
 * @package MarbellaInStyle
 */

if ( ! function_exists( 'glsr_create_review' ) || ! function_exists( 'pll_get_post_translations' ) ) {
	WP_CLI::error( 'Faltan Site Reviews o Polylang.' );
}

$mis_simular = in_array( 'simular', $args ?? array(), true );
$mis_datos   = json_decode( (string) file_get_contents( __DIR__ . '/opiniones-tours.json' ), true );
if ( ! is_array( $mis_datos ) || empty( $mis_datos['opiniones'] ) ) {
	WP_CLI::error( 'No puedo leer opiniones-tours.json.' );
}

$mis_admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( $mis_admins ) {
	wp_set_current_user( (int) $mis_admins[0] );
}
$mis_status = class_exists( 'Maros_Reviews' ) ? Maros_Reviews::default_status() : 'publish';

$mis_cuenta = array();
foreach ( $mis_datos['opiniones'] as $r ) {
	$slug = (string) $r['tour'];
	$mis_cuenta[ $slug ] ??= array( 'nuevas' => 0, 'ya_estaban' => 0, 'error' => '' );

	$found = get_posts( array( 'post_type' => 'mis_tour', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'lang' => 'en' ) );
	if ( ! $found ) {
		$mis_cuenta[ $slug ]['error'] = 'sin experiencia en la web (ejecuta antes servicios.php)';
		continue;
	}
	$tour     = (int) $found[0];
	$assigned = array_values( array_unique( array_map( 'intval', array_merge( array( $tour ), array_values( (array) pll_get_post_translations( $tour ) ) ) ) ) );
	$key      = 'web-actual:' . $slug . ':' . md5( strtolower( (string) $r['author'] ) . '|' . $r['date'] . '|' . $r['content'] );

	$ya = get_posts( array( 'post_type' => 'site-review', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'lang' => '', 'meta_key' => '_mis_review_key', 'meta_value' => $key ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( $ya ) {
		++$mis_cuenta[ $slug ]['ya_estaban'];
		continue;
	}
	if ( $mis_simular ) {
		++$mis_cuenta[ $slug ]['nuevas'];
		continue;
	}
	$review = glsr_create_review(
		array(
			'assigned_posts' => $assigned,
			'name'           => sanitize_text_field( (string) $r['author'] ),
			'rating'         => (int) $r['rating'],
			'title'          => sanitize_text_field( (string) $r['title'] ),
			'content'        => sanitize_textarea_field( (string) $r['content'] ),
			'date'           => $r['date'] . ' 12:00:00',
		)
	);
	if ( ! $review || empty( $review->ID ) ) {
		$mis_cuenta[ $slug ]['error'] = 'Site Reviews no pudo guardar una opinión';
		continue;
	}
	update_post_meta( (int) $review->ID, '_mis_review_key', $key );
	update_post_meta( (int) $review->ID, '_mis_review_source', 'direct' );
	update_post_meta( (int) $review->ID, '_mis_review_language', 'en' );
	if ( get_post_status( (int) $review->ID ) !== $mis_status ) {
		wp_update_post( array( 'ID' => (int) $review->ID, 'post_status' => $mis_status ) );
	}
	++$mis_cuenta[ $slug ]['nuevas'];
}

WP_CLI::log( $mis_simular ? 'Simulación (no se guarda nada):' : 'Opiniones de las experiencias de la web actual:' );
foreach ( $mis_cuenta as $slug => $c ) {
	WP_CLI::log( sprintf( '  %-48s nuevas %2d · ya estaban %2d%s', $slug, $c['nuevas'], $c['ya_estaban'], $c['error'] ? ' · ' . $c['error'] : '' ) );
}
WP_CLI::success( sprintf( '%d nuevas, %d ya estaban, de %d.', array_sum( array_column( $mis_cuenta, 'nuevas' ) ), array_sum( array_column( $mis_cuenta, 'ya_estaban' ) ), count( $mis_datos['opiniones'] ) ) );
