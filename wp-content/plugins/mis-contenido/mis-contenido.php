<?php
/**
 * Plugin Name:       MIS Contenido
 * Description:       Tipos de contenido de Marbella in Style que se editan en WordPress: tours, servicios y zonas (spec 004).
 * Version:           0.1.0
 * Requires at least: 6.9
 * Requires PHP:      8.3
 * Text Domain:       mis-contenido
 * License:           GPL-2.0-or-later
 *
 * Los tipos viven en un plugin (no en el tema) para sobrevivir a un cambio de diseño (D17).
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

const MIS_CONTENIDO_VERSION = '0.1.0';

/**
 * Campos de los tours (spec 004, data-model). Se registran como metadatos con esquema para que
 * los usen la REST, las Abilities del MCP y las vinculaciones de bloques.
 */
function mis_tour_fields(): array {
	return array(
		'tour_duration'       => array( 'type' => 'string', 'label' => 'Duración' ),
		'tour_start_time'     => array( 'type' => 'string', 'label' => 'Hora de inicio' ),
		'tour_private'        => array( 'type' => 'boolean', 'label' => 'Privado' ),
		'tour_guide'          => array( 'type' => 'string', 'label' => 'Guía' ),
		'tour_languages'      => array( 'type' => 'string', 'label' => 'Idiomas' ),
		'tour_pickup'         => array( 'type' => 'string', 'label' => 'Recogida' ),
		'tour_price_from'     => array( 'type' => 'number', 'label' => 'Precio desde (€)' ),
		'tour_group_size'     => array( 'type' => 'string', 'label' => 'Tamaño de grupo' ),
		'tour_fareharbor_item'=> array( 'type' => 'string', 'label' => 'Id de artículo en FareHarbor' ),
		'tour_booking_mode'   => array( 'type' => 'string', 'label' => 'Reserva: fareharbor | formulario' ),
	);
}

function mis_service_fields(): array {
	return array(
		'service_summary'    => array( 'type' => 'string', 'label' => 'Resumen corto' ),
		'service_price_from' => array( 'type' => 'string', 'label' => 'Precio orientativo' ),
	);
}

add_action( 'init', 'mis_contenido_register', 5 );

function mis_contenido_register(): void {
	// Tours primero: sus reglas (services/tours/…) deben ir antes que las de servicios.
	register_post_type(
		'mis_tour',
		array(
			'labels'       => array(
				'name'          => __( 'Experiences', 'mis-contenido' ),
				'singular_name' => __( 'Experience', 'mis-contenido' ),
				'add_new_item'  => __( 'Add experience', 'mis-contenido' ),
			),
			'public'       => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-palmtree',
			'menu_position'=> 21,
			'has_archive'  => false,
			'rewrite'      => array( 'slug' => 'services/tours', 'with_front' => false ),
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
			'taxonomies'   => array( 'zona', 'tipo-tour' ),
		)
	);

	register_post_type(
		'mis_service',
		array(
			'labels'       => array(
				'name'          => __( 'Services', 'mis-contenido' ),
				'singular_name' => __( 'Service', 'mis-contenido' ),
				'add_new_item'  => __( 'Add service', 'mis-contenido' ),
			),
			'public'       => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-star-filled',
			'menu_position'=> 22,
			'has_archive'  => false,
			'rewrite'      => array( 'slug' => 'services/s', 'with_front' => false ),
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
			'taxonomies'   => array( 'zona', 'tipo-servicio' ),
		)
	);

	register_taxonomy(
		'zona',
		array( 'mis_property', 'mis_tour', 'mis_service', 'post' ),
		array(
			'labels'            => array(
				'name'          => __( 'Areas', 'mis-contenido' ),
				'singular_name' => __( 'Area', 'mis-contenido' ),
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'areas', 'with_front' => false ),
		)
	);

	register_taxonomy(
		'tipo-servicio',
		array( 'mis_service' ),
		array(
			'labels'            => array(
				'name'          => __( 'Service types', 'mis-contenido' ),
				'singular_name' => __( 'Service type', 'mis-contenido' ),
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'services', 'with_front' => false ),
		)
	);

	register_taxonomy(
		'tipo-tour',
		array( 'mis_tour' ),
		array(
			'labels'            => array(
				'name'          => __( 'Experience types', 'mis-contenido' ),
				'singular_name' => __( 'Experience type', 'mis-contenido' ),
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'services/tours/type', 'with_front' => false ),
		)
	);

	foreach ( array( 'mis_tour' => mis_tour_fields(), 'mis_service' => mis_service_fields() ) as $post_type => $fields ) {
		foreach ( $fields as $key => $field ) {
			register_post_meta(
				$post_type,
				$key,
				array(
					'type'          => $field['type'],
					'label'         => $field['label'],
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => static fn() => current_user_can( 'edit_posts' ),
				)
			);
		}
	}
}

