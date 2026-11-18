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
<p>The New Golden Mile is the stretch of coast between San Pedro Alcántara and Estepona: around ten kilometres of beaches, golf courses and quiet residential streets running between the sea and the mountains. It has the climate and the sea views of Marbella, with more space, more privacy and a slower pace.</p>

<h2>Why stay here</h2>
<ul>
<li><strong>Beachfront living.</strong> Long, wide beaches such as El Saladillo and Guadalmansa, with beach clubs and chiringuitos that rarely feel crowded, even in August.</li>
<li><strong>Golf on the doorstep.</strong> Several courses within a few minutes' drive, including Atalaya and El Paraíso, with Los Flamingos and the golf valley of Nueva Andalucía close by.</li>
<li><strong>Space for families and groups.</strong> Large villas with gardens and pools in gated communities such as Cancelada, Bel Air, El Paraíso and Costalita.</li>
<li><strong>Between two towns.</strong> Puerto Banús is around 15 minutes away and Estepona's old town around 10, so you can dine out every night and come back to a quiet house.</li>
</ul>

<h2>Getting around</h2>
<p>The A-7 coast road runs the length of the area, so a car (or our private transport) is the easiest way to move around. Málaga airport is about an hour's drive and Gibraltar about 45 minutes.</p>

<h2>Good to know</h2>
<p>Supermarkets, pharmacies, international schools and medical centres are all within a short drive. It is a lively area in summer and a calm one the rest of the year, which makes it a good choice for long stays as well as holidays.</p>
HTML,
		'es'     => <<<'HTML'
<p>La New Golden Mile es el tramo de costa entre San Pedro Alcántara y Estepona: unos diez kilómetros de playas, campos de golf y urbanizaciones tranquilas entre el mar y la sierra. Tiene el clima y las vistas de Marbella, con más espacio, más privacidad y un ritmo más pausado.</p>

<h2>Por qué alojarse aquí</h2>
<ul>
<li><strong>Vida de playa.</strong> Playas largas y amplias como El Saladillo y Guadalmansa, con beach clubs y chiringuitos que rara vez se llenan, incluso en agosto.</li>
<li><strong>Golf a la puerta de casa.</strong> Varios campos a pocos minutos en coche, entre ellos Atalaya y El Paraíso, y Los Flamingos y el valle del golf de Nueva Andalucía muy cerca.</li>
<li><strong>Espacio para familias y grupos.</strong> Villas grandes con jardín y piscina en urbanizaciones cerradas como Cancelada, Bel Air, El Paraíso o Costalita.</li>
<li><strong>Entre dos pueblos.</strong> Puerto Banús queda a unos 15 minutos y el casco antiguo de Estepona a unos 10: se puede cenar fuera cada noche y volver a una casa tranquila.</li>
</ul>

<h2>Cómo moverse</h2>
<p>La A-7 recorre toda la zona junto a la costa, así que lo más cómodo es el coche (o nuestro transporte privado). El aeropuerto de Málaga está a una hora en coche y Gibraltar a unos 45 minutos.</p>

<h2>Bueno saber</h2>
<p>Supermercados, farmacias, colegios internacionales y centros médicos quedan a pocos minutos. En verano es una zona animada y el resto del año es tranquila, por eso funciona tanto para vacaciones como para estancias largas.</p>
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
