<?php
/**
 * Tema hijo Marbella in Style.
 *
 * Solo diseño: tipos de contenido y lógica van en los plugins propios
 * (maros-connector, mis-contenido), según la constitución.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function () {
		load_child_theme_textdomain( 'marbellainstyle', get_stylesheet_directory() . '/languages' );

		// Menús clásicos: Polylang (gratis) asigna un menú por idioma a cada ubicación.
		register_nav_menus(
			array(
				'primary'        => __( 'Cabecera: principal', 'marbellainstyle' ),
				'overlay'        => __( 'Capa «Menú»: secundario', 'marbellainstyle' ),
				'footer-stay'    => __( 'Pie: alojamiento', 'marbellainstyle' ),
				'footer-services'=> __( 'Pie: servicios y experiencias', 'marbellainstyle' ),
				'footer-company' => __( 'Pie: empresa', 'marbellainstyle' ),
				'footer-legal'   => __( 'Pie: legal', 'marbellainstyle' ),
			)
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		$file = get_stylesheet_directory() . '/assets/css/mis.css';
		wp_enqueue_style( 'marbellainstyle', get_stylesheet_directory_uri() . '/assets/css/mis.css', array(), (string) filemtime( $file ) );
		// Inicio en el móvil: panel del buscador, carruseles con puntos y «See all» de experiencias.
		$js = get_stylesheet_directory() . '/assets/js/inicio.js';
		wp_register_script( 'mis-inicio', get_stylesheet_directory_uri() . '/assets/js/inicio.js', array(), (string) filemtime( $js ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		if ( is_front_page() ) {
			wp_enqueue_script( 'mis-inicio' );
		}
	}
);
add_action(
	'after_setup_theme',
	static function () {
		add_editor_style( 'assets/css/mis.css' );
	}
);

// Estilos de bloque de la marca (spec 006 §1). El CSS está en assets/css/mis.css.
add_action(
	'init',
	static function () {
		$styles = array(
			'core/paragraph' => array(
				'etiqueta'       => __( 'Etiqueta', 'marbellainstyle' ),
				'enlace-accion'  => __( 'Enlace de acción', 'marbellainstyle' ),
			),
			'core/button'    => array(
				'contorno-claro' => __( 'Contorno claro (sobre foto)', 'marbellainstyle' ),
			),
			'core/image'     => array(
				'redonda'        => __( 'Redonda', 'marbellainstyle' ),
			),
			'core/group'     => array(
				'tarjeta'        => __( 'Tarjeta', 'marbellainstyle' ),
				'nota'           => __( 'Nota de aviso', 'marbellainstyle' ),
			),
		);
		foreach ( $styles as $block => $variations ) {
			foreach ( $variations as $name => $label ) {
				register_block_style( $block, array( 'name' => $name, 'label' => $label ) );
			}
		}
	}
);

/**
 * [mis_logo variant="color|negro|oro|blanco"] Logo de la marca (T33) enlazado a Inicio.
 * Color o negro sobre claro, oro sobre negro, blanco sobre foto u oro.
 */
add_shortcode(
	'mis_logo',
	static function ( $atts ) {
		$atts    = shortcode_atts( array( 'variant' => 'color' ), $atts );
		$variant = in_array( $atts['variant'], array( 'color', 'negro', 'oro', 'blanco' ), true ) ? $atts['variant'] : 'color';
		$home    = function_exists( 'pll_home_url' ) ? pll_home_url() : home_url( '/' );
		return sprintf(
			'<a class="mis-logo mis-logo--%4$s" href="%1$s" rel="home"><img src="%2$s" width="216" height="83" alt="%3$s"></a>',
			esc_url( $home ),
			esc_url( get_stylesheet_directory_uri() . '/assets/logo/marbellainstyle-logo-' . $variant . '.svg' ),
			esc_attr( get_bloginfo( 'name' ) ?: 'Marbella in Style' ),
			esc_attr( $variant )
		);
	}
);

/**
 * [mis_search] Buscador de la portada: fechas y huéspedes (mockup M1/E1).
 * Envía a Alojamiento con ?checkin=AAAA-MM-DD&checkout=AAAA-MM-DD&guests=N, las rutas del
 * estudio de URLs (spec 005). Alojamiento filtra el listado con esos parámetros y pinta el
 * buscador completo (marOS Connector, includes/search.php).
 */
