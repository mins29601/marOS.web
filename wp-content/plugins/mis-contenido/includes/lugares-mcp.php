<?php
/**
 * Lugares por el MCP (Julio, 18-11-2026): Rebecca los gestiona desde su asistente (ChatGPT)
 * con un simple chat: ver zonas, listar lugares, añadir o editar uno y retirarlo.
 *
 * - Abilities `mis/list-zones`, `mis/list-places`, `mis/save-place` y `mis/delete-place`.
 *   Salen también en el MCP general de la web (MCP Adapter) para quien ya lo usa.
 * - Un servidor MCP propio solo con ellas, en /wp-json/mis/lugares. Admite:
 *     · una contraseña de aplicación (como el MCP general), o
 *     · la clave personal de un usuario con el rol «Gestor de lugares», en la cabecera
 *       `x-api-key` / `Authorization: Bearer`, o en la URL (`?clave=`), porque ChatGPT solo
 *       conecta servidores sin clave o con OAuth. La clave solo vale para este servidor y este
 *       rol solo puede tocar lugares; se revoca desde el perfil del usuario.
 * - Lo que llega entra como borrador salvo que se pida publicar (constante MIS_LUGARES_STATUS
 *   para cambiar el estado por defecto).
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

const MIS_LUGAR_CLAVE_META = 'mis_lugares_clave';

/** Estado por defecto de un lugar nuevo que llega por el MCP: draft o publish. */
function mis_lugar_estado_por_defecto(): string {
	$status = defined( 'MIS_LUGARES_STATUS' ) ? (string) MIS_LUGARES_STATUS : 'draft';
	return in_array( $status, array( 'draft', 'publish' ), true ) ? $status : 'draft';
}

// ------------------------------------------------------------------ abilities

add_action(
	'wp_abilities_api_categories_init',
	static function () {
		if ( function_exists( 'wp_register_ability_category' ) && ! ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( 'mis-lugares' ) ) ) {
			wp_register_ability_category(
				'mis-lugares',
				array(
					'label'       => 'Lugares recomendados',
					'description' => 'Restaurantes, beach clubs, golf y servicios que Marbella in Style recomienda en cada zona.',
				)
			);
		}
	}
);

add_action( 'wp_abilities_api_init', 'mis_lugar_register_abilities' );

/** Nombres de las abilities de lugares (son las herramientas del servidor propio). */
function mis_lugar_abilities(): array {
	return array( 'mis/list-zones', 'mis/list-places', 'mis/save-place', 'mis/delete-place' );
}

