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
	}
);

// Categoría propia para los patrones de marca (en /patterns se registran solos).
add_action(
	'init',
	static function () {
		register_block_pattern_category(
			'marbellainstyle',
			array( 'label' => __( 'Marbella in Style', 'marbellainstyle' ) )
		);
	}
);
