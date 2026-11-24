<?php
/**
 * Sincronización de propiedades marOS → WordPress (spec 003, US1 y US2).
 *
 * Solo toca campos operativos, tipo, equipamiento, zona, fotos, calendario y el extracto automático
 * (includes/excerpt.php, que respeta el escrito a mano). Nunca título visible, descripción, slug ni
 * SEO de una propiedad que ya existe. Las nuevas nacen en borrador.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

class Maros_Sync {

	/** Nombres en/es de zonas conocidas (por slug de la zona). Las demás se crean con el nombre de marOS. */
	private const ZONES = array(
		'nueva-andalucia' => array( 'Nueva Andalucía', 'Nueva Andalucía' ),
		'sierra-blanca'   => array( 'Sierra Blanca', 'Sierra Blanca' ),
		'puerto-banus'    => array( 'Puerto Banús', 'Puerto Banús' ),
		'golden-mile'     => array( 'Golden Mile', 'Milla de Oro' ),
		'new-golden-mile' => array( 'New Golden Mile', 'New Golden Mile' ),
		'los-flamingos'   => array( 'Los Flamingos', 'Los Flamingos' ),
		'la-alqueria'     => array( 'La Alquería', 'La Alquería' ),
		'guadalmina'      => array( 'Guadalmina', 'Guadalmina' ),
		'rio-real'        => array( 'Río Real', 'Río Real' ),
		'estepona'        => array( 'Estepona', 'Estepona' ),
		'benahavis'       => array( 'Benahavís village', 'Benahavís pueblo' ),
		'san-pedro'       => array( 'San Pedro de Alcántara', 'San Pedro de Alcántara' ),
		'marbella-centro' => array( 'Marbella town centre', 'Marbella centro' ),
	);

	/** Zonas que marOS puede nombrar en español. */
	private const ZONE_ALIASES = array(
		'milla-de-oro'       => 'golden-mile',
		'nueva-milla-de-oro'     => 'new-golden-mile',
		'golden-mile-estepona'   => 'new-golden-mile',
		'san-pedro-alcantara'    => 'san-pedro',
		'san-pedro-de-alcantara' => 'san-pedro',
		'marbella'               => 'marbella-centro',
		'marbella-centre'        => 'marbella-centro',
		'marbella-town-centre'   => 'marbella-centro',
		'benahavis-pueblo'       => 'benahavis',
		'benahavis-village'      => 'benahavis',
		'flamingos'              => 'los-flamingos',
		'alqueria'               => 'la-alqueria',
	);

	/**
	 * Municipio de cada Area, para las migas de pan cuando marOS no trae un municipio reconocible
	 * (el campo es texto libre: «Saladillo» es una urbanización de Estepona, no un municipio).
	 */
	public const ZONE_TOWNS = array(
		'nueva-andalucia' => 'Marbella',
		'sierra-blanca'   => 'Marbella',
		'puerto-banus'    => 'Marbella',
		'golden-mile'     => 'Marbella',
		'rio-real'        => 'Marbella',
		'marbella-centro' => 'Marbella',
		'san-pedro'       => 'Marbella',
		'guadalmina'      => 'Marbella',
		'new-golden-mile' => 'Estepona',
		'estepona'        => 'Estepona',
		'los-flamingos'   => 'Benahavís',
		'la-alqueria'     => 'Benahavís',
		'benahavis'       => 'Benahavís',
	);

	/** Municipios que puede traer marOS, escritos como en la web (sin tilde y en minúsculas → nombre). */
	public const TOWNS = array(
		'marbella'   => 'Marbella',
		'estepona'   => 'Estepona',
		'benahavis'  => 'Benahavís',
		'mijas'      => 'Mijas',
		'ojen'       => 'Ojén',
		'istan'      => 'Istán',
		'casares'    => 'Casares',
		'manilva'    => 'Manilva',
		'fuengirola' => 'Fuengirola',
		'benalmadena' => 'Benalmádena',
		'malaga'     => 'Málaga',
	);

	private const TYPES = array(
		'villa'     => array( 'Villa', 'Villa' ),
		'apartment' => array( 'Apartment', 'Apartamento' ),
	);

	private const AMENITIES = array(
		'private-pool'     => array( 'Private pool', 'Piscina privada' ),
		'heated-pool'      => array( 'Heated pool', 'Piscina climatizada' ),
		'shared-pool'      => array( 'Shared pool', 'Piscina comunitaria' ),
		'sea-view'         => array( 'Sea view', 'Vistas al mar' ),
		'air-conditioning' => array( 'Air conditioning', 'Aire acondicionado' ),
		'wifi'             => array( 'Wi-Fi', 'Wi-Fi' ),
		'parking'          => array( 'Parking', 'Aparcamiento' ),
		'bbq'              => array( 'BBQ', 'Barbacoa' ),
		'gym'              => array( 'Gym', 'Gimnasio' ),
		'terrace'          => array( 'Terrace', 'Terraza' ),
		'dryer'            => array( 'Tumble dryer', 'Secadora' ),
		'washer'           => array( 'Washing machine', 'Lavadora' ),
		'dishwasher'       => array( 'Dishwasher', 'Lavavajillas' ),
		'hair-dryer'       => array( 'Hair dryer', 'Secador de pelo' ),
	);

	/**
	 * Nombres de Lodgify que se guardan con un código fijo, para que todas las fichas compartan
	 * término y nombre traducido («ClothesDryer», «Tumble dryer» → dryer). En orden: el secador
	 * de pelo y el lavavajillas antes que la secadora y la lavadora.
	 */
	private const AMENITY_ALIASES = array(
		'/hair-?dryer/'                 => 'hair-dryer',
		'/dish-?washer/'                => 'dishwasher',
		'/dryer|secadora/'              => 'dryer',
		'/washing|washer|lavadora/'     => 'washer',
	);

	/**
	 * Versión de lo que la web guarda de cada ficha. Se sube cuando la web empieza a guardar un dato
	 * nuevo (2: municipio para las migas de pan; 3: extracto automático), para que todas las fichas se relean una vez
	 * aunque su etag en marOS no haya cambiado.
	 */
	private const FORMAT = '3';

	/** Idiomas de la web: toda propiedad existe en todos, vinculada en Polylang (Julio, 19-11-2026). */
	public const LANGS = array( 'en', 'es' );

	/** Etag de marOS tal como se guarda en maros_etag: con la versión de formato de la web. */
	private static function stamp( string $etag ): string {
		return '' === $etag ? '' : $etag . '#' . self::FORMAT;
	}

	/** Fotos que se descargan como mucho en una pasada; el resto, en la siguiente (cron de 5 min). */
	private const PHOTO_BUDGET = 40;

	private static int $photo_budget = self::PHOTO_BUDGET;

	/**
	 * Sincroniza todas las propiedades publicables y retira las que ya no lo son.
	 *
	 * @param bool $publish Publicar las nuevas (solo para el contenido de ejemplo; en real nacen en borrador).
	 * @param bool $photos  Descargar fotos.
	 * @param bool $force   Releer ficha y calendario aunque el etag no haya cambiado (reconciliación nocturna).
	 * @return array{created:int,updated:int,skipped:int,withdrawn:int,errors:string[]}
	 */
	public static function sync_all( bool $publish = false, bool $photos = true, bool $force = false ): array {
		$result = array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'withdrawn' => 0, 'errors' => array() );
		$list   = Maros_Client::list_properties();
		if ( is_wp_error( $list ) ) {
			$result['errors'][] = $list->get_error_message();
			Maros_Log::error( 'Sincronización completa: ' . $list->get_error_message() );
			return $result;
		}
		Maros_Log::mark_ok();

		foreach ( $list as $summary ) {
			$outcome = self::sync_property( $summary['id'], $summary, $publish, $photos, $force );
			if ( is_wp_error( $outcome ) ) {
				$result['errors'][] = $outcome->get_error_message();
				if ( class_exists( 'Maros_Scheduler' ) ) {
					Maros_Scheduler::enqueue( $summary['id'], 'error en la sincronización completa' );
				}
			} else {
				++$result[ $outcome ];
			}
		}
		$result['withdrawn'] = self::withdraw_missing( array_column( $list, 'id' ) );

		update_option( 'maros_last_full_sync', gmdate( 'c' ), false );
		Maros_Log::add(
			$result['errors'] ? 'error' : 'info',
			sprintf(
				'Sincronización completa (%s): %d creadas, %d actualizadas, %d sin cambios, %d retiradas, %d errores.',
				Maros_Client::env(),
				$result['created'],
				$result['updated'],
				$result['skipped'],
				$result['withdrawn'],
				count( $result['errors'] )
			)
		);
		return $result;
	}

	/**
	 * Lee la lista de marOS y encola las propiedades cuya ficha o calendario han cambiado.
	 * Es barata (una llamada) y sustituye al aviso de marOS mientras no existan los webhooks.
	 *
	 * @return int|WP_Error Propiedades encoladas.
	 */
	public static function poll() {
		$list = Maros_Client::list_properties();
		if ( is_wp_error( $list ) ) {
			return $list;
		}
		Maros_Log::mark_ok();
		$queued = 0;
		foreach ( $list as $summary ) {
			$post = self::find_by_maros_id( $summary['id'] );
			if ( ! $post
				|| ! self::has_all_languages( $summary['id'] )
				|| get_post_meta( $post, 'maros_etag', true ) !== self::stamp( (string) ( $summary['etag'] ?? '' ) )
				|| get_post_meta( $post, 'maros_calendar_etag', true ) !== (string) ( $summary['calendar_etag'] ?? '' )
				|| get_post_meta( $post, 'maros_withdrawn', true ) ) {
				Maros_Scheduler::enqueue( $summary['id'], 'cambio en marOS' );
				++$queued;
			}
		}
		self::withdraw_missing( array_column( $list, 'id' ) );
		return $queued;
	}

	/**
	 * Sincroniza una propiedad: ficha (si cambió) y calendario (si cambió).
	 *
	 * @param string     $maros_id Id de marOS.
	 * @param array|null $summary  Resumen de la lista, con etag y calendar_etag, si se tiene.
	 * @return string|WP_Error created | updated | skipped
	 */
	public static function sync_property( string $maros_id, ?array $summary = null, bool $publish = false, bool $photos = true, bool $force = false ) {
		$existing   = self::find_by_maros_id( $maros_id );
		$same_card  = $existing && ! $force && $summary && '' !== (string) ( $summary['etag'] ?? '' )
			&& get_post_meta( $existing, 'maros_etag', true ) === self::stamp( (string) $summary['etag'] )
			&& ! get_post_meta( $existing, 'maros_withdrawn', true )
			&& self::has_all_languages( $maros_id );
		$outcome    = 'skipped';

		if ( ! $same_card ) {
			$data = Maros_Client::get_property( $maros_id );
			if ( is_wp_error( $data ) ) {
				if ( 'maros_not_found' === $data->get_error_code() && $existing ) {
					self::withdraw( $maros_id );
					return 'skipped';
				}
				return $data;
			}
			$outcome = self::sync_one( $data, $publish, $photos );
			if ( is_wp_error( $outcome ) ) {
				return $outcome;
			}
			$existing = self::find_by_maros_id( $maros_id );
		}

		$cal_etag = (string) ( $summary['calendar_etag'] ?? '' );
		if ( $existing && ( $force || '' === $cal_etag || get_post_meta( $existing, 'maros_calendar_etag', true ) !== $cal_etag ) ) {
			$cal = self::sync_calendar( $maros_id );
			if ( is_wp_error( $cal ) ) {
				return $cal;
			}
			if ( 'skipped' === $outcome ) {
				$outcome = 'updated';
			}
		}
		return $outcome;
	}

	/**
	 * Carga el calendario y las tarifas de una propiedad en las tablas propias.
	 *
	 * @return int|WP_Error Días guardados.
	 */
	public static function sync_calendar( string $maros_id ) {
		$data = Maros_Client::get_calendar( $maros_id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$saved = Maros_Calendar::replace( $maros_id, $data );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$etag = (string) ( $data['etag'] ?? $data['_etag'] ?? '' );
		foreach ( self::all_posts( $maros_id ) as $post_id ) {
			update_post_meta( $post_id, 'maros_calendar_etag', $etag );
			update_post_meta( $post_id, 'maros_calendar_synced_at', gmdate( 'c' ) );
		}
		return $saved;
	}

	/**
	 * Crea o actualiza las dos traducciones (en, es) de una propiedad a partir de su ficha.
	 *
	 * @return string|WP_Error created | updated | skipped
	 */
	public static function sync_one( array $data, bool $publish = false, bool $photos = true ) {
		$maros_id = Maros_Client::safe_id( (string) ( $data['id'] ?? '' ) );
		if ( ! $maros_id || empty( $data['name'] ) ) {
			return new WP_Error( 'maros_contract', 'Ficha de marOS sin id o sin nombre.' );
		}
		$found     = self::translations_of( $maros_id );
		$existing  = (int) ( reset( $found ) ?: 0 );
		$etag      = (string) ( $data['etag'] ?? $data['_etag'] ?? '' );
		$withdrawn = $existing && get_post_meta( $existing, 'maros_withdrawn', true );
		$complete  = self::complete( $found );

		if ( $existing && $complete && ! $withdrawn && '' !== $etag && get_post_meta( $existing, 'maros_etag', true ) === self::stamp( $etag ) ) {
			return 'skipped';
		}
		if ( $existing && ! $complete ) {
			Maros_Log::info( sprintf( '%s: le faltaba una traducción (%s), no estaban vinculadas o no tenían el mismo estado; se corrige.', $maros_id, implode( ', ', array_diff( self::LANGS, array_keys( $found ) ) ) ?: 'en y es existen' ) );
		}

		// La API del catálogo solo entrega publicables; el campo se respeta por si llega en false.
		$publishable = ! array_key_exists( 'publishable', $data ) || ! empty( $data['publishable'] );
		$status      = $publishable && $publish ? 'publish' : 'draft';
		$ids         = array();
		$title       = self::title_of( $data );

		foreach ( self::LANGS as $lang ) {
			$post_id = (int) ( $found[ $lang ] ?? 0 );
			if ( ! $post_id ) {
				// Si la propiedad ya existe en otro idioma, la traducción que faltaba nace con su mismo estado
				// (una propiedad publicada tiene que estar publicada en todos los idiomas).
				$sibling = $existing ? get_post( $existing ) : null;
				// El slug de la hermana sin su sufijo de idioma («villa-concha-es» → «villa-concha»).
				$slug = $sibling ? preg_replace( '/-' . preg_quote( (string) array_key_first( $found ), '/' ) . '$/', '', $sibling->post_name ) : sanitize_title( $title );
				$post_id = wp_insert_post(
					array(
						'post_type'    => 'mis_property',
						'post_title'   => $sibling ? $sibling->post_title : $title,
						'post_name'    => $slug,
						'post_status'  => $sibling && $publishable ? $sibling->post_status : $status,
					),
					true
				);
				if ( is_wp_error( $post_id ) ) {
					return $post_id;
				}
			} else {
				$update = $publishable ? array() : array( 'post_status' => 'draft' );
				// El título es el nombre interno de marOS (Julio 19-11-2026). Se cambia solo si sigue siendo
				// el que puso la sincronización (el nombre de antes); un título escrito a mano se respeta.
				$current = (string) get_post_field( 'post_title', $post_id, 'raw' );
				$synced  = array_filter( array( (string) get_post_meta( $post_id, 'maros_name', true ), (string) get_post_meta( $post_id, 'maros_internal_name', true ), (string) $data['name'] ) );
				// También el título de antes de esa decisión, «nombre interno - nombre comercial» con un
				// comercial que ya no es el de marOS («Villa Flamingo - Modern Villa in Los Flamingos»,
				// Julio 20-11-2026): empieza por el nombre interno y sigue con un separador.
				$legacy = '' !== $title && preg_match( '/^' . preg_quote( $title, '/' ) . '\s*[\-–—|·:,]\s*\S/u', $current );
				if ( $current !== $title && ( in_array( $current, $synced, true ) || $legacy ) ) {
					$update['post_title'] = $title;
				}
				if ( $update ) {
					wp_update_post( array( 'ID' => $post_id ) + $update );
				}
			}
			if ( function_exists( 'pll_set_post_language' ) ) {
				pll_set_post_language( $post_id, $lang );
			}
			self::set_terms( $post_id, $data, $lang );
			self::set_meta( $post_id, $data );
			delete_post_meta( $post_id, 'maros_withdrawn' );
			$ids[ $lang ] = $post_id;
		}

		if ( function_exists( 'pll_save_post_translations' ) ) {
			pll_save_post_translations( $ids );
		}
		// Publicada en un idioma = publicada en todos (si sigue siendo publicable en marOS).
		if ( $publishable && ! self::same_status( $ids ) && in_array( 'publish', array_map( 'get_post_status', $ids ), true ) ) {
			foreach ( $ids as $post_id ) {
				if ( 'publish' !== get_post_status( $post_id ) ) {
					wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
				}
			}
		}

		$complete = $photos ? self::sync_photos( $ids, $data['photos'] ?? array() ) : true;
		// marOS spec 040: dormitorios para «Sleeping arrangements», con la foto de cada uno en Medios.
		$complete = self::sync_bedrooms( $ids, $data['sleeping_arrangements'] ?? array(), $photos ) && $complete;
		// marOS spec 041: estancias con fotos para la galería por estancias (maqueta B, Julio 16-11-2026).
		$complete = self::sync_tour( $ids, $data['photo_tour'] ?? array(), $photos ) && $complete;

		// Extracto automático (zona, capacidad y lo más destacado), salvo si se escribió a mano.
		foreach ( $ids as $lang => $post_id ) {
			maros_refresh_excerpt( $post_id, 'es' === $lang );
		}

		// El etag se guarda al final: si algo queda a medias (fotos), la próxima pasada lo repite.
		foreach ( $ids as $post_id ) {
			update_post_meta( $post_id, 'maros_etag', $complete && '' !== $etag ? self::stamp( $etag ) : '' );
		}
		if ( ! $complete && class_exists( 'Maros_Scheduler' ) ) {
			Maros_Scheduler::enqueue( $maros_id, 'fotos pendientes' );
		}
		if ( $withdrawn ) {
			Maros_Log::info( sprintf( '%s vuelve a ser publicable en marOS; sigue en borrador hasta que un editor la publique.', $data['name'] ) );
		}

		return $existing ? 'updated' : 'created';
	}

	/**
	 * Pasa a borrador las propiedades que ya no están en la lista de marOS (US1, escenario 3).
	 *
	 * @param string[] $present Ids que sí están.
	 * @return int Retiradas ahora.
	 */
	public static function withdraw_missing( array $present ): int {
		$known = array_unique( array_filter( array_map( static fn( $id ) => (string) get_post_meta( $id, 'maros_id', true ), self::all_posts() ) ) );
		if ( ! $present && $known ) {
			// Una lista vacía de golpe es más probable que sea un fallo que una decisión: no se retira nada.
			Maros_Log::error( 'marOS devolvió la lista de propiedades vacía; no se retira ninguna. Revisa la marca «publicable» en marOS.' );
			return 0;
		}
		$count = 0;
		foreach ( array_diff( $known, $present ) as $maros_id ) {
			$count += self::withdraw( $maros_id ) ? 1 : 0;
		}
		return $count;
	}

	/** Pasa a borrador las traducciones de una propiedad y borra su calendario. */
	public static function withdraw( string $maros_id ): bool {
		$posts = self::all_posts( $maros_id );
		if ( ! $posts || get_post_meta( $posts[0], 'maros_withdrawn', true ) ) {
			return false;
		}
		$was_public = false;
		$urls       = array();
		foreach ( $posts as $post_id ) {
			if ( 'publish' === get_post_status( $post_id ) ) {
				$was_public = true;
				// La URL pública se guarda antes de pasar a borrador: después sería ?p=… (todas las traducciones).
				$urls[] = get_permalink( $post_id );
			}
		}
		foreach ( $posts as $post_id ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
			update_post_meta( $post_id, 'maros_withdrawn', 1 );
			update_post_meta( $post_id, 'maros_calendar_etag', '' );
		}
		Maros_Calendar::delete( $maros_id );
		$name = get_post_meta( $posts[0], 'maros_name', true ) ?: $maros_id;
		Maros_Log::error(
			sprintf(
				'%s ya no es publicable en marOS: pasa a borrador.%s',
				$name,
				$was_public ? ' Estaba publicada: pon una redirección de su URL.' : ''
			)
		);
		if ( $was_public ) {
			$notices                = get_option( 'maros_notices', array() );
			$notices[ $maros_id ]   = array( 'name' => $name, 'url' => implode( ' · ', $urls ), 'time' => time() );
			update_option( 'maros_notices', $notices, false );
		}
		return true;
	}

	/** Título de la ficha: el nombre interno de marOS («Casa Coral»); sin él, el de siempre. */
	public static function title_of( array $d ): string {
		$internal = trim( (string) ( $d['internal_name'] ?? '' ) );
		return '' !== $internal ? $internal : trim( (string) $d['name'] );
	}

	/**
	 * Subtítulo: el nombre comercial sin el nombre interno delante («Casa Coral - Beachfront 3BR
	 * Townhouse with Garden» → «Beachfront 3BR Townhouse with Garden»). Vacío si no añade nada.
	 */
	public static function subtitle_of( array $d ): string {
		$title      = self::title_of( $d );
		$commercial = trim( (string) ( array_key_exists( 'commercial_name', $d ) ? ( $d['commercial_name'] ?? '' ) : ( $d['name'] ?? '' ) ) );
		if ( '' !== $title && 0 === stripos( $commercial, $title ) ) {
			$commercial = trim( preg_replace( '/^[\s\-–—|·:,]+/u', '', substr( $commercial, strlen( $title ) ) ) );
		}
		return 0 === strcasecmp( $commercial, $title ) ? '' : $commercial;
	}

	private static function type_of( array $d ): array {
		$type = $d['type'] ?? '';
		$slug = is_array( $type ) ? (string) ( $type['id'] ?? '' ) : (string) $type;
		$name = is_array( $type ) ? (string) ( $type['name'] ?? $slug ) : $slug;
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return array( '', array( 'Property', 'Propiedad' ) );
		}
		return array( $slug, self::TYPES[ $slug ] ?? array( $name ?: ucfirst( $slug ), $name ?: ucfirst( $slug ) ) );
	}

	private static function cleaning_fee( array $d ): float {
		if ( isset( $d['fees']['cleaning'] ) ) {
			return (float) $d['fees']['cleaning'];
		}
		foreach ( is_array( $d['fees'] ?? null ) ? $d['fees'] : array() as $fee ) {
			if ( is_array( $fee ) && preg_match( '/clean|limpieza/i', ( $fee['type'] ?? '' ) . ' ' . ( $fee['name'] ?? '' ) ) ) {
				return (float) ( $fee['amount'] ?? 0 );
			}
		}
		return 0.0;
	}

	private static function set_meta( int $post_id, array $d ): void {
		$map = array(
			'maros_id'          => (string) $d['id'],
			'maros_lodgify_id'  => (int) ( $d['lodgify_id'] ?? 0 ),
			'maros_updated_at'  => (string) ( $d['updated_at'] ?? '' ),
			'maros_name'        => (string) $d['name'],
			'maros_internal_name' => self::title_of( $d ),
			'maros_subtitle'    => self::subtitle_of( $d ),
			'maros_max_guests'  => (int) ( $d['max_guests'] ?? 0 ),
			'maros_bedrooms'    => (int) ( $d['bedrooms'] ?? 0 ),
			'maros_bathrooms'   => (int) ( $d['bathrooms'] ?? 0 ),
			'maros_beds'        => (int) ( $d['beds'] ?? 0 ),
			'maros_area'        => (string) ( $d['location']['area'] ?? '' ),
			'maros_municipality' => (string) ( $d['location']['municipality'] ?? '' ),
			'maros_lat'         => (float) ( $d['location']['lat'] ?? 0 ),
			'maros_lng'         => (float) ( $d['location']['lng'] ?? 0 ),
			'maros_check_in'    => (string) ( $d['check_in'] ?? '' ),
			'maros_check_out'   => (string) ( $d['check_out'] ?? '' ),
			'maros_deposit'     => (float) ( $d['deposit'] ?? 0 ),
			'maros_cleaning'    => self::cleaning_fee( $d ),
			'maros_currency'    => (string) ( $d['currency'] ?? $d['price_from']['currency'] ?? 'EUR' ),
			'maros_price_from'  => (float) ( $d['price_from']['amount'] ?? 0 ),
			'maros_room_types'  => wp_json_encode( array_values( is_array( $d['room_types'] ?? null ) ? $d['room_types'] : array() ) ),
			'maros_synced_at'   => gmdate( 'c' ),
			'maros_fees'        => wp_json_encode( array_values( self::fees( $d ) ) ),
			'maros_nearby'      => wp_slash( wp_json_encode( self::nearby( $d ) ) ),
		) + self::text_facts( $d );
		foreach ( $map as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Distancias en coche de marOS medidas con OpenStreetMap (se pueden publicar, a diferencia de las de
	 * Google). Solo las filas completas: nombre, km, minutos y fecha de la medida.
	 */
	private static function nearby( array $d ): array {
		$out = array();
		foreach ( is_array( $d['nearby'] ?? null ) ? $d['nearby'] : array() as $r ) {
			if ( ! is_array( $r ) || 'openstreetmap' !== ( $r['source'] ?? '' ) || ! is_string( $r['name'] ?? null ) || '' === trim( $r['name'] )
				|| ! is_numeric( $r['distance_km'] ?? null ) || ! is_numeric( $r['duration_minutes'] ?? null )
				|| ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $r['measured_on'] ?? '' ) ) ) {
				continue;
			}
			$out[] = array(
				'name'             => sanitize_text_field( $r['name'] ),
				'icon'             => sanitize_key( (string) ( $r['icon'] ?? '' ) ),
				'distance_km'      => round( (float) $r['distance_km'], 1 ),
				'duration_minutes' => max( 1, (int) $r['duration_minutes'] ),
				'measured_on'      => (string) $r['measured_on'],
			);
		}
		return $out;
	}

	private static function fees( array $d ): array {
		return array_filter( is_array( $d['fees'] ?? null ) && array_is_list( $d['fees'] ) ? $d['fees'] : array(), 'is_array' );
	}

	/** Primera tasa de Lodgify cuyo tipo o nombre encaja; para lo que marOS aún no da en su campo. */
	private static function fee_matching( array $d, string $pattern ): ?array {
		foreach ( self::fees( $d ) as $fee ) {
			if ( preg_match( $pattern, ( $fee['type'] ?? '' ) . ' ' . ( $fee['name'] ?? '' ) ) ) {
				return $fee;
			}
		}
		return null;
	}

	/** true/false de marOS → yes/no; ausente o null → '' («marOS no lo sabe»). */
	private static function flag( $value ): string {
		return is_bool( $value ) ? ( $value ? 'yes' : 'no' ) : '';
	}

	/**
	 * Datos que los textos de la ficha no repiten y leen de aquí (PC-15 §5). Contrato propuesto a
	 * marOS en data-model.md («Datos para los textos»); mientras no llegan, quedan vacíos.
	 */
	private static function text_facts( array $d ): array {
		$pool    = (string) ( $d['pool'] ?? '' );
		$heating = is_array( $d['pool_heating'] ?? null ) ? $d['pool_heating'] : null;
		// Solo una tasa que hable de la piscina: «Heating» o «Calefacción» a secas es la de la casa.
		$heat_fee = $heating ? null : self::fee_matching( $d, '/(pool|piscina).*(heat|climat|calefac)|(heat|climat|calefac).*(pool|piscina)/i' );
		$pets    = is_array( $d['pets'] ?? null ) ? $d['pets'] : array();
		$pet_fee = isset( $pets['fee'] ) ? null : self::fee_matching( $d, '/\bpet|mascota/i' );
		$extra   = is_array( $d['extra_cleaning'] ?? null ) ? $d['extra_cleaning'] : array();

		return array(
			'maros_pool'                 => in_array( $pool, array( 'none', 'private', 'communal', 'both' ), true ) ? $pool : '',
			'maros_pool_heating'         => $heating ? self::flag( $heating['available'] ?? null ) : ( $heat_fee ? 'yes' : '' ),
			'maros_pool_heating_amount'  => (float) ( $heating['amount'] ?? $heat_fee['amount'] ?? 0 ),
			'maros_pool_heating_freq'    => (string) ( $heating['frequency'] ?? $heat_fee['frequency'] ?? '' ),
			// marOS spec 032: incluida en el precio u obligatoria; PerWeek = mínimo 7 noches y proporcional después.
			'maros_pool_heating_included'  => $heating ? self::flag( $heating['included'] ?? null ) : '',
			'maros_pool_heating_mandatory' => $heating ? self::flag( $heating['mandatory'] ?? null ) : '',
			'maros_pool_heating_min_nights' => (int) ( $heating['minimum_nights'] ?? 0 ),
			// wp_slash: las comodidades de cada piscina llevan acentos («\u00ed») que update_post_meta quitaría.
			'maros_pools'                => wp_slash( wp_json_encode( array_values( array_filter( is_array( $d['pools'] ?? null ) ? $d['pools'] : array(), 'is_array' ) ) ) ),
			'maros_extra_cleaning'       => (float) ( $extra['standard'] ?? 0 ),
			'maros_extra_cleaning_linen' => (float) ( $extra['with_linen'] ?? 0 ),
			'maros_self_check_in'        => self::flag( $d['self_check_in'] ?? null ),
			'maros_pets'                 => self::flag( $pets['allowed'] ?? $d['pets_allowed'] ?? null ),
			'maros_pets_fee'             => (float) ( $pets['fee'] ?? $pet_fee['amount'] ?? 0 ),
			'maros_pets_fee_freq'        => (string) ( $pets['frequency'] ?? $pet_fee['frequency'] ?? '' ),
			'maros_events'               => self::flag( $d['events_allowed'] ?? null ),
			// Julio 24-11-2026: la casa la gestiona Marbella in Style (marOS, Datos generales); sin el dato, nada.
			'maros_managed'              => self::flag( $d['managed_by_us'] ?? null ),
			'maros_license'              => sanitize_text_field( (string) ( $d['tourist_license'] ?? '' ) ),
		);
	}

	private static function set_terms( int $post_id, array $d, string $lang ): void {
		list( $type_slug, $type_names ) = self::type_of( $d );
		if ( $type_slug ) {
			wp_set_object_terms( $post_id, array( self::term( 'tipo-propiedad', $type_slug, $type_names, $lang ) ), 'tipo-propiedad' );
		}

		// Solo las 13 Areas de la web (ZONES). Un nombre que no es ninguna (p. ej. la urbanización
		// «Saladillo», texto libre de marOS) ya no crea una zona nueva: se deja la que tuviera y se avisa.
		$zone = self::zone_slug( $d );
		if ( $zone && taxonomy_exists( 'zona' ) ) {
			wp_set_object_terms( $post_id, array( self::term( 'zona', $zone, self::ZONES[ $zone ], $lang ) ), 'zona' );
		} elseif ( 'en' === $lang && '' !== trim( (string) ( $d['zone'] ?? $d['location']['area'] ?? '' ) ) ) {
			Maros_Log::info( sprintf( '%s: el área «%s» de marOS no es una de las Areas de la web; no se crea zona.', (string) ( $d['id'] ?? '' ), (string) ( $d['zone'] ?? $d['location']['area'] ) ) );
		}

		$amenities = array();
		foreach ( is_array( $d['amenities'] ?? null ) ? $d['amenities'] : array() as $item ) {
			$code = sanitize_title( is_array( $item ) ? (string) ( $item['code'] ?? '' ) : (string) $item );
			if ( ! $code ) {
				continue;
			}
			$name = is_array( $item ) ? (string) ( $item['name'] ?? $code ) : $code;
			foreach ( self::AMENITY_ALIASES as $re => $alias ) {
				if ( preg_match( $re, $code ) ) {
					$code = $alias;
					break;
				}
			}
			$amenities[] = self::term( 'equipamiento', $code, self::AMENITIES[ $code ] ?? array( $name, $name ), $lang );
		}
		// Piscinas del expediente (marOS spec 032) como equipamiento, para filtrar en el buscador.
		foreach ( self::pool_amenities( $d ) as $code ) {
			$amenities[] = self::term( 'equipamiento', $code, self::AMENITIES[ $code ], $lang );
		}
		wp_set_object_terms( $post_id, array_values( array_unique( array_filter( $amenities ) ) ), 'equipamiento' );
	}

	/**
	 * Slug de la Area de la web para los datos de marOS (zone, location.area o location.area_en,
	 * con los alias en español), o '' si no es ninguna de las de ZONES.
	 */
	public static function zone_slug( array $d ): string {
		$loc = is_array( $d['location'] ?? null ) ? $d['location'] : array();
		foreach ( array( $d['zone'] ?? null, $loc['area'] ?? null, $loc['area_en'] ?? null ) as $name ) {
			$slug = sanitize_title( remove_accents( (string) $name ) );
			$slug = self::ZONE_ALIASES[ $slug ] ?? $slug;
			if ( '' !== $slug && isset( self::ZONES[ $slug ] ) ) {
				return $slug;
			}
		}
		return '';
	}

	/** Nombre de una Area de la web en inglés o en español. */
	public static function zone_name( string $slug, bool $es ): string {
		return self::ZONES[ $slug ][ $es ? 1 : 0 ] ?? '';
	}

	/** private-pool, shared-pool y heated-pool a partir de pool y pools de marOS. */
	private static function pool_amenities( array $d ): array {
		$pool  = (string) ( $d['pool'] ?? '' );
		$pools = array_filter( is_array( $d['pools'] ?? null ) ? $d['pools'] : array(), 'is_array' );
		$codes = array();
		if ( in_array( $pool, array( 'private', 'both' ), true ) || array_filter( $pools, static fn( $p ) => 'private' === ( $p['access'] ?? '' ) ) ) {
			$codes[] = 'private-pool';
		}
		if ( in_array( $pool, array( 'communal', 'both' ), true ) || array_filter( $pools, static fn( $p ) => 'communal' === ( $p['access'] ?? '' ) ) ) {
			$codes[] = 'shared-pool';
		}
		if ( array_filter( $pools, static fn( $p ) => ! empty( $p['heated'] ) ) || ! empty( $d['pool_heating']['available'] ) ) {
			$codes[] = 'heated-pool';
		}
		return $codes;
	}

	/**
	 * Id del término en ese idioma; lo crea (y enlaza sus traducciones) si falta. Los nombres de
	 * términos que ya existen no se tocan: los traduce y corrige el equipo en WordPress.
	 */
	public static function term( string $tax, string $slug, array $names, string $lang ): int {
		$ids = array();
		foreach ( array( 'en' => 0, 'es' => 1 ) as $l => $i ) {
			$term_slug = 'en' === $l ? $slug : $slug . '-es';
			$term      = get_term_by( 'slug', $term_slug, $tax );
			if ( ! $term ) {
				$created = wp_insert_term( $names[ $i ], $tax, array( 'slug' => $term_slug ) );
				if ( is_wp_error( $created ) ) {
					// Mismo nombre que otro término (p. ej. «Wi-Fi» en los dos idiomas sin Polylang).
					$existing = $created->get_error_data( 'term_exists' );
					if ( ! $existing ) {
						continue;
					}
					$created = array( 'term_id' => (int) $existing );
				}
				$term_id = (int) $created['term_id'];
			} else {
				$term_id = (int) $term->term_id;
			}
			if ( function_exists( 'pll_set_term_language' ) ) {
				pll_set_term_language( $term_id, $l );
			}
			$ids[ $l ] = $term_id;
		}
		if ( function_exists( 'pll_save_term_translations' ) && 2 === count( $ids ) ) {
			pll_save_term_translations( $ids );
		}
		return $ids[ $lang ] ?? 0;
	}

	/**
	 * Descarga las fotos nuevas a Medios (identificadas por hash) y guarda el orden de marOS.
	 *
	 * @return bool false si quedan fotos por descargar en otra pasada.
	 */
	private static function sync_photos( array $ids, array $photos ): bool {
		$primary = $ids['en'];
		$current = array_filter( explode( ',', (string) get_post_meta( $primary, 'maros_gallery', true ) ) );
		$known   = array();
		foreach ( $current as $att ) {
			$known[ (string) get_post_meta( (int) $att, 'maros_photo_hash', true ) ] = (int) $att;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$photos = array_values( array_filter( $photos, static fn( $p ) => is_array( $p ) && ! empty( $p['url'] ) ) );
		usort( $photos, static fn( $a, $b ) => ( $a['order'] ?? 0 ) <=> ( $b['order'] ?? 0 ) );
		$gallery  = array();
		$complete = true;
		foreach ( $photos as $photo ) {
			$hash = (string) ( $photo['hash'] ?? md5( $photo['url'] ) );
			if ( isset( $known[ $hash ] ) && get_post( $known[ $hash ] ) ) {
				$gallery[] = $known[ $hash ];
				continue;
			}
			$att = self::find_attachment_by_hash( $hash );
			if ( ! $att ) {
				if ( self::$photo_budget <= 0 ) {
					$complete = false;
					continue;
				}
				--self::$photo_budget;
				$att = self::download_photo( $photo, $hash, $primary );
				if ( ! $att ) {
					$complete = false;
					continue;
				}
			}
			$gallery[] = $att;
		}

		foreach ( $ids as $post_id ) {
			update_post_meta( $post_id, 'maros_gallery', implode( ',', $gallery ) );
			if ( $gallery && ! has_post_thumbnail( $post_id ) ) {
				set_post_thumbnail( $post_id, $gallery[0] );
			}
		}
		return $complete;
	}

	/**
	 * Guarda los dormitorios de marOS (spec 040) en maros_sleeping, en los dos idiomas, con el id
	 * de Medios de la primera foto de cada uno. Las fotos suelen ser las de la galería (mismo hash).
	 *
	 * @return bool false si queda alguna foto por descargar en otra pasada.
	 */
	private static function sync_bedrooms( array $ids, $rooms, bool $photos ): bool {
		$text     = static fn( $v ) => sanitize_text_field( (string) $v );
		$pair     = static fn( $v ) => array( 'en' => sanitize_text_field( (string) ( $v['en'] ?? '' ) ), 'es' => sanitize_text_field( (string) ( $v['es'] ?? '' ) ) );
		$out      = array();
		$complete = true;
		foreach ( is_array( $rooms ) && array_is_list( $rooms ) ? $rooms : array() as $room ) {
			if ( ! is_array( $room ) ) {
				continue;
			}
			$att   = 0;
			$first = null;
			foreach ( is_array( $room['photos'] ?? null ) ? $room['photos'] : array() as $p ) {
				if ( is_array( $p ) && ! empty( $p['url'] ) && ( ! $first || ( $p['order'] ?? 0 ) < ( $first['order'] ?? 0 ) ) ) {
					$first = $p;
				}
			}
			if ( $first ) {
				$hash = (string) ( $first['hash'] ?? md5( $first['url'] ) );
				$att  = self::find_attachment_by_hash( $hash );
				if ( ! $att && $photos ) {
					if ( self::$photo_budget > 0 ) {
						--self::$photo_budget;
						$att = self::download_photo( $first, $hash, $ids['en'] );
					}
					$complete = $complete && $att > 0;
				}
			}
			$floor = is_array( $room['floor'] ?? null ) ? $room['floor'] : null;
			$out[] = array(
				'number'    => max( 1, (int) ( $room['number'] ?? count( $out ) + 1 ) ),
				'master'    => ! empty( $room['master'] ),
				'floor'     => $floor ? $pair( $floor ) : null,
				'beds'      => array_values(
					array_map(
						static fn( $b ) => array( 'type' => $text( $b['type'] ?? '' ), 'count' => max( 1, (int) ( $b['count'] ?? 1 ) ) ),
						array_filter( is_array( $room['beds'] ?? null ) ? $room['beds'] : array(), 'is_array' )
					)
				),
				'en_suite'  => is_array( $room['en_suite'] ?? null ) ? array( 'bathtub' => ! empty( $room['en_suite']['bathtub'] ), 'shower' => ! empty( $room['en_suite']['shower'] ) ) : null,
				'amenities' => array_values(
					array_map(
						static fn( $a ) => array( 'code' => sanitize_key( (string) ( $a['code'] ?? '' ) ) ) + $pair( $a ),
						array_filter( is_array( $room['amenities'] ?? null ) ? $room['amenities'] : array(), 'is_array' )
					)
				),
				'photo'     => $att,
			);
		}
		foreach ( $ids as $post_id ) {
			update_post_meta( $post_id, 'maros_sleeping', wp_slash( wp_json_encode( $out ) ) );
		}
		return $complete;
	}

	/**
	 * Guarda las estancias con fotos de marOS (spec 041) en maros_tour, en los dos idiomas, con los
	 * ids de Medios de sus fotos en orden. Casi siempre son fotos de la galería (mismo hash), así que
	 * no se descargan otra vez.
	 *
	 * @return bool false si queda alguna foto por descargar en otra pasada.
	 */
	private static function sync_tour( array $ids, $rooms, bool $photos ): bool {
		$pair     = static fn( $v ) => array( 'en' => sanitize_text_field( (string) ( $v['en'] ?? '' ) ), 'es' => sanitize_text_field( (string) ( $v['es'] ?? '' ) ) );
		$number   = static fn( $v ) => is_numeric( $v ) && (int) $v > 0 ? (int) $v : null;
		$out      = array();
		$complete = true;
		foreach ( is_array( $rooms ) && array_is_list( $rooms ) ? $rooms : array() as $room ) {
			if ( ! is_array( $room ) ) {
				continue;
			}
			$list = array_values( array_filter( is_array( $room['photos'] ?? null ) ? $room['photos'] : array(), static fn( $p ) => is_array( $p ) && ! empty( $p['url'] ) ) );
			usort( $list, static fn( $a, $b ) => ( $a['order'] ?? 0 ) <=> ( $b['order'] ?? 0 ) );
			$atts = array();
			foreach ( $list as $p ) {
				$hash = (string) ( $p['hash'] ?? md5( $p['url'] ) );
				$att  = self::find_attachment_by_hash( $hash );
				if ( ! $att && $photos ) {
					if ( self::$photo_budget > 0 ) {
						--self::$photo_budget;
						$att = self::download_photo( $p, $hash, $ids['en'] );
					}
					$complete = $complete && $att > 0;
				}
				if ( $att && ! in_array( $att, $atts, true ) ) {
					$atts[] = $att;
				}
			}
			if ( ! $atts ) {
				continue;
			}
			$floor = is_array( $room['floor'] ?? null ) ? $room['floor'] : null;
			$out[] = array(
				'name'        => sanitize_text_field( (string) ( $room['name'] ?? '' ) ),
				'type'        => sanitize_key( (string) ( $room['type'] ?? '' ) ),
				'outdoor'     => ! empty( $room['outdoor'] ),
				'floor'       => $floor ? $pair( $floor ) : null,
				'bedroom'     => $number( $room['bedroom'] ?? null ),
				'en_suite_of' => $number( $room['en_suite_of'] ?? null ),
				'amenities'   => array_values(
					array_map(
						static fn( $a ) => array( 'code' => sanitize_key( (string) ( $a['code'] ?? '' ) ) ) + $pair( $a ),
						array_filter( is_array( $room['amenities'] ?? null ) ? $room['amenities'] : array(), 'is_array' )
					)
				),
				'photos'      => $atts,
			);
		}
		foreach ( $ids as $post_id ) {
			update_post_meta( $post_id, 'maros_tour', wp_slash( wp_json_encode( $out ) ) );
		}
		return $complete;
	}

	private static function download_photo( array $photo, string $hash, int $parent ): int {
		$tmp = self::fetch_photo( (string) $photo['url'] );
		if ( is_wp_error( $tmp ) ) {
			Maros_Log::error( 'Foto no descargada (' . get_the_title( $parent ) . '): ' . $tmp->get_error_message() );
			return 0;
		}
		$ext = strtolower( pathinfo( (string) wp_parse_url( $photo['url'], PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		$ext = in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) ? $ext : 'jpg';
		$att = media_handle_sideload(
			array( 'name' => sanitize_file_name( sanitize_title( get_the_title( $parent ) ) . '-' . substr( md5( $hash ), 0, 8 ) ) . '.' . $ext, 'tmp_name' => $tmp ),
			$parent,
			get_the_title( $parent )
		);
		if ( is_wp_error( $att ) ) {
			wp_delete_file( $tmp );
			Maros_Log::error( 'Foto no guardada (' . get_the_title( $parent ) . '): ' . $att->get_error_message() );
			return 0;
		}
		update_post_meta( $att, 'maros_photo_hash', $hash );
		$alt = trim( (string) ( $photo['caption'] ?? '' ) ) ?: get_the_title( $parent );
		update_post_meta( $att, '_wp_attachment_image_alt', $alt );
		return (int) $att;
	}

	private static function find_attachment_by_hash( string $hash ): int {
		$found = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => 'maros_photo_hash', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => $hash, // phpcs:ignore WordPress.DB.SlowDBQuery
				'lang'           => '',
			)
		);
		return $found ? (int) $found[0] : 0;
	}

	/**
	 * Descarga una foto a un archivo temporal. «mock:ruta» se lee de la carpeta del mock.
	 *
	 * @return string|WP_Error
	 */
	private static function fetch_photo( string $url ) {
		if ( str_starts_with( $url, 'mock:' ) ) {
			if ( 'mock' !== Maros_Client::env() || ! function_exists( 'mis_maros_mock_dir' ) ) {
				return new WP_Error( 'maros_mock', 'Foto del mock fuera del modo mock.' );
			}
			$src = mis_maros_mock_dir() . '/' . ltrim( str_replace( '..', '', substr( $url, 5 ) ), '/' );
			$tmp = wp_tempnam( basename( $src ) );
			return ( is_readable( $src ) && copy( $src, $tmp ) ) ? $tmp : new WP_Error( 'maros_mock', 'No existe ' . $src );
		}
		if ( str_starts_with( $url, '//' ) ) {
			$url = 'https:' . $url;
		}
		if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) {
			return new WP_Error( 'maros_photo', 'URL de foto no https: ' . $url );
		}
		return download_url( $url, 30 );
	}

	public static function find_by_maros_id( string $maros_id, string $lang = 'en' ): int {
		$args = array(
			'post_type'      => 'mis_property',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => 'maros_id', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $maros_id, // phpcs:ignore WordPress.DB.SlowDBQuery
		);
		if ( function_exists( 'pll_current_language' ) ) {
			$args['lang'] = $lang;
		}
		$found = get_posts( $args );
		return $found ? (int) $found[0] : 0;
	}

	/**
	 * Todas las Propiedades (cualquier idioma y estado), o las de un id de marOS.
	 *
	 * @return int[]
	 */
	public static function all_posts( string $maros_id = '' ): array {
		$args = array(
			'post_type'      => 'mis_property',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'lang'           => '',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);
		if ( '' !== $maros_id ) {
			$args['meta_key']   = 'maros_id'; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['meta_value'] = $maros_id; // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		return array_map( 'intval', get_posts( $args ) );
	}

	/**
	 * Las traducciones que existen de una propiedad, por idioma: [ 'en' => id, 'es' => id ].
	 *
	 * Parte de las vinculadas en Polylang y, si falta algún idioma, recupera la entrada suelta de ese
	 * idioma con el mismo id de marOS (p. ej. si se perdió el vínculo). No incluye las de la papelera.
	 *
	 * @return array<string,int>
	 */
	public static function translations_of( string $maros_id ): array {
		$posts = self::all_posts( $maros_id );
		if ( ! $posts ) {
			return array();
		}
		if ( ! function_exists( 'pll_get_post_language' ) ) {
			return array( 'en' => $posts[0] );
		}
		$found = array();
		$first = self::find_by_maros_id( $maros_id ) ?: self::find_by_maros_id( $maros_id, 'es' );
		if ( $first && function_exists( 'pll_get_post_translations' ) ) {
			foreach ( (array) pll_get_post_translations( $first ) as $lang => $id ) {
				if ( in_array( (int) $id, $posts, true ) ) {
					$found[ $lang ] = (int) $id;
				}
			}
		}
		// Entradas sueltas: la publicada primero, después la más antigua.
		usort( $posts, static fn( $a, $b ) => ( 'publish' !== get_post_status( $a ) ) <=> ( 'publish' !== get_post_status( $b ) ) ?: $a <=> $b );
		foreach ( $posts as $id ) {
			$lang = (string) pll_get_post_language( $id );
			if ( in_array( $lang, self::LANGS, true ) && empty( $found[ $lang ] ) ) {
				$found[ $lang ] = $id;
			}
		}
		$sorted = array();
		foreach ( self::LANGS as $lang ) {
			if ( ! empty( $found[ $lang ] ) ) {
				$sorted[ $lang ] = $found[ $lang ];
			}
		}
		return $sorted;
	}

	/** Si la propiedad existe en todos los idiomas y con las traducciones vinculadas. */
	public static function has_all_languages( string $maros_id ): bool {
		return self::complete( self::translations_of( $maros_id ) );
	}

	/** Si están todas las traducciones y vinculadas (sin Polylang basta con una entrada). */
	private static function complete( array $found ): bool {
		if ( ! function_exists( 'pll_get_post_translations' ) ) {
			return (bool) $found;
		}
		return count( $found ) === count( self::LANGS ) && self::linked( $found ) && self::same_status( $found );
	}

	/** Si todas las traducciones tienen el mismo estado (publicada en un idioma = publicada en todos). */
	private static function same_status( array $found ): bool {
		return 1 === count( array_unique( array_map( 'get_post_status', array_values( $found ) ) ) );
	}

	/**
	 * Iguala el estado de las traducciones de una propiedad al de la que acaba de cambiar (p. ej. al
	 * publicar la española en el editor, se publica también la inglesa). Solo publicar, borrador,
	 * pendiente y privado; la papelera no se propaga.
	 */
	public static function align_status( string $new, string $old, WP_Post $post ): void {
		static $running = false;
		if ( $running || $new === $old || 'mis_property' !== $post->post_type || ! in_array( $new, array( 'publish', 'draft', 'pending', 'private' ), true )
			|| ! function_exists( 'pll_get_post_translations' ) ) {
			return;
		}
		$running = true;
		foreach ( (array) pll_get_post_translations( $post->ID ) as $id ) {
			$status = get_post_status( (int) $id );
			if ( (int) $id !== $post->ID && $status && 'trash' !== $status && $status !== $new ) {
				wp_update_post( array( 'ID' => (int) $id, 'post_status' => $new ) );
			}
		}
		$running = false;
	}

	/** Si Polylang tiene vinculadas entre sí estas traducciones. */
	private static function linked( array $ids ): bool {
		if ( ! function_exists( 'pll_get_post_translations' ) || ! $ids ) {
			return true;
		}
		$group = array_map( 'intval', (array) pll_get_post_translations( (int) reset( $ids ) ) );
		foreach ( $ids as $lang => $id ) {
			if ( ( $group[ $lang ] ?? 0 ) !== (int) $id ) {
				return false;
			}
		}
		return true;
	}
}