function mis_lugar_register_abilities(): void {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}
	$meta = static fn( bool $readonly, bool $destructive, bool $idempotent ) => array(
		'show_in_rest' => true,
		'annotations'  => array(
			'readonly'    => $readonly,
			'destructive' => $destructive,
			'idempotent'  => $idempotent,
		),
		'mcp'          => array( 'public' => true ),
	);
	$can  = static fn() => current_user_can( 'edit_mis_lugares' );
	$date = array( 'type' => 'string', 'pattern' => '^\\d{4}-\\d{2}-\\d{2}$' );

	$lugar_out = array(
		'type'       => 'object',
		'properties' => array(
			'id'           => array( 'type' => 'integer' ),
			'nombre'       => array( 'type' => 'string' ),
			'estado'       => array( 'type' => 'string', 'description' => 'publish (se ve en la web) o draft (borrador).' ),
			'zonas'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'tipo'         => array( 'type' => 'string' ),
			'resumen_en'   => array( 'type' => 'string' ),
			'resumen_es'   => array( 'type' => 'string' ),
			'donde'        => array( 'type' => 'string' ),
			'web'          => array( 'type' => 'string' ),
			'web_fecha'    => array( 'type' => 'string' ),
			'fuente'       => array( 'type' => 'string' ),
			'fuente_url'   => array( 'type' => 'string' ),
			'fuente_fecha' => array( 'type' => 'string' ),
			'notas'        => array( 'type' => 'string' ),
			'orden'        => array( 'type' => 'integer' ),
		),
	);

	wp_register_ability(
		'mis/list-zones',
		array(
			'label'               => 'Listar zonas',
			'description'         => 'Lista las zonas de la web (slug, nombre en inglés y en español) y cuántos lugares recomendados tiene cada una. Usa el slug para asignar un lugar a una zona.',
			'category'            => 'mis-lugares',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array( 'buscar' => array( 'type' => 'string', 'description' => 'Texto en el nombre (opcional).' ) ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'slug'      => array( 'type' => 'string' ),
						'nombre_en' => array( 'type' => 'string' ),
						'nombre_es' => array( 'type' => 'string' ),
						'lugares'   => array( 'type' => 'integer' ),
						'url'       => array( 'type' => 'string' ),
					),
				),
			),
			'execute_callback'    => 'mis_lugar_ability_list_zones',
			'permission_callback' => $can,
			'meta'                => $meta( true, false, true ),
		)
	);

	wp_register_ability(
		'mis/list-places',
		array(
			'label'               => 'Listar lugares recomendados',
			'description'         => 'Lista los lugares recomendados, publicados y borradores, con todos sus datos y su id. Filtra por zona (slug), tipo o texto. Úsalo antes de añadir uno para no duplicarlo y para saber el id de uno que quieras editar o retirar.',
			'category'            => 'mis-lugares',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'zona'   => array( 'type' => 'string', 'description' => 'Slug o nombre de la zona (opcional).' ),
					'tipo'   => array( 'type' => 'string', 'enum' => array_keys( mis_lugar_tipos() ) ),
					'estado' => array( 'type' => 'string', 'enum' => array( 'publish', 'draft', 'todos' ), 'default' => 'todos' ),
					'buscar' => array( 'type' => 'string', 'description' => 'Texto en el nombre (opcional).' ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array( 'type' => 'array', 'items' => $lugar_out ),
			'execute_callback'    => 'mis_lugar_ability_list',
			'permission_callback' => $can,
			'meta'                => $meta( true, false, true ),
		)
	);

	$tipos = array();
	foreach ( mis_lugar_tipos() as $key => $t ) {
		$tipos[] = "$key ({$t[1]})";
	}

	wp_register_ability(
		'mis/save-place',
		array(
			'label'               => 'Añadir o editar un lugar recomendado',
			'description'         => 'Crea un lugar recomendado o, con id, cambia los campos que envíes (los demás se quedan como están). '
				. 'Reglas de Marbella in Style: solo locales que recomienda Marbella in Style (guía interna o un post de @marbellainstyle, con su enlace y fecha); si en una zona no hay, los primeros de Tripadvisor con la fecha de consulta. '
				. 'Frase corta: para qué lo recomendamos, inglés británico en resumen_en y español de tú en resumen_es; sin teléfonos, sin distancias ni tiempos y sin superlativos («el mejor»). No inventes datos: si no sabes la web oficial o la fuente, pregúntalo. '
				. 'Un lugar nuevo entra como borrador; publícalo solo si te lo piden (publicar: true). Tipos: ' . implode( ', ', $tipos ) . '.',
			'category'            => 'mis-lugares',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'id'           => array( 'type' => 'integer', 'description' => 'Id del lugar para editarlo (de mis/list-places). Sin id, se crea uno nuevo.' ),
					'nombre'       => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 120, 'description' => 'Nombre del local, tal como lo escribe él.' ),
					'zonas'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'minItems' => 1, 'description' => 'Slugs de zona (de mis/list-zones). Al editar, sustituye a las que tenga.' ),
					'tipo'         => array( 'type' => 'string', 'enum' => array_keys( mis_lugar_tipos() ) ),
					'resumen_en'   => array( 'type' => 'string', 'maxLength' => 140, 'description' => 'Frase corta en inglés británico: para qué lo recomendamos.' ),
					'resumen_es'   => array( 'type' => 'string', 'maxLength' => 140, 'description' => 'La misma frase en español, de tú.' ),
					'donde'        => array( 'type' => 'string', 'maxLength' => 80, 'description' => 'Playa, urbanización o calle (p. ej. «Guadalmansa»). Sin distancias.' ),
					'web'          => array( 'type' => 'string', 'description' => 'Web oficial (https), comprobada.' ),
					'web_fecha'    => $date + array( 'description' => 'Día en que se comprobó la web. Por defecto, hoy.' ),
					'fuente'       => array( 'type' => 'string', 'enum' => array_keys( mis_lugar_fuentes() ), 'description' => 'guia (guía interna), instagram (post de @marbellainstyle) o tripadvisor.' ),
					'fuente_url'   => array( 'type' => 'string', 'description' => 'Enlace del post de Instagram o de la página de Tripadvisor.' ),
					'fuente_fecha' => $date + array( 'description' => 'Fecha del post o de la consulta en Tripadvisor.' ),
					'notas'        => array( 'type' => 'string', 'maxLength' => 1000, 'description' => 'Notas internas, no se publican.' ),
					'orden'        => array( 'type' => 'integer', 'description' => 'Orden dentro de la zona (menor, antes).' ),
					'publicar'     => array( 'type' => 'boolean', 'description' => 'true lo publica en la web; false lo pasa a borrador. Sin indicar: nuevo como borrador, editado como estaba.' ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'creado' => array( 'type' => 'boolean' ),
					'lugar'  => $lugar_out,
					'aviso'  => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => 'mis_lugar_ability_save',
			'permission_callback' => $can,
			'meta'                => $meta( false, false, false ),
		)
	);

	wp_register_ability(
		'mis/delete-place',
		array(
			'label'               => 'Retirar un lugar recomendado',
			'description'         => 'Quita un lugar de la web (va a la papelera de WordPress, de donde se puede recuperar). Confirma con la persona antes de hacerlo.',
			'category'            => 'mis-lugares',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'id' ),
				'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'id'       => array( 'type' => 'integer' ),
					'nombre'   => array( 'type' => 'string' ),
					'retirado' => array( 'type' => 'boolean' ),
				),
			),
			'execute_callback'    => 'mis_lugar_ability_delete',
			'permission_callback' => static fn( $input = array() ) => current_user_can( 'delete_mis_lugares' ),
			'meta'                => $meta( false, true, true ),
		)
	);
}

