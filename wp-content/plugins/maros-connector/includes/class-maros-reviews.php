<?php
/**
 * Opiniones de portales (Airbnb…) publicadas por un agente a través del MCP (Julio, 16/11).
 *
 * Un agente lee las opiniones de cada casa en Airbnb y las envía con la Ability
 * `maros/publish-review`. Cada opinión se guarda en Site Reviews (D11), asignada a la propiedad y a
 * sus traducciones, con su origen y su id en el portal para no duplicarla. La ficha las muestra con
 * el shortcode `[maros_reviews]`.
 *
 * Para encontrar la propiedad, el agente usa `maros/list-properties` (id de WordPress, id de marOS y
 * de Lodgify).
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

class Maros_Reviews {

	/** Portales de los que se aceptan opiniones. */
	public const SOURCES = array( 'airbnb', 'booking', 'vrbo', 'google', 'direct' );

	private const META_SOURCE    = '_mis_review_source';
	private const META_SOURCE_ID = '_mis_review_source_id';
	private const META_KEY       = '_mis_review_key';
	private const META_LANGUAGE  = '_mis_review_language';
	private const META_LOCATION  = '_mis_review_location';
	private const META_STAY      = '_mis_review_stay_date';
	private const META_PROPERTY  = '_mis_review_property';

	public static function init(): void {
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
		add_shortcode( 'maros_reviews', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Estado con el que entra una opinión del agente: `publish` (se ve al momento) o `pending`
	 * (la aprueba una persona en Opiniones). Se puede fijar con la constante MIS_REVIEWS_STATUS.
	 */
	public static function default_status(): string {
		$status = defined( 'MIS_REVIEWS_STATUS' ) ? (string) MIS_REVIEWS_STATUS : 'publish';
		$status = (string) apply_filters( 'maros_reviews_status', $status );
		return in_array( $status, array( 'publish', 'pending' ), true ) ? $status : 'pending';
	}

	public static function register_category(): void {
		if ( function_exists( 'wp_register_ability_category' ) && ! ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( 'maros' ) ) ) {
			wp_register_ability_category(
				'maros',
				array(
					'label'       => 'marOS',
					'description' => 'Propiedades de Marbella in Style y sus opiniones.',
				)
			);
		}
	}

	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$meta = static fn( bool $readonly, bool $idempotent ) => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'readonly'    => $readonly,
				'destructive' => false,
				'idempotent'  => $idempotent,
			),
			'mcp'          => array( 'public' => true ),
		);

		wp_register_ability(
			'maros/list-properties',
			array(
				'label'               => 'Listar propiedades',
				'description'         => 'Lista las propiedades de la web con su id de WordPress, su id de marOS, su id de Lodgify, su idioma y su URL. Sirve para saber a qué propiedad asignar una opinión.',
				'category'            => 'maros',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'search' => array(
							'type'        => 'string',
							'description' => 'Texto a buscar en el nombre (opcional).',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'         => array( 'type' => 'integer' ),
							'title'      => array( 'type' => 'string' ),
							'maros_id'   => array( 'type' => 'string' ),
							'lodgify_id' => array( 'type' => 'integer' ),
							'language'   => array( 'type' => 'string' ),
							'status'     => array( 'type' => 'string' ),
							'url'        => array( 'type' => 'string' ),
							'reviews'    => array( 'type' => 'integer' ),
						),
					),
				),
				'execute_callback'    => array( __CLASS__, 'list_properties' ),
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'meta'                => $meta( true, true ),
			)
		);

		wp_register_ability(
			'maros/publish-review',
			array(
				'label'               => 'Publicar una opinión',
				'description'         => 'Guarda en la web una opinión de un huésped sacada de un portal (Airbnb…), asignada a una propiedad y a sus traducciones. Si la misma opinión (mismo portal e id) ya existe, no la duplica y devuelve la que hay. Copia el texto tal cual, sin traducirlo ni corregirlo. No envíes emails, teléfonos ni fotos de huéspedes.',
				'category'            => 'maros',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'source', 'author', 'date', 'rating', 'content' ),
					'properties'           => array(
						'property_id'      => array(
							'type'        => 'integer',
							'description' => 'Id de WordPress de la propiedad (de maros/list-properties). Da este, maros_id o lodgify_id.',
						),
						'maros_id'         => array(
							'type'        => 'string',
							'description' => 'Id de la propiedad en marOS, por ejemplo «lodgify-373016» o «villa-concha».',
						),
						'lodgify_id'       => array(
							'type'        => 'integer',
							'description' => 'Id de la propiedad en Lodgify.',
						),
						'source'           => array(
							'type'        => 'string',
							'enum'        => self::SOURCES,
							'description' => 'Portal de origen.',
						),
						'source_review_id' => array(
							'type'        => 'string',
							'maxLength'   => 191,
							'description' => 'Id de la opinión en el portal. Recomendado: evita duplicados aunque cambie el texto.',
						),
						'author'           => array(
							'type'        => 'string',
							'minLength'   => 1,
							'maxLength'   => 100,
							'description' => 'Nombre que muestra el portal (normalmente solo el nombre de pila).',
						),
						'author_location'  => array(
							'type'        => 'string',
							'maxLength'   => 100,
							'description' => 'Ciudad o país del huésped, si el portal lo muestra.',
						),
						'date'             => array(
							'type'        => 'string',
							'pattern'     => '^\\d{4}-\\d{2}(-\\d{2})?$',
							'description' => 'Fecha de la opinión, AAAA-MM-DD (o AAAA-MM si el portal solo da mes y año).',
						),
						'stay_date'        => array(
							'type'        => 'string',
							'pattern'     => '^\\d{4}-\\d{2}(-\\d{2})?$',
							'description' => 'Mes de la estancia, AAAA-MM, si el portal lo da.',
						),
						'rating'           => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 5,
							'description' => 'Estrellas, de 1 a 5.',
						),
						'title'            => array(
							'type'      => 'string',
							'maxLength' => 200,
						),
						'content'          => array(
							'type'        => 'string',
							'minLength'   => 1,
							'maxLength'   => 10000,
							'description' => 'Texto de la opinión, tal cual.',
						),
						'language'         => array(
							'type'        => 'string',
							'pattern'     => '^[a-z]{2}(-[A-Za-z]{2})?$',
							'description' => 'Idioma del texto (en, es, fr…).',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'review_id'   => array( 'type' => 'integer' ),
						'created'     => array( 'type' => 'boolean', 'description' => 'false si ya existía (duplicada).' ),
						'status'      => array( 'type' => 'string', 'description' => 'publish (se ve en la web) o pending (espera aprobación).' ),
						'property_id' => array( 'type' => 'integer' ),
						'assigned_to' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'publish_review' ),
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'meta'                => $meta( false, true ),
			)
		);
	}

	/**
	 * Ability maros/list-properties.
	 */
	public static function list_properties( $input = array() ): array {
		$input = is_array( $input ) ? $input : array();
		$posts = get_posts(
			array(
				'post_type'      => 'mis_property',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
				's'              => (string) ( $input['search'] ?? '' ),
				'lang'           => '', // Polylang: todos los idiomas.
			)
		);
		$out = array();
		foreach ( $posts as $post ) {
			$out[] = array(
				'id'         => (int) $post->ID,
				'title'      => get_the_title( $post ),
				'maros_id'   => (string) get_post_meta( $post->ID, 'maros_id', true ),
				'lodgify_id' => (int) get_post_meta( $post->ID, 'maros_lodgify_id', true ),
				'language'   => function_exists( 'pll_get_post_language' ) ? (string) pll_get_post_language( $post->ID ) : '',
				'status'     => $post->post_status,
				'url'        => (string) get_permalink( $post ),
				'reviews'    => self::count_for( (int) $post->ID ),
			);
		}
		return $out;
	}

	/**
	 * Ability maros/publish-review.
	 *
	 * @return array|WP_Error
	 */
	public static function publish_review( $input = array() ) {
		$input = is_array( $input ) ? $input : array();

		if ( ! function_exists( 'glsr_create_review' ) ) {
			return new WP_Error( 'maros_reviews_unavailable', 'Site Reviews no está activo en esta web.' );
		}

		$property = self::find_property( $input );
		if ( is_wp_error( $property ) ) {
			return $property;
		}

		$source    = (string) $input['source'];
		$author    = sanitize_text_field( (string) $input['author'] );
		$content   = sanitize_textarea_field( (string) $input['content'] );
		$date      = self::normalize_date( (string) $input['date'] );
		$source_id = sanitize_text_field( (string) ( $input['source_review_id'] ?? '' ) );
		$maros_id  = (string) get_post_meta( $property, 'maros_id', true );
		$prop_key  = '' !== $maros_id ? $maros_id : (string) $property;

		if ( '' === $author || '' === $content || ! $date ) {
			return new WP_Error( 'maros_review_invalid', 'Faltan el autor, el texto o una fecha válida.' );
		}
		if ( self::looks_personal( $content ) ) {
			return new WP_Error( 'maros_review_personal_data', 'El texto parece llevar un email o un teléfono: quítalo antes de enviarlo (principio VI).' );
		}

		// Clave para no duplicar: el id del portal si lo hay; si no, autor + fecha + texto.
		$key = '' !== $source_id
			? $source . ':' . $source_id
			: $source . ':' . $prop_key . ':' . md5( strtolower( $author ) . '|' . $date . '|' . $content );

		$existing = self::find_by_key( $key );
		if ( $existing ) {
			return array(
				'review_id'   => $existing,
				'created'     => false,
				'status'      => (string) get_post_status( $existing ),
				'property_id' => $property,
				'assigned_to' => self::translations( $property ),
			);
		}

		$assigned = self::translations( $property );
		$review   = glsr_create_review(
			array(
				'assigned_posts' => $assigned,
				'name'           => $author,
				'rating'         => (int) $input['rating'],
				'title'          => sanitize_text_field( (string) ( $input['title'] ?? '' ) ),
				'content'        => $content,
				'date'           => $date . ' 12:00:00',
				'is_verified'    => true,
			)
		);
		if ( ! $review || empty( $review->ID ) ) {
			Maros_Log::error( sprintf( 'Opinión de %s para %s no guardada (Site Reviews la rechazó).', $source, $prop_key ) );
			return new WP_Error( 'maros_review_failed', 'Site Reviews no pudo guardar la opinión.' );
		}
		$review_id = (int) $review->ID;

		update_post_meta( $review_id, self::META_KEY, $key );
		update_post_meta( $review_id, self::META_SOURCE, $source );
		update_post_meta( $review_id, self::META_PROPERTY, $property );
		if ( '' !== $source_id ) {
			update_post_meta( $review_id, self::META_SOURCE_ID, $source_id );
		}
		foreach ( array(
			self::META_LANGUAGE => sanitize_key( (string) ( $input['language'] ?? '' ) ),
			self::META_LOCATION => sanitize_text_field( (string) ( $input['author_location'] ?? '' ) ),
			self::META_STAY     => substr( (string) self::normalize_date( (string) ( $input['stay_date'] ?? '' ) ), 0, 7 ),
		) as $meta_key => $value ) {
			if ( '' !== $value ) {
				update_post_meta( $review_id, $meta_key, $value );
			}
		}

		// El estado lo decide la web, no Site Reviews (que puede pedir aprobación para todo lo que llega).
		$status = self::default_status();
		if ( get_post_status( $review_id ) !== $status ) {
			wp_update_post( array( 'ID' => $review_id, 'post_status' => $status ) );
		}

		$user = wp_get_current_user();
		Maros_Log::info(
			sprintf(
				'Opinión %d de %s (%d★) para %s guardada como %s por %s.',
				$review_id,
				$source,
				(int) $input['rating'],
				$prop_key,
				$status,
				$user->exists() ? $user->user_login : 'desconocido'
			)
		);

		return array(
			'review_id'   => $review_id,
			'created'     => true,
			'status'      => (string) get_post_status( $review_id ),
			'property_id' => $property,
			'assigned_to' => $assigned,
		);
	}

	/**
	 * @return int|WP_Error Id de la propiedad en el idioma por defecto (o la que se pidió).
	 */
	private static function find_property( array $input ) {
		$id = 0;
		if ( ! empty( $input['property_id'] ) ) {
			$id = (int) $input['property_id'];
		} else {
			$meta = ! empty( $input['maros_id'] )
				? array( 'key' => 'maros_id', 'value' => (string) $input['maros_id'] )
				: ( ! empty( $input['lodgify_id'] ) ? array( 'key' => 'maros_lodgify_id', 'value' => (int) $input['lodgify_id'] ) : null );
			if ( ! $meta ) {
				return new WP_Error( 'maros_review_no_property', 'Indica property_id, maros_id o lodgify_id.' );
			}
			$found = get_posts(
				array(
					'post_type'      => 'mis_property',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => array( $meta ), // phpcs:ignore WordPress.DB.SlowDBQuery
					'lang'           => function_exists( 'pll_default_language' ) ? (string) pll_default_language() : '',
				)
			);
			$id = (int) ( $found[0] ?? 0 );
		}
		if ( ! $id || 'mis_property' !== get_post_type( $id ) ) {
			return new WP_Error( 'maros_review_property_not_found', 'No hay ninguna propiedad con ese id en la web. Usa maros/list-properties.' );
		}
		return $id;
	}

	/** La propiedad y sus traducciones (Polylang): la opinión se ve en todos los idiomas. */
	private static function translations( int $property ): array {
		$ids = function_exists( 'pll_get_post_translations' ) ? array_values( (array) pll_get_post_translations( $property ) ) : array();
		$ids = array_map( 'intval', array_merge( array( $property ), $ids ) );
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	private static function find_by_key( string $key ): int {
		$found = get_posts(
			array(
				'post_type'      => 'site-review',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
				'lang'           => '',
			)
		);
		return (int) ( $found[0] ?? 0 );
	}

	/** AAAA-MM-DD; AAAA-MM pasa a día 1. Devuelve '' si no es una fecha válida o es futura. */
	private static function normalize_date( string $date ): string {
		if ( preg_match( '/^(\d{4})-(\d{2})$/', $date, $m ) ) {
			$date = $m[1] . '-' . $m[2] . '-01';
		}
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return '';
		}
		return $date <= wp_date( 'Y-m-d' ) ? $date : '';
	}

	/** Email o número de teléfono largo dentro del texto. */
	private static function looks_personal( string $text ): bool {
		if ( preg_match( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text ) ) {
			return true;
		}
		// Teléfono: 9 cifras o más seguidas (con espacios, puntos o guiones). Una fecha tiene 8.
		preg_match_all( '/\+?\d[\d\s().-]{7,}\d/', $text, $m );
		foreach ( $m[0] as $candidate ) {
			if ( strlen( (string) preg_replace( '/\D/', '', $candidate ) ) >= 9 ) {
				return true;
			}
		}
		return false;
	}

	private static function count_for( int $property ): int {
		if ( ! function_exists( 'glsr_get_reviews' ) ) {
			return 0;
		}
		$reviews = glsr_get_reviews( array( 'assigned_posts' => $property, 'per_page' => 1 ) );
		return (int) ( $reviews->total ?? 0 );
	}

	/**
	 * Nota media y número de opiniones aprobadas de una casa, o null si no tiene. La usan el pie
	 * fijo del móvil ([maros_cta_line]) y el bloque de opiniones; se calcula una vez por página.
	 *
	 * @return array{average: float, total: int}|null
	 */
	public static function rating( int $post_id ): ?array {
		static $cache = array();
		if ( array_key_exists( $post_id, $cache ) ) {
			return $cache[ $post_id ];
		}
		if ( ! $post_id || ! function_exists( 'glsr_get_reviews' ) ) {
			return null;
		}
		$reviews = glsr_get_reviews(
			array(
				'assigned_posts' => $post_id,
				'per_page'       => 100,
				'status'         => 'approved',
			)
		);
		$ratings = array();
		foreach ( $reviews as $review ) {
			$ratings[] = (int) $review->rating;
		}
		$cache[ $post_id ] = $ratings ? array(
			'average' => array_sum( $ratings ) / count( $ratings ),
			'total'   => max( count( $ratings ), (int) ( $reviews->total ?? 0 ) ),
		) : null;
		return $cache[ $post_id ];
	}

	/**
	 * [maros_reviews] en la ficha, a todo el ancho (Julio 23-11-2026, con el de Airbnb de modelo): nota
	 * media, número de opiniones y las cuatro últimas en dos columnas, cada una cortada a unas cinco
	 * líneas con «Ver más» si es más larga. Si hay más, «Ver todas las opiniones» abre una ventana con
	 * todas. No pinta nada si la casa no tiene.
	 */
	public static function shortcode( $atts = array() ): string {
		$atts    = shortcode_atts( array( 'limit' => 4 ), (array) $atts, 'maros_reviews' );
		$post_id = (int) get_the_ID();
		if ( ! $post_id || ! function_exists( 'glsr_get_reviews' ) ) {
			return '';
		}
		$reviews = glsr_get_reviews(
			array(
				'assigned_posts' => $post_id,
				'per_page'       => 100,
				'status'         => 'approved',
				'order'          => 'DESC',
				'orderby'        => 'date',
			)
		);
		$list = array();
		foreach ( $reviews as $review ) {
			$list[] = $review;
		}
		if ( ! $list ) {
			return '';
		}

		$es      = maros_is_es( $post_id );
		$total   = max( count( $list ), (int) ( $reviews->total ?? 0 ) );
		$average = array_sum( array_map( static fn( $r ) => (int) $r->rating, $list ) ) / count( $list );
		$limit   = max( 1, (int) $atts['limit'] );
		$summary = sprintf(
			'<p class="mis-reviews__summary"><span class="mis-reviews__average">★ %s</span> · %s</p>',
			esc_html( number_format_i18n( $average, 2 ) ),
			esc_html( sprintf( $es ? _n( '%d opinión', '%d opiniones', $total, 'maros-connector' ) : _n( '%d review', '%d reviews', $total, 'maros-connector' ), $total ) )
		);

		$html  = '<section id="reviews" class="mis-reviews">';
		$html .= '<h2 class="wp-block-heading has-large-font-size">' . esc_html( $es ? 'Opiniones' : 'Reviews' ) . '</h2>' . $summary;
		$html .= '<ul class="mis-reviews__list">';
		foreach ( array_slice( $list, 0, $limit ) as $review ) {
			$html .= self::review_html( $review, $es, true );
		}
		$html .= '</ul>';

		if ( count( $list ) > $limit ) {
			$title = $es ? 'Opiniones' : 'Reviews';
			$html .= '<button type="button" class="mis-reviews__all" data-mis-reviews-open>' . esc_html( sprintf( $es ? 'Ver todas las opiniones (%d)' : 'Show all reviews (%d)', $total ) ) . '</button>';
			$html .= '<dialog class="mis-reviews-modal" aria-labelledby="mis-reviews-modal-title">';
			$html .= '<div class="mis-reviews-modal__bar"><button type="button" class="mis-reviews-modal__close" data-mis-reviews-close aria-label="' . esc_attr( $es ? 'Cerrar' : 'Close' ) . '"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>';
			$html .= '<div class="mis-reviews-modal__body"><h2 id="mis-reviews-modal-title" class="wp-block-heading has-large-font-size">' . esc_html( $title ) . '</h2>' . $summary . '<ul class="mis-reviews-modal__list">';
			foreach ( $list as $review ) {
				$html .= self::review_html( $review, $es, false );
			}
			$html .= '</ul></div></dialog>';
		}
		$html .= '</section>';

		$file = dirname( __DIR__ ) . '/assets/reviews.js';
		wp_enqueue_script( 'maros-reviews', plugins_url( 'assets/reviews.js', __DIR__ ), array(), (string) filemtime( $file ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		return $html;
	}

	/**
	 * Una opinión: nombre, estrellas, lugar, fecha de estancia, origen y texto. En la ficha ($clamp)
	 * el texto se corta a unas cinco líneas y lleva «Ver más», que reviews.js enseña solo si hace falta.
	 */
	private static function review_html( $review, bool $es, bool $clamp ): string {
		$sources  = array( 'airbnb' => 'Airbnb', 'booking' => 'Booking.com', 'vrbo' => 'Vrbo', 'google' => 'Google', 'direct' => '' );
		$id       = (int) $review->ID;
		$source   = $sources[ (string) get_post_meta( $id, self::META_SOURCE, true ) ] ?? '';
		$location = (string) get_post_meta( $id, self::META_LOCATION, true );
		$stay     = (string) get_post_meta( $id, self::META_STAY, true );
		$when     = $stay ? $stay . '-01' : substr( (string) $review->date, 0, 10 );
		$lang     = sanitize_key( (string) get_post_meta( $id, self::META_LANGUAGE, true ) );
		$meta     = array_filter(
			array(
				$location,
				$when ? wp_date( 'F Y', strtotime( $when ) ) : '',
				$source ? ( $es ? 'en ' : 'on ' ) . $source : '',
			)
		);
		$html  = '<li class="mis-review">';
		$html .= sprintf(
			'<p class="mis-review__head"><strong>%s</strong> <span class="mis-review__stars" aria-label="%s">%s</span></p>',
			esc_html( (string) $review->author ),
			esc_attr( sprintf( $es ? '%d de 5 estrellas' : '%d out of 5 stars', (int) $review->rating ) ),
			esc_html( str_repeat( '★', (int) $review->rating ) . str_repeat( '☆', 5 - (int) $review->rating ) )
		);
		$html .= $meta ? '<p class="mis-review__meta mis-muted">' . esc_html( implode( ' · ', $meta ) ) . '</p>' : '';
		$html .= $review->title ? '<p class="mis-review__title"><strong>' . esc_html( (string) $review->title ) . '</strong></p>' : '';
		$html .= sprintf(
			'<div class="mis-review__text%s"%s>%s</div>',
			$clamp ? ' is-clamped' : '',
			$lang ? ' lang="' . esc_attr( $lang ) . '"' : '',
			wpautop( esc_html( (string) $review->content ) )
		);
		if ( $clamp ) {
			$html .= '<button type="button" class="mis-review__more" data-mis-review-more hidden data-less="' . esc_attr( $es ? 'Ver menos' : 'Show less' ) . '">' . esc_html( $es ? 'Ver más' : 'Show more' ) . '</button>';
		}
		return $html . '</li>';
	}
}
