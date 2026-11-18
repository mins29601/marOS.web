<?php
/**
 * Zonas nuevas con su guía (spec 005, mapa del sitio: «Zonas sin guía»). Idempotente.
 *
 * Uso: wp eval-file scripts/setup/zonas.php
 * (en local: npm run wp -- eval-file wp-content/mis-scripts/setup/zonas.php)
 *
 * Por cada zona:
 * - crea el término de la taxonomía `zona` en inglés y español, enlazados con Polylang
 *   (/areas/<slug>/ y /es/zonas/<slug>-es/), con el mismo slug que usa marOS Connector;
 * - escribe la guía (la descripción del término, que la plantilla taxonomy-zona muestra
 *   bajo el título) solo si la zona aún no tiene texto: lo que el equipo edite en
 *   WordPress no se pisa. Para reescribirla: `wp eval-file zonas.php forzar`;
 * - la añade a los menús «primary» y «footer-stay» de cada idioma, bajo Zonas/Alojamiento,
 *   si aún no está.
 *
 * Para añadir otra zona basta con otra entrada en $mis_zonas.
 *
 * Los textos siguen la estructura y los criterios de PC-17 en marOS.content
 * (contenidos/ideas/PC-17-revision-zonas.md y docs/guia-editorial.md): sin distancias ni
 * tiempos que no se hayan medido y sin locales que no recomiende Marbella in Style. Faltan
 * «Comer y salir» y «Servicios», que esperan la lista de locales recomendados. La versión
 * definitiva sale del flujo de marOS.content y se pega en WordPress o aquí con «forzar».
 *
 * @package MarbellaInStyle
 */

if ( ! function_exists( 'pll_set_term_language' ) || ! class_exists( 'Maros_Sync' ) ) {
	WP_CLI::error( 'Faltan Polylang o marOS Connector.' );
}

// WP-CLI corre sin usuario: sin esto, kses quita los <h2> y <ul> de la guía.
kses_remove_filters();

$mis_forzar = in_array( 'forzar', $args ?? array(), true );

$mis_zonas = array(
	'new-golden-mile' => array(
		'nombre' => array( 'New Golden Mile', 'New Golden Mile' ),
		'en'     => <<<'HTML'
<p>The New Golden Mile is the stretch of coast in Estepona between San Pedro de Alcántara and Estepona town, with the sea on one side and the Sierra Bermeja foothills on the other. It suits families and groups who want a large villa with a garden and pool, close to the beach and the golf courses, and are happy to drive to restaurants and shops.</p>

<h2>What it is like</h2>
<p>A residential area of villas and gated communities around Cancelada, El Paraíso and Atalaya, with the N-340 coast road (A-7) and the AP-7 toll road running through it. It is quieter than Marbella and Puerto Banús, especially outside the summer months.</p>

<h2>Beaches</h2>
<p>Long, open beaches such as El Saladillo and Guadalmansa, with beach clubs and chiringuitos along the shore.</p>

<h2>Golf and sport</h2>
<p>Atalaya Golf &amp; Country Club and El Paraíso Golf Club are within the area, and the courses of Benahavís and Nueva Andalucía are a short drive away.</p>

<h2>Getting around</h2>
<p>You will need a car or private transport for almost everything: shops, restaurants and the beach are spread along the coast road. Our team can arrange a driver for airport transfers and evenings out.</p>

<h2>Good to know</h2>
<p>Choose the New Golden Mile for space and quiet, not for walking to the old town or the port. If nightlife on your doorstep matters, Puerto Banús or the Golden Mile will suit you better.</p>
HTML,
		'es'     => <<<'HTML'
<p>La New Golden Mile es el tramo de costa de Estepona entre San Pedro de Alcántara y el pueblo de Estepona, con el mar a un lado y las faldas de Sierra Bermeja al otro. Encaja con familias y grupos que buscan una villa grande con jardín y piscina, cerca de la playa y del golf, y no les importa coger el coche para ir a cenar o a comprar.</p>

<h2>Cómo es</h2>
<p>Una zona residencial de villas y urbanizaciones cerradas en torno a Cancelada, El Paraíso y Atalaya, que cruzan la N-340 (A-7) y la autopista de peaje AP-7. Es más tranquila que Marbella y Puerto Banús, sobre todo fuera del verano.</p>

<h2>Playas</h2>
<p>Playas largas y abiertas como El Saladillo y Guadalmansa, con beach clubs y chiringuitos a lo largo de la orilla.</p>

<h2>Golf y deporte</h2>
<p>Atalaya Golf &amp; Country Club y El Paraíso Golf Club están en la propia zona, y los campos de Benahavís y Nueva Andalucía quedan a un rato en coche.</p>

<h2>Cómo moverse</h2>
<p>Necesitarás coche o transporte privado para casi todo: tiendas, restaurantes y playa están repartidos a lo largo de la carretera de la costa. Nuestro equipo te organiza un chófer para el aeropuerto y para salir por la noche.</p>

<h2>Lo que hay que saber</h2>
<p>Elige la New Golden Mile por espacio y tranquilidad, no para ir andando al casco antiguo o al puerto. Si quieres la vida nocturna a la puerta de casa, te encajarán mejor Puerto Banús o la Milla de Oro.</p>
HTML,
	),
);

