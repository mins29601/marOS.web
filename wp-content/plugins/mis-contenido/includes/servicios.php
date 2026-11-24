<?php
/**
 * Tipos de servicio (Transporte, Chef y catering, Eventos y bodas, Conserjería): cada uno tiene
 * su portada en /services/<tipo>/ con el texto del tipo (la descripción del término) y sus
 * servicios en /services/<tipo>/<slug>/ (spec 005; mapa del sitio, «misma URL»).
 *
 *  [mis_servicios_tipos]  Tarjetas de los tipos de servicio del idioma actual (Inicio y Servicios).
 *  [mis_servicio_tipo]    Portada de un tipo: título, texto y sus servicios.
 *  [mis_servicios_listado] Todos los servicios del idioma actual con botones para filtrarlos por
 *                          categoría (categoria-servicio), como el listado de Experiencias.
 *
 * Los textos se cargan con scripts/setup/servicios.php desde los borradores de marOS.content.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

/** Campos del tipo de servicio: tarjeta, título de la portada y orden. */
function mis_servicio_tipo_campos(): array {
	return array(
		'service_summary' => array( 'type' => 'string', 'label' => 'Frase corta (tarjeta)' ),
		'service_h1'      => array( 'type' => 'string', 'label' => 'Título de la portada (H1)' ),
		'service_order'   => array( 'type' => 'integer', 'label' => 'Orden' ),
		'service_image'   => array( 'type' => 'integer', 'label' => 'Imagen (id de Medios)' ),
	);
}

add_action(
	'init',
	static function () {
		foreach ( mis_servicio_tipo_campos() as $key => $field ) {
			register_term_meta(
				'tipo-servicio',
				$key,
				array(
					'type'          => $field['type'],
					'label'         => $field['label'],
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => static fn() => current_user_can( 'edit_posts' ),
				)
			);
		}
	}
);

function mis_servicio_t( string $en, string $es ): string {
	return ( function_exists( 'pll_current_language' ) && 'es' === pll_current_language() ) ? $es : $en;
}

