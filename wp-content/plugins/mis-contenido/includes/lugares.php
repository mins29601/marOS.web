<?php
/**
 * Lugares recomendados (Julio, 18-11-2026): restaurantes, beach clubs, golf, supermercados…
 * que Marbella in Style recomienda en cada zona. La guía de zona los muestra bajo su sección
 * («Comer y salir», «Golf y deporte», «Servicios»), sin tocar el texto de la guía.
 *
 * Reglas de la guía editorial de marOS.content (§4) y de PC-17:
 * - solo locales que recomienda Marbella in Style (guía interna o post de @marbellainstyle con
 *   enlace y fecha); si en la zona no hay, los primeros de Tripadvisor con la fecha de consulta.
 *   Por eso la fuente es obligatoria;
 * - web oficial comprobada, con la fecha de la comprobación;
 * - sin teléfonos, sin distancias ni tiempos y sin superlativos («el mejor»).
 *
 * Un lugar es un solo registro para los dos idiomas (la frase corta va en inglés y en español) y
 * se asigna a las zonas del idioma por defecto; la ficha de zona en español busca por su
 * traducción. Se gestionan en el panel (Zonas → Lugares) y por el MCP (lugares-mcp.php).
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

const MIS_LUGAR_CPT = 'mis_lugar';

/**
 * Tipos de lugar: clave => [en, es, sección de la guía].
 */
function mis_lugar_tipos(): array {
	return array(
		'restaurant'   => array( 'Restaurant', 'Restaurante', 'comer' ),
		'beach_club'   => array( 'Beach club', 'Beach club', 'comer' ),
		'chiringuito'  => array( 'Beach bar', 'Chiringuito', 'comer' ),
		'bar'          => array( 'Bar', 'Bar', 'comer' ),
		'cafe'         => array( 'Café', 'Cafetería', 'comer' ),
		'golf'         => array( 'Golf', 'Golf', 'deporte' ),
		'sport'        => array( 'Sport', 'Deporte', 'deporte' ),
		'supermarket'  => array( 'Supermarket', 'Supermercado', 'servicios' ),
		'pharmacy'     => array( 'Pharmacy', 'Farmacia', 'servicios' ),
		'shopping'     => array( 'Shopping', 'Compras', 'servicios' ),
		'health'       => array( 'Health', 'Salud', 'servicios' ),
		'service'      => array( 'Service', 'Servicio', 'servicios' ),
	);
}

/**
 * Secciones de la guía de zona: clave => [título en, título es]. Son los <h2> de las guías
 * (scripts/setup/zonas.php); si la guía no tiene la sección, se añade al final.
 */
function mis_lugar_secciones(): array {
	return array(
		'comer'     => array( 'Eating out', 'Comer y salir' ),
		'deporte'   => array( 'Golf and sport', 'Golf y deporte' ),
		'servicios' => array( 'Services', 'Servicios' ),
	);
}

/** De dónde sale la recomendación (guía editorial §4): clave => etiqueta. */
function mis_lugar_fuentes(): array {
	return array(
		'guia'        => 'Guía interna de Marbella in Style',
		'instagram'   => 'Post de @marbellainstyle (enlace y fecha)',
		'tripadvisor' => 'Tripadvisor, primeros de la zona (fecha de consulta)',
	);
}

/**
 * Campos del lugar (metadatos): clave => [tipo, etiqueta].
 */
function mis_lugar_campos(): array {
	return array(
		'lugar_tipo'         => array( 'string', 'Tipo' ),
		'lugar_resumen_en'   => array( 'string', 'Frase corta en inglés' ),
		'lugar_resumen_es'   => array( 'string', 'Frase corta en español' ),
		'lugar_donde'        => array( 'string', 'Dónde (playa, urbanización, calle)' ),
		'lugar_web'          => array( 'string', 'Web oficial' ),
		'lugar_web_fecha'    => array( 'string', 'Web comprobada el (AAAA-MM-DD)' ),
		'lugar_fuente'       => array( 'string', 'Fuente de la recomendación' ),
		'lugar_fuente_url'   => array( 'string', 'Enlace de la fuente (post, Tripadvisor)' ),
		'lugar_fuente_fecha' => array( 'string', 'Fecha del post o de la consulta (AAAA-MM-DD)' ),
		'lugar_notas'        => array( 'string', 'Notas internas (no se publican)' ),
		'lugar_orden'        => array( 'integer', 'Orden en la zona (menor, antes)' ),
	);
}

