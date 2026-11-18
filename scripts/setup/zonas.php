<?php
/**
 * Zonas de la web nueva que no crea contenido-inicial.php, con su guía (spec 005; las 13 zonas
 * de Julio del 14-11-2026 en marOS.content, preguntas-ola-1 Z1). Idempotente.
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
	// Texto provisional (Julio, 18-11-2026): una entrada de «para quién encaja» hasta que PC-17
	// escriba la guía completa en marOS.content.
	'los-flamingos' => array(
		'nombre' => array( 'Los Flamingos', 'Los Flamingos' ),
		'en'     => '<p>A golf community in the hills of Benahavís, above the New Golden Mile. It suits golfers and families who want a quiet villa with views of the coast.</p>',
		'es'     => '<p>Una urbanización de golf en las colinas de Benahavís, sobre la New Golden Mile. Encaja con golfistas y familias que buscan una villa tranquila con vistas a la costa.</p>',
	),
	'la-alqueria' => array(
		'nombre' => array( 'La Alquería', 'La Alquería' ),
		'en'     => '<p>A residential area in the hills of Benahavís, close to Los Flamingos. It suits guests who want space and quiet, a short drive from the coast.</p>',
		'es'     => '<p>Una zona residencial en las colinas de Benahavís, cerca de Los Flamingos. Encaja con quien busca espacio y tranquilidad, a poca distancia en coche de la costa.</p>',
	),
	'guadalmina' => array(
		'nombre' => array( 'Guadalmina', 'Guadalmina' ),
		'en'     => '<p>A residential area by the sea at the western end of Marbella, next to San Pedro de Alcántara, with its own golf club and beach. It suits families and golfers.</p>',
		'es'     => '<p>Una zona residencial junto al mar en el extremo oeste de Marbella, al lado de San Pedro de Alcántara, con club de golf y playa propios. Encaja con familias y golfistas.</p>',
	),
	'rio-real' => array(
		'nombre' => array( 'Río Real', 'Río Real' ),
		'en'     => '<p>A residential area east of Marbella town, between the Río Real golf course and the beach. It suits guests who want to be close to the town centre and the sea.</p>',
		'es'     => '<p>Una zona residencial al este de Marbella, entre el campo de golf de Río Real y la playa. Encaja con quien quiere estar cerca del centro y del mar.</p>',
	),
	'estepona' => array(
		'nombre' => array( 'Estepona', 'Estepona' ),
		'en'     => '<p>A coastal town to the west of Marbella, with an old town of whitewashed streets, a port and a long seafront promenade. It suits guests who want town life at a slower pace.</p>',
		'es'     => '<p>Un pueblo de costa al oeste de Marbella, con casco antiguo de calles blancas, puerto y un largo paseo marítimo. Encaja con quien quiere vida de pueblo a un ritmo más tranquilo.</p>',
	),
	'benahavis' => array(
		'nombre' => array( 'Benahavís village', 'Benahavís pueblo' ),
		'en'     => '<p>A white village in the mountains above San Pedro de Alcántara, known for its restaurants. It suits guests who want countryside and views, a short drive from the coast.</p>',
		'es'     => '<p>Un pueblo blanco en la sierra, sobre San Pedro de Alcántara, conocido por sus restaurantes. Encaja con quien busca campo y vistas, a poca distancia en coche de la costa.</p>',
	),
	'san-pedro' => array(
		'nombre' => array( 'San Pedro de Alcántara', 'San Pedro de Alcántara' ),
		'en'     => '<p>A town within Marbella, between Puerto Banús and Guadalmina, with its own boulevard, shops and seafront promenade. It suits families who want everyday life within walking distance.</p>',
		'es'     => '<p>Un núcleo de Marbella entre Puerto Banús y Guadalmina, con su bulevar, comercios y paseo marítimo. Encaja con familias que quieren tener el día a día a pie.</p>',
	),
	'marbella-centro' => array(
		'nombre' => array( 'Marbella town centre', 'Marbella centro' ),
		'en'     => '<p>The old town around Plaza de los Naranjos, the Alameda park and the seafront promenade. It suits guests who want to walk to restaurants, shops and the beach.</p>',
		'es'     => '<p>El casco antiguo en torno a la plaza de los Naranjos, la Alameda y el paseo marítimo. Encaja con quien quiere ir andando a restaurantes, tiendas y playa.</p>',
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