// Polylang: tipos y taxonomías traducibles (FR-002).
add_filter(
	'pll_get_post_types',
	static function ( array $types ): array {
		return $types + array( 'mis_tour' => 'mis_tour', 'mis_service' => 'mis_service' );
	}
);
add_filter(
	'pll_get_taxonomies',
	static function ( array $taxonomies ): array {
		return $taxonomies + array( 'zona' => 'zona', 'tipo-servicio' => 'tipo-servicio', 'tipo-tour' => 'tipo-tour' );
	}
);
// Solo los datos que no dependen del idioma se copian a la traducción (los textos, no).
add_filter(
	'pll_copy_post_metas',
	static function ( array $metas ): array {
		return array_merge( $metas, array( 'tour_start_time', 'tour_private', 'tour_price_from', 'tour_fareharbor_item', 'tour_booking_mode' ) );
	}
);

// Vinculación de bloques: «mis/tour» devuelve un dato del tour ya formateado.
add_action(
	'init',
	static function () {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}
		register_block_bindings_source(
			'mis/tour',
			array(
				'label'              => __( 'Experience data', 'mis-contenido' ),
				'get_value_callback' => 'mis_tour_binding_value',
				'uses_context'       => array( 'postId', 'postType' ),
			)
		);
	}
);

function mis_tour_binding_value( array $args, $block_instance ) {
	$post_id = $block_instance->context['postId'] ?? get_the_ID();
	$key     = $args['key'] ?? '';
	$es      = function_exists( 'pll_get_post_language' ) && 'es' === pll_get_post_language( $post_id );

	switch ( $key ) {
		case 'facts':
			$parts = array_filter(
				array(
					get_post_meta( $post_id, 'tour_duration', true ),
					get_post_meta( $post_id, 'tour_private', true ) ? ( $es ? 'Privado' : 'Private' ) : '',
					get_post_meta( $post_id, 'tour_languages', true ),
				)
			);
			return esc_html( implode( ' · ', $parts ) );
		case 'price':
			$price = (float) get_post_meta( $post_id, 'tour_price_from', true );
			if ( ! $price ) {
				return $es ? 'Precio a consultar' : 'Price on request';
			}
			return esc_html( sprintf( $es ? 'Desde %s €' : 'From €%s', number_format_i18n( $price ) ) );
		case 'booking_url':
			$item = get_post_meta( $post_id, 'tour_fareharbor_item', true );
			if ( $item && 'formulario' !== get_post_meta( $post_id, 'tour_booking_mode', true ) ) {
				return esc_url( 'https://fareharbor.com/embeds/book/marbella-in-style/items/' . rawurlencode( $item ) . '/?full-items=yes&flow=no' );
			}
			return esc_url( home_url( $es ? '/es/contacto/' : '/contact/' ) . '?tour=' . rawurlencode( get_the_title( $post_id ) ) );
		default:
			$value = get_post_meta( $post_id, $key, true );
			return is_scalar( $value ) ? esc_html( (string) $value ) : '';
	}
}

// FareHarbor Lightframe: solo se carga en las fichas de tour (spec 004, US2).
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( is_singular( 'mis_tour' ) ) {
			wp_enqueue_script( 'fareharbor-lightframe', 'https://fareharbor.com/embeds/api/v1/?autolightframe=yes', array(), null, array( 'strategy' => 'defer', 'in_footer' => true ) );
		}
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		mis_contenido_register();
		flush_rewrite_rules();
	}
);