add_action( 'init', 'mis_lugar_register', 6 );

function mis_lugar_register(): void {
	register_post_type(
		MIS_LUGAR_CPT,
		array(
			'labels'          => array(
				'name'               => 'Lugares',
				'singular_name'      => 'Lugar',
				'add_new'            => 'Añadir lugar',
				'add_new_item'       => 'Añadir lugar recomendado',
				'edit_item'          => 'Editar lugar',
				'search_items'       => 'Buscar lugares',
				'not_found'          => 'No hay lugares.',
				'menu_name'          => 'Lugares',
			),
			'description'     => 'Lugares que Marbella in Style recomienda en cada zona.',
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_icon'       => 'dashicons-location',
			'menu_position'   => 23,
			'show_in_rest'    => true,
			'rest_base'       => 'lugares',
			'capability_type' => array( 'mis_lugar', 'mis_lugares' ),
			'map_meta_cap'    => true,
			'supports'        => array( 'title', 'custom-fields', 'revisions' ),
			'taxonomies'      => array( 'zona' ),
		)
	);
	register_taxonomy_for_object_type( 'zona', MIS_LUGAR_CPT );

	foreach ( mis_lugar_campos() as $key => $field ) {
		register_post_meta(
			MIS_LUGAR_CPT,
			$key,
			array(
				'type'          => $field[0],
				'label'         => $field[1],
				'single'        => true,
				'show_in_rest'  => 'lugar_notas' !== $key,
				'auth_callback' => static fn() => current_user_can( 'edit_mis_lugares' ),
			)
		);
	}
}

// El editor clásico basta: un lugar no tiene cuerpo, solo campos.
add_filter( 'use_block_editor_for_post_type', static fn( $use, $type ) => MIS_LUGAR_CPT === $type ? false : $use, 10, 2 );

// ------------------------------------------------------------------ permisos y rol

/** Capacidades del tipo (capability_type mis_lugar / mis_lugares). */
function mis_lugar_caps(): array {
	return array(
		'edit_mis_lugares',
		'edit_others_mis_lugares',
		'edit_published_mis_lugares',
		'edit_private_mis_lugares',
		'publish_mis_lugares',
		'read_private_mis_lugares',
		'delete_mis_lugares',
		'delete_others_mis_lugares',
		'delete_published_mis_lugares',
		'delete_private_mis_lugares',
	);
}

/**
 * Rol «Gestor de lugares»: solo entra al panel para los lugares (y su perfil). Es el de Rebecca
 * para su ChatGPT: con esta cuenta no se pueden tocar casas, páginas ni opiniones.
 * Administradores y editores reciben las mismas capacidades.
 */
add_action(
	'init',
	static function () {
		$version = '1';
		if ( get_option( 'mis_lugar_caps' ) === $version ) {
			return;
		}
		$caps = array_fill_keys( mis_lugar_caps(), true );
		remove_role( 'mis_lugares' );
		add_role( 'mis_lugares', 'Gestor de lugares', array( 'read' => true ) + $caps );
		foreach ( array( 'administrator', 'editor' ) as $name ) {
			$role = get_role( $name );
			if ( $role ) {
				foreach ( array_keys( $caps ) as $cap ) {
					$role->add_cap( $cap );
				}
			}
		}
		update_option( 'mis_lugar_caps', $version, true );
	},
	7
);

// ------------------------------------------------------------------ datos

/**
 * Normaliza y valida los datos de un lugar (los usan el panel y el MCP).
 *
 * @param array $in      Campos sin prefijo: nombre, zonas, tipo, resumen_en, resumen_es, donde, web,
 *                       web_fecha, fuente, fuente_url, fuente_fecha, notas, orden.
 * @param array $current Datos actuales (al editar), con las mismas claves.
 * @return array|WP_Error
 */
