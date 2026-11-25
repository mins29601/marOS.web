<?php
/**
 * Fotos de Inicio en el móvil (Julio, 25-11-2026, maqueta «home en móvil»): las tres tarjetas del
 * carrusel Stay / Concierge / Experiences y las fotos a todo lo ancho encima de «Exclusive homes»
 * y «Experiences».
 *
 * Se eligen en la página de Inicio (caja «Fotos de Inicio en el móvil», ids de Medios). Si una
 * está vacía, se usa la foto destacada de una casa o de una experiencia publicada, o la foto del
 * tipo de servicio de chef. Las mismas fotos sirven para los dos idiomas: se leen de la página
 * de Inicio en el idioma por defecto.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

/** Huecos de foto: clave => etiqueta de la caja. */
function mis_inicio_huecos(): array {
	return array(
		'stay'           => 'Carrusel: Stay',
		'concierge'      => 'Carrusel: Concierge',
		'experiences'    => 'Carrusel: Experiences',
		'banner_homes'   => 'Foto encima de «Exclusive homes»',
		'banner_tours'   => 'Foto encima de «Experiences»',
	);
}

/** Página de Inicio en el idioma por defecto (donde se guardan las fotos). */
function mis_inicio_pagina(): int {
	$id = (int) get_option( 'page_on_front' );
	if ( $id && function_exists( 'pll_get_post' ) && function_exists( 'pll_default_language' ) ) {
		$id = (int) ( pll_get_post( $id, pll_default_language() ) ?: $id );
	}
	return $id;
}

/** Ids de las fotos destacadas de un tipo de contenido, en el orden de los listados. */
function mis_inicio_destacadas( string $post_type ): array {
	$ids = get_posts(
		array(
			'post_type'        => $post_type,
			'numberposts'      => 6,
			'fields'           => 'ids',
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => false,
			'meta_key'         => '_thumbnail_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		)
	);
	return array_values( array_filter( array_map( 'get_post_thumbnail_id', $ids ), 'wp_attachment_is_image' ) );
}

/**
 * Id de Medios de un hueco, o 0 si no hay ninguna foto que poner.
 */
function mis_inicio_foto( string $hueco ): int {
	$propia = (int) get_post_meta( mis_inicio_pagina(), 'mis_inicio_' . $hueco, true );
	if ( $propia && wp_attachment_is_image( $propia ) ) {
		return $propia;
	}
	// Sin elegir: una foto destacada que no salga ya en las cuatro tarjetas que Inicio enseña en
	// el móvil (la quinta o la sexta), o la última que haya.
	$de = static function ( string $post_type, int $i ): int {
		$fotos = mis_inicio_destacadas( $post_type );
		return $fotos[ $i ] ?? ( $fotos ? end( $fotos ) : 0 );
	};
	switch ( $hueco ) {
		case 'banner_homes':
			return $de( 'mis_property', 4 );
		case 'stay':
			return $de( 'mis_property', 5 );
		case 'banner_tours':
			return $de( 'mis_tour', 4 );
		case 'experiences':
			return $de( 'mis_tour', 5 );
		case 'concierge':
			if ( function_exists( 'mis_servicio_tipos' ) && function_exists( 'mis_servicio_tipo_foto' ) ) {
				$tipos = mis_servicio_tipos();
				// El chef primero (es la foto de la maqueta); si no, el primer tipo con foto.
				usort( $tipos, static fn( $a, $b ) => (int) ( false === strpos( $a->slug, 'chef' ) ) <=> (int) ( false === strpos( $b->slug, 'chef' ) ) );
				foreach ( $tipos as $tipo ) {
					$foto = mis_servicio_tipo_foto( $tipo );
					if ( $foto ) {
						return $foto;
					}
				}
			}
			return 0;
	}
	return 0;
}

add_action(
	'add_meta_boxes_page',
	static function ( WP_Post $post ) {
		if ( $post->ID !== mis_inicio_pagina() ) {
			return;
		}
		add_meta_box(
			'mis-inicio-fotos',
			'Fotos de Inicio en el móvil',
			static function ( WP_Post $post ) {
				wp_nonce_field( 'mis_inicio', 'mis_inicio_nonce' );
				echo '<p>Id de Medios de cada foto (se ve en la dirección al abrirla en Medios). Vacío: se pone una foto de una casa o una experiencia publicada.</p>';
				foreach ( mis_inicio_huecos() as $key => $label ) {
					printf(
						'<p><label for="mis-inicio-%1$s">%2$s</label><br><input type="number" min="0" class="small-text" id="mis-inicio-%1$s" name="mis_inicio[%1$s]" value="%3$s"></p>',
						esc_attr( $key ),
						esc_html( $label ),
						esc_attr( (string) get_post_meta( $post->ID, 'mis_inicio_' . $key, true ) )
					);
				}
			},
			'page',
			'side'
		);
	}
);

add_action(
	'save_post_page',
	static function ( int $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['mis_inicio_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mis_inicio_nonce'] ) ), 'mis_inicio' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in = (array) wp_unslash( $_POST['mis_inicio'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se pasa a absint().
		foreach ( array_keys( mis_inicio_huecos() ) as $key ) {
			$id = absint( $in[ $key ] ?? 0 );
			$id ? update_post_meta( $post_id, 'mis_inicio_' . $key, $id ) : delete_post_meta( $post_id, 'mis_inicio_' . $key );
		}
	}
);
