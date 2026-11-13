<?php
/**
 * Sincronización de propiedades marOS → WordPress (spec 003, US1 y US2).
 *
 * Solo toca campos operativos, tipo, equipamiento, zona, fotos y calendario. Nunca título visible,
 * textos, slug ni SEO de una propiedad que ya existe. Las nuevas nacen en borrador.
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
	);

	/** Zonas que marOS puede nombrar en español. */
	private const ZONE_ALIASES = array( 'milla-de-oro' => 'golden-mile' );

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
	);

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
				|| get_post_meta( $post, 'maros_etag', true ) !== (string) ( $summary['etag'] ?? '' )
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
			&& get_post_meta( $existing, 'maros_etag', true ) === (string) $summary['etag']
			&& ! get_post_meta( $existing, 'maros_withdrawn', true );
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
		$existing  = self::find_by_maros_id( $maros_id );
		$etag      = (string) ( $data['etag'] ?? $data['_etag'] ?? '' );
		$withdrawn = $existing && get_post_meta( $existing, 'maros_withdrawn', true );

		if ( $existing && ! $withdrawn && '' !== $etag && get_post_meta( $existing, 'maros_etag', true ) === $etag ) {
			return 'skipped';
		}

		// La API del catálogo solo entrega publicables; el campo se respeta por si llega en false.
		$publishable = ! array_key_exists( 'publishable', $data ) || ! empty( $data['publishable'] );
		$status      = $publishable && $publish ? 'publish' : 'draft';
		$ids         = array();

		foreach ( array( 'en', 'es' ) as $lang ) {
			$post_id = $existing ? self::translation( $existing, $lang ) : 0;
			if ( ! $post_id ) {
				$post_id = wp_insert_post(
					array(
						'post_type'    => 'mis_property',
						'post_title'   => $data['name'],
						'post_name'    => sanitize_title( $data['name'] ),
						'post_status'  => $status,
						'post_excerpt' => self::default_excerpt( $data, $lang ),
					),
					true
				);
				if ( is_wp_error( $post_id ) ) {
					return $post_id;
				}
			} elseif ( ! $publishable ) {
				wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
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

		$complete = $photos ? self::sync_photos( $ids, $data['photos'] ?? array() ) : true;

		// El etag se guarda al final: si algo queda a medias (fotos), la próxima pasada lo repite.
		foreach ( $ids as $post_id ) {
			update_post_meta( $post_id, 'maros_etag', $complete ? $etag : '' );
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

	private static function default_excerpt( array $d, string $lang ): string {
		$area = (string) ( $d['location']['area'] ?? '' );
		$type = self::type_of( $d )[1];
		$max  = (int) ( $d['max_guests'] ?? 0 );
		if ( 'es' === $lang ) {
			return trim( sprintf( '%s%s para %d huéspedes, gestionada por nuestro equipo.', $type[1], $area ? ' en ' . $area : '', $max ) );
		}
		return trim( sprintf( '%s%s for %d guests, managed by our team.', $type[0], $area ? ' in ' . $area : '', $max ) );
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
			'maros_max_guests'  => (int) ( $d['max_guests'] ?? 0 ),
			'maros_bedrooms'    => (int) ( $d['bedrooms'] ?? 0 ),
			'maros_bathrooms'   => (int) ( $d['bathrooms'] ?? 0 ),
			'maros_beds'        => (int) ( $d['beds'] ?? 0 ),
			'maros_area'        => (string) ( $d['location']['area'] ?? '' ),
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
		);
		foreach ( $map as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	private static function set_terms( int $post_id, array $d, string $lang ): void {
		list( $type_slug, $type_names ) = self::type_of( $d );
		if ( $type_slug ) {
			wp_set_object_terms( $post_id, array( self::term( 'tipo-propiedad', $type_slug, $type_names, $lang ) ), 'tipo-propiedad' );
		}

		$area = (string) ( $d['location']['area'] ?? '' );
		$zone = sanitize_title( (string) ( $d['zone'] ?? $area ) );
		$zone = self::ZONE_ALIASES[ $zone ] ?? $zone;
		if ( $zone && taxonomy_exists( 'zona' ) ) {
			wp_set_object_terms( $post_id, array( self::term( 'zona', $zone, self::ZONES[ $zone ] ?? array( $area, $area ), $lang ) ), 'zona' );
		}

		$amenities = array();
		foreach ( is_array( $d['amenities'] ?? null ) ? $d['amenities'] : array() as $item ) {
			$code = sanitize_title( is_array( $item ) ? (string) ( $item['code'] ?? '' ) : (string) $item );
			if ( ! $code ) {
				continue;
			}
			$name        = is_array( $item ) ? (string) ( $item['name'] ?? $code ) : $code;
			$amenities[] = self::term( 'equipamiento', $code, self::AMENITIES[ $code ] ?? array( $name, $name ), $lang );
		}
		wp_set_object_terms( $post_id, array_filter( $amenities ), 'equipamiento' );
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

	private static function translation( int $post_id, string $lang ): int {
		if ( function_exists( 'pll_get_post' ) ) {
			return (int) pll_get_post( $post_id, $lang );
		}
		return 'en' === $lang ? $post_id : 0;
	}
}
