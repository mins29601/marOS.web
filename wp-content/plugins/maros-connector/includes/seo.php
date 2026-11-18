<?php
/**
 * Yoast SEO en las fichas de propiedad (Julio 18-11-2026: «cada propiedad debería cumplir con todo
 * lo que se exige»).
 *
 * - Frase clave, título SEO y meta descripción de Yoast salen solos de marOS (tipo, dormitorios,
 *   zona, huéspedes y key features) mientras nadie los edite a mano: se guarda el último valor
 *   automático en maros_seo_auto y, si el campo de Yoast ya no coincide, se respeta. Vaciarlo en
 *   Yoast vuelve al automático.
 * - Yoast solo analiza el contenido del editor; aquí se le pasa también lo que pinta la plantilla
 *   (key features, dormitorios, comodidades, ubicación con el enlace a la guía de la zona, FAQ y la
 *   foto principal), para que el análisis sea el de la página real.
 * - Datos estructurados VacationRental en el grafo de Yoast y noindex en archivos pobres.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

/** Largo máximo del título SEO en caracteres (Yoast mide ~600 px; unos 65 caracteres en texto normal). */
const MAROS_SEO_TITLE_MAX = 65;

/** Meta descripción: Yoast la quiere entre 120 y 156 caracteres. */
const MAROS_SEO_DESC_MIN = 120;
const MAROS_SEO_DESC_MAX = 156;

/** Nombre del primer término de una taxonomía en el idioma del post. */
function maros_seo_term( int $post_id, string $tax ): string {
	$terms = get_the_terms( $post_id, $tax );
	return $terms && ! is_wp_error( $terms ) ? (string) $terms[0]->name : '';
}

/** Zona de la propiedad: término «zona» o, si falta, el área de marOS. */
function maros_seo_zone( int $post_id ): string {
	return maros_seo_term( $post_id, 'zona' ) ?: trim( (string) get_post_meta( $post_id, 'maros_area', true ) );
}

/** Primera letra en mayúscula / minúscula sin romper tildes. */
function maros_seo_ucfirst( string $s ): string {
	return mb_strtoupper( mb_substr( $s, 0, 1 ) ) . mb_substr( $s, 1 );
}
function maros_seo_lcfirst( string $s ): string {
	// «Wi-Fi», «BBQ»: solo se baja si la segunda letra ya es minúscula.
	$second = mb_substr( $s, 1, 1 );
	return $second === mb_strtolower( $second ) ? mb_strtolower( mb_substr( $s, 0, 1 ) ) . mb_substr( $s, 1 ) : $s;
}

/**
 * Nombre de la propiedad en texto plano: get_the_title() lo devuelve con entidades HTML («&#8211;»),
 * que en los campos de Yoast se verían tal cual.
 */
