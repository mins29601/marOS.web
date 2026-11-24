<?php
/**
 * Servicios y experiencias de la web nueva con sus textos (borradores de marOS.content:
 * PC-35 traslados, PC-36 chef, PC-37 conserjería y las fichas de tour de PC-40). Idempotente.
 *
 * Uso: wp eval-file scripts/setup/servicios.php [forzar | forzar-experiencias]
 * (en local: npm run wp -- eval-file wp-content/mis-scripts/setup/servicios.php)
 *
 * - Tipos de servicio con las URLs de la web actual: /services/transport/, /services/catering/,
 *   /services/events/ y /services/concierge/ (cambia «chef-catering» y «events-weddings»).
 * - Transporte y Conserjería son la portada de su tipo (el texto va en la descripción del
 *   término); el chef privado es un servicio en /services/catering/private-chef-in-marbella/.
 * - Las dieciséis experiencias de la web actual con sus slugs, sin precios (se toman de FareHarbor):
 *   la ficha (destacados, programa, qué incluye, punto de encuentro, cancelación…) va en campos
 *   propios, el id de FareHarbor es el del botón de la ficha actual y las fotos se bajan de su
 *   galería (servicios/fotos-tours.json).
 * - Los servicios de ejemplo «Private driver» y «Concierge» pasan a borrador: su texto está
 *   ahora en la portada de su tipo.
 * - Los servicios de la web actual (borradores/servicios/ de marOS.content, 24-11-2026): cinco de
 *   transporte, cuatro de chef y catering, tres de eventos, jet privado y barcos, con el texto de las
 *   portadas de Chef y catering y de Eventos, y las fotos de su página actual
 *   (servicios/fotos-servicios.json). Sin los que no están activos (personal shopper, paquete
 *   vacacional, personal doméstico, wellness). El de ejemplo de Eventos pasa a borrador.
 * - Categorías de los filtros de la página Concierge (categoria-servicio, Julio 24-11-2026), y esa
 *   página pasa de las tarjetas de los tipos al listado con filtros (patrón service-list).
 * - Menús: bajo Servicios, los cuatro tipos; bajo Experiencias, las que falten.
 * - Ilustraciones (servicios/img/, dibujadas con dibujar.py): sin etiqueta visible; el texto
 *   alternativo y el nombre del archivo dicen que son imágenes generadas con IA (Julio, 18-11-2026): imagen de cada tipo y de los servicios que aún no tienen foto.
 *
 * Los textos están en servicios/servicios.json, que genera servicios/generar.py desde
 * marOS.content. Lo que ya se cargó desde marOS.content y luego se editó en WordPress no se
 * pisa; para volver a escribirlo: `wp eval-file servicios.php forzar`.
 *
 * @package MarbellaInStyle
 */

if ( ! function_exists( 'pll_set_post_language' ) || ! class_exists( 'Maros_Sync' ) ) {
	WP_CLI::error( 'Faltan Polylang o marOS Connector.' );
}

// Sin usuario, kses quitaría las tablas y los <h2> de los textos.
kses_remove_filters();
remove_filter( 'pre_term_description', 'wp_filter_kses' );

$mis_forzar = in_array( 'forzar', $args ?? array(), true );
// Solo las experiencias (p. ej. para pasar las siete del 18-11-2026 a la ficha nueva sin tocar el resto).
$mis_forzar_tours = $mis_forzar || in_array( 'forzar-experiencias', $args ?? array(), true );
$mis_datos  = json_decode( (string) file_get_contents( __DIR__ . '/servicios/servicios.json' ), true );
if ( ! is_array( $mis_datos ) ) {
	WP_CLI::error( 'No puedo leer servicios/servicios.json' );
}
const MIS_ORIGEN = 'marOS.content';

/** ¿Se puede escribir? Lo nuevo, lo de ejemplo o con «forzar»; lo ya cargado y editado, no. */
function mis_srv_escribir( string $origen, bool $forzar ): bool {
	return $forzar || MIS_ORIGEN !== $origen;
}

function mis_srv_buscar( string $type, string $lang, array $slugs ): int {
	foreach ( $slugs as $slug ) {
		$found = get_posts( array( 'post_type' => $type, 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1, 'lang' => $lang, 'fields' => 'ids' ) );
		if ( $found ) {
			return (int) $found[0];
		}
	}
	return 0;
}