function mis_lugar_ability_list_zones( $input = array() ): array {
	$buscar = is_array( $input ) ? remove_accents( mb_strtolower( (string) ( $input['buscar'] ?? '' ) ) ) : '';
	$lang  = function_exists( 'pll_default_language' ) ? (string) pll_default_language() : '';
	$terms = get_terms( array( 'taxonomy' => 'zona', 'hide_empty' => false, 'lang' => $lang, 'orderby' => 'name' ) );
	$out   = array();
	foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
		$es     = function_exists( 'pll_get_term' ) ? (int) pll_get_term( $term->term_id, 'es' ) : 0;
		$nom_es = $es ? (string) get_term_field( 'name', $es, 'zona' ) : $term->name;
		if ( '' !== $buscar && false === strpos( remove_accents( mb_strtolower( $term->name . ' ' . $nom_es . ' ' . $term->slug ) ), $buscar ) ) {
			continue;
		}
		$count = get_posts(
			array(
				'post_type'   => MIS_LUGAR_CPT,
				'post_status' => array( 'publish', 'draft' ),
				'numberposts' => -1,
				'fields'      => 'ids',
				'lang'        => '',
				'tax_query'   => array( array( 'taxonomy' => 'zona', 'terms' => $term->term_id, 'include_children' => false ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);
		$link  = get_term_link( $term );
		$out[] = array(
			'slug'      => $term->slug,
			'nombre_en' => $term->name,
			'nombre_es' => $nom_es,
			'lugares'   => count( $count ),
			'url'       => is_wp_error( $link ) ? '' : $link,
		);
	}
	return $out;
}

function mis_lugar_ability_list( $input = array() ) {
	$input = is_array( $input ) ? $input : array();
	$args  = array(
		'post_type'   => MIS_LUGAR_CPT,
		'post_status' => in_array( $input['estado'] ?? '', array( 'publish', 'draft' ), true ) ? $input['estado'] : array( 'publish', 'draft', 'pending' ),
		'numberposts' => 300,
		'orderby'     => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		'lang'        => '',
		's'           => (string) ( $input['buscar'] ?? '' ),
	);
	if ( ! empty( $input['zona'] ) ) {
		$zonas = mis_lugar_resolver_zonas( array( (string) $input['zona'] ) );
		if ( is_wp_error( $zonas ) ) {
			return $zonas;
		}
		$args['tax_query'] = array( array( 'taxonomy' => 'zona', 'terms' => $zonas, 'include_children' => false ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	}
	if ( ! empty( $input['tipo'] ) ) {
		$args['meta_query'] = array( array( 'key' => 'lugar_tipo', 'value' => (string) $input['tipo'] ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	}
	return array_map( static fn( $p ) => mis_lugar_datos( (int) $p->ID ), get_posts( $args ) );
}

/** Lugar con el mismo nombre en alguna de las zonas (para no duplicar). */
function mis_lugar_duplicado( string $nombre, array $zonas, int $except = 0 ): int {
	$found = get_posts(
		array(
			'post_type'   => MIS_LUGAR_CPT,
			'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
			'numberposts' => 50,
			'lang'        => '',
			'exclude'     => $except ? array( $except ) : array(),
			'tax_query'   => array( array( 'taxonomy' => 'zona', 'terms' => $zonas, 'include_children' => false ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		)
	);
	$key = remove_accents( mb_strtolower( trim( html_entity_decode( $nombre, ENT_QUOTES, 'UTF-8' ) ) ) );
	foreach ( $found as $post ) {
		if ( remove_accents( mb_strtolower( trim( html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' ) ) ) ) === $key ) {
			return (int) $post->ID;
		}
	}
	return 0;
}

function mis_lugar_ability_save( $input = array() ) {
	$input = is_array( $input ) ? $input : array();
	$id    = (int) ( $input['id'] ?? 0 );

	$current = array();
	if ( $id ) {
		if ( MIS_LUGAR_CPT !== get_post_type( $id ) || 'trash' === get_post_status( $id ) ) {
			return new WP_Error( 'mis_lugar_no_existe', 'No hay ningún lugar con ese id. Usa mis/list-places.' );
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'mis_lugar_permiso', 'No puedes editar este lugar.' );
		}
		$current = mis_lugar_datos( $id );
	}

	$fields = array( 'nombre', 'zonas', 'tipo', 'resumen_en', 'resumen_es', 'donde', 'web', 'web_fecha', 'fuente', 'fuente_url', 'fuente_fecha', 'notas', 'orden' );
	$in     = array_intersect_key( $input, array_flip( $fields ) );
	$data   = mis_lugar_validar( $in, $current );
	if ( is_wp_error( $data ) ) {
		return $data;
	}

	$dup = mis_lugar_duplicado( $data['nombre'], $data['zonas'], $id );
	if ( $dup ) {
		return new WP_Error( 'mis_lugar_duplicado', sprintf( 'Ya hay un lugar «%s» en esa zona (id %d). Para cambiarlo, envía ese id.', get_the_title( $dup ), $dup ) );
	}

	$status = '';
	if ( array_key_exists( 'publicar', $input ) ) {
		$status = $input['publicar'] ? 'publish' : 'draft';
	} elseif ( ! $id ) {
		$status = mis_lugar_estado_por_defecto();
	}
	if ( 'publish' === $status && ! current_user_can( 'publish_mis_lugares' ) ) {
		$status = 'draft';
	}

	$saved = mis_lugar_guardar( $data, $id, $status );
	if ( is_wp_error( $saved ) ) {
		return $saved;
	}

	$user = wp_get_current_user();
	if ( class_exists( 'Maros_Log' ) ) {
		Maros_Log::info( sprintf( 'Lugar %d «%s» %s por %s (%s).', $saved, $data['nombre'], $id ? 'editado' : 'creado', $user->exists() ? $user->user_login : 'desconocido', (string) get_post_status( $saved ) ) );
	}

	$lugar = mis_lugar_datos( $saved );
	return array(
		'creado' => ! $id,
		'lugar'  => $lugar,
		'aviso'  => 'publish' === $lugar['estado']
			? 'Publicado: ya se ve en la guía de la zona.'
			: 'Guardado como borrador: no se ve en la web hasta que se publique.',
	);
}

function mis_lugar_ability_delete( $input = array() ) {
	$id = (int) ( is_array( $input ) ? ( $input['id'] ?? 0 ) : 0 );
	if ( ! $id || MIS_LUGAR_CPT !== get_post_type( $id ) ) {
		return new WP_Error( 'mis_lugar_no_existe', 'No hay ningún lugar con ese id. Usa mis/list-places.' );
	}
	if ( ! current_user_can( 'delete_post', $id ) ) {
		return new WP_Error( 'mis_lugar_permiso', 'No puedes retirar este lugar.' );
	}
	$nombre = html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' );
	$ok     = 'trash' === get_post_status( $id ) || (bool) wp_trash_post( $id );
	if ( $ok && class_exists( 'Maros_Log' ) ) {
		$user = wp_get_current_user();
		Maros_Log::info( sprintf( 'Lugar %d «%s» retirado por %s.', $id, $nombre, $user->exists() ? $user->user_login : 'desconocido' ) );
	}
	return array( 'id' => $id, 'nombre' => $nombre, 'retirado' => $ok );
}

// ------------------------------------------------------------------ servidor MCP propio

add_action(
	'mcp_adapter_init',
	static function ( $adapter ) {
		if ( ! is_object( $adapter ) || ! method_exists( $adapter, 'create_server' ) || ! class_exists( '\WP\MCP\Transport\HttpTransport' ) ) {
			return;
		}
		$adapter->create_server(
			'mis-lugares',
			'mis',
			'lugares',
			'Marbella in Style · Lugares recomendados',
			'Restaurantes, beach clubs, golf y servicios que Marbella in Style recomienda en cada zona de su web.',
			'1.0.0',
			array( \WP\MCP\Transport\HttpTransport::class ),
			class_exists( '\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler' ) ? \WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class : null,
			null,
			mis_lugar_abilities(),
			array(),
			array(),
			'mis_lugar_mcp_permiso'
		);
	}
);

/**
 * Quién entra al servidor de lugares: un usuario ya identificado (contraseña de aplicación) que
 * pueda editar lugares, o la clave personal de un usuario que pueda hacerlo.
 *
 * @return true|WP_Error
 */
function mis_lugar_mcp_permiso( $request = null ) {
	if ( is_user_logged_in() ) {
		return current_user_can( 'edit_mis_lugares' )
			? true
			: new WP_Error( 'mis_lugares_forbidden', 'Este usuario no puede gestionar lugares.', array( 'status' => 403 ) );
	}

	$clave = mis_lugar_clave_de_peticion( $request instanceof WP_REST_Request ? $request : null );
	if ( '' === $clave ) {
		return new WP_Error( 'mis_lugares_unauthorized', 'Falta la clave.', array( 'status' => 401 ) );
	}
	$user_id = mis_lugar_usuario_de_clave( $clave );
	if ( ! $user_id || ! user_can( $user_id, 'edit_mis_lugares' ) ) {
		return new WP_Error( 'mis_lugares_unauthorized', 'Clave no válida.', array( 'status' => 401 ) );
	}

	// Límite de peticiones por usuario y minuto.
	$key  = 'mis_lugares_rl_' . $user_id . '_' . gmdate( 'YmdHi' );
	$hits = (int) get_transient( $key ) + 1;
	set_transient( $key, $hits, 120 );
	if ( $hits > (int) apply_filters( 'mis_lugares_rate_limit', 60 ) ) {
		return new WP_Error( 'mis_lugares_rate_limited', 'Demasiadas peticiones; espera un minuto.', array( 'status' => 429 ) );
	}

	wp_set_current_user( $user_id );
	update_user_meta( $user_id, 'mis_lugares_clave_uso', wp_date( 'Y-m-d H:i' ) );
	return true;
}

function mis_lugar_clave_de_peticion( ?WP_REST_Request $request ): string {
	$clave = '';
	if ( $request ) {
		$clave = (string) $request->get_header( 'x-api-key' );
		if ( '' === $clave && preg_match( '/^Bearer\s+(\S+)$/i', (string) $request->get_header( 'authorization' ), $m ) ) {
			$clave = $m[1];
		}
		if ( '' === $clave ) {
			$clave = (string) $request->get_param( 'clave' );
		}
	}
	return preg_match( '/^mis_[A-Za-z0-9]{40}$/', $clave ) ? $clave : '';
}

function mis_lugar_hash_clave( string $clave ): string {
	return hash_hmac( 'sha256', $clave, wp_salt( 'auth' ) );
}

function mis_lugar_usuario_de_clave( string $clave ): int {
	$users = get_users(
		array(
			'meta_key'   => MIS_LUGAR_CLAVE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => mis_lugar_hash_clave( $clave ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'number'     => 1,
			'fields'     => 'ID',
		)
	);
	return (int) ( $users[0] ?? 0 );
}

/** Crea una clave nueva para el usuario (sustituye a la anterior) y la devuelve; solo se ve una vez. */
function mis_lugar_nueva_clave( int $user_id ): string {
	$clave = 'mis_' . wp_generate_password( 40, false, false );
	update_user_meta( $user_id, MIS_LUGAR_CLAVE_META, mis_lugar_hash_clave( $clave ) );
	update_user_meta( $user_id, 'mis_lugares_clave_fecha', wp_date( 'Y-m-d H:i' ) );
	delete_user_meta( $user_id, 'mis_lugares_clave_uso' );
	return $clave;
}

function mis_lugar_revocar_clave( int $user_id ): void {
	delete_user_meta( $user_id, MIS_LUGAR_CLAVE_META );
	delete_user_meta( $user_id, 'mis_lugares_clave_fecha' );
	delete_user_meta( $user_id, 'mis_lugares_clave_uso' );
}

/** URL del servidor de lugares (sin la clave). */
function mis_lugar_mcp_url(): string {
	return rest_url( 'mis/lugares' );
}

// ------------------------------------------------------------------ perfil: generar y revocar la clave

add_action( 'show_user_profile', 'mis_lugar_perfil' );
add_action( 'edit_user_profile', 'mis_lugar_perfil' );

function mis_lugar_perfil( WP_User $user ): void {
	if ( ! current_user_can( 'manage_options' ) || ! user_can( $user, 'edit_mis_lugares' ) ) {
		return;
	}
	$fecha = (string) get_user_meta( $user->ID, 'mis_lugares_clave_fecha', true );
	$uso   = (string) get_user_meta( $user->ID, 'mis_lugares_clave_uso', true );
	$nueva = get_transient( 'mis_lugares_clave_nueva_' . $user->ID );
	if ( $nueva ) {
		delete_transient( 'mis_lugares_clave_nueva_' . $user->ID );
	}
	wp_nonce_field( 'mis_lugares_clave', 'mis_lugares_clave_nonce' );
	echo '<h2 id="lugares-chatgpt">Lugares desde ChatGPT</h2><table class="form-table" role="presentation"><tr><th>Enlace del conector</th><td>';
	if ( $nueva ) {
		$url = add_query_arg( 'clave', $nueva, mis_lugar_mcp_url() );
		echo '<p><strong>Cópialo ahora: no se vuelve a mostrar.</strong></p><input type="text" readonly class="large-text code" onclick="this.select()" value="' . esc_attr( $url ) . '">';
		echo '<p class="description">En ChatGPT: Ajustes → Aplicaciones y conectores → Crear, con este enlace como URL del servidor MCP y «Sin autenticación». Quien tenga el enlace puede gestionar lugares como este usuario: no lo compartas.</p>';
	} elseif ( $fecha ) {
		echo '<p>Hay un enlace activo, creado el ' . esc_html( $fecha ) . ( $uso ? ', usado por última vez el ' . esc_html( $uso ) : ', aún sin usar' ) . '.</p>';
	} else {
		echo '<p>Este usuario no tiene enlace.</p>';
	}
	echo '<p><label><input type="checkbox" name="mis_lugares_clave" value="nueva"> ' . esc_html( $fecha ? 'Crear un enlace nuevo (el anterior deja de funcionar)' : 'Crear un enlace' ) . '</label>';
	if ( $fecha ) {
		echo '<br><label><input type="checkbox" name="mis_lugares_clave" value="revocar"> Revocar el enlace</label>';
	}
	echo '</p></td></tr></table>';
}

add_action( 'personal_options_update', 'mis_lugar_perfil_guardar' );
add_action( 'edit_user_profile_update', 'mis_lugar_perfil_guardar' );

function mis_lugar_perfil_guardar( int $user_id ): void {
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['mis_lugares_clave_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mis_lugares_clave_nonce'] ) ), 'mis_lugares_clave' ) ) {
		return;
	}
	$accion = sanitize_key( wp_unslash( $_POST['mis_lugares_clave'] ?? '' ) );
	if ( 'nueva' === $accion && user_can( $user_id, 'edit_mis_lugares' ) ) {
		set_transient( 'mis_lugares_clave_nueva_' . $user_id, mis_lugar_nueva_clave( $user_id ), 300 );
	} elseif ( 'revocar' === $accion ) {
		mis_lugar_revocar_clave( $user_id );
	}
}

// ------------------------------------------------------------------ WP-CLI

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Claves del servidor MCP de lugares.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mis-lugares clave rebecca
	 *     wp mis-lugares clave rebecca --revocar
	 */
	WP_CLI::add_command(
		'mis-lugares clave',
		static function ( array $args, array $assoc ) {
			$user = get_user_by( 'login', (string) ( $args[0] ?? '' ) ) ?: get_user_by( 'email', (string) ( $args[0] ?? '' ) );
			if ( ! $user ) {
				WP_CLI::error( 'No existe ese usuario.' );
			}
			if ( ! empty( $assoc['revocar'] ) ) {
				mis_lugar_revocar_clave( $user->ID );
				WP_CLI::success( 'Clave revocada.' );
				return;
			}
			if ( ! user_can( $user, 'edit_mis_lugares' ) ) {
				WP_CLI::error( 'Ese usuario no puede gestionar lugares: dale el rol «Gestor de lugares».' );
			}
			WP_CLI::line( add_query_arg( 'clave', mis_lugar_nueva_clave( $user->ID ), mis_lugar_mcp_url() ) );
			WP_CLI::success( 'Enlace creado (no se vuelve a mostrar).' );
		}
	);
}