function maros_seo_name( int $post_id ): string {
	return trim( html_entity_decode( wp_strip_all_tags( get_the_title( $post_id ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
}

/**
 * Frase clave genérica: «villa de 5 dormitorios en Nueva Andalucía, Marbella» / «5-bedroom villa in
 * Nueva Andalucía, Marbella». Lleva el municipio porque la zona puede ser de grano muy fino (Julio
 * 18-11-2026). Si marOS no trae zona, va solo el municipio; si no trae tipo, «alojamiento» /
 * «holiday home»: así la ficha en español nunca se queda con el nombre en inglés de Lodgify.
 */
function maros_seo_generic_keyphrase( int $post_id ): string {
	$es   = maros_is_es( $post_id );
	$town = trim( (string) get_post_meta( $post_id, 'maros_municipality', true ) ) ?: 'Marbella';
	$zone = maros_seo_zone( $post_id );
	if ( '' === $zone ) {
		$zone = $town;
	} elseif ( 0 !== strcasecmp( remove_accents( $town ), remove_accents( $zone ) ) ) {
		$zone .= ', ' . $town;
	}
	$type = mb_strtolower( maros_seo_term( $post_id, 'tipo-propiedad' ) ?: ( $es ? 'Alojamiento' : 'Holiday home' ) );
	$beds = (int) get_post_meta( $post_id, 'maros_bedrooms', true );
	if ( $es ) {
		$beds_txt = $beds > 0 ? sprintf( 1 === $beds ? ' de %d dormitorio' : ' de %d dormitorios', $beds ) : '';
		return $type . $beds_txt . ' en ' . $zone;
	}
	return ( $beds > 0 ? $beds . '-bedroom ' : '' ) . $type . ' in ' . $zone;
}

/** Propiedades publicadas o en borrador del mismo idioma (sin la propia). */
function maros_seo_siblings( int $post_id ): array {
	$args = array(
		'post_type'      => 'mis_property',
		'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'no_found_rows'  => true,
		'post__not_in'   => array( $post_id ),
	);
	if ( function_exists( 'pll_get_post_language' ) ) {
		$args['lang'] = (string) pll_get_post_language( $post_id );
	}
	return get_posts( $args );
}

/**
 * Frase clave de la propiedad. Si otra propiedad más antigua del mismo idioma tiene la misma frase
 * genérica, esta usa «Nombre + zona» para que Yoast no la marque como repetida. Así el resultado no
 * depende del orden en que se sincronizan.
 */
function maros_seo_keyphrase( int $post_id ): string {
	$generic = maros_seo_generic_keyphrase( $post_id );
	$name    = maros_seo_name( $post_id );
	$zone    = maros_seo_zone( $post_id );
	if ( '' === $generic ) {
		return $name;
	}
	foreach ( maros_seo_siblings( $post_id ) as $other ) {
		if ( $other < $post_id && maros_seo_generic_keyphrase( $other ) === $generic ) {
			$town = trim( (string) get_post_meta( $post_id, 'maros_municipality', true ) ) ?: 'Marbella';
			return trim( $name . ' ' . $zone . ( false === mb_stripos( $zone, $town ) ? ' ' . $town : '' ) );
		}
	}
	return $generic;
}

/** Título SEO: la frase clave delante (Yoast lo pide), el nombre y la marca si caben. */
function maros_seo_title( int $post_id, string $keyphrase ): string {
	$name = maros_seo_name( $post_id );
	$site = get_bloginfo( 'name' );
	$kw   = maros_seo_ucfirst( $keyphrase );
	$has  = false !== mb_stripos( $keyphrase, $name );
	$candidates = array_filter(
		array(
			$has ? '' : $kw . ' · ' . $name . ' | ' . $site,
			$has ? '' : $kw . ' · ' . $name,
			$kw . ' | ' . $site,
			$kw,
		)
	);
	foreach ( $candidates as $title ) {
		if ( mb_strlen( $title ) <= MAROS_SEO_TITLE_MAX ) {
			return $title;
		}
	}
	return $kw;
}

/**
 * Meta descripción: frase clave, huéspedes y las key features que quepan, entre 120 y 156 caracteres.
 */
function maros_seo_description( int $post_id, string $keyphrase ): string {
	$es     = maros_is_es( $post_id );
	$guests = (int) get_post_meta( $post_id, 'maros_max_guests', true );
	$name   = maros_seo_name( $post_id );
	// «Villa Concha, villa de 5 dormitorios en…»: con la frase genérica también cuando la clave es
	// «Nombre + zona», porque así lleva todas sus palabras y se lee como una frase.
	$generic = maros_seo_generic_keyphrase( $post_id ) ?: $keyphrase;
	$lead    = false !== mb_stripos( $generic, $name ) ? maros_seo_ucfirst( $generic ) : $name . ', ' . $generic;
	if ( $guests > 0 ) {
		$lead .= sprintf( $es ? ' para %d huéspedes' : ' for %d guests', $guests );
	}
	$features = function_exists( 'maros_key_features' ) ? array_map( static fn( $f ) => maros_seo_lcfirst( (string) $f['title'] ), maros_key_features( $post_id, $es ) ) : array();
	$close    = $es ? ' Reserva directa.' : ' Book direct.';
	$extra    = $es ? ' Conserjería para chef, traslados y experiencias.' : ' Concierge for chefs, transfers and experiences.';

	$join = static function ( array $items ) use ( $es ): string {
		if ( count( $items ) < 2 ) {
			return implode( '', $items );
		}
		$last = array_pop( $items );
		return implode( ', ', $items ) . ( $es ? ' y ' : ' and ' ) . $last;
	};

	$text = $lead . '.';
	for ( $n = count( $features ); $n >= 0; $n-- ) {
		$with = $n > 0 ? $lead . ( $es ? ', con ' : ', with ' ) . $join( array_slice( $features, 0, $n ) ) . '.' : $lead . '.';
		if ( mb_strlen( $with . $close ) <= MAROS_SEO_DESC_MAX ) {
			$text = $with;
			break;
		}
	}
	$text .= $close;
	if ( mb_strlen( $text ) < MAROS_SEO_DESC_MIN && mb_strlen( $text . $extra ) <= MAROS_SEO_DESC_MAX ) {
		$text .= $extra;
	}
	if ( mb_strlen( $text ) > MAROS_SEO_DESC_MAX ) {
		$text = rtrim( mb_substr( $text, 0, MAROS_SEO_DESC_MAX - 1 ), " ,.;" ) . '…';
	}
	return $text;
}

/** Valores automáticos de Yoast para una propiedad. */
function maros_seo_values( int $post_id ): array {
	$kw = maros_seo_keyphrase( $post_id );
	return array(
		'focuskw'  => $kw,
		'title'    => maros_seo_title( $post_id, $kw ),
		'metadesc' => maros_seo_description( $post_id, $kw ),
	);
}

/**
 * Escribe frase clave, título y meta descripción de Yoast de una propiedad. Un campo que alguien ha
 * cambiado a mano (no coincide con el último automático y no está vacío) no se toca.
 *
 * @param bool $force También los editados a mano (wp maros seo --force).
 * @return string[] Campos que ha cambiado.
 */
function maros_seo_apply( int $post_id, bool $force = false ): array {
	if ( 'mis_property' !== get_post_type( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return array();
	}
	$auto    = json_decode( (string) get_post_meta( $post_id, 'maros_seo_auto', true ), true );
	$auto    = is_array( $auto ) ? $auto : array();
	$values  = maros_seo_values( $post_id );
	$changed = array();
	foreach ( $values as $field => $value ) {
		$key     = '_yoast_wpseo_' . $field;
		$current = (string) get_post_meta( $post_id, $key, true );
		$manual  = '' !== $current && $current !== (string) ( $auto[ $field ] ?? '' );
		if ( $manual && ! $force ) {
			continue;
		}
		$auto[ $field ] = $value;
		if ( $current !== $value ) {
			update_post_meta( $post_id, $key, wp_slash( $value ) );
			$changed[] = $field;
		}
	}
	update_post_meta( $post_id, 'maros_seo_auto', wp_slash( wp_json_encode( $auto, JSON_UNESCAPED_UNICODE ) ) );
	return $changed;
}

/** Propiedades (todas las traducciones) que hay que regenerar al final de la petición. */
function maros_seo_queue( int $post_id = 0 ): array {
	static $queue = array();
	if ( $post_id ) {
		$queue[ $post_id ] = true;
	}
	return array_keys( $queue );
}

// Tras la sincronización: la última meta que escribe Maros_Sync en cada traducción es maros_etag.
$maros_seo_on_meta = static function ( $meta_id, $post_id, $key ) {
	if ( 'maros_etag' === $key && 'mis_property' === get_post_type( (int) $post_id ) ) {
		maros_seo_queue( (int) $post_id );
	}
};
add_action( 'added_post_meta', $maros_seo_on_meta, 10, 3 );
add_action( 'updated_post_meta', $maros_seo_on_meta, 10, 3 );

// Al guardar en el editor (después de que Yoast guarde sus campos) y al cambiar zona o tipo.
add_action( 'save_post_mis_property', static fn( $post_id ) => maros_seo_queue( (int) $post_id ), 99 );
add_action(
	'set_object_terms',
	static function ( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( in_array( $taxonomy, array( 'zona', 'tipo-propiedad', 'equipamiento' ), true ) && 'mis_property' === get_post_type( (int) $object_id ) ) {
			maros_seo_queue( (int) $object_id );
		}
	},
	10,
	4
);
add_action(
	'shutdown',
	static function () {
		foreach ( maros_seo_queue() as $post_id ) {
			maros_seo_apply( (int) $post_id );
		}
	},
	5
);

/* ------------------------------------------------------------------------------------------------
 * Análisis de Yoast con la página completa.
 * ---------------------------------------------------------------------------------------------- */

/**
 * HTML que la plantilla single-mis_property añade alrededor del contenido del editor, tal como se
 * ve en la web, para el análisis de Yoast.
 */
function maros_seo_template_html( int $post_id ): string {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}
	$es = maros_is_es( $post_id );
	$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	setup_postdata( $post );

	$html = '';
	// Foto principal con el mismo texto alternativo que la galería.
	$thumb = (int) get_post_thumbnail_id( $post_id );
	if ( ! $thumb ) {
		$ids   = array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $post_id, 'maros_gallery', true ) ) ) );
		$thumb = (int) reset( $ids );
	}
	if ( $thumb ) {
		$html .= wp_get_attachment_image( $thumb, 'medium', false, array( 'alt' => maros_seo_hero_alt( $post_id ) ) );
	}
	foreach ( array( '[maros_facts]', '[maros_key_features]', '[maros_sleeping]' ) as $sc ) {
		$html .= do_shortcode( $sc );
	}
	$html .= '<h2>' . esc_html( $es ? 'Características y comodidades' : 'Features and amenities' ) . '</h2>' . do_shortcode( '[maros_amenities]' );

	$html .= '<h2>' . esc_html( $es ? 'Ubicación' : 'Location' ) . '</h2>';
	$terms = get_the_terms( $post_id, 'zona' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		$link  = get_term_link( $terms[0] );
		$html .= '<p>' . ( is_wp_error( $link ) ? esc_html( $terms[0]->name ) : '<a href="' . esc_url( $link ) . '">' . esc_html( $terms[0]->name ) . '</a>' ) . '</p>';
	}
	$html .= '<p>' . esc_html( $es ? 'La dirección exacta se envía tras la reserva.' : 'The exact address is shared after booking.' ) . '</p>';
	$html .= '<h2>FAQ</h2>';
	$html .= $es
		? '<p>¿El precio es cerrado? Sí. Confirmamos el precio exacto y la disponibilidad contigo antes de pagar.</p><p>¿Podéis organizar chef, traslados o barco? Claro. Nuestro equipo de conserjería se encarga antes y durante tu estancia.</p>'
		: '<p>Is the price final? Yes. We confirm the exact price and availability with you before you pay.</p><p>Can you arrange a chef, transfers or a boat? Of course. Our concierge team takes care of it before and during your stay.</p>';

	wp_reset_postdata();
	// Sin scripts ni SVG: Yoast cuenta palabras e imágenes, no iconos.
	$html = preg_replace( '#<(script|svg)\b.*?</\1>#is', '', $html );
	return (string) $html;
}