add_shortcode(
	'mis_search',
	static function () {
		$guests = '';
		for ( $n = 1; $n <= 16; $n++ ) {
			$guests .= sprintf( '<option value="%1$d"%2$s>%1$d</option>', $n, 2 === $n ? ' selected' : '' );
		}
		$today = wp_date( 'Y-m-d' );
		// Fechas que abren el calendario al pulsar la celda (script de marOS Connector).
		wp_enqueue_script( 'maros-search' );
		wp_enqueue_script( 'mis-inicio' );
		// En el móvil, sobre la foto solo se ve una barra (fechas, huéspedes y lupa) que abre el
		// buscador en un panel desde abajo (opción 5 de la maqueta, Julio, 25-11-2026). En
		// escritorio la barra no se ve y el formulario sigue como antes.
		$bar = sprintf(
			'<button type="button" class="mis-qs__bar" aria-expanded="false" aria-controls="mis-qs-sheet">'
			. '<span class="mis-qs__cell"><span class="mis-qs__label">%1$s</span><span class="mis-qs__value" data-qs="dates">%2$s</span></span>'
			. '<span class="mis-qs__cell"><span class="mis-qs__label">%3$s</span><span class="mis-qs__value" data-qs="guests">2</span></span>'
			. '<span class="mis-qs__go" aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 21 21"/></svg></span>'
			. '<span class="screen-reader-text">%4$s</span>'
			. '</button>',
			esc_html( mis_t( 'Dates', 'Fechas' ) ),
			esc_html( mis_t( 'Add dates', 'Añadir fechas' ) ),
			esc_html( mis_t( 'Guests', 'Huéspedes' ) ),
			esc_html( mis_t( 'Search homes', 'Buscar casas' ) )
		);
		$top = sprintf(
			'<div class="mis-qs__top"><strong>%1$s</strong><button type="button" class="mis-qs__close" aria-label="%2$s">×</button></div>',
			esc_html( mis_t( 'Your stay', 'Tu estancia' ) ),
			esc_html( mis_t( 'Close', 'Cerrar' ) )
		);
		return '<div class="mis-qs">' . $bar . '<div class="mis-qs__sheet" id="mis-qs-sheet"><div class="mis-qs__panel">' . $top . sprintf(
			'<form class="mis-search" role="search" method="get" action="%1$s">'
			. '<div class="mis-search__field"><label for="mis-checkin">%2$s</label><input id="mis-checkin" type="date" name="checkin" min="%8$s"></div>'
			. '<div class="mis-search__field"><label for="mis-checkout">%3$s</label><input id="mis-checkout" type="date" name="checkout" min="%8$s"></div>'
			. '<div class="mis-search__field mis-search__field--guests"><label for="mis-guests">%4$s</label><select id="mis-guests" name="guests">%5$s</select></div>'
			. '<button class="mis-search__submit wp-element-button" type="submit">%6$s</button>'
			. '<p class="mis-search__hint">%7$s</p>'
			. '</form>',
			esc_url( mis_page_url( 'stay' ) ),
			esc_html( mis_t( 'Arrival', 'Llegada' ) ),
			esc_html( mis_t( 'Departure', 'Salida' ) ),
			esc_html( mis_t( 'Guests', 'Huéspedes' ) ),
			$guests,
			esc_html( mis_t( 'See available homes', 'Ver casas disponibles' ) ),
			esc_html( mis_t( 'Flexible dates? Leave them empty and we will show you every home.', '¿Fechas flexibles? Déjalas en blanco y te enseñamos todas las casas.' ) ),
			esc_attr( $today )
		) . '</div></div></div>';
	}
);

// Categoría propia para los patrones de marca (en /patterns se registran solos).
add_action(
	'init',
	static function () {
		register_block_pattern_category( 'marbellainstyle', array( 'label' => __( 'Marbella in Style', 'marbellainstyle' ) ) );
	}
);

function mis_is_es(): bool {
	return function_exists( 'pll_current_language' ) && 'es' === pll_current_language();
}

/**
 * Texto en el idioma actual: mis_t( 'Stay', 'Alojamiento' ).
 */
function mis_t( string $en, string $es ): string {
	return mis_is_es() ? $es : $en;
}

/**
 * [mis_menu location="primary" class="…"] Menú clásico de una ubicación.
 */
add_shortcode(
	'mis_menu',
	static function ( $atts ) {
		$atts = shortcode_atts( array( 'location' => 'primary', 'class' => '' ), $atts );
		if ( ! has_nav_menu( $atts['location'] ) ) {
			return '';
		}
		return wp_nav_menu(
			array(
				'theme_location' => $atts['location'],
				'container'      => 'nav',
				'container_class'=> 'mis-menu mis-menu--' . sanitize_html_class( $atts['location'] ) . ' ' . sanitize_html_class( $atts['class'] ),
				'depth'          => 2,
				'echo'           => false,
				'fallback_cb'    => false,
			)
		);
	}
);