// ---------------------------------------------------------------- zonas y guías

WP_CLI::log( 'Zonas nuevas…' );
$mis_nuevas = array(); // lang => [term_id]
foreach ( $mis_zonas as $mis_slug => $mis_z ) {
	foreach ( array( 'en', 'es' ) as $mis_lang ) {
		$mis_id = Maros_Sync::term( 'zona', $mis_slug, $mis_z['nombre'], $mis_lang );
		if ( ! $mis_id ) {
			WP_CLI::warning( "No se pudo crear la zona $mis_slug ($mis_lang)" );
			continue;
		}
		$mis_nuevas[ $mis_lang ][] = $mis_id;

		$mis_term = get_term( $mis_id, 'zona' );
		if ( '' === trim( (string) $mis_term->description ) || $mis_forzar ) {
			wp_update_term( $mis_id, 'zona', array( 'description' => trim( $mis_z[ $mis_lang ] ) ) );
			WP_CLI::log( "  + guía $mis_slug ($mis_lang)" );
		} else {
			WP_CLI::log( "  = $mis_slug ($mis_lang) ya tiene guía; no se toca" );
		}
	}
}

// ---------------------------------------------------------------- menús

WP_CLI::log( 'Menús…' );

/** Elemento de primer nivel del que cuelgan las zonas: el que ya tiene zonas debajo o, si no, el primero. */
function mis_zonas_padre( array $items ): int {
	$top = array();
	foreach ( $items as $it ) {
		if ( 0 === (int) $it->menu_item_parent ) {
			$top[] = (int) $it->ID;
		}
	}
	foreach ( $items as $it ) {
		if ( 'zona' === $it->object && in_array( (int) $it->menu_item_parent, $top, true ) ) {
			return (int) $it->menu_item_parent;
		}
	}
	return 0;
}

foreach ( array( 'en', 'es' ) as $mis_lang ) {
	foreach ( array( 'primary', 'footer-stay' ) as $mis_loc ) {
		$mis_menu = wp_get_nav_menu_object( "$mis_loc ($mis_lang)" );
		if ( ! $mis_menu ) {
			continue; // Sin contenido inicial no hay menús: no se crean aquí.
		}
		$mis_items = wp_get_nav_menu_items( $mis_menu->term_id ) ?: array();
		$mis_padre = mis_zonas_padre( $mis_items );
		if ( ! $mis_padre && 'primary' === $mis_loc ) {
			continue; // En el menú principal solo se añade bajo «Zonas»; sin él no se adivina.
		}
		if ( ! $mis_padre ) {
			foreach ( $mis_items as $mis_it ) {
				if ( 0 === (int) $mis_it->menu_item_parent ) {
					$mis_padre = (int) $mis_it->ID;
					break;
				}
			}
		}
		$mis_ya = array_map( static fn( $it ) => 'zona' === $it->object ? (int) $it->object_id : 0, $mis_items );
		foreach ( $mis_nuevas[ $mis_lang ] ?? array() as $mis_id ) {
			if ( in_array( $mis_id, $mis_ya, true ) ) {
				continue;
			}
			wp_update_nav_menu_item(
				$mis_menu->term_id,
				0,
				array(
					'menu-item-status'    => 'publish',
					'menu-item-parent-id' => $mis_padre,
					'menu-item-object'    => 'zona',
					'menu-item-object-id' => $mis_id,
					'menu-item-type'      => 'taxonomy',
				)
			);
			WP_CLI::log( "  + " . get_term( $mis_id )->name . " en $mis_loc ($mis_lang)" );
		}
	}
}

flush_rewrite_rules( true );
WP_CLI::success( 'Zonas listas.' );