/** Texto alternativo de la foto principal: «Villa Concha · villa de 5 dormitorios en Nueva Andalucía». */
function maros_seo_hero_alt( int $post_id ): string {
	$name = maros_seo_name( $post_id );
	$kw   = (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ) ?: maros_seo_keyphrase( $post_id );
	return false !== mb_stripos( $kw, $name ) ? maros_seo_ucfirst( $kw ) : $name . ' · ' . $kw;
}

add_action(
	'enqueue_block_editor_assets',
	static function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post   = get_post();
		if ( ! $post || 'mis_property' !== $post->post_type || ( $screen && 'mis_property' !== $screen->post_type ) ) {
			return;
		}
		wp_enqueue_script( 'maros-seo-analysis', plugins_url( 'assets/seo-analysis.js', __DIR__ ), array(), MAROS_CONNECTOR_VERSION, true );
		wp_add_inline_script( 'maros-seo-analysis', 'window.marosSeoTemplate = ' . wp_json_encode( maros_seo_template_html( (int) $post->ID ) ) . ';', 'before' );
	}
);

/* ------------------------------------------------------------------------------------------------
 * Datos estructurados y archivos.
 * ---------------------------------------------------------------------------------------------- */

/**
 * Pieza VacationRental para el grafo de Yoast. Sin coordenadas exactas: la dirección se da tras la
 * reserva, así que solo zona y municipio.
 */