function mis_lugar_validar( array $in, array $current = array() ) {
	$d = array_merge( $current, array_filter( $in, static fn( $v ) => null !== $v ) );

	$out = array(
		'nombre'       => trim( sanitize_text_field( (string) ( $d['nombre'] ?? '' ) ) ),
		'tipo'         => sanitize_key( (string) ( $d['tipo'] ?? '' ) ),
		'resumen_en'   => trim( sanitize_text_field( (string) ( $d['resumen_en'] ?? '' ) ) ),
		'resumen_es'   => trim( sanitize_text_field( (string) ( $d['resumen_es'] ?? '' ) ) ),
		'donde'        => trim( sanitize_text_field( (string) ( $d['donde'] ?? '' ) ) ),
		'web'          => trim( (string) ( $d['web'] ?? '' ) ),
		'web_fecha'    => trim( (string) ( $d['web_fecha'] ?? '' ) ),
		'fuente'       => sanitize_key( (string) ( $d['fuente'] ?? '' ) ),
		'fuente_url'   => trim( (string) ( $d['fuente_url'] ?? '' ) ),
		'fuente_fecha' => trim( (string) ( $d['fuente_fecha'] ?? '' ) ),
		'notas'        => trim( sanitize_textarea_field( (string) ( $d['notas'] ?? '' ) ) ),
		'orden'        => (int) ( $d['orden'] ?? 0 ),
		'zonas'        => array(),
	);

	$errors = array();
	if ( '' === $out['nombre'] ) {
		$errors[] = 'Falta el nombre.';
	}
	if ( ! isset( mis_lugar_tipos()[ $out['tipo'] ] ) ) {
		$errors[] = 'Tipo no válido. Usa uno de: ' . implode( ', ', array_keys( mis_lugar_tipos() ) ) . '.';
	}
	foreach ( array( 'resumen_en', 'resumen_es' ) as $key ) {
		if ( mb_strlen( $out[ $key ] ) > 140 ) {
			$errors[] = "La frase corta ($key) pasa de 140 caracteres.";
		}
	}
	if ( mb_strlen( $out['donde'] ) > 80 ) {
		$errors[] = '«donde» pasa de 80 caracteres: basta el nombre de la playa, la urbanización o la calle.';
	}

	// Sin teléfonos, distancias ni superlativos (guía editorial y PC-17).
	$texto = implode( ' ', array( $out['resumen_en'], $out['resumen_es'], $out['donde'] ) );
	if ( preg_match( '/\+?\d[\d\s().-]{7,}\d/', $texto ) ) {
		$errors[] = 'Quita el teléfono: la guía no da teléfonos.';
	}
	if ( preg_match( '/\b\d+([.,]\d+)?\s*(km|kilómetros?|kilometres?|kilometers?|m|metros?|metres?|meters?|min|minutos?|minutes?|mins?)\b/iu', $texto ) ) {
		$errors[] = 'Quita la distancia o el tiempo: solo se dan si se han medido, y en el texto de la guía, no en el lugar.';
	}
	if ( preg_match( '/\b(the best|best in|el mejor|la mejor|los mejores|las mejores|número uno|number one)\b/iu', $texto ) ) {
		$errors[] = 'Quita el superlativo («el mejor»…): decimos para qué lo recomendamos.';
	}

	if ( '' !== $out['web'] ) {
		$out['web'] = esc_url_raw( $out['web'], array( 'https', 'http' ) );
		if ( '' === $out['web'] ) {
			$errors[] = 'La web no es un enlace válido.';
		} elseif ( '' === $out['web_fecha'] ) {
			$out['web_fecha'] = wp_date( 'Y-m-d' );
		}
	}

	if ( ! isset( mis_lugar_fuentes()[ $out['fuente'] ] ) ) {
		$errors[] = 'Falta la fuente de la recomendación: guia, instagram o tripadvisor.';
	}
	if ( '' !== $out['fuente_url'] ) {
		$out['fuente_url'] = esc_url_raw( $out['fuente_url'], array( 'https' ) );
	}
	if ( 'instagram' === $out['fuente'] && ( '' === $out['fuente_url'] || '' === $out['fuente_fecha'] ) ) {
		$errors[] = 'Con fuente instagram hacen falta el enlace del post (fuente_url) y su fecha (fuente_fecha).';
	}
	if ( 'tripadvisor' === $out['fuente'] && '' === $out['fuente_fecha'] ) {
		$errors[] = 'Con fuente tripadvisor hace falta la fecha de consulta (fuente_fecha).';
	}
	foreach ( array( 'web_fecha', 'fuente_fecha' ) as $key ) {
		if ( '' !== $out[ $key ] && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $out[ $key ] ) ) {
			$errors[] = "$key debe ser AAAA-MM-DD.";
		}
	}

	$zonas = mis_lugar_resolver_zonas( (array) ( $d['zonas'] ?? array() ) );
	if ( is_wp_error( $zonas ) ) {
		$errors[] = $zonas->get_error_message();
	} elseif ( ! $zonas ) {
		$errors[] = 'Falta la zona (slug o nombre; usa la lista de zonas).';
	} else {
		$out['zonas'] = $zonas;
	}

	return $errors ? new WP_Error( 'mis_lugar_invalido', implode( ' ', $errors ) ) : $out;
}

