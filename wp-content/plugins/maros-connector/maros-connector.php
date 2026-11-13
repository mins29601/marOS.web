<?php
/**
 * Plugin Name:       marOS Connector
 * Description:       Propiedades (villas, apartamentos…) cargadas desde la API de marOS (spec 003): ficha, fotos, calendario y tarifas, con cron, cola, avisos firmados y pantalla de estado.
 * Version:           0.2.0
 * Requires at least: 6.9
 * Requires PHP:      8.3
 * Text Domain:       maros-connector
 * License:           GPL-2.0-or-later
 *
 * Pendiente (spec 003): proxy de presupuesto y reserva, solicitudes Open, bloques de búsqueda y
 * calendario, Abilities del MCP, purga de APO.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

const MAROS_CONNECTOR_VERSION = '0.2.0';

require_once __DIR__ . '/includes/class-maros-client.php';
require_once __DIR__ . '/includes/class-maros-log.php';
require_once __DIR__ . '/includes/class-maros-calendar.php';
require_once __DIR__ . '/includes/class-maros-sync.php';
require_once __DIR__ . '/includes/class-maros-scheduler.php';
require_once __DIR__ . '/includes/class-maros-webhook.php';
require_once __DIR__ . '/includes/bindings.php';

Maros_Scheduler::init();
Maros_Webhook::init();
add_action( 'plugins_loaded', array( 'Maros_Calendar', 'maybe_install' ) );

if ( is_admin() ) {
	require_once __DIR__ . '/includes/class-maros-admin.php';
	Maros_Admin::init();
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/includes/class-maros-cli.php';
	WP_CLI::add_command( 'maros', 'Maros_CLI' );
}

/**
 * Campos operativos: vienen de marOS, se ven en el editor pero no se editan (US1, escenario 5).
 * Sin guion bajo para que los usen la REST y las vinculaciones de bloques.
 */
function maros_property_fields(): array {
	return array(
		'maros_id'         => 'string',
		'maros_lodgify_id' => 'integer',
		'maros_etag'       => 'string',
		'maros_updated_at' => 'string',
		'maros_name'       => 'string',
		'maros_max_guests' => 'integer',
		'maros_bedrooms'   => 'integer',
		'maros_bathrooms'  => 'integer',
		'maros_beds'       => 'integer',
		'maros_area'       => 'string',
		'maros_lat'        => 'number',
		'maros_lng'        => 'number',
		'maros_check_in'   => 'string',
		'maros_check_out'  => 'string',
		'maros_deposit'    => 'number',
		'maros_cleaning'   => 'number',
		'maros_gallery'    => 'string', // Ids de adjuntos separados por comas.
		'maros_currency'   => 'string',
		'maros_price_from' => 'number', // Precio «desde» por noche que da marOS; nunca se calcula aquí.
		'maros_room_types' => 'string', // JSON: [{id, name, max_guests, units}].
		'maros_synced_at'  => 'string',
		'maros_calendar_etag'      => 'string',
		'maros_calendar_synced_at' => 'string',
		'maros_withdrawn'  => 'integer', // 1 si ya no es publicable en marOS.
	);
}

add_action( 'init', 'maros_register_types', 5 );

function maros_register_types(): void {
	register_post_type(
		'mis_property',
		array(
			'labels'       => array(
				'name'          => __( 'Properties', 'maros-connector' ),
				'singular_name' => __( 'Property', 'maros-connector' ),
			),
			'public'       => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-admin-home',
			'menu_position'=> 20,
			'has_archive'  => false,
			// /stay/<slug>/ (Julio, 13/11): base genérica sin tipología, igual que la página Alojamiento.
			'rewrite'      => array( 'slug' => 'stay', 'with_front' => false ),
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
			'taxonomies'   => array( 'zona', 'tipo-propiedad', 'equipamiento', 'coleccion' ),
		)
	);

	$taxonomies = array(
		'tipo-propiedad' => array( __( 'Property types', 'maros-connector' ), __( 'Property type', 'maros-connector' ), 'stay/type' ),
		'equipamiento'   => array( __( 'Amenities', 'maros-connector' ), __( 'Amenity', 'maros-connector' ), 'stay/amenity' ),
		'coleccion'      => array( __( 'Collections', 'maros-connector' ), __( 'Collection', 'maros-connector' ), 'stay/collection' ),
	);
	foreach ( $taxonomies as $tax => $labels ) {
		register_taxonomy(
			$tax,
			array( 'mis_property' ),
			array(
				'labels'            => array( 'name' => $labels[0], 'singular_name' => $labels[1] ),
				'public'            => true,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => 'equipamiento' !== $tax,
				'rewrite'           => array( 'slug' => $labels[2], 'with_front' => false ),
			)
		);
	}

	foreach ( maros_property_fields() as $key => $type ) {
		register_post_meta(
			'mis_property',
			$key,
			array(
				'type'          => $type,
				'single'        => true,
				'show_in_rest'  => true,
				// Solo la sincronización escribe estos campos.
				'auth_callback' => '__return_false',
			)
		);
	}
}

add_filter(
	'pll_get_post_types',
	static fn( array $types ): array => $types + array( 'mis_property' => 'mis_property' )
);
add_filter(
	'pll_get_taxonomies',
	static fn( array $tax ): array => $tax + array( 'tipo-propiedad' => 'tipo-propiedad', 'equipamiento' => 'equipamiento', 'coleccion' => 'coleccion' )
);
// Los campos operativos son iguales en todos los idiomas (spec 003).
add_filter(
	'pll_copy_post_metas',
	static fn( array $metas ): array => array_merge( $metas, array_keys( maros_property_fields() ) )
);

register_activation_hook(
	__FILE__,
	static function () {
		maros_register_types();
		Maros_Calendar::install();
		Maros_Scheduler::ensure_scheduled();
		flush_rewrite_rules();
	}
);

register_deactivation_hook( __FILE__, array( 'Maros_Scheduler', 'unschedule' ) );