/** Crea o actualiza un servicio o una experiencia en un idioma; devuelve su id. */
function mis_srv_post( string $type, string $lang, string $slug, array $old_slugs, array $t, bool $forzar ): int {
	$id     = mis_srv_buscar( $type, $lang, array_merge( array( $slug ), $old_slugs ) );
	$origen = $id ? (string) get_post_meta( $id, 'mis_origen', true ) : '';
	// Lo cargado que nadie ha tocado desde entonces (mismo texto que se guardó) sí se actualiza.
	$intacto = $id && MIS_ORIGEN === $origen && get_post_meta( $id, 'mis_origen_md5', true ) === md5( (string) get_post_field( 'post_content', $id, 'raw' ) );
	$GLOBALS['mis_srv_escrito'] = false;
	if ( $id && ! $intacto && ! mis_srv_escribir( $origen, $forzar ) ) {
		WP_CLI::log( "  = $type $lang /$slug/ (editado en WordPress; «forzar» para reescribirlo)" );
		return $id;
	}
	$post = array(
		'post_type'    => $type,
		'post_status'  => 'publish',
		'post_name'    => $slug,
		'post_title'   => $t['title'],
		'post_excerpt' => $t['excerpt'],
		'post_content' => $t['content'],
	);
	if ( $id ) {
		$post['ID'] = $id;
		$res        = wp_update_post( $post, true );
	} else {
		$res = wp_insert_post( $post, true );
	}
	if ( is_wp_error( $res ) ) {
		WP_CLI::error( $res->get_error_message() );
	}
	$id = (int) $res;
	pll_set_post_language( $id, $lang );
	update_post_meta( $id, 'mis_origen', MIS_ORIGEN );
	update_post_meta( $id, 'mis_origen_md5', md5( (string) get_post_field( 'post_content', $id, 'raw' ) ) );
	$GLOBALS['mis_srv_escrito'] = true;
	update_post_meta( $id, '_yoast_wpseo_title', $t['seo_title'] );
	update_post_meta( $id, '_yoast_wpseo_metadesc', $t['metadesc'] );
	WP_CLI::log( "  + $type $lang " . wp_make_link_relative( get_permalink( $id ) ) );
	return $id;
}

// ---------------------------------------------------------------- tipos de servicio

WP_CLI::log( 'Tipos de servicio…' );
$mis_tipos = array(
	// slug => nombres, frase de la tarjeta, slug anterior.
	'transport' => array( array( 'Transport', 'Transporte' ), array( 'Airport transfers, a chauffeur and transport for groups.', 'Traslados al aeropuerto, chófer y transporte para grupos.' ), '' ),
	'catering'  => array( array( 'Chef & catering', 'Chef y catering' ), array( 'A private chef at your villa, for one meal or your whole stay.', 'Chef privado en tu villa, para una comida o toda tu estancia.' ), 'chef-catering' ),
	'events'    => array( array( 'Events & weddings', 'Eventos y bodas' ), array( 'Celebrations planned end to end by our team.', 'Celebraciones organizadas de principio a fin.' ), 'events-weddings' ),
	'concierge' => array( array( 'Concierge', 'Conserjería' ), array( 'Restaurants, beach clubs, nightlife and golf, booked for you.', 'Restaurantes, beach clubs, ocio nocturno y golf, reservados por ti.' ), '' ),
);
$mis_tipo_ids = array();
$mis_orden    = 0;
foreach ( $mis_tipos as $slug => $tipo ) {
	// Cambio de slug: el término anterior pasa a tener el de la web actual.
	if ( $tipo[2] ) {
		foreach ( array( '' => '', '-es' => '-es' ) as $suf ) {
			$old = get_term_by( 'slug', $tipo[2] . $suf, 'tipo-servicio' );
			if ( $old && ! get_term_by( 'slug', $slug . $suf, 'tipo-servicio' ) ) {
				wp_update_term( $old->term_id, 'tipo-servicio', array( 'slug' => $slug . $suf ) );
				WP_CLI::log( "  ~ tipo {$tipo[2]}$suf → $slug$suf" );
			}
		}
	}
	++$mis_orden;
	foreach ( array( 'en' => 0, 'es' => 1 ) as $lang => $i ) {
		$tid = Maros_Sync::term( 'tipo-servicio', $slug, $tipo[0], $lang );
		if ( ! $tid ) {
			continue;
		}
		wp_update_term( $tid, 'tipo-servicio', array( 'name' => $tipo[0][ $i ] ) );
		update_term_meta( $tid, 'service_summary', $tipo[1][ $i ] );
		update_term_meta( $tid, 'service_order', $mis_orden );
		$mis_tipo_ids[ $slug ][ $lang ] = $tid;
	}
}

// ---------------------------------------------------------------- servicios