/** Zona en el idioma por defecto (los lugares se asignan ahí). */
function mis_lugar_zona_base( int $term_id ): int {
	if ( function_exists( 'pll_get_term' ) && function_exists( 'pll_default_language' ) ) {
		$base = (int) pll_get_term( $term_id, (string) pll_default_language() );
		if ( $base ) {
			return $base;
		}
	}
	return $term_id;
}

/**
 * Convierte slugs, nombres (en o es) o ids de zona en ids de término del idioma por defecto.
 *
 * @return int[]|WP_Error
 */
function mis_lugar_resolver_zonas( array $zonas ) {
	$ids  = array();
	$fail = array();
	foreach ( $zonas as $z ) {
		$term = null;
		if ( is_numeric( $z ) ) {
			$term = get_term( (int) $z, 'zona' );
		} else {
			$z = trim( (string) $z );
			foreach ( array( 'slug' => sanitize_title( $z ), 'name' => $z ) as $field => $value ) {
				$found = get_terms( array( 'taxonomy' => 'zona', $field => $value, 'hide_empty' => false, 'lang' => '', 'number' => 1 ) );
				if ( ! is_wp_error( $found ) && $found ) {
					$term = $found[0];
					break;
				}
			}
		}
		if ( $term instanceof WP_Term && 'zona' === $term->taxonomy ) {
			$ids[] = mis_lugar_zona_base( (int) $term->term_id );
		} else {
			$fail[] = (string) $z;
		}
	}
	if ( $fail ) {
		return new WP_Error( 'mis_lugar_zona', 'No conozco estas zonas: ' . implode( ', ', $fail ) . '. Usa la lista de zonas.' );
	}
	return array_values( array_unique( $ids ) );
}

/**
 * Guarda un lugar validado. $id = 0 lo crea.
 *
 * @return int|WP_Error Id del lugar.
 */
