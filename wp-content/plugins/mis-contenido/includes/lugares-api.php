<?php
/**
 * Lugares por una API REST sencilla para un GPT personalizado de ChatGPT con «Acciones»
 * (Julio, 18-11-2026). Es la otra vía al MCP (lugares-mcp.php): los GPT con Acciones van en
 * cualquier plan de pago de ChatGPT y llevan la clave en la cabecera, no en el enlace.
 *
 * Rutas (en /wp-json/mis/v1):
 *   GET    /zonas             mis/list-zones
 *   GET    /lugares           mis/list-places (zona, tipo, estado, buscar)
 *   POST   /lugares           mis/save-place (con id en el cuerpo, edita)
 *   DELETE /lugares/{id}      mis/delete-place
 *   GET    /openapi           Esquema OpenAPI para pegar en el GPT (público, sin datos).
 *
 * Misma clave personal y mismo rol que el MCP: `Authorization: Bearer <clave>` (en el GPT,
 * Autenticación → Clave de API → Bearer), o una contraseña de aplicación.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'mis_lugar_api_rutas' );

function mis_lugar_api_rutas(): void {
	$perm = static function ( WP_REST_Request $request ) {
		$ok = mis_lugar_mcp_permiso( $request );
		return is_wp_error( $ok ) ? $ok : true;
	};
	$run  = static function ( string $ability, callable $input ) {
		return static function ( WP_REST_Request $request ) use ( $ability, $input ) {
			$a = function_exists( 'wp_get_ability' ) ? wp_get_ability( $ability ) : null;
			if ( ! $a ) {
				return new WP_Error( 'mis_lugares_no_ability', 'La herramienta no está disponible.', array( 'status' => 500 ) );
			}
			$out = $a->execute( $input( $request ) );
			if ( is_wp_error( $out ) ) {
				$data = $out->get_error_data();
				$out->add_data( array( 'status' => is_array( $data ) && isset( $data['status'] ) ? $data['status'] : 400 ) );
				return $out;
			}
			return rest_ensure_response( $out );
		};
	};
	$pick = static fn( array $keys ) => static fn( WP_REST_Request $r ) => array_filter(
		array_intersect_key( $r->get_params(), array_flip( $keys ) ),
		static fn( $v ) => null !== $v && '' !== $v
	);

	register_rest_route(
		'mis/v1',
		'/zonas',
		array(
			'methods'             => 'GET',
			'callback'            => $run( 'mis/list-zones', $pick( array( 'buscar' ) ) ),
			'permission_callback' => $perm,
		)
	);
	register_rest_route(
		'mis/v1',
		'/lugares',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => $run( 'mis/list-places', $pick( array( 'zona', 'tipo', 'estado', 'buscar' ) ) ),
				'permission_callback' => $perm,
			),
			array(
				'methods'             => 'POST',
				'callback'            => $run(
					'mis/save-place',
					static function ( WP_REST_Request $r ) {
						$body = (array) ( $r->get_json_params() ?: $r->get_body_params() );
						$keys = array( 'id', 'nombre', 'zonas', 'tipo', 'resumen_en', 'resumen_es', 'donde', 'web', 'web_fecha', 'fuente', 'fuente_url', 'fuente_fecha', 'notas', 'orden', 'publicar' );
						$in   = array_intersect_key( $body, array_flip( $keys ) );
						if ( isset( $in['zonas'] ) && is_string( $in['zonas'] ) ) {
							$in['zonas'] = array_map( 'trim', explode( ',', $in['zonas'] ) );
						}
						foreach ( array( 'id', 'orden' ) as $int ) {
							if ( isset( $in[ $int ] ) ) {
								$in[ $int ] = (int) $in[ $int ];
							}
						}
						if ( isset( $in['publicar'] ) ) {
							$in['publicar'] = rest_sanitize_boolean( $in['publicar'] );
						}
						return $in;
					}
				),
				'permission_callback' => $perm,
			),
		)
	);
	register_rest_route(
		'mis/v1',
		'/lugares/(?P<id>\d+)',
		array(
			'methods'             => 'DELETE',
			'callback'            => $run( 'mis/delete-place', static fn( WP_REST_Request $r ) => array( 'id' => (int) $r['id'] ) ),
			'permission_callback' => $perm,
		)
	);
	register_rest_route(
		'mis/v1',
		'/openapi',
		array(
			'methods'             => 'GET',
			'callback'            => static fn() => rest_ensure_response( mis_lugar_openapi() ),
			'permission_callback' => '__return_true',
		)
	);
}

/** Esquema OpenAPI 3.1 de las rutas, para «Acciones» de un GPT. */
function mis_lugar_openapi(): array {
	$ability = static function ( string $name ): array {
		$a = function_exists( 'wp_get_ability' ) ? wp_get_ability( $name ) : null;
		return $a ? array( 'description' => (string) $a->get_description(), 'schema' => (array) $a->get_input_schema() ) : array( 'description' => '', 'schema' => array() );
	};
	$save   = $ability( 'mis/save-place' );
	$list   = $ability( 'mis/list-places' );
	$zones  = $ability( 'mis/list-zones' );
	$delete = $ability( 'mis/delete-place' );
	$query  = static function ( array $schema ): array {
		$out = array();
		foreach ( (array) ( $schema['properties'] ?? array() ) as $name => $prop ) {
			$out[] = array(
				'name'        => $name,
				'in'          => 'query',
				'required'    => false,
				'description' => (string) ( $prop['description'] ?? '' ),
				'schema'      => array_intersect_key( (array) $prop, array_flip( array( 'type', 'enum' ) ) ),
			);
		}
		return $out;
	};
	$ok = array( '200' => array( 'description' => 'Correcto', 'content' => array( 'application/json' => array( 'schema' => array( 'type' => 'object', 'additionalProperties' => true ) ) ) ) );
	$ok_list = array( '200' => array( 'description' => 'Correcto', 'content' => array( 'application/json' => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'object', 'additionalProperties' => true ) ) ) ) ) );

	return array(
		'openapi'    => '3.1.0',
		'info'       => array(
			'title'       => 'Marbella in Style · Lugares recomendados',
			'description' => 'Restaurantes, beach clubs, golf y servicios que Marbella in Style recomienda en cada zona de su web.',
			'version'     => '1.0.0',
		),
		'servers'    => array( array( 'url' => untrailingslashit( rest_url( 'mis/v1' ) ) ) ),
		'paths'      => array(
			'/zonas'        => array(
				'get' => array(
					'operationId' => 'listarZonas',
					'summary'     => 'Listar zonas',
					'description' => mb_substr( $zones['description'], 0, 300 ),
					'parameters'  => $query( $zones['schema'] ),
					'responses'   => $ok_list,
				),
			),
			'/lugares'      => array(
				'get'  => array(
					'operationId' => 'listarLugares',
					'summary'     => 'Listar lugares recomendados',
					'description' => mb_substr( $list['description'], 0, 300 ),
					'parameters'  => $query( $list['schema'] ),
					'responses'   => $ok_list,
				),
				'post' => array(
					'operationId'         => 'guardarLugar',
					'summary'             => 'Añadir o editar un lugar recomendado',
					'description'         => mb_substr( $save['description'], 0, 300 ),
					'x-openai-isConsequential' => true,
					'requestBody'         => array(
						'required' => true,
						'content'  => array( 'application/json' => array( 'schema' => $save['schema'] ) ),
					),
					'responses'           => $ok,
				),
			),
			'/lugares/{id}' => array(
				'delete' => array(
					'operationId'              => 'retirarLugar',
					'summary'                  => 'Retirar un lugar recomendado',
					'description'              => mb_substr( $delete['description'], 0, 300 ),
					'x-openai-isConsequential' => true,
					'parameters'               => array( array( 'name' => 'id', 'in' => 'path', 'required' => true, 'schema' => array( 'type' => 'integer' ) ) ),
					'responses'                => $ok,
				),
			),
		),
		'components' => array(
			'schemas'         => new stdClass(),
			'securitySchemes' => array( 'clave' => array( 'type' => 'http', 'scheme' => 'bearer' ) ),
		),
		'security'   => array( array( 'clave' => array() ) ),
	);
}