WP_CLI::log( 'Servicios…' );
$ids_par      = array();
$mis_srv_ids  = array();
$mis_srv_cats = array();
foreach ( $mis_datos['servicios'] as $s ) {
	foreach ( array( 'en', 'es' ) as $lang ) {
		$t   = $s[ $lang ];
		$tid = $mis_tipo_ids[ $s['type'] ][ $lang ] ?? 0;
		if ( ! $tid ) {
			continue;
		}
		if ( 'term' === $s['kind'] ) {
			$origen = (string) get_term_meta( $tid, 'mis_origen', true );
			if ( ! mis_srv_escribir( $origen, $mis_forzar ) ) {
				WP_CLI::log( "  = tipo {$s['type']} $lang (editado en WordPress; «forzar» para reescribirlo)" );
				continue;
			}
			wp_update_term( $tid, 'tipo-servicio', array( 'description' => $t['html'] ) );
			update_term_meta( $tid, 'service_h1', $t['title'] );
			update_term_meta( $tid, 'mis_origen', MIS_ORIGEN );
			if ( class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
				WPSEO_Taxonomy_Meta::set_value( $tid, 'tipo-servicio', 'wpseo_title', $t['seo_title'] );
				WPSEO_Taxonomy_Meta::set_value( $tid, 'tipo-servicio', 'wpseo_desc', $t['metadesc'] );
			}
			WP_CLI::log( "  + tipo $lang " . wp_make_link_relative( get_term_link( $tid ) ) );
			continue;
		}
		$old  = 'private-chef-in-marbella' === $s['slug']['en'] ? ( 'en' === $lang ? array( 'private-chef' ) : array( 'chef-privado' ) ) : array();
		$slug = $s['slug'][ $lang ];
		// Polylang (gratuito) no deja repetir el slug en otro idioma: el español lleva «-es».
		if ( 'es' === $lang && $slug === $s['slug']['en'] ) {
			$slug .= '-es';
		}
		$id = mis_srv_post( 'mis_service', $lang, $slug, $old, $t, $mis_forzar );
		wp_set_object_terms( $id, array( $tid ), 'tipo-servicio' );
		// La frase del tipo solo encaja con el chef (único servicio de su tipo cuando se escribió); los de
		// la web actual ya abren con su extracto. Lo escrito en WordPress se respeta.
		$frase = 'private-chef-in-marbella' === $s['slug']['en'] ? $mis_tipos[ $s['type'] ][1][ 'en' === $lang ? 0 : 1 ] : '';
		if ( ! empty( $GLOBALS['mis_srv_escrito'] ) ) {
			update_post_meta( $id, 'service_summary', $frase );
		}
		update_post_meta( $id, 'service_price_from', 'en' === $lang ? 'Price on request' : 'Precio a consultar' );
		$ids_par[ $lang ] = $id;
		$mis_srv_ids[ $s['slug']['en'] ][ $lang ] = $id;
		$mis_srv_cats[ $s['slug']['en'] ]         = $s['cats'] ?? array();
	}
	if ( ! empty( $ids_par['en'] ) && ! empty( $ids_par['es'] ) ) {
		pll_save_post_translations( $ids_par );
	}
	$ids_par = array();
}

// Los servicios de ejemplo cuyo texto está ahora en la portada de su tipo.
// También el de ejemplo de Eventos: ya están los de la web actual (24-11-2026).
foreach ( array( 'en' => array( 'private-driver', 'concierge', 'events-weddings' ), 'es' => array( 'chofer-privado', 'conserjeria', 'eventos-bodas' ) ) as $lang => $slugs ) {
	foreach ( $slugs as $slug ) {
		$id = mis_srv_buscar( 'mis_service', $lang, array( $slug ) );
		if ( $id && 'publish' === get_post_status( $id ) ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
			WP_CLI::log( "  ~ mis_service $lang /$slug/ a borrador (era de ejemplo)" );
		}
	}
}