/**
 * Menú principal: bajo «Stay» van «Villas» y «Apartments», que llevan al listado filtrado por tipo
 * (Julio, 25-11-2026). Sustituyen a las casas sueltas que el menú guardado tiene como hijas de
 * «Stay», así que no hace falta tocar los menús de WordPress. En escritorio se abre al pasar el
 * ratón; en el menú en capa salen debajo de «Stay».
 */
add_filter(
	'wp_nav_menu_objects',
	static function ( array $items, $args ): array {
		if ( 'primary' !== ( $args->theme_location ?? '' ) || ! class_exists( 'Maros_Search' ) ) {
			return $items;
		}
		$stay = get_page_by_path( 'stay' );
		if ( ! $stay ) {
			return $items;
		}
		$en_id  = static fn( int $id ): int => function_exists( 'pll_get_post' ) ? (int) ( pll_get_post( $id, 'en' ) ?: $id ) : $id;
		$stay   = $en_id( (int) $stay->ID );
		$parent = null;
		foreach ( $items as $item ) {
			if ( 'page' === $item->object && ! (int) $item->menu_item_parent && $en_id( (int) $item->object_id ) === $stay ) {
				$parent = $item;
				break;
			}
		}
		if ( ! $parent ) {
			return $items;
		}
		$on_stay = Maros_Search::is_stay_page();
		$type    = $on_stay ? Maros_Search::params()['type'] : '';
		$out     = array();
		foreach ( $items as $item ) {
			if ( (int) $item->menu_item_parent === (int) $parent->db_id ) {
				continue;
			}
			$out[] = $item;
			if ( $item !== $parent ) {
				continue;
			}
			$item->classes[] = 'menu-item-has-children';
			$item->classes[] = 'mis-menu__stay';
			$order           = 0;
			foreach ( Maros_Search::TYPES as $code => $names ) {
				$id      = -1000 - ( ++$order );
				$current = $type === $code;
				$out[]   = new WP_Post(
					(object) array(
						'ID'                    => $id,
						'db_id'                 => $id,
						'menu_item_parent'      => (string) $parent->db_id,
						'object_id'             => 0,
						'object'                => 'custom',
						'type'                  => 'custom',
						'type_label'            => '',
						'post_type'             => 'nav_menu_item',
						'post_status'           => 'publish',
						'title'                 => $names[ mis_is_es() ? 1 : 0 ],
						'url'                   => Maros_Search::type_url( $code ),
						'target'                => '',
						'attr_title'            => '',
						'description'           => '',
						'xfn'                   => '',
						'classes'               => array( 'menu-item', 'menu-item-type-custom', 'mis-menu__type' ) + ( $current ? array( 3 => 'current-menu-item' ) : array() ),
						'current'               => $current,
						'current_item_ancestor' => false,
						'current_item_parent'   => false,
						'menu_order'            => (int) $parent->menu_order,
					)
				);
			}
			// Con un tipo elegido, «Stay» no es la página actual: lo es su hija.
			if ( $type ) {
				$item->classes = array_diff( $item->classes, array( 'current-menu-item', 'current_page_item' ) );
				$item->classes[] = 'current-menu-parent';
			}
		}
		return $out;
	},
	10,
	2
);

/**
 * [mis_lang] Selector de idioma EN / ES.
 */
add_shortcode(
	'mis_lang',
	static function () {
		if ( ! function_exists( 'pll_the_languages' ) ) {
			return '';
		}
		$langs = pll_the_languages( array( 'raw' => 1, 'hide_if_empty' => 0 ) );
		$out   = '<nav class="mis-lang" aria-label="Language">';
		foreach ( (array) $langs as $lang ) {
			$out .= sprintf(
				'<a href="%s" hreflang="%s" lang="%s"%s>%s</a>',
				esc_url( $lang['url'] ),
				esc_attr( $lang['slug'] ),
				esc_attr( $lang['slug'] ),
				$lang['current_lang'] ? ' aria-current="true"' : '',
				esc_html( strtoupper( $lang['slug'] ) )
			);
		}
		return $out . '</nav>';
	}
);

/**
 * [mis_t en="…" es="…"] Texto fijo de plantilla en el idioma actual.
 */
add_shortcode(
	'mis_t',
	static fn( $atts ) => esc_html( mis_t( (string) ( $atts['en'] ?? '' ), (string) ( $atts['es'] ?? '' ) ) )
);