function mis_lugar_guardar( array $data, int $id = 0, string $status = '' ) {
	$post = array(
		'post_type'  => MIS_LUGAR_CPT,
		'post_title' => $data['nombre'],
		'menu_order' => (int) $data['orden'],
	);
	if ( $id ) {
		$post['ID'] = $id;
	} else {
		$post['post_status'] = $status ?: 'draft';
	}
	if ( $id && $status ) {
		$post['post_status'] = $status;
	}
	$id = $id ? wp_update_post( wp_slash( $post ), true ) : wp_insert_post( wp_slash( $post ), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	foreach ( array( 'tipo', 'resumen_en', 'resumen_es', 'donde', 'web', 'web_fecha', 'fuente', 'fuente_url', 'fuente_fecha', 'notas' ) as $key ) {
		'' === $data[ $key ] ? delete_post_meta( $id, 'lugar_' . $key ) : update_post_meta( $id, 'lugar_' . $key, wp_slash( $data[ $key ] ) );
	}
	update_post_meta( $id, 'lugar_orden', (int) $data['orden'] );
	wp_set_object_terms( $id, $data['zonas'], 'zona' );
	return (int) $id;
}

/** Datos de un lugar con claves sin prefijo (para el MCP y para validar una edición). */
function mis_lugar_datos( int $id ): array {
	$post = get_post( $id );
	$out  = array(
		'id'     => $id,
		'nombre' => $post ? html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' ) : '',
		'estado' => $post ? $post->post_status : '',
	);
	foreach ( array( 'tipo', 'resumen_en', 'resumen_es', 'donde', 'web', 'web_fecha', 'fuente', 'fuente_url', 'fuente_fecha', 'notas' ) as $key ) {
		$out[ $key ] = (string) get_post_meta( $id, 'lugar_' . $key, true );
	}
	$out['orden'] = (int) get_post_meta( $id, 'lugar_orden', true );
	$terms        = wp_get_object_terms( $id, 'zona', array( 'fields' => 'all' ) );
	$out['zonas'] = is_wp_error( $terms ) ? array() : array_map( static fn( $t ) => $t->slug, $terms );
	return $out;
}

/**
 * Lugares publicados de una zona (cualquier idioma de la zona), por sección.
 *
 * @return array<string, WP_Post[]> sección => lugares.
 */
function mis_lugares_de_zona( WP_Term $term ): array {
	static $cache = array();
	$base = mis_lugar_zona_base( (int) $term->term_id );
	if ( isset( $cache[ $base ] ) ) {
		return $cache[ $base ];
	}
	$posts = get_posts(
		array(
			'post_type'        => MIS_LUGAR_CPT,
			'post_status'      => 'publish',
			'numberposts'      => 100,
			'orderby'          => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			'lang'             => '',
			'suppress_filters' => false,
			'tax_query'        => array( array( 'taxonomy' => 'zona', 'terms' => $base, 'include_children' => false ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		)
	);
	$tipos = mis_lugar_tipos();
	$out   = array();
	foreach ( $posts as $post ) {
		$tipo = (string) get_post_meta( $post->ID, 'lugar_tipo', true );
		$sec  = $tipos[ $tipo ][2] ?? 'servicios';
		$out[ $sec ][] = $post;
	}
	$cache[ $base ] = $out;
	return $out;
}

// ------------------------------------------------------------------ vista en la guía de zona

/** Lista de tarjetas de una sección. */
function mis_lugares_html( array $posts ): string {
	$es    = function_exists( 'mis_zona_es' ) && mis_zona_es();
	$tipos = mis_lugar_tipos();
	$items = '';
	foreach ( $posts as $post ) {
		$tipo   = (string) get_post_meta( $post->ID, 'lugar_tipo', true );
		$label  = $tipos[ $tipo ][ $es ? 1 : 0 ] ?? '';
		$donde  = (string) get_post_meta( $post->ID, 'lugar_donde', true );
		$frase  = (string) get_post_meta( $post->ID, $es ? 'lugar_resumen_es' : 'lugar_resumen_en', true );
		$web    = (string) get_post_meta( $post->ID, 'lugar_web', true );
		$nombre = esc_html( get_the_title( $post ) );
		$meta   = implode( ' · ', array_filter( array( $label, $donde ) ) );

		$items .= '<li class="mis-lugar">'
			. '<p class="mis-lugar__name">' . ( $web ? '<a href="' . esc_url( $web ) . '" target="_blank" rel="noopener">' . $nombre . '<span class="screen-reader-text"> ' . esc_html( $es ? '(se abre su web)' : '(opens their website)' ) . '</span></a>' : $nombre ) . '</p>'
			. ( '' !== $meta ? '<p class="mis-lugar__meta">' . esc_html( $meta ) . '</p>' : '' )
			. ( '' !== $frase ? '<p class="mis-lugar__text">' . esc_html( $frase ) . '</p>' : '' )
			. '</li>';
	}
	return '<ul class="mis-lugares">' . $items . '</ul>';
}

/**
 * Inserta los lugares en la guía de la zona: bajo el último párrafo de su sección (el <h2>
 * «Comer y salir»…), o como sección nueva al final si la guía no la tiene.
 */
function mis_lugares_en_guia( string $html, WP_Term $term ): string {
	$lugares = mis_lugares_de_zona( $term );
	if ( ! $lugares ) {
		return $html;
	}
	$es    = function_exists( 'mis_zona_es' ) && mis_zona_es();
	$norm  = static fn( string $s ) => mb_strtolower( trim( html_entity_decode( wp_strip_all_tags( $s ), ENT_QUOTES, 'UTF-8' ) ) );
	$parts = preg_split( '/(?=<h2[\s>])/i', $html );

	foreach ( mis_lugar_secciones() as $sec => $titles ) {
		if ( empty( $lugares[ $sec ] ) ) {
			continue;
		}
		$list = mis_lugares_html( $lugares[ $sec ] );
		$done = false;
		foreach ( $parts as $i => $part ) {
			if ( preg_match( '/^<h2[^>]*>(.*?)<\/h2>/is', $part, $m ) && in_array( $norm( $m[1] ), array_map( $norm, $titles ), true ) ) {
				// Antes del cierre del bloque si la parte es la última (lleva el </div> del bloque).
				$parts[ $i ] = preg_match( '/<\/div>\s*$/', $part ) && count( $parts ) - 1 === $i
					? preg_replace( '/<\/div>\s*$/', $list . '</div>', $part )
					: $part . $list;
				$done        = true;
				break;
			}
		}
		if ( ! $done ) {
			$extra = '<h2 class="wp-block-heading">' . esc_html( $titles[ $es ? 1 : 0 ] ) . '</h2>' . $list;
			$last  = count( $parts ) - 1;
			$parts[ $last ] = preg_match( '/<\/div>\s*$/', $parts[ $last ] )
				? preg_replace( '/<\/div>\s*$/', $extra . '</div>', $parts[ $last ] )
				: $parts[ $last ] . $extra;
		}
	}
	return implode( '', $parts );
}

add_filter(
	'render_block_core/term-description',
	static function ( $content ) {
		$term = function_exists( 'mis_zona_actual' ) ? mis_zona_actual() : null;
		return $term ? mis_lugares_en_guia( (string) $content, $term ) : $content;
	}
);

// ------------------------------------------------------------------ panel

add_action(
	'add_meta_boxes_' . MIS_LUGAR_CPT,
	static function () {
		add_meta_box( 'mis_lugar_datos', 'Datos del lugar', 'mis_lugar_caja', MIS_LUGAR_CPT, 'normal', 'high' );
	}
);

function mis_lugar_caja( WP_Post $post ): void {
	$d = mis_lugar_datos( (int) $post->ID );
	wp_nonce_field( 'mis_lugar', 'mis_lugar_nonce' );
	echo '<p class="description">Solo locales que recomienda Marbella in Style (guía interna o post de @marbellainstyle); si en la zona no hay, los primeros de Tripadvisor con la fecha de consulta. Sin teléfonos, distancias ni «el mejor». Las zonas se marcan en la caja «Zonas».</p>';
	echo '<table class="form-table" role="presentation">';

	$opts = '<option value="">—</option>';
	foreach ( mis_lugar_tipos() as $key => $t ) {
		$opts .= '<option value="' . esc_attr( $key ) . '"' . selected( $d['tipo'], $key, false ) . '>' . esc_html( $t[1] ) . '</option>';
	}
	printf( '<tr><th><label for="lugar_tipo">Tipo</label></th><td><select id="lugar_tipo" name="lugar[tipo]" required>%s</select></td></tr>', $opts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	$text = static function ( string $key, string $label, string $type = 'text', string $help = '' ) use ( $d ) {
		printf(
			'<tr><th><label for="lugar_%1$s">%2$s</label></th><td><input type="%3$s" id="lugar_%1$s" name="lugar[%1$s]" value="%4$s" class="regular-text">%5$s</td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( (string) $d[ $key ] ),
			$help ? '<p class="description">' . esc_html( $help ) . '</p>' : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	};
	$text( 'resumen_en', 'Frase corta en inglés', 'text', 'Para qué lo recomendamos, en inglés británico. 140 caracteres como mucho.' );
	$text( 'resumen_es', 'Frase corta en español', 'text', 'Lo mismo en español, de tú.' );
	$text( 'donde', 'Dónde', 'text', 'Playa, urbanización o calle (p. ej. «Guadalmansa»).' );
	$text( 'web', 'Web oficial', 'url' );
	$text( 'web_fecha', 'Web comprobada el', 'date' );

	$opts = '<option value="">—</option>';
	foreach ( mis_lugar_fuentes() as $key => $label ) {
		$opts .= '<option value="' . esc_attr( $key ) . '"' . selected( $d['fuente'], $key, false ) . '>' . esc_html( $label ) . '</option>';
	}
	printf( '<tr><th><label for="lugar_fuente">Fuente</label></th><td><select id="lugar_fuente" name="lugar[fuente]" required>%s</select></td></tr>', $opts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$text( 'fuente_url', 'Enlace de la fuente', 'url' );
	$text( 'fuente_fecha', 'Fecha del post o de la consulta', 'date' );
	$text( 'orden', 'Orden en la zona', 'number', 'Menor, antes. Con el mismo número, por nombre.' );
	printf( '<tr><th><label for="lugar_notas">Notas internas</label></th><td><textarea id="lugar_notas" name="lugar[notas]" rows="3" class="large-text">%s</textarea><p class="description">No se publican.</p></td></tr>', esc_textarea( $d['notas'] ) );
	echo '</table>';
}

add_action(
	'save_post_' . MIS_LUGAR_CPT,
	static function ( int $post_id, WP_Post $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['mis_lugar_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mis_lugar_nonce'] ) ), 'mis_lugar' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in = (array) wp_unslash( $_POST['lugar'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se sanea en mis_lugar_validar().
		foreach ( array( 'tipo', 'resumen_en', 'resumen_es', 'donde', 'web', 'web_fecha', 'fuente', 'fuente_url', 'fuente_fecha', 'notas' ) as $key ) {
			$val = 'notas' === $key ? sanitize_textarea_field( (string) ( $in[ $key ] ?? '' ) ) : sanitize_text_field( (string) ( $in[ $key ] ?? '' ) );
			if ( in_array( $key, array( 'web', 'fuente_url' ), true ) ) {
				$val = esc_url_raw( $val );
			}
			'' === $val ? delete_post_meta( $post_id, 'lugar_' . $key ) : update_post_meta( $post_id, 'lugar_' . $key, wp_slash( $val ) );
		}
		update_post_meta( $post_id, 'lugar_orden', (int) ( $in['orden'] ?? 0 ) );
		if ( ! get_post_meta( $post_id, 'lugar_web_fecha', true ) && get_post_meta( $post_id, 'lugar_web', true ) ) {
			update_post_meta( $post_id, 'lugar_web_fecha', wp_date( 'Y-m-d' ) );
		}
		// Si se publica sin pasar las reglas, se avisa (el panel no lo bloquea: lo revisa una persona).
		$check = mis_lugar_validar( mis_lugar_datos( $post_id ) );
		if ( is_wp_error( $check ) ) {
			set_transient( 'mis_lugar_aviso_' . get_current_user_id(), $check->get_error_message(), 60 );
		}
	},
	10,
	2
);

// El orden del panel también va a menu_order, que es por lo que se ordena en la guía.
add_filter(
	'wp_insert_post_data',
	static function ( array $data, array $postarr ) {
		if ( MIS_LUGAR_CPT === $data['post_type'] && isset( $postarr['lugar']['orden'], $postarr['mis_lugar_nonce'] ) ) {
			$data['menu_order'] = (int) $postarr['lugar']['orden'];
		}
		return $data;
	},
	10,
	2
);

add_action(
	'admin_notices',
	static function () {
		$key = 'mis_lugar_aviso_' . get_current_user_id();
		$msg = get_transient( $key );
		if ( $msg ) {
			delete_transient( $key );
			echo '<div class="notice notice-warning"><p><strong>Revisa este lugar:</strong> ' . esc_html( (string) $msg ) . '</p></div>';
		}
	}
);

// Columnas del listado: tipo y fuente.
add_filter(
	'manage_' . MIS_LUGAR_CPT . '_posts_columns',
	static function ( array $cols ): array {
		$out = array();
		foreach ( $cols as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'title' === $k ) {
				$out['lugar_tipo']   = 'Tipo';
				$out['lugar_fuente'] = 'Fuente';
			}
		}
		return $out;
	}
);
add_action(
	'manage_' . MIS_LUGAR_CPT . '_posts_custom_column',
	static function ( string $col, int $post_id ) {
		if ( 'lugar_tipo' === $col ) {
			$tipo = (string) get_post_meta( $post_id, 'lugar_tipo', true );
			echo esc_html( mis_lugar_tipos()[ $tipo ][1] ?? $tipo );
		} elseif ( 'lugar_fuente' === $col ) {
			$fuente = (string) get_post_meta( $post_id, 'lugar_fuente', true );
			$fecha  = (string) get_post_meta( $post_id, 'lugar_fuente_fecha', true );
			echo esc_html( trim( $fuente . ( $fecha ? ' · ' . $fecha : '' ) ) );
		}
	},
	10,
	2
);