// Los de ejemplo que siguen (Eventos y bodas) llevan la nota que tenía la plantilla.
foreach ( get_posts( array( 'post_type' => 'mis_service', 'lang' => '', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
	if ( MIS_ORIGEN !== get_post_meta( $id, 'mis_origen', true ) && '' === (string) get_post_meta( $id, 'service_note', true ) ) {
		$es = 'es' === pll_get_post_language( $id );
		update_post_meta( $id, 'service_note', $es ? 'También si no te alojas con nosotros.' : 'Also available if you are not staying with us.' );
	}
}

// Categorías de los filtros de la página Concierge (Julio, 24-11-2026). Un servicio puede tener
// varias. Solo se ponen si el servicio aún no tiene ninguna: lo cambiado en WordPress se respeta.
WP_CLI::log( 'Categorías de servicio…' );
$mis_cat_ids = array();
foreach ( mis_servicio_categorias() as $slug => $names ) {
	Maros_Sync::term( 'categoria-servicio', $slug, $names, 'en' );
	foreach ( array( 'en' => $slug, 'es' => $slug . '-es' ) as $lang => $term_slug ) {
		$term = get_term_by( 'slug', $term_slug, 'categoria-servicio' );
		if ( $term ) {
			$mis_cat_ids[ $lang ][ $slug ] = (int) $term->term_id;
		}
	}
}
$mis_cats_puestas = 0;
foreach ( $mis_srv_ids as $en_slug => $par ) {
	foreach ( $par as $lang => $id ) {
		if ( empty( $mis_srv_cats[ $en_slug ] ) || wp_get_object_terms( $id, 'categoria-servicio', array( 'fields' => 'ids' ) ) ) {
			continue;
		}
		wp_set_object_terms( $id, array_values( array_filter( array_map( static fn( $c ) => $mis_cat_ids[ $lang ][ $c ] ?? 0, $mis_srv_cats[ $en_slug ] ) ) ), 'categoria-servicio' );
		++$mis_cats_puestas;
	}
}
// Conserjería no tiene servicios sueltos: su portada sale como tarjeta en el listado.
foreach ( $mis_tipo_ids['concierge'] ?? array() as $tid ) {
	if ( '' === (string) get_term_meta( $tid, 'service_categories', true ) ) {
		update_term_meta( $tid, 'service_categories', 'concierge-villa' );
	}
}
WP_CLI::log( "  {$mis_cats_puestas} servicios con categoría nueva." );

// ---------------------------------------------------------------- experiencias

WP_CLI::log( 'Experiencias…' );
// Slugs de la versión de ejemplo (contenido-inicial.php) o de la web actual que pasan a los nuevos.
$mis_old_tours = array(
	'wine-tour-ronda' => array( 'en' => array( 'ronda-wine-tour' ), 'es' => array( 'ruta-vino-ronda' ) ),
	// El mapa del sitio fusiona «…-tour-2» en «…-tour» (inventario-urls.csv).
	'marbella-puerto-banus-night-life-walking-tour' => array( 'en' => array( 'marbella-puerto-banus-night-life-walking-tour-2' ), 'es' => array() ),
);
// Zona de la experiencia (las que salen de Marbella a otra ciudad no llevan zona).
$mis_tour_zona = array(
	'marbella-tapas-tour'                           => 'marbella-centro',
	'private-wine-tasting-in-marbella'              => 'marbella-centro',
	'marbella-puerto-banus-night-life-walking-tour' => 'puerto-banus',
);
// Campos de la ficha: clave del JSON → meta (las listas, una línea por elemento).
$mis_tour_listas = array(
	'highlights'  => 'tour_highlights',
	'includes'    => 'tour_includes',
	'excludes'    => 'tour_excludes',
	'extras'      => 'tour_extras',
	'bring'       => 'tour_bring',
	'not_allowed' => 'tour_not_allowed',
	'know'        => 'tour_know',
);
$mis_tours_ids = array();
foreach ( $mis_datos['tours'] as $tour ) {
	$en_slug = $tour['slug']['en'];
	// Polylang (gratuito) no deja repetir el slug en otro idioma: el español lleva «-es».
	$es_slug = $tour['slug']['es'] === $en_slug ? $en_slug . '-es' : $tour['slug']['es'];
	$par     = array();
	foreach ( array( 'en' => $en_slug, 'es' => $es_slug ) as $lang => $slug ) {
		$id = mis_srv_post( 'mis_tour', $lang, $slug, $mis_old_tours[ $en_slug ][ $lang ] ?? array(), $tour[ $lang ], $mis_forzar_tours );
		$f  = $tour['fields'];
		update_post_meta( $id, 'tour_kind', $tour['kind'] );
		update_post_meta( $id, 'tour_duration', $f['duration'][ $lang ] );
		update_post_meta( $id, 'tour_start_time', $f['start_time'][ $lang ] );
		update_post_meta( $id, 'tour_meeting_point', $f['meeting_point'][ $lang ] );
		update_post_meta( $id, 'tour_private', (bool) $f['private'] );
		update_post_meta( $id, 'tour_languages', $f['languages'][ $lang ] );
		update_post_meta( $id, 'tour_pickup', $f['pickup'][ $lang ] );
		update_post_meta( $id, 'tour_group_size', $f['group_size'][ $lang ] );
		// Los campos de la ficha se escriben con el texto, y con la misma regla: lo editado no se pisa.
		if ( ! empty( $GLOBALS['mis_srv_escrito'] ) ) {
			$c = $tour[ $lang ]['campos'];
			foreach ( $mis_tour_listas as $key => $meta ) {
				update_post_meta( $id, $meta, implode( "\n", $c[ $key ] ) );
			}
			update_post_meta( $id, 'tour_itinerary', implode( "\n", array_map( static fn( $s ) => $s[0] . ' | ' . $s[1], $c['itinerary'] ) ) );
			update_post_meta( $id, 'tour_itinerary_note', $c['itinerary_note'] );
			update_post_meta( $id, 'tour_cancellation', $c['cancellation'] );
		}
		// Reserva: FareHarbor con el id de la ficha actual; sin él (viajes de varios días), formulario.
		if ( $tour['fareharbor'] ) {
			update_post_meta( $id, 'tour_fareharbor_item', $tour['fareharbor'] );
			update_post_meta( $id, 'tour_booking_mode', 'fareharbor' );
		} elseif ( ! get_post_meta( $id, 'tour_fareharbor_item', true ) ) {
			delete_post_meta( $id, 'tour_price_from' );
			update_post_meta( $id, 'tour_booking_mode', 'formulario' );
		}
		$zona = $mis_tour_zona[ $en_slug ] ?? '';
		$zid  = $zona ? get_term_by( 'slug', 'en' === $lang ? $zona : $zona . '-es', 'zona' ) : null;
		wp_set_object_terms( $id, $zid ? array( (int) $zid->term_id ) : array(), 'zona' );
		$par[ $lang ] = $id;
	}
	pll_save_post_translations( $par );
	$mis_tours_ids[ $en_slug ] = $par;
}

// Tipos de experiencia para los filtros del listado (Julio, 24-11-2026). Una experiencia puede tener
// varios. Solo se ponen si la experiencia aún no tiene ninguno: lo cambiado en WordPress se respeta.
WP_CLI::log( 'Tipos de experiencia…' );
$mis_tipo_tour_ids = array();
foreach ( mis_tour_tipos() as $slug => $names ) {
	Maros_Sync::term( 'tipo-tour', $slug, $names, 'en' );
	foreach ( array( 'en' => $slug, 'es' => $slug . '-es' ) as $lang => $term_slug ) {
		$t = get_term_by( 'slug', $term_slug, 'tipo-tour' );
		if ( $t ) {
			$mis_tipo_tour_ids[ $lang ][ $slug ] = (int) $t->term_id;
		}
	}
}
$mis_tour_tipo = array(
	'granada-private-tour'                          => array( 'day-tours' ),
	'cordoba-private-tour'                          => array( 'day-tours' ),
	'sevilla-private-tour'                          => array( 'day-tours' ),
	'gibraltar-private-tour'                        => array( 'day-tours', 'nature-adventure' ),
	'tangier-private-tour'                          => array( 'day-tours' ),
	'wine-tour-ronda'                               => array( 'day-tours', 'food-drink' ),
	'malaga-private-tour'                           => array( 'day-tours' ),
	'winery-tour-by-jerez'                          => array( 'day-tours', 'food-drink' ),
	'mijas-private-tour'                            => array( 'day-tours' ),
	'marbella-tapas-tour'                           => array( 'food-drink' ),
	'private-wine-tasting-in-marbella'              => array( 'food-drink' ),
	'marbella-3-hour-guided-bicycle-tour'           => array( 'nature-adventure' ),
	'marbella-puerto-banus-night-life-walking-tour' => array( 'lifestyle' ),
	'marbella-golf-holidays'                        => array( 'lifestyle', 'multi-day-trips' ),
	'3-day-best-of-andalusia-private-tour'          => array( 'multi-day-trips' ),
	'5-day-andalusia-private-tour'                  => array( 'multi-day-trips' ),
);
$mis_tipos_puestos = 0;
foreach ( $mis_tours_ids as $en_slug => $par ) {
	foreach ( $par as $lang => $id ) {
		if ( empty( $mis_tour_tipo[ $en_slug ] ) || wp_get_object_terms( $id, 'tipo-tour', array( 'fields' => 'ids' ) ) ) {
			continue;
		}
		$ids = array_values( array_filter( array_map( static fn( $t ) => $mis_tipo_tour_ids[ $lang ][ $t ] ?? 0, $mis_tour_tipo[ $en_slug ] ) ) );
		wp_set_object_terms( $id, $ids, 'tipo-tour' );
		++$mis_tipos_puestos;
	}
}
WP_CLI::log( "  {$mis_tipos_puestos} experiencias con tipo nuevo." );

// ---------------------------------------------------------------- menús

// Página Concierge: el listado con filtros sustituye a las tarjetas de los tipos (Inicio las conserva).
foreach ( array( 'en', 'es' ) as $lang ) {
	$page = get_page_by_path( 'services' );
	$pid  = $page ? (int) ( pll_get_post( $page->ID, $lang ) ?: 0 ) : 0;
	$html = $pid ? (string) get_post_field( 'post_content', $pid, 'raw' ) : '';
	if ( str_contains( $html, '"marbellainstyle/service-grid"' ) ) {
		wp_update_post( array( 'ID' => $pid, 'post_content' => str_replace( '"marbellainstyle/service-grid"', '"marbellainstyle/service-list"', $html ) ) );
		WP_CLI::log( "  ~ página Concierge ($lang): listado de servicios con filtros" );
	}
}

WP_CLI::log( 'Menús…' );
$mis_pagina = static function ( string $slug, string $lang ): int {
	$page = get_page_by_path( $slug );
	return $page ? (int) ( pll_get_post( $page->ID, $lang ) ?: 0 ) : 0;
};
foreach ( array( 'en', 'es' ) as $lang ) {
	$serv_page = $mis_pagina( 'services', $lang );
	$exp_page  = $mis_pagina( 'experiences', $lang );
	foreach ( array( 'primary', 'footer-services' ) as $loc ) {
		$menu = wp_get_nav_menu_object( "$loc ($lang)" );
		if ( ! $menu ) {
			continue;
		}
		$items = wp_get_nav_menu_items( $menu->term_id, array( 'post_status' => 'any' ) ) ?: array();
		$padre = static function ( int $page ) use ( $items ): int {
			foreach ( $items as $it ) {
				if ( 0 === (int) $it->menu_item_parent && 'page' === $it->object && $page === (int) $it->object_id ) {
					return (int) $it->ID;
				}
			}
			return 0;
		};
		$p_serv = $padre( $serv_page );
		if ( $p_serv ) {
			// Bajo Servicios: los cuatro tipos, en su orden (fuera los servicios sueltos).
			foreach ( $items as $it ) {
				if ( $p_serv === (int) $it->menu_item_parent && in_array( $it->object, array( 'mis_service', 'tipo-servicio' ), true ) ) {
					wp_delete_post( $it->ID, true );
				}
			}
			$pos = 0;
			foreach ( $mis_tipo_ids as $by_lang ) {
				if ( empty( $by_lang[ $lang ] ) ) {
					continue;
				}
				wp_update_nav_menu_item(
					$menu->term_id,
					0,
					array(
						'menu-item-status'    => 'publish',
						'menu-item-parent-id' => $p_serv,
						'menu-item-object'    => 'tipo-servicio',
						'menu-item-object-id' => $by_lang[ $lang ],
						'menu-item-type'      => 'taxonomy',
						'menu-item-position'  => 100 + ( ++$pos ),
					)
				);
			}
			WP_CLI::log( "  ~ $loc ($lang): Servicios con sus $pos tipos" );
		}
		$p_exp = 'primary' === $loc ? $padre( $exp_page ) : 0;
		if ( $p_exp ) {
			$ya = array();
			foreach ( $items as $it ) {
				if ( $p_exp === (int) $it->menu_item_parent && 'mis_tour' === $it->object ) {
					$ya[] = (int) $it->object_id;
				}
			}
			foreach ( $mis_tours_ids as $par ) {
				if ( empty( $par[ $lang ] ) || in_array( $par[ $lang ], $ya, true ) ) {
					continue;
				}
				wp_update_nav_menu_item(
					$menu->term_id,
					0,
					array(
						'menu-item-status'    => 'publish',
						'menu-item-parent-id' => $p_exp,
						'menu-item-object'    => 'mis_tour',
						'menu-item-object-id' => $par[ $lang ],
						'menu-item-type'      => 'post_type',
					)
				);
				WP_CLI::log( "  + $loc ($lang): " . get_the_title( $par[ $lang ] ) );
			}
		}
	}
}

// ---------------------------------------------------------------- ilustraciones

WP_CLI::log( 'Ilustraciones…' );
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
// Son imágenes generadas con IA: se dice en el texto alternativo (EN y ES) y en el nombre del
// archivo, sin etiqueta visible (guía editorial, punto 6; Julio, 18-11-2026).
$mis_ilus_alt = array(
	'transport' => array( 'AI-generated image: a car on the road', 'Imagen generada con IA: un coche en la carretera' ),
	'catering'  => array( 'AI-generated image: a chef’s hat and a plate', 'Imagen generada con IA: un gorro de chef y un plato' ),
	'events'    => array( 'AI-generated image: two glasses raised in a toast', 'Imagen generada con IA: dos copas brindando' ),
	'concierge' => array( 'AI-generated image: a reception bell', 'Imagen generada con IA: un timbre de recepción' ),
);
$mis_ilus = array();
foreach ( $mis_ilus_alt as $slug => $alt ) {
	$found = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'lang' => '', 'meta_key' => 'mis_ilustracion', 'meta_value' => $slug ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$old   = $found ? (int) $found[0] : 0;
	$att   = $old;
	// La primera versión se subió como «…-ilustracion.jpg»: se sube de nuevo con el nombre que lo dice.
	if ( ! $old || ! str_contains( basename( (string) get_attached_file( $old ) ), 'generada-con-ia' ) ) {
		$tmp = wp_tempnam( "servicio-$slug.jpg" );
		copy( __DIR__ . "/servicios/img/$slug.jpg", $tmp );
		$att = media_handle_sideload( array( 'name' => "servicio-$slug-imagen-generada-con-ia.jpg", 'tmp_name' => $tmp ), 0, $alt[0] );
		if ( is_wp_error( $att ) ) {
			WP_CLI::warning( "Imagen $slug: " . $att->get_error_message() );
			continue;
		}
		update_post_meta( $att, 'mis_ilustracion', $slug );
		WP_CLI::log( "  + imagen $slug (Medios $att): " . basename( (string) get_attached_file( $att ) ) );
		if ( $old ) {
			// Lo que apuntaba a la anterior pasa a la nueva, y la anterior se borra.
			foreach ( get_terms( array( 'taxonomy' => 'tipo-servicio', 'hide_empty' => false, 'lang' => '', 'meta_key' => 'service_image', 'meta_value' => $old ) ) as $t ) { // phpcs:ignore WordPress.DB.SlowDBQuery
				update_term_meta( $t->term_id, 'service_image', $att );
			}
			foreach ( get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'lang' => '', 'meta_key' => '_thumbnail_id', 'meta_value' => $old ) ) as $pid ) { // phpcs:ignore WordPress.DB.SlowDBQuery
				set_post_thumbnail( $pid, $att );
			}
			wp_delete_attachment( $old, true );
			WP_CLI::log( "  - imagen anterior $old borrada" );
		}
	}
	update_post_meta( $att, '_wp_attachment_image_alt', $alt[0] );
	update_post_meta( $att, 'mis_alt_es', $alt[1] );
	wp_update_post( array( 'ID' => $att, 'post_title' => $alt[0], 'post_excerpt' => '' ) );
	$mis_ilus[ $slug ] = (int) $att;
}
foreach ( $mis_tipo_ids as $slug => $by_lang ) {
	foreach ( $by_lang as $lang => $tid ) {
		if ( ! empty( $mis_ilus[ $slug ] ) && ! get_term_meta( $tid, 'service_image', true ) ) {
			update_term_meta( $tid, 'service_image', $mis_ilus[ $slug ] );
			WP_CLI::log( "  ~ tipo $slug ($lang) con ilustración" );
		}
	}
}
// Servicios publicados sin foto: la ilustración de su tipo (si luego se pone una foto, se respeta).
foreach ( get_posts( array( 'post_type' => 'mis_service', 'lang' => '', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
	$terms = get_the_terms( $id, 'tipo-servicio' );
	$slug  = ( $terms && ! is_wp_error( $terms ) ) ? preg_replace( '/-es$/', '', $terms[0]->slug ) : '';
	$thumb = (int) get_post_thumbnail_id( $id );
	// Las fotos de villas que puso contenido-inicial.php como ejemplo no corresponden al servicio.
	$de_ejemplo = $thumb && ! get_post_meta( $thumb, 'mis_ilustracion', true ) && get_post_meta( $thumb, 'maros_photo_hash', true );
	if ( ! empty( $mis_ilus[ $slug ] ) && ( ! $thumb || $de_ejemplo ) ) {
		set_post_thumbnail( $id, $mis_ilus[ $slug ] );
		WP_CLI::log( '  ~ ' . get_the_title( $id ) . ' con ilustración' );
	}
}

// ---------------------------------------------------------------- fotos de las experiencias

WP_CLI::log( 'Fotos de las experiencias (web actual)…' );
$mis_fotos = json_decode( (string) file_get_contents( __DIR__ . '/servicios/fotos-tours.json' ), true );
foreach ( $mis_tours_ids as $en_slug => $par ) {
	$urls = $mis_fotos['fotos'][ $en_slug ] ?? array();
	if ( ! $urls || empty( $par['en'] ) ) {
		continue;
	}
	// Si alguien ya montó la galería en WordPress, se respeta.
	if ( get_post_meta( $par['en'], 'tour_gallery', true ) && 'web-actual' !== get_post_meta( $par['en'], 'tour_gallery_origen', true ) ) {
		WP_CLI::log( "  = $en_slug (galería editada en WordPress)" );
		continue;
	}
	$title_en = get_the_title( $par['en'] );
	$title_es = empty( $par['es'] ) ? $title_en : get_the_title( $par['es'] );
	$ids      = array();
	foreach ( $urls as $n => $url ) {
		$found = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'lang' => '', 'meta_key' => 'mis_foto_origen', 'meta_value' => $url ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$att   = $found ? (int) $found[0] : 0;
		if ( ! $att ) {
			$tmp = download_url( $url, 60 );
			if ( is_wp_error( $tmp ) ) {
				WP_CLI::warning( "  $url: " . $tmp->get_error_message() );
				continue;
			}
			$att = media_handle_sideload( array( 'name' => sanitize_file_name( "experiencia-$en_slug-" . ( $n + 1 ) . '.' . pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) ), 'tmp_name' => $tmp ), 0, $title_en );
			if ( is_wp_error( $att ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
				WP_CLI::warning( "  $url: " . $att->get_error_message() );
				continue;
			}
			update_post_meta( $att, 'mis_foto_origen', $url );
		}
		update_post_meta( $att, '_wp_attachment_image_alt', $title_en );
		update_post_meta( $att, 'mis_alt_es', $title_es );
		$ids[] = (int) $att;
	}
	if ( ! $ids ) {
		continue;
	}
	foreach ( $par as $id ) {
		update_post_meta( $id, 'tour_gallery', implode( ',', $ids ) );
		update_post_meta( $id, 'tour_gallery_origen', 'web-actual' );
		// La foto destacada de ejemplo (de una villa, puesta por contenido-inicial.php) pasa a la primera.
		$thumb      = (int) get_post_thumbnail_id( $id );
		$de_ejemplo = $thumb && ( get_post_meta( $thumb, 'maros_photo_hash', true ) || get_post_meta( $thumb, 'mis_ilustracion', true ) );
		if ( ! $thumb || $de_ejemplo || in_array( $thumb, $ids, true ) ) {
			set_post_thumbnail( $id, $ids[0] );
		}
	}
	WP_CLI::log( sprintf( '  + %s: %d fotos', $en_slug, count( $ids ) ) );
}

// ---------------------------------------------------------------- fotos de los servicios

WP_CLI::log( 'Fotos de los servicios (web actual)…' );
$mis_fotos_srv = json_decode( (string) file_get_contents( __DIR__ . '/servicios/fotos-servicios.json' ), true );
// Sube (o encuentra, por su URL de origen) una foto de la web actual; devuelve su id en Medios.
$mis_foto = static function ( string $url, string $name, string $alt_en, string $alt_es ): int {
	$found = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'lang' => '', 'meta_key' => 'mis_foto_origen', 'meta_value' => $url ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( $found ) {
		return (int) $found[0];
	}
	$tmp = download_url( $url, 60 );
	if ( is_wp_error( $tmp ) ) {
		WP_CLI::warning( "  $url: " . $tmp->get_error_message() );
		return 0;
	}
	$att = media_handle_sideload( array( 'name' => sanitize_file_name( $name . '.' . pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) ), 'tmp_name' => $tmp ), 0, $alt_en );
	if ( is_wp_error( $att ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
		WP_CLI::warning( "  $url: " . $att->get_error_message() );
		return 0;
	}
	update_post_meta( $att, 'mis_foto_origen', $url );
	update_post_meta( $att, '_wp_attachment_image_alt', $alt_en );
	update_post_meta( $att, 'mis_alt_es', $alt_es );
	return (int) $att;
};
foreach ( $mis_srv_ids as $en_slug => $par ) {
	$urls = $mis_fotos_srv['fotos'][ $en_slug ] ?? array();
	if ( ! $urls || empty( $par['en'] ) ) {
		continue;
	}
	// Si alguien ya montó la galería en WordPress, se respeta.
	if ( get_post_meta( $par['en'], 'service_gallery', true ) && 'web-actual' !== get_post_meta( $par['en'], 'service_gallery_origen', true ) ) {
		WP_CLI::log( "  = $en_slug (galería editada en WordPress)" );
		continue;
	}
	$title_en = get_the_title( $par['en'] );
	$title_es = empty( $par['es'] ) ? $title_en : get_the_title( $par['es'] );
	$ids      = array_values( array_filter( array_map( static fn( $n, $url ) => $mis_foto( $url, "servicio-$en_slug-" . ( $n + 1 ), $title_en, $title_es ), array_keys( $urls ), $urls ) ) );
	if ( ! $ids ) {
		continue;
	}
	foreach ( $par as $id ) {
		update_post_meta( $id, 'service_gallery', implode( ',', $ids ) );
		update_post_meta( $id, 'service_gallery_origen', 'web-actual' );
		// La ilustración o la foto de ejemplo pasa a la primera foto real.
		$thumb      = (int) get_post_thumbnail_id( $id );
		$de_ejemplo = $thumb && ( get_post_meta( $thumb, 'maros_photo_hash', true ) || get_post_meta( $thumb, 'mis_ilustracion', true ) );
		if ( ! $thumb || $de_ejemplo || in_array( $thumb, $ids, true ) ) {
			set_post_thumbnail( $id, $ids[0] );
		}
	}
	WP_CLI::log( sprintf( '  + %s: %d fotos', $en_slug, count( $ids ) ) );
}
// Foto real de cada tipo en lugar de la ilustración (si alguien puso otra en WordPress, se respeta).
foreach ( $mis_fotos_srv['tipos'] ?? array() as $slug => $url ) {
	foreach ( $mis_tipo_ids[ $slug ] ?? array() as $lang => $tid ) {
		$actual = (int) get_term_meta( $tid, 'service_image', true );
		if ( $actual && ! get_post_meta( $actual, 'mis_ilustracion', true ) ) {
			continue;
		}
		$name = wp_specialchars_decode( get_term( $tid )->name );
		$att  = $mis_foto( $url, "servicios-$slug", $name, $name );
		if ( $att ) {
			update_term_meta( $tid, 'service_image', $att );
			WP_CLI::log( "  ~ tipo $slug ($lang) con foto de la web actual" );
		}
	}
}

flush_rewrite_rules( true );
WP_CLI::success( 'Servicios y experiencias listos.' );
