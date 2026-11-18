<?php
/**
 * Servicios y experiencias de la web nueva con sus textos (borradores de marOS.content:
 * PC-35 traslados, PC-36 chef, PC-37 conserjería y las fichas de tour de PC-40). Idempotente.
 *
 * Uso: wp eval-file scripts/setup/servicios.php [forzar]
 * (en local: npm run wp -- eval-file wp-content/mis-scripts/setup/servicios.php)
 *
 * - Tipos de servicio con las URLs de la web actual: /services/transport/, /services/catering/,
 *   /services/events/ y /services/concierge/ (cambia «chef-catering» y «events-weddings»).
 * - Transporte y Conserjería son la portada de su tipo (el texto va en la descripción del
 *   término); el chef privado es un servicio en /services/catering/private-chef-in-marbella/.
 * - Siete experiencias con los slugs de la web actual, sin precios (se toman de FareHarbor).
 * - Los servicios de ejemplo «Private driver» y «Concierge» pasan a borrador: su texto está
 *   ahora en la portada de su tipo.
 * - Menús: bajo Servicios, los cuatro tipos; bajo Experiencias, las que falten.
 * - Ilustraciones (servicios/img/, dibujadas con dibujar.py; no son fotos y la web lo dice con
 *   «Illustration»): imagen de cada tipo y de los servicios que aún no tienen foto.
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
	if ( $id && ! mis_srv_escribir( $origen, $forzar ) ) {
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
$ids_par = array();
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
		$old = 'en' === $lang ? array( 'private-chef' ) : array( 'chef-privado' );
		$id  = mis_srv_post( 'mis_service', $lang, $s['slug'][ $lang ], $old, $t, $mis_forzar );
		wp_set_object_terms( $id, array( $tid ), 'tipo-servicio' );
		update_post_meta( $id, 'service_summary', $mis_tipos[ $s['type'] ][1][ 'en' === $lang ? 0 : 1 ] );
		update_post_meta( $id, 'service_price_from', 'en' === $lang ? 'Price on request' : 'Precio a consultar' );
		$ids_par[ $lang ] = $id;
	}
	if ( ! empty( $ids_par['en'] ) && ! empty( $ids_par['es'] ) ) {
		pll_save_post_translations( $ids_par );
	}
	$ids_par = array();
}

// Los servicios de ejemplo cuyo texto está ahora en la portada de su tipo.
foreach ( array( 'en' => array( 'private-driver', 'concierge' ), 'es' => array( 'chofer-privado', 'conserjeria' ) ) as $lang => $slugs ) {
	foreach ( $slugs as $slug ) {
		$id = mis_srv_buscar( 'mis_service', $lang, array( $slug ) );
		if ( $id && 'publish' === get_post_status( $id ) ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
			WP_CLI::log( "  ~ mis_service $lang /$slug/ a borrador (su texto está en la portada del tipo)" );
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

// ---------------------------------------------------------------- experiencias

WP_CLI::log( 'Experiencias…' );
// Slugs de la versión de ejemplo (contenido-inicial.php) que pasan a los de la web actual.
$mis_old_tours = array(
	'wine-tour-ronda' => array( 'en' => array( 'ronda-wine-tour' ), 'es' => array( 'ruta-vino-ronda' ) ),
);
// Zona de la experiencia (las que salen de Marbella a otra ciudad no llevan zona).
$mis_tour_zona = array( 'marbella-tapas-tour' => 'marbella-centro' );
$mis_tours_ids = array();
foreach ( $mis_datos['tours'] as $tour ) {
	$en_slug = $tour['slug']['en'];
	// Polylang (gratuito) no deja repetir el slug en otro idioma: el español lleva «-es».
	$es_slug = $tour['slug']['es'] === $en_slug ? $en_slug . '-es' : $tour['slug']['es'];
	$par     = array();
	foreach ( array( 'en' => $en_slug, 'es' => $es_slug ) as $lang => $slug ) {
		$id = mis_srv_post( 'mis_tour', $lang, $slug, $mis_old_tours[ $en_slug ][ $lang ] ?? array(), $tour[ $lang ], $mis_forzar );
		$f  = $tour['fields'];
		update_post_meta( $id, 'tour_duration', $f['duration'][ $lang ] );
		update_post_meta( $id, 'tour_private', (bool) $f['private'] );
		update_post_meta( $id, 'tour_languages', $f['languages'][ $lang ] );
		update_post_meta( $id, 'tour_pickup', $f['pickup'][ $lang ] );
		update_post_meta( $id, 'tour_group_size', $f['group_size'][ $lang ] );
		// Sin precio ni id de FareHarbor todavía: «Precio a consultar» y el botón lleva al contacto.
		if ( ! get_post_meta( $id, 'tour_fareharbor_item', true ) ) {
			delete_post_meta( $id, 'tour_price_from' );
			update_post_meta( $id, 'tour_booking_mode', 'formulario' );
		}
		$zona = $mis_tour_zona[ $en_slug ] ?? '';
		$zid  = $zona ? get_term_by( 'slug', 'en' === $lang ? $zona : $zona . '-es', 'zona' ) : null;
		wp_set_object_terms( $id, $zid ? array( (int) $zid->term_id ) : array(), 'zona' );
		$par[ $lang ] = $id;
	}
	pll_save_post_translations( $par );
	$mis_tours_ids[] = $par;
}

// ---------------------------------------------------------------- menús

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
$mis_ilus_alt = array(
	'transport' => array( 'Illustration of a car on the road', 'Ilustración de un coche en la carretera' ),
	'catering'  => array( 'Illustration of a chef’s hat and a plate', 'Ilustración de un gorro de chef y un plato' ),
	'events'    => array( 'Illustration of two glasses raised in a toast', 'Ilustración de dos copas brindando' ),
	'concierge' => array( 'Illustration of a reception bell', 'Ilustración de un timbre de recepción' ),
);
$mis_ilus = array();
foreach ( $mis_ilus_alt as $slug => $alt ) {
	$found = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'lang' => '', 'meta_key' => 'mis_ilustracion', 'meta_value' => $slug ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( $found ) {
		$mis_ilus[ $slug ] = (int) $found[0];
		continue;
	}
	$tmp = wp_tempnam( "servicio-$slug.jpg" );
	copy( __DIR__ . "/servicios/img/$slug.jpg", $tmp );
	$att = media_handle_sideload( array( 'name' => "servicio-$slug-ilustracion.jpg", 'tmp_name' => $tmp ), 0, $alt[0] );
	if ( is_wp_error( $att ) ) {
		WP_CLI::warning( "Ilustración $slug: " . $att->get_error_message() );
		continue;
	}
	update_post_meta( $att, 'mis_ilustracion', $slug );
	update_post_meta( $att, '_wp_attachment_image_alt', $alt[0] );
	wp_update_post( array( 'ID' => $att, 'post_excerpt' => 'Illustration' ) );
	$mis_ilus[ $slug ] = (int) $att;
	WP_CLI::log( "  + ilustración $slug (Medios $att)" );
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

flush_rewrite_rules( true );
WP_CLI::success( 'Servicios y experiencias listos.' );