/** Tipos de servicio del idioma actual, en su orden. */
function mis_servicio_tipos(): array {
	$terms = get_terms( array( 'taxonomy' => 'tipo-servicio', 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	usort( $terms, static fn( $a, $b ) => (int) get_term_meta( $a->term_id, 'service_order', true ) <=> (int) get_term_meta( $b->term_id, 'service_order', true ) );
	return $terms;
}

/** Foto de la tarjeta de un tipo: la suya o la del primer servicio que tenga foto. */
function mis_servicio_tipo_foto( WP_Term $term ): int {
	$propia = (int) get_term_meta( $term->term_id, 'service_image', true );
	if ( $propia && wp_attachment_is_image( $propia ) ) {
		return $propia;
	}
	$ids = get_posts(
		array(
			'post_type'        => 'mis_service',
			'numberposts'      => 1,
			'fields'           => 'ids',
			'lang'             => '',
			'suppress_filters' => false,
			'meta_key'         => '_thumbnail_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'tax_query'        => array( array( 'taxonomy' => 'tipo-servicio', 'terms' => $term->term_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		)
	);
	return $ids ? (int) get_post_thumbnail_id( $ids[0] ) : 0;
}

add_shortcode(
	'mis_servicios_tipos',
	static function () {
		$out = '';
		foreach ( mis_servicio_tipos() as $term ) {
			$url  = get_term_link( $term );
			$foto = mis_servicio_tipo_foto( $term );
			$out .= '<div class="mis-card">';
			if ( $foto ) {
				$out .= sprintf( '<a class="mis-card__foto" href="%s">%s</a>', esc_url( $url ), wp_get_attachment_image( $foto, 'medium_large' ) );
			}
			$out .= sprintf( '<h3 class="wp-block-post-title"><a href="%s">%s</a></h3>', esc_url( $url ), esc_html( wp_specialchars_decode( $term->name ) ) );
			$resumen = (string) get_term_meta( $term->term_id, 'service_summary', true );
			if ( $resumen ) {
				$out .= '<p class="mis-muted">' . esc_html( $resumen ) . '</p>';
			}
			$out .= '</div>';
		}
		return $out ? '<div class="mis-servicios-tipos alignwide">' . $out . '</div>' : '';
	}
);

add_shortcode(
	'mis_servicio_tipo',
	static function () {
		$term = get_queried_object();
		if ( ! $term instanceof WP_Term || 'tipo-servicio' !== $term->taxonomy ) {
			return '';
		}
		$h1  = (string) get_term_meta( $term->term_id, 'service_h1', true );
		$out = '<h1 class="wp-block-heading has-x-large-font-size">' . esc_html( $h1 ? $h1 : wp_specialchars_decode( $term->name ) ) . '</h1>';
		$foto = (int) get_term_meta( $term->term_id, 'service_image', true );
		if ( $foto && wp_attachment_is_image( $foto ) ) {
			$out .= '<figure class="mis-servicio-foto">' . wp_get_attachment_image( $foto, 'large' ) . '</figure>';
		}
		if ( $term->description ) {
			$out .= '<div class="mis-servicio-texto">' . wp_kses_post( wpautop( $term->description ) ) . '</div>';
		}
		$ids = get_posts(
			array(
				'post_type'        => 'mis_service',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'orderby'          => 'title',
				'order'            => 'ASC',
				'lang'             => '',
				'suppress_filters' => false,
				'tax_query'        => array( array( 'taxonomy' => 'tipo-servicio', 'terms' => $term->term_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);
		if ( $ids ) {
			$query = '<!-- wp:query {"queryId":21,"query":{"perPage":12,"pages":0,"offset":0,"postType":"mis_service","order":"asc","orderBy":"title","inherit":false,"taxQuery":{"tipo-servicio":[' . (int) $term->term_id . ']}}} -->
<div class="wp-block-query"><!-- wp:post-template {"layout":{"type":"grid","columnCount":2,"minimumColumnWidth":"14rem"}} -->
<!-- wp:group {"className":"mis-card","layout":{"type":"default"}} --><div class="wp-block-group mis-card">
<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3"} /-->
<!-- wp:post-title {"isLink":true,"level":3} /-->
<!-- wp:paragraph {"className":"mis-muted","metadata":{"bindings":{"content":{"source":"mis/tour","args":{"key":"service_summary"}}}}} --><p class="mis-muted"></p><!-- /wp:paragraph -->
</div><!-- /wp:group -->
<!-- /wp:post-template --></div><!-- /wp:query -->';
			$out .= '<h2 class="wp-block-heading">' . esc_html( mis_servicio_t( 'Our services', 'Nuestros servicios' ) ) . '</h2>' . do_blocks( $query );
		}
		return $out;
	}
);

/** Orden y nombres de las categorías de los filtros (slug en inglés; el español lleva «-es»). */
function mis_servicio_categorias(): array {
	return array(
		'transfers'       => array( 'Transfers & chauffeur', 'Traslados y chófer' ),
		'chef-catering'   => array( 'Chef & catering', 'Chef y catering' ),
		'events-weddings' => array( 'Events & weddings', 'Eventos y bodas' ),
		'yachts-jets'     => array( 'Yachts & jets', 'Barcos y jets' ),
		'concierge-villa' => array( 'Concierge & villa', 'Conserjería y villa' ),
	);
}

/** Tarjeta del listado de servicios. */
function mis_servicio_tarjeta( string $url, int $foto, string $tipo, string $titulo, string $texto, array $cats ): string {
	$out = '<div class="mis-card" data-cats="' . esc_attr( implode( ' ', $cats ) ) . '">';
	if ( $foto ) {
		$out .= '<a class="mis-card__foto" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . wp_get_attachment_image( $foto, 'medium_large', false, array( 'alt' => '', 'loading' => 'lazy' ) ) . '</a>';
	}
	$out .= $tipo ? '<p class="mis-eyebrow">' . esc_html( $tipo ) . '</p>' : '';
	$out .= '<h3 class="wp-block-post-title"><a href="' . esc_url( $url ) . '">' . esc_html( $titulo ) . '</a></h3>';
	$out .= $texto ? '<p class="mis-muted">' . esc_html( $texto ) . '</p>' : '';
	return $out . '</div>';
}

add_shortcode(
	'mis_servicios_listado',
	static function () {
		$es    = function_exists( 'pll_current_language' ) && 'es' === pll_current_language();
		$lang  = $es ? 'es' : 'en';
		$base  = static fn( string $slug ): string => (string) preg_replace( '/-es$/', '', $slug );
		$cards = array();
		$usadas = array();
		foreach ( mis_servicio_tipos() as $tipo ) {
			$nombre = wp_specialchars_decode( $tipo->name );
			// La portada del tipo va como tarjeta si tiene categorías (Conserjería: su servicio es la portada).
			$cats_tipo = array_filter( array_map( 'trim', explode( ',', (string) get_term_meta( $tipo->term_id, 'service_categories', true ) ) ) );
			if ( $cats_tipo ) {
				$h1      = (string) get_term_meta( $tipo->term_id, 'service_h1', true );
				$cards[] = mis_servicio_tarjeta( (string) get_term_link( $tipo ), mis_servicio_tipo_foto( $tipo ), $nombre, $h1 ? $h1 : $nombre, (string) get_term_meta( $tipo->term_id, 'service_summary', true ), $cats_tipo );
				$usadas  = array_merge( $usadas, $cats_tipo );
			}
			$ids = get_posts(
				array(
					'post_type'        => 'mis_service',
					'numberposts'      => -1,
					'fields'           => 'ids',
					'orderby'          => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
					'lang'             => $lang,
					'suppress_filters' => false,
					'tax_query'        => array( array( 'taxonomy' => 'tipo-servicio', 'terms' => $tipo->term_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				)
			);
			foreach ( $ids as $id ) {
				$terms = get_the_terms( $id, 'categoria-servicio' );
				$cats  = ( $terms && ! is_wp_error( $terms ) ) ? array_map( static fn( $c ) => $base( $c->slug ), $terms ) : array();
				$texto = (string) get_post_meta( $id, 'service_card', true );
				if ( '' === $texto ) {
					$texto = wp_trim_words( wp_strip_all_tags( get_the_excerpt( $id ) ), 22 );
				}
				$cards[] = mis_servicio_tarjeta( (string) get_permalink( $id ), (int) get_post_thumbnail_id( $id ), $nombre, get_the_title( $id ), $texto, $cats );
				$usadas  = array_merge( $usadas, $cats );
			}
		}
		if ( ! $cards ) {
			return '';
		}
		$out    = '';
		$usadas = array_unique( $usadas );
		$chips  = '';
		foreach ( mis_servicio_categorias() as $slug => $names ) {
			if ( ! in_array( $slug, $usadas, true ) ) {
				continue;
			}
			// El nombre editado en WordPress manda sobre el de mis_servicio_categorias().
			$term   = get_term_by( 'slug', $es ? $slug . '-es' : $slug, 'categoria-servicio' );
			$nombre = $term ? wp_specialchars_decode( $term->name ) : $names[ $es ? 1 : 0 ];
			$chips .= '<button type="button" class="mis-chip" data-tipo="' . esc_attr( $slug ) . '" aria-pressed="false">' . esc_html( $nombre ) . '</button>';
		}
		if ( $chips ) {
			$out .= '<div class="mis-tour-filters" role="group" aria-label="' . esc_attr( $es ? 'Filtrar servicios' : 'Filter services' ) . '">';
			$out .= '<button type="button" class="mis-chip is-active" data-tipo="" aria-pressed="true">' . esc_html( $es ? 'Todos' : 'All' ) . '</button>' . $chips . '</div>';
		}
		$out .= '<div class="mis-servicios-listado">' . implode( '', $cards ) . '</div>';
		// Filtra las tarjetas por data-cats y guarda el filtro en #<categoría>, como en Experiencias (sin
		// «&&»: wptexturize lo estropea si el shortcode va en un bloque Shortcode).
		$out .= "<script>(function(){var w=document.currentScript.parentElement,b=w.querySelector('.mis-tour-filters'),g=w.querySelector('.mis-servicios-listado');if(!b||!g)return;function f(t){b.querySelectorAll('.mis-chip').forEach(function(c){var on=c.dataset.tipo===t;c.classList.toggle('is-active',on);c.setAttribute('aria-pressed',on);});g.querySelectorAll('.mis-card').forEach(function(c){c.hidden=t?(' '+c.dataset.cats+' ').indexOf(' '+t+' ')===-1:false;});}b.addEventListener('click',function(e){var c=e.target.closest('.mis-chip');if(!c)return;f(c.dataset.tipo);history.replaceState(null,'',c.dataset.tipo?'#'+c.dataset.tipo:location.pathname+location.search);});var h=location.hash.slice(1);if(h)if(b.querySelector('[data-tipo=\"'+CSS.escape(h)+'\"]'))f(h);})();</script>";
		return '<div class="mis-servicios alignwide">' . $out . '</div>';
	}
);

// La portada de un tipo sin servicios sueltos (Transporte, Conserjería) no es un 404: su texto
// es la descripción del término.
add_filter(
	'pre_handle_404',
	static function ( $handled, WP_Query $query ) {
		if ( $query->is_main_query() && $query->is_tax( 'tipo-servicio' ) && ! is_paged() && $query->get_queried_object() instanceof WP_Term ) {
			status_header( 200 );
			return true;
		}
		return $handled;
	},
	10,
	2
);

// Texto alternativo en español de las imágenes que lo tienen (mis_alt_es), en las páginas en español.
add_filter(
	'wp_get_attachment_image_attributes',
	static function ( array $attr, WP_Post $attachment ): array {
		$es = function_exists( 'pll_current_language' ) && 'es' === pll_current_language();
		$alt = $es ? (string) get_post_meta( $attachment->ID, 'mis_alt_es', true ) : '';
		if ( $alt ) {
			$attr['alt'] = $alt;
		}
		return $attr;
	},
	10,
	2
);