/**
 * [mis_link to="contact" en="…" es="…" class="…"] Enlace a una página por su slug inglés,
 * resuelto a la traducción del idioma actual.
 */
add_shortcode(
	'mis_link',
	static function ( $atts ) {
		$atts = shortcode_atts( array( 'to' => '', 'en' => '', 'es' => '', 'class' => '' ), $atts );
		$url  = mis_page_url( $atts['to'] );
		return sprintf( '<a class="%s" href="%s">%s</a>', esc_attr( $atts['class'] ), esc_url( $url ), esc_html( mis_t( $atts['en'], $atts['es'] ) ) );
	}
);

function mis_page_url( string $slug ): string {
	$page = get_page_by_path( $slug );
	if ( ! $page ) {
		return home_url( '/' );
	}
	$id = function_exists( 'pll_get_post' ) ? ( pll_get_post( $page->ID ) ?: $page->ID ) : $page->ID;
	return (string) get_permalink( $id );
}

/**
 * [mis_book] Botón «Reservar» de la cabecera: lleva a Alojamiento.
 */
add_shortcode(
	'mis_book',
	static fn() => sprintf( '<a class="mis-book wp-element-button" href="%s">%s</a>', esc_url( mis_page_url( 'stay' ) ), esc_html( mis_t( 'Book', 'Reservar' ) ) )
);

/**
 * [mis_form name="contact|request"] Formulario de Contact Form 7 del idioma actual.
 * Los formularios se llaman «<name>-en» y «<name>-es». No guardan solicitudes (D4).
 */
add_shortcode(
	'mis_form',
	static function ( $atts ) {
		$name  = sanitize_key( $atts['name'] ?? 'contact' ) . '-' . ( mis_is_es() ? 'es' : 'en' );
		$forms = get_posts( array( 'post_type' => 'wpcf7_contact_form', 'name' => $name, 'numberposts' => 1, 'lang' => '' ) );
		return $forms ? do_shortcode( sprintf( '[contact-form-7 id="%d"]', $forms[0]->ID ) ) : '';
	}
);

/**
 * Una plantilla del tema con el slug de la página (page-areas.html, page-zonas.html) gana a la plantilla
 * elegida en la página. Areas y Zonas se crearon con «page-no-title» (de Ollie), que estrecha el
 * contenido a 760 px: la rejilla de zonas salía más estrecha que la cabecera y el resto de la web, y
 * sin la portada de la plantilla (Julio, 24-11-2026).
 */
add_filter(
	'page_template_hierarchy',
	static function ( array $templates ): array {
		$page = get_queried_object();
		if ( ! $page instanceof WP_Post || '' === $page->post_name ) {
			return $templates;
		}
		$slug = 'page-' . $page->post_name;
		if ( ! file_exists( get_stylesheet_directory() . '/templates/' . $slug . '.html' ) ) {
			return $templates;
		}
		$own = array_values( array_filter( $templates, static fn( $t ) => $t === $slug . '.php' || $t === $slug ) );
		return array_values( array_unique( array_merge( $own ?: array( $slug . '.php' ), $templates ) ) );
	}
);

/*
 * ------------------------------------------------------------------ Inicio en el móvil
 * Maqueta «home en móvil» (Julio, 25-11-2026). El contenido de Inicio vive en la base de datos;
 * estos filtros añaden al pintarlo, solo en la portada, lo que el móvil necesita. En escritorio
 * no se ve nada nuevo (lo esconde mis.css).
 */

/** Foto de Inicio (mis-contenido, includes/inicio.php) con el tamaño y la clase pedidos. */
function mis_inicio_img( string $hueco, string $class, string $size = 'large' ): string {
	$id = function_exists( 'mis_inicio_foto' ) ? mis_inicio_foto( $hueco ) : 0;
	return $id ? wp_get_attachment_image( $id, $size, false, array( 'class' => $class, 'loading' => 'lazy', 'sizes' => '(max-width: 767px) 100vw, 33vw' ) ) : '';
}

/**
 * Tarjetas Stay / Concierge / Experiences: cada columna lleva encima su foto con el título,
 * enlazada como el «See the …» de la columna. En el móvil van en carrusel con puntos.
 */
