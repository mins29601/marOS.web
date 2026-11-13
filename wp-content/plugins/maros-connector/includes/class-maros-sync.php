<?php
/**
 * Sincronización de propiedades marOS → WordPress (spec 003, US1).
 *
 * Solo toca campos operativos, tipo, equipamiento, zona y fotos. Nunca título visible, textos,
 * slug ni SEO de una propiedad que ya existe.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

class Maros_Sync {

	/** Zonas de marOS → nombre en/es. */
	private const ZONES = array(
		'nueva-andalucia' => array( 'Nueva Andalucía', 'Nueva Andalucía' ),
		'sierra-blanca'   => array( 'Sierra Blanca', 'Sierra Blanca' ),
		'puerto-banus'    => array( 'Puerto Banús', 'Puerto Banús' ),
		'golden-mile'     => array( 'Golden Mile', 'Milla de Oro' ),
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
	);

	/**
	 * Sincroniza todas las propiedades publicables.
	 *
	 * @param bool $publish Publicar las nuevas (en real nacen en borrador).
	 * @param bool $photos  Descargar fotos.
	 * @return array{created:int,updated:int,skipped:int,errors:string[]}
	 */
	public static function sync_all( bool $publish = false, bool $photos = true ): array {
		$result = array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array() );
		$list   = Maros_Client::get( '/properties' );
		if ( is_wp_error( $list ) ) {
			$result['errors'][] = $list->get_error_message();
			return $result;
		}
		foreach ( $list['data'] ?? array() as $summary ) {
			$data = Maros_Client::get( '/properties/' . $summary['id'] );
			if ( is_wp_error( $data ) ) {
				$result['errors'][] = $data->get_error_message();
				continue;
			}
			$outcome = self::sync_one( $data, $publish, $photos );
			if ( is_wp_error( $outcome ) ) {
				$result['errors'][] = $outcome->get_error_message();
			} else {
				++$result[ $outcome ];
			}
		}
		update_option( 'maros_last_full_sync', gmdate( 'c' ), false );
		return $result;
	}

	/**
	 * @return string|WP_Error created | updated | skipped
	 */
	public static function sync_one( array $data, bool $publish = false, bool $photos = true ) {
		$existing = self::find_by_maros_id( $data['id'], 'en' );

		if ( $existing && get_post_meta( $existing, 'maros_etag', true ) === ( $data['etag'] ?? '' ) ) {
			return 'skipped';
		}

		$status = empty( $data['publishable'] ) ? 'draft' : ( $publish ? 'publish' : 'draft' );
		$ids    = array();

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
			} elseif ( empty( $data['publishable'] ) ) {
				wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
			}
			if ( function_exists( 'pll_set_post_language' ) ) {
				pll_set_post_language( $post_id, $lang );
			}
			self::set_terms( $post_id, $data, $lang );
			self::set_meta( $post_id, $data );
			$ids[ $lang ] = $post_id;
		}

		if ( function_exists( 'pll_save_post_translations' ) ) {
			pll_save_post_translations( $ids );
		}

		if ( $photos ) {
			self::sync_photos( $ids, $data['photos'] ?? array() );
		}

		return $existing ? 'updated' : 'created';
	}

	private static function default_excerpt( array $d, string $lang ): string {
		$area = $d['location']['area'] ?? '';
		$type = self::TYPES[ $d['type'] ] ?? array( 'Property', 'Propiedad' );
		return 'es' === $lang
			? sprintf( '%s en %s para %d huéspedes, gestionada por nuestro equipo.', $type[1], $area, $d['max_guests'] )
			: sprintf( '%s in %s for %d guests, managed by our team.', $type[0], $area, $d['max_guests'] );
	}

	private static function set_meta( int $post_id, array $d ): void {
		$map = array(
			'maros_id'         => $d['id'],
			'maros_lodgify_id' => (int) ( $d['lodgify_id'] ?? 0 ),
			'maros_etag'       => $d['etag'] ?? '',
			'maros_updated_at' => $d['updated_at'] ?? '',
			'maros_name'       => $d['name'],
			'maros_max_guests' => (int) $d['max_guests'],
			'maros_bedrooms'   => (int) $d['bedrooms'],
			'maros_bathrooms'  => (int) $d['bathrooms'],
			'maros_beds'       => (int) ( $d['beds'] ?? 0 ),
			'maros_area'       => $d['location']['area'] ?? '',
			'maros_lat'        => (float) ( $d['location']['lat'] ?? 0 ),
			'maros_lng'        => (float) ( $d['location']['lng'] ?? 0 ),
			'maros_check_in'   => $d['check_in'] ?? '',
			'maros_check_out'  => $d['check_out'] ?? '',
			'maros_deposit'    => (float) ( $d['deposit'] ?? 0 ),
			'maros_cleaning'   => (float) ( $d['fees']['cleaning'] ?? 0 ),
		);
		foreach ( $map as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	private static function set_terms( int $post_id, array $d, string $lang ): void {
		if ( isset( self::TYPES[ $d['type'] ] ) ) {
			wp_set_object_terms( $post_id, array( self::term( 'tipo-propiedad', $d['type'], self::TYPES[ $d['type'] ], $lang ) ), 'tipo-propiedad' );
		}
		if ( isset( self::ZONES[ $d['zone'] ?? '' ] ) && taxonomy_exists( 'zona' ) ) {
			wp_set_object_terms( $post_id, array( self::term( 'zona', $d['zone'], self::ZONES[ $d['zone'] ], $lang ) ), 'zona' );
		}
		$amenities = array();
		foreach ( $d['amenities'] ?? array() as $slug ) {
			if ( isset( self::AMENITIES[ $slug ] ) ) {
				$amenities[] = self::term( 'equipamiento', $slug, self::AMENITIES[ $slug ], $lang );
			}
		}
		wp_set_object_terms( $post_id, $amenities, 'equipamiento' );
	}

	/**
	 * Id del término en ese idioma; lo crea (y enlaza sus traducciones) si falta.
	 */
	public static function term( string $tax, string $slug, array $names, string $lang ): int {
		$ids = array();
		foreach ( array( 'en' => 0, 'es' => 1 ) as $l => $i ) {
			$term_slug = 'en' === $l ? $slug : $slug . '-es';
			$term      = get_term_by( 'slug', $term_slug, $tax );
			if ( ! $term ) {
				$created = wp_insert_term( $names[ $i ], $tax, array( 'slug' => $term_slug ) );
				if ( is_wp_error( $created ) ) {
					continue;
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

	private static function sync_photos( array $ids, array $photos ): void {
		$primary = $ids['en'];
		$current = array_filter( explode( ',', (string) get_post_meta( $primary, 'maros_gallery', true ) ) );
		$known   = array();
		foreach ( $current as $att ) {
			$known[ get_post_meta( (int) $att, 'maros_photo_hash', true ) ] = (int) $att;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		usort( $photos, static fn( $a, $b ) => ( $a['order'] ?? 0 ) <=> ( $b['order'] ?? 0 ) );
		$gallery = array();
		foreach ( $photos as $photo ) {
			$hash = $photo['hash'] ?? md5( $photo['url'] );
			if ( isset( $known[ $hash ] ) ) {
				$gallery[] = $known[ $hash ];
				continue;
			}
			$tmp = self::fetch_photo( $photo['url'] );
			if ( is_wp_error( $tmp ) ) {
				continue;
			}
			$att = media_handle_sideload(
				array( 'name' => sanitize_file_name( $hash ) . '.jpg', 'tmp_name' => $tmp ),
				$primary,
				get_the_title( $primary )
			);
			if ( is_wp_error( $att ) ) {
				wp_delete_file( $tmp );
				continue;
			}
			update_post_meta( $att, 'maros_photo_hash', $hash );
			update_post_meta( $att, '_wp_attachment_image_alt', get_the_title( $primary ) );
			$gallery[] = $att;
		}

		foreach ( $ids as $post_id ) {
			update_post_meta( $post_id, 'maros_gallery', implode( ',', $gallery ) );
			if ( $gallery && ! has_post_thumbnail( $post_id ) ) {
				set_post_thumbnail( $post_id, $gallery[0] );
			}
		}
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

	private static function translation( int $post_id, string $lang ): int {
		if ( function_exists( 'pll_get_post' ) ) {
			return (int) pll_get_post( $post_id, $lang );
		}
		return 'en' === $lang ? $post_id : 0;
	}
}
