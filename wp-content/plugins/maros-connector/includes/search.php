<?php
/**
 * Búsqueda de propiedades (spec 003, US3 y FR-003b; spec 005, conclusiones 4, 6 y 10).
 *
 * En la página Alojamiento, el listado de casas (patrón «property-grid») se filtra con los
 * parámetros de la URL, los mismos que envía el buscador de la portada:
 *
 *   ?checkin=AAAA-MM-DD&checkout=AAAA-MM-DD  fechas (las dos o ninguna)
 *   &guests=N                                huéspedes (máximo de la casa ≥ N)
 *   &area=<slug de zona>                     zona, en el idioma de la página
 *   &bedrooms=N                              dormitorios mínimos
 *   &type=villa|apartment                    tipo de propiedad (categoría de la propiedad en marOS)
 *   &feat=private-pool,sea-view              equipamiento (códigos de marOS, iguales en ES y EN)
 *
 * La disponibilidad sale solo de las tablas que llena la sincronización (Maros_Calendar), nunca
 * de marOS. Encima del listado se pinta el buscador completo ([maros_buscador]).
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

class Maros_Search {

	/**
	 * Equipamiento que entiende la URL (&feat=…). Código => [EN, ES]. En el buscador solo salen los
	 * de CHIPS; los demás siguen valiendo en enlaces.
	 */
	public const FEATURES = array(
		'private-pool'     => array( 'Private pool', 'Piscina privada' ),
		'heated-pool'      => array( 'Heated pool', 'Piscina climatizada' ),
		'sea-view'         => array( 'Sea view', 'Vistas al mar' ),
		'air-conditioning' => array( 'Air conditioning', 'Aire acondicionado' ),
		'gym'              => array( 'Gym', 'Gimnasio' ),
		'bbq'              => array( 'BBQ', 'Barbacoa' ),
	);

	/**
	 * Chips del buscador: solo las características importantes (Julio, 25-11-2026: «los filtros los
	 * podemos quitar… o dejar sólo características importantes»). Fuera tipo, zona y dormitorios.
	 */
	private const CHIPS = array( 'sea-view', 'heated-pool' );

	/**
	 * Tipos de propiedad del filtro: la categoría de la propiedad en marOS (catálogo «category»,
	 * hoy villa y apartamento), que la sincronización guarda en la taxonomía «tipo-propiedad»
	 * (código en inglés, código-es en español). Código => [EN, ES], en plural.
	 */
	public const TYPES = array(
		'villa'     => array( 'Villas', 'Villas' ),
		'apartment' => array( 'Apartments', 'Apartamentos' ),
	);

	/** Parámetros de la URL que forman la búsqueda (para «Quitar filtros»). */
	private const QUERY_ARGS = array( 'checkin', 'checkout', 'guests', 'bedrooms', 'area', 'type', 'feat' );

	/** Estancia más larga que se busca (el calendario llega a 18 meses). */
	private const MAX_NIGHTS = 90;

	private static ?array $params = null;

	/** @var array<int,array{nights:int,total:float,currency:string}>|null Casas libres por id de entrada. */
	private static ?array $available = null;

	/** Si el buscador ya se pintó encima del título de Alojamiento. */
	private static bool $form_on_top = false;

	public static function init(): void {
		add_filter( 'query_loop_block_query_vars', array( __CLASS__, 'filter_query' ), 10, 2 );
		add_filter( 'render_block_core/group', array( __CLASS__, 'render_hero' ), 10, 2 );
		add_filter( 'render_block_core/query', array( __CLASS__, 'render_query' ), 10, 2 );
		add_filter( 'render_block_core/query-no-results', array( __CLASS__, 'render_no_results' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'headers' ) );
		add_shortcode( 'maros_buscador', array( __CLASS__, 'form' ) );
		// El script lo usa también el buscador de la portada ([mis_search], del tema).
		add_action(
			'init',
			static function () {
				$dir = dirname( __DIR__ ) . '/assets/';
				// Calendario único de la web (reserva de la ficha y buscadores).
				wp_register_script( 'maros-calendar', plugins_url( 'assets/calendar.js', __DIR__ ), array(), (string) filemtime( $dir . 'calendar.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
				wp_register_script( 'maros-search', plugins_url( 'assets/search.js', __DIR__ ), array( 'maros-calendar' ), (string) filemtime( $dir . 'search.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
			}
		);
	}

	/**
	 * Parámetros de la búsqueda, ya validados. Fechas: las dos, en orden, desde hoy y como mucho
	 * MAX_NIGHTS noches; si no, se ignoran las dos.
	 */
	public static function params(): array {
		if ( null !== self::$params ) {
			return self::$params;
		}
		// phpcs:disable WordPress.Security.NonceVerification
		// Un parámetro repetido (feat[]=…, o una URL manipulada) llega como lista: aquí cuenta como vacío.
		$get      = static fn( string $k ): string => is_scalar( $_GET[ $k ] ?? '' ) ? sanitize_text_field( wp_unslash( (string) ( $_GET[ $k ] ?? '' ) ) ) : '';
		$int      = static fn( string $k ): int => is_scalar( $_GET[ $k ] ?? 0 ) ? absint( $_GET[ $k ] ?? 0 ) : 0;
		$checkin  = $get( 'checkin' );
		$checkout = $get( 'checkout' );
		$nights   = 0;
		if ( self::is_date( $checkin ) && self::is_date( $checkout ) && $checkin >= wp_date( 'Y-m-d' ) ) {
			$nights = (int) round( ( strtotime( $checkout . ' 00:00:00 UTC' ) - strtotime( $checkin . ' 00:00:00 UTC' ) ) / DAY_IN_SECONDS );
		}
		if ( $nights < 1 || $nights > self::MAX_NIGHTS ) {
			$checkin  = '';
			$checkout = '';
			$nights   = 0;
		}
		// Chips: feat[]=a&feat[]=b (formulario) o feat=a,b (enlaces).
		$raw  = $_GET['feat'] ?? '';
		$raw  = is_array( $raw ) ? array_filter( wp_unslash( $raw ), 'is_scalar' ) : explode( ',', (string) wp_unslash( $raw ) );
		$feat = array_values( array_intersect( array_keys( self::FEATURES ), array_map( static fn( $v ) => sanitize_title( (string) $v ), $raw ) ) );
		$type = sanitize_title( $get( 'type' ) );
		self::$params = array(
			'checkin'  => $checkin,
			'checkout' => $checkout,
			'nights'   => $nights,
			'guests'   => min( 30, $int( 'guests' ) ),
			'bedrooms' => min( 12, $int( 'bedrooms' ) ),
			'area'     => sanitize_title( $get( 'area' ) ),
			'type'     => isset( self::TYPES[ $type ] ) ? $type : '',
			'feat'     => $feat,
		);
		// phpcs:enable
		return self::$params;
	}

	/** ¿Hay algún filtro? (los huéspedes por defecto del formulario, 2, también cuentan). */
	public static function active(): bool {
		$p = self::params();
		return $p['nights'] || $p['guests'] || $p['bedrooms'] || $p['area'] || $p['type'] || $p['feat'];
	}

	private static function is_date( string $d ): bool {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) && checkdate( (int) substr( $d, 5, 2 ), (int) substr( $d, 8, 2 ), (int) substr( $d, 0, 4 ) );
	}

	/**
	 * ¿Es la página Alojamiento (en cualquier idioma)? Solo ahí se filtra el listado; la rejilla de
	 * la portada sigue enseñando todas las casas.
	 */
	public static function is_stay_page(): bool {
		if ( ! is_page() ) {
			return false;
		}
		$id = (int) get_queried_object_id();
		if ( function_exists( 'pll_get_post' ) ) {
			$id = (int) ( pll_get_post( $id, 'en' ) ?: $id );
		}
		return 'stay' === get_post_field( 'post_name', $id );
	}

	/** Las búsquedas no se guardan en caché: la disponibilidad cambia (US3, escenario 5). */
	public static function headers(): void {
		if ( self::is_stay_page() && self::active() ) {
			nocache_headers();
			header( 'Cache-Control: no-store, max-age=0' );
			// Cada combinación de filtros es la misma página: que no se indexe (mapa del sitio, «filtro, no indexable»).
			header( 'X-Robots-Tag: noindex, follow' );
		}
	}

	private static function is_property_query( $block ): bool {
		$query = $block instanceof WP_Block ? ( $block->context['query'] ?? array() ) : ( $block['attrs']['query'] ?? array() );
		return 'mis_property' === ( $query['postType'] ?? '' );
	}

	/**
	 * Filtra el bloque Query de propiedades de Alojamiento.
	 *
	 * @param array    $vars  Argumentos de WP_Query.
	 * @param WP_Block $block Bloque Query (o su plantilla).
	 */
	public static function filter_query( array $vars, $block ): array {
		if ( ! self::is_stay_page() || ! self::is_property_query( $block ) || ! self::active() ) {
			return $vars;
		}
		$p    = self::params();
		$meta = array();
		if ( $p['guests'] ) {
			$meta[] = array( 'key' => 'maros_max_guests', 'value' => $p['guests'], 'compare' => '>=', 'type' => 'NUMERIC' );
		}
		if ( $p['bedrooms'] ) {
			$meta[] = array( 'key' => 'maros_bedrooms', 'value' => $p['bedrooms'], 'compare' => '>=', 'type' => 'NUMERIC' );
		}
		if ( $meta ) {
			$vars['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta, ! empty( $vars['meta_query'] ) ? array( $vars['meta_query'] ) : array() );
		}

		$tax = array();
		if ( $p['area'] ) {
			$tax[] = array( 'taxonomy' => 'zona', 'field' => 'slug', 'terms' => array( $p['area'] ) );
		}
		if ( $p['type'] ) {
			$tax[] = array( 'taxonomy' => 'tipo-propiedad', 'field' => 'slug', 'terms' => array( $p['type'], $p['type'] . '-es' ) );
		}
		foreach ( $p['feat'] as $code ) {
			// Cada término existe en inglés (código) y en español (código-es), como los crea la sincronización.
			$tax[] = array( 'taxonomy' => 'equipamiento', 'field' => 'slug', 'terms' => array( $code, $code . '-es' ) );
		}
		if ( $tax ) {
			$vars['tax_query'] = array_merge( array( 'relation' => 'AND' ), $tax, ! empty( $vars['tax_query'] ) ? array( $vars['tax_query'] ) : array() );
		}

		if ( $p['nights'] ) {
			$ids              = array_keys( self::available() );
			$vars['post__in'] = $ids ?: array( 0 );
			// Con fechas, primero las más baratas para esa estancia.
			if ( $ids ) {
				$vars['orderby'] = 'post__in';
			}
		}
		// Con filtros, todas las casas en una página: el catálogo es corto y así no se pierde nada.
		$vars['posts_per_page'] = 48;
		$vars['no_found_rows']  = false;
		return $vars;
	}

	/**
	 * Casas libres para las fechas buscadas, de la más barata a la más cara.
	 *
	 * Una casa está libre si, para algún tipo de alojamiento, todas las noches de la estancia están
	 * disponibles, la llegada no está cerrada, la estancia mínima del día de llegada se cumple y la
	 * salida no está cerrada. El total suma, noche a noche, la tarifa del tramo que corresponde a la
	 * duración (como Lodgify). Es orientativo: el precio final lo da marOS (US3b).
	 *
	 * @return array<int,array{nights:int,total:float,currency:string}>
	 */
	public static function available(): array {
		if ( null !== self::$available ) {
			return self::$available;
		}
		self::$available = array();
		$p               = self::params();
		if ( ! $p['nights'] ) {
			return self::$available;
		}
		global $wpdb;
		$cal    = Maros_Calendar::calendar_table();
		$rates  = Maros_Calendar::rates_table();
		$in     = $p['checkin'];
		$out    = $p['checkout'];
		$nights = $p['nights'];

		// phpcs:disable WordPress.DB.PreparedSQL
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT property_id, room_type_id FROM {$cal}
				WHERE date >= %s AND date < %s
				GROUP BY property_id, room_type_id
				HAVING COUNT(*) = %d AND SUM(available) = %d
					AND MAX(CASE WHEN date = %s THEN closed_arrival END) = 0
					AND MAX(CASE WHEN date = %s THEN min_stay END) <= %d",
				$in,
				$out,
				$nights,
				$nights,
				$in,
				$in,
				$nights
			),
			ARRAY_A
		);
		$closed_out = $wpdb->get_results(
			$wpdb->prepare( "SELECT property_id, room_type_id FROM {$cal} WHERE date = %s AND closed_departure = 1", $out ),
			ARRAY_A
		);
		$closed = array();
		foreach ( $closed_out as $r ) {
			$closed[ $r['property_id'] . '|' . $r['room_type_id'] ] = true;
		}

		// Tarifa de cada noche: la del tramo de estancia más largo que se cumple.
		$totals = array();
		$prices = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.property_id, r.room_type_id, r.date, r.amount, r.currency FROM {$rates} r
				INNER JOIN (
					SELECT property_id, room_type_id, date, MAX(min_stay) AS tier FROM {$rates}
					WHERE date >= %s AND date < %s AND min_stay <= %d AND ( max_stay IS NULL OR max_stay = 0 OR max_stay >= %d )
					GROUP BY property_id, room_type_id, date
				) t ON t.property_id = r.property_id AND t.room_type_id = r.room_type_id AND t.date = r.date AND t.tier = r.min_stay",
				$in,
				$out,
				$nights,
				$nights
			),
			ARRAY_A
		);
		// phpcs:enable
		foreach ( $prices as $r ) {
			$k = $r['property_id'] . '|' . $r['room_type_id'];
			if ( ! isset( $totals[ $k ] ) ) {
				$totals[ $k ] = array( 'sum' => 0.0, 'days' => array(), 'currency' => $r['currency'] );
			}
			// Una sola tarifa por noche, aunque dos tramos empaten.
			if ( ! isset( $totals[ $k ]['days'][ $r['date'] ] ) ) {
				$totals[ $k ]['days'][ $r['date'] ] = true;
				$totals[ $k ]['sum']               += (float) $r['amount'];
			}
		}

		// Por propiedad, el tipo de alojamiento libre más barato.
		$best = array();
		foreach ( $rows as $r ) {
			$k = $r['property_id'] . '|' . $r['room_type_id'];
			if ( isset( $closed[ $k ] ) ) {
				continue;
			}
			// Sin tarifa para todas las noches no damos total (0), pero la casa sale.
			$total    = isset( $totals[ $k ] ) && count( $totals[ $k ]['days'] ) === $nights ? $totals[ $k ]['sum'] : 0.0;
			$currency = $totals[ $k ]['currency'] ?? 'EUR';
			$prev     = $best[ $r['property_id'] ] ?? null;
			if ( ! $prev || ( $total > 0 && ( $prev['total'] <= 0 || $total < $prev['total'] ) ) ) {
				$best[ $r['property_id'] ] = array( 'nights' => $nights, 'total' => $total, 'currency' => $currency );
			}
		}
		if ( ! $best ) {
			return self::$available;
		}

		// Ids de marOS → entradas de WordPress (las dos traducciones; Polylang filtra el idioma).
		$posts = get_posts(
			array(
				'post_type'        => 'mis_property',
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'lang'             => '',
				'suppress_filters' => false,
				'meta_query'       => array( array( 'key' => 'maros_id', 'value' => array_map( 'strval', array_keys( $best ) ), 'compare' => 'IN' ) ),
			)
		);
		$found = array();
		foreach ( $posts as $post_id ) {
			$maros = (string) get_post_meta( $post_id, 'maros_id', true );
			if ( isset( $best[ $maros ] ) && ! get_post_meta( $post_id, 'maros_withdrawn', true ) ) {
				$found[ (int) $post_id ] = $best[ $maros ];
			}
		}
		// Primero las que tienen total, de menor a mayor.
		uasort(
			$found,
			static fn( $a, $b ) => ( $a['total'] <= 0 ) <=> ( $b['total'] <= 0 ) ?: $a['total'] <=> $b['total']
		);
		self::$available = $found;
		return self::$available;
	}

	/** Total de la estancia buscada para una casa, o null sin fechas. */
	public static function stay_for( int $post_id ): ?array {
		if ( ! self::params()['nights'] ) {
			return null;
		}
		return self::available()[ $post_id ] ?? null;
	}

	public static function money( float $amount, string $currency, bool $es ): string {
		$n = number_format_i18n( round( $amount ) );
		if ( 'EUR' === $currency ) {
			return $es ? $n . ' €' : '€' . $n;
		}
		return $n . ' ' . $currency;
	}

	/**
	 * En Alojamiento, el buscador va encima del título (Julio, 25-11-2026): se mete al principio del
	 * grupo que lleva el h1 de la página.
	 */
	public static function render_hero( string $html, array $block ): string {
		if ( self::$form_on_top || ! self::is_stay_page() || ! self::has_h1( $block['innerBlocks'] ?? array() ) ) {
			return $html;
		}
		$at = strpos( $html, '>' );
		if ( false === $at ) {
			return $html;
		}
		self::$form_on_top = true;
		// Fuera la entradilla «Todas las casas las gestiona nuestro equipo»: no todas las gestionamos
		// nosotros (Julio, 25-11-2026). Está en el contenido de la página, por eso se quita aquí.
		$html = preg_replace( '#<p[^>]*class="[^"]*\bmis-muted\b[^"]*"[^>]*>.*?</p>#s', '', $html );
		$at   = strpos( $html, '>' );
		return substr( $html, 0, $at + 1 ) . '<div class="mis-stay-search mis-stay-search--top alignwide">' . self::form() . '</div>' . substr( $html, $at + 1 );
	}

	/** Si entre estos bloques (o justo dentro de ellos) está el título h1. */
	private static function has_h1( array $blocks ): bool {
		foreach ( $blocks as $b ) {
			$name = $b['blockName'] ?? '';
			if ( in_array( $name, array( 'core/heading', 'core/post-title' ), true ) && 1 === (int) ( $b['attrs']['level'] ?? ( 'core/post-title' === $name ? 2 : 0 ) ) ) {
				return true;
			}
			if ( 'core/heading' === $name && preg_match( '/^\s*<h1[\s>]/', $b['innerHTML'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/** Pone el resumen encima del listado de Alojamiento (y el buscador, si no lo tiene el título). */
	public static function render_query( string $html, array $block ): string {
		if ( ! self::is_stay_page() || ! self::is_property_query( $block ) ) {
			return $html;
		}
		// Mismo ancho que el listado (el bloque Query es «alignwide»).
		return '<div class="mis-stay-search alignwide">' . ( self::$form_on_top ? '' : self::form() ) . self::summary() . '</div>' . $html;
	}

	/** «3 casas libres del 12 al 19 de julio · 7 noches» y el enlace para quitar filtros. */
	private static function summary(): string {
		if ( ! self::active() ) {
			return '';
		}
		$es    = mis_search_is_es();
		$p     = self::params();
		$parts = array();
		if ( $p['nights'] ) {
			$parts[] = wp_date( $es ? 'j M' : 'M j', strtotime( $p['checkin'] . ' 12:00:00' ) )
				. ' – ' . wp_date( $es ? 'j M Y' : 'M j, Y', strtotime( $p['checkout'] . ' 12:00:00' ) );
			$parts[] = 1 === $p['nights'] ? ( $es ? '1 noche' : '1 night' ) : sprintf( $es ? '%d noches' : '%d nights', $p['nights'] );
		}
		if ( $p['guests'] ) {
			$parts[] = sprintf( $es ? '%d huéspedes' : '%d guests', $p['guests'] );
		}
		$clear = remove_query_arg( self::QUERY_ARGS );
		return '<p class="mis-results-summary" role="status">'
			. ( $parts ? '<span>' . esc_html( implode( ' · ', $parts ) ) . '</span>' : '' )
			. '<a href="' . esc_url( $clear ) . '">' . esc_html( $es ? 'Quitar filtros' : 'Clear filters' ) . '</a></p>';
	}

	/** Sin resultados: qué hacer, en vez de «Próximamente». */
	public static function render_no_results( string $html, array $block ): string {
		// El bloque se pinta vacío cuando sí hay casas: entonces no hay nada que cambiar.
		if ( '' === trim( $html ) || ! self::is_stay_page() || ! self::active() ) {
			return $html;
		}
		$es    = mis_search_is_es();
		$p     = self::params();
		$clear = remove_query_arg( self::QUERY_ARGS );
		$title = $p['nights']
			? ( $es ? 'No hay casas libres con estos filtros para esas fechas.' : 'No homes are available with these filters for those dates.' )
			: ( $es ? 'Ninguna casa cumple estos filtros.' : 'No homes match these filters.' );
		$text  = $es
			? 'Prueba con otras fechas o menos filtros, o escríbenos: conocemos más casas y te buscamos una.'
			: 'Try other dates or fewer filters, or write to us: we know more homes and will find you one.';
		$contact = function_exists( 'mis_page_url' ) ? mis_page_url( 'contact' ) : home_url( '/' );
		return '<div class="mis-no-results"><p><strong>' . esc_html( $title ) . '</strong></p><p class="mis-muted">' . esc_html( $text ) . '</p>'
			. '<p class="mis-no-results__actions"><a class="wp-element-button" href="' . esc_url( $clear ) . '">' . esc_html( $es ? 'Ver todas las casas' : 'See all homes' ) . '</a>'
			. ' <a href="' . esc_url( $contact ) . '">' . esc_html( $es ? 'Habla con nosotros' : 'Talk to us' ) . '</a></p></div>';
	}

	/** Chips con alguna casa detrás (no ofrecemos un filtro que deja la lista vacía). */
	private static function features(): array {
		$out = array();
		foreach ( self::CHIPS as $code ) {
			$names = self::FEATURES[ $code ];
			$en = get_term_by( 'slug', $code, 'equipamiento' );
			$es = get_term_by( 'slug', $code . '-es', 'equipamiento' );
			if ( ( $en && $en->count ) || ( $es && $es->count ) ) {
				$out[ $code ] = $names;
			}
		}
		return $out;
	}

	/** URL del listado de Alojamiento filtrado por un tipo (submenú «Villas» / «Apartments»). */
	public static function type_url( string $code ): string {
		$stay = function_exists( 'mis_page_url' ) ? mis_page_url( 'stay' ) : home_url( '/stay/' );
		return add_query_arg( 'type', $code, $stay );
	}

	/**
	 * [maros_buscador] Buscador completo (spec 003, FR-005 «maros/buscador»): fechas, huéspedes y
	 * chips de características (vistas al mar, piscina climatizada). Sigue el buscador de la portada (Julio,
	 * 25-11-2026): en escritorio, una barra redonda con todas las celdas y la lupa dorada, y los chips
	 * debajo; en el móvil, la barra de la portada, que abre el formulario con los filtros en un panel
	 * desde abajo. Envía por GET a Alojamiento.
	 */
	public static function form(): string {
		static $n = 0;
		++$n;
		$es     = mis_search_is_es();
		$p      = self::params();
		$id     = static fn( string $k ): string => 'mis-s' . $n . '-' . $k;
		$today  = wp_date( 'Y-m-d' );
		$action = function_exists( 'mis_page_url' ) ? mis_page_url( 'stay' ) : home_url( '/' );
		$t      = static fn( string $en, string $es_text ): string => esc_html( $es ? $es_text : $en );

		$guests = '<option value="">' . $t( 'Any', 'Cualquiera' ) . '</option>';
		for ( $g = 1; $g <= 16; $g++ ) {
			$guests .= sprintf( '<option value="%1$d"%2$s>%1$d</option>', $g, selected( $p['guests'], $g, false ) );
		}
		// Lo que llega en la URL y el buscador ya no enseña (tipo desde el menú Villas / Apartments,
		// zona, dormitorios o un chip antiguo) sigue en la búsqueda al volver a buscar.
		$keep = '';
		foreach ( array( 'type', 'area', 'bedrooms' ) as $k ) {
			if ( $p[ $k ] ) {
				$keep .= '<input type="hidden" name="' . $k . '" value="' . esc_attr( (string) $p[ $k ] ) . '">';
			}
		}
		$shown = self::features();
		foreach ( array_diff( $p['feat'], array_keys( $shown ) ) as $code ) {
			$keep .= '<input type="hidden" name="feat[]" value="' . esc_attr( $code ) . '">';
		}
		$chips = '';
		foreach ( $shown as $code => $names ) {
			$chips .= sprintf(
				'<label class="mis-chip"><input type="checkbox" name="feat[]" value="%1$s"%2$s><span>%3$s</span></label>',
				esc_attr( $code ),
				checked( in_array( $code, $p['feat'], true ), true, false ),
				esc_html( $names[ $es ? 1 : 0 ] )
			);
		}
		$icon  = '<svg class="mis-search__icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 21 21"/></svg>';

		$html  = '<form class="mis-search mis-search--full" role="search" method="get" action="' . esc_url( $action ) . '">';
		// La barra de la portada: llegada, salida, huéspedes y lupa. En el panel del móvil no es una
		// caja (display: contents) y la lupa es el botón ancho «Search».
		$html .= '<div class="mis-search__row">';
		$html .= '<div class="mis-search__field"><label for="' . $id( 'in' ) . '">' . $t( 'Arrival', 'Llegada' ) . '</label><input id="' . $id( 'in' ) . '" type="date" name="checkin" min="' . esc_attr( $today ) . '" value="' . esc_attr( $p['checkin'] ) . '"></div>';
		$html .= '<div class="mis-search__field"><label for="' . $id( 'out' ) . '">' . $t( 'Departure', 'Salida' ) . '</label><input id="' . $id( 'out' ) . '" type="date" name="checkout" min="' . esc_attr( $today ) . '" value="' . esc_attr( $p['checkout'] ) . '"></div>';
		$html .= '<div class="mis-search__field mis-search__field--guests"><label for="' . $id( 'g' ) . '">' . $t( 'Guests', 'Huéspedes' ) . '</label><select id="' . $id( 'g' ) . '" name="guests">' . $guests . '</select></div>';
		$html .= '<button class="mis-search__submit wp-element-button" type="submit">' . $icon . '<span class="mis-search__submit-text">' . $t( 'Search', 'Buscar' ) . '</span></button></div>';
		// Filtros: en escritorio, una línea de píldoras bajo la barra; en el panel del móvil, celdas.
		// Chips de características debajo: en escritorio, una línea; en el panel del móvil, antes de «Search».
		$html .= '<div class="mis-search__filters">' . $keep;
		if ( $chips ) {
			$html .= '<fieldset class="mis-search__chips"><legend>' . $t( 'Features', 'Características' ) . '</legend>' . $chips . '</fieldset>';
		}
		$html .= '</div>';
		$html .= '<p class="mis-search__error" role="alert" hidden>' . $t( 'Choose a departure date after the arrival date.', 'Elige una salida posterior a la llegada.' ) . '</p>';
		$html .= '</form>';
		wp_enqueue_script( 'maros-search' );
		if ( ! self::is_stay_page() ) {
			return $html;
		}

		// Alojamiento en el móvil: la barra del buscador de la portada (fechas, huéspedes y lupa), que
		// abre el buscador completo, con los filtros, en un panel desde abajo (Julio, 25-11-2026: sin
		// botón de filtros aparte). En escritorio no se ve: el formulario va en línea.
		$dates = $p['nights']
			? wp_date( $es ? 'j M' : 'M j', strtotime( $p['checkin'] . ' 12:00:00' ) ) . ' – ' . wp_date( $es ? 'j M' : 'M j', strtotime( $p['checkout'] . ' 12:00:00' ) )
			: ( $es ? 'Añadir fechas' : 'Add dates' );
		$sheet = 'mis-qs-sheet-' . $n;
		$bar   = '<button type="button" class="mis-qs__bar" aria-expanded="false" aria-controls="' . $sheet . '">'
			. '<span class="mis-qs__cell"><span class="mis-qs__label">' . $t( 'Dates', 'Fechas' ) . '</span><span class="mis-qs__value" data-qs="dates" data-empty="' . esc_attr( $es ? 'Añadir fechas' : 'Add dates' ) . '">' . esc_html( $dates ) . '</span></span>'
			. '<span class="mis-qs__cell"><span class="mis-qs__label">' . $t( 'Guests', 'Huéspedes' ) . '</span><span class="mis-qs__value" data-qs="guests">' . ( $p['guests'] ? (int) $p['guests'] : $t( 'Any', 'Cualquiera' ) ) . '</span></span>'
			. '<span class="mis-qs__go" aria-hidden="true">' . str_replace( 'class="mis-search__icon" ', '', $icon ) . '</span>'
			. '<span class="screen-reader-text">' . $t( 'Search homes', 'Buscar casas' ) . '</span>'
			. '</button>'
			. '</button>';
		$top   = '<div class="mis-qs__top"><strong>' . $t( 'Your stay', 'Tu estancia' ) . '</strong><button type="button" class="mis-qs__close" aria-label="' . esc_attr( $es ? 'Cerrar' : 'Close' ) . '">×</button></div>';
		// Panel del móvil: el script de la portada (tema). Se registra después; la cola lo espera.
		wp_enqueue_script( 'mis-inicio' );
		return '<div class="mis-qs mis-qs--stay">' . $bar . '<div class="mis-qs__sheet" id="' . $sheet . '"><div class="mis-qs__panel">' . $top . $html . '</div></div></div>';
	}
}

/** Idioma de la página que se está viendo (no de cada tarjeta). */
function mis_search_is_es(): bool {
	return function_exists( 'pll_current_language' ) && 'es' === pll_current_language();
}

Maros_Search::init();