add_filter(
	'render_block_core/columns',
	static function ( string $html, array $block ): string {
		if ( ! is_front_page() || false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'mis-pillars' ) ) {
			return $html;
		}
		$huecos = array( 'stay', 'concierge', 'experiences' );
		$i      = 0;
		$html   = preg_replace_callback(
			'#<div class="wp-block-column(?: [^"]*)?"[^>]*>(.*?)</div>(?=\s*(?:<div class="wp-block-column[ "]|</div>\s*$))#s',
			static function ( array $m ) use ( &$i, $huecos ): string {
				$hueco = $huecos[ $i++ ] ?? '';
				$img   = $hueco ? mis_inicio_img( $hueco, 'mis-pillar__img' ) : '';
				preg_match( '#<h3[^>]*>(.*?)</h3>#s', $m[1], $h );
				preg_match( '#<a [^>]*href="([^"]+)"#', $m[1], $a );
				if ( ! $img || ! $h || ! $a ) {
					return $m[0];
				}
				$foto = sprintf(
					'<a class="mis-pillar__ph" href="%1$s" tabindex="-1" aria-hidden="true">%2$s<span class="mis-pillar__name">%3$s</span></a>',
					esc_url( html_entity_decode( $a[1] ) ),
					$img,
					wp_strip_all_tags( $h[1] )
				);
				return str_replace( $m[1], $foto . $m[1], $m[0] );
			},
			$html
		);
		$dots = '<div class="mis-dots" aria-hidden="true">' . str_repeat( '<i></i>', max( 1, $i ) ) . '</div>';
		return $html . $dots;
	},
	10,
	2
);

/** Patrón de rejilla que lleva dentro un bloque (o '' si ninguno). */
function mis_inicio_rejilla( array $block ): string {
	foreach ( $block['innerBlocks'] ?? array() as $inner ) {
		if ( 'core/pattern' === $inner['blockName'] ) {
			return (string) ( $inner['attrs']['slug'] ?? '' );
		}
	}
	return '';
}

/**
 * Foto a todo lo ancho encima de las secciones «Exclusive homes» y «Experiences» (como en el
 * ejemplo de Louis Vuitton que pasó Julio). Solo en el móvil.
 */
add_filter(
	'render_block_core/group',
	static function ( string $html, array $block ): string {
		if ( ! is_front_page() ) {
			return $html;
		}
		$huecos = array(
			'marbellainstyle/property-grid' => 'banner_homes',
			'marbellainstyle/tour-grid'     => 'banner_tours',
		);
		$hueco  = $huecos[ mis_inicio_rejilla( $block ) ] ?? '';
		$img    = $hueco ? mis_inicio_img( $hueco, 'mis-home-banner__img' ) : '';
		if ( ! $img ) {
			return $html;
		}
		return preg_replace( '#^(\s*<div[^>]*>)#', '$1<figure class="mis-home-banner">' . $img . '</figure>', $html, 1 );
	},
	10,
	2
);

/**
 * «See all …» bajo las casas, los servicios y las experiencias de Inicio. En el móvil solo se ven
 * cuatro de cada; en escritorio el botón no se ve y la rejilla sigue entera.
 */
add_filter(
	'render_block_core/pattern',
	static function ( string $html, array $block ): string {
		if ( ! is_front_page() ) {
			return $html;
		}
		$botones = array(
			'marbellainstyle/property-grid' => array( 'stay', 'See all homes', 'Ver todas las casas' ),
			'marbellainstyle/service-grid'  => array( 'services', 'See all services', 'Ver todos los servicios' ),
			'marbellainstyle/tour-grid'     => array( 'experiences', 'See all experiences', 'Ver todas las experiencias' ),
		);
		$b = $botones[ (string) ( $block['attrs']['slug'] ?? '' ) ] ?? null;
		if ( ! $b ) {
			return $html;
		}
		return $html . sprintf(
			'<p class="mis-home-more"><a class="mis-btn-outline" href="%1$s"%3$s>%2$s</a></p>',
			esc_url( mis_page_url( $b[0] ) ),
			esc_html( mis_t( $b[1], $b[2] ) ),
			'experiences' === $b[0] ? ' data-mis-tours-all' : ''
		);
	},
	10,
	2
);

/**
 * Portada, columna «Stay»: no todas las casas las gestionamos nosotros (Julio, 25-11-2026). La frase
 * está guardada en el contenido de la página, así que se cambia al pintarla.
 */
add_filter(
	'render_block_core/paragraph',
	static function ( string $html ): string {
		return strtr(
			$html,
			array(
				'Villas and apartments managed by our own team.'          => 'Villas and apartments direct from their owners.',
				'Villas y apartamentos gestionados por nuestro equipo.' => 'Villas y apartamentos directamente de sus propietarios.',
			)
		);
	}
);