function maros_seo_schema_piece( int $post_id ): array {
	$es    = maros_is_es( $post_id );
	$url   = get_permalink( $post_id );
	$m     = static fn( string $k ) => get_post_meta( $post_id, $k, true );
	$terms = get_the_terms( $post_id, 'equipamiento' );
	$piece = array(
		'@type'       => 'VacationRental',
		'@id'         => $url . '#rental',
		'name'        => maros_seo_name( $post_id ),
		'url'         => $url,
		'description' => (string) ( get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ) ?: get_the_excerpt( $post_id ) ),
		'inLanguage'  => $es ? 'es' : 'en',
		'mainEntityOfPage' => array( '@id' => $url ),
		'address'     => array_filter(
			array(
				'@type'           => 'PostalAddress',
				'addressLocality' => (string) ( $m( 'maros_municipality' ) ?: 'Marbella' ),
				'addressRegion'   => 'Málaga',
				'addressCountry'  => 'ES',
				'streetAddress'   => maros_seo_zone( $post_id ),
			)
		),
		'containsPlace' => array_filter(
			array(
				'@type'              => 'Accommodation',
				'additionalType'     => 'EntirePlace',
				'numberOfBedrooms'   => (int) $m( 'maros_bedrooms' ) ?: null,
				'numberOfBathroomsTotal' => (int) $m( 'maros_bathrooms' ) ?: null,
				'occupancy'          => (int) $m( 'maros_max_guests' ) ? array( '@type' => 'QuantitativeValue', 'value' => (int) $m( 'maros_max_guests' ) ) : null,
				'amenityFeature'     => $terms && ! is_wp_error( $terms ) ? array_map( static fn( $t ) => array( '@type' => 'LocationFeatureSpecification', 'name' => $t->name, 'value' => true ), array_values( $terms ) ) : null,
			)
		),
	);
	if ( '' !== (string) $m( 'maros_check_in' ) ) {
		$piece['checkinTime'] = (string) $m( 'maros_check_in' );
	}
	if ( '' !== (string) $m( 'maros_check_out' ) ) {
		$piece['checkoutTime'] = (string) $m( 'maros_check_out' );
	}
	$thumb = get_the_post_thumbnail_url( $post_id, 'large' );
	if ( $thumb ) {
		$piece['image'] = $thumb;
	}
	if ( function_exists( 'glsr_get_reviews' ) ) {
		$reviews = glsr_get_reviews( array( 'assigned_posts' => $post_id, 'per_page' => 100, 'status' => 'approved' ) );
		$ratings = array();
		foreach ( $reviews as $r ) {
			$ratings[] = (int) $r->rating;
		}
		if ( $ratings ) {
			$piece['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => round( array_sum( $ratings ) / count( $ratings ), 2 ),
				'reviewCount' => max( count( $ratings ), (int) ( $reviews->total ?? 0 ) ),
				'bestRating'  => 5,
			);
		}
	}
	return $piece;
}

add_filter(
	'wpseo_schema_graph',
	static function ( $graph ) {
		if ( is_singular( 'mis_property' ) && is_array( $graph ) ) {
			$graph[] = maros_seo_schema_piece( (int) get_queried_object_id() );
		}
		return $graph;
	}
);

/** Archivos de comodidades y colecciones: páginas pobres, fuera del índice y del sitemap. */
const MAROS_SEO_NOINDEX_TAXONOMIES = array( 'equipamiento', 'coleccion' );

add_filter(
	'wpseo_robots_array',
	static function ( $robots ) {
		if ( is_tax( MAROS_SEO_NOINDEX_TAXONOMIES ) && is_array( $robots ) ) {
			$robots['index'] = 'noindex';
		}
		return $robots;
	}
);
add_filter( 'wpseo_sitemap_exclude_taxonomy', static fn( $exclude, $taxonomy ) => $exclude || in_array( $taxonomy, MAROS_SEO_NOINDEX_TAXONOMIES, true ), 10, 2 );
