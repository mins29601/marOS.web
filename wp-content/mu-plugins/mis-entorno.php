<?php
/**
 * Plugin Name: MIS Entorno
 * Description: Ajustes por entorno. Fuera de producción: noindex, correo saliente bloqueado
 *              y comprobación de que no se apunta a marOS producción.
 * Version:     0.1.0
 *
 * Spec 001 (plan, estructura del repo) y constitución, principio IV «Entornos separados».
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

$mis_env = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';

if ( 'production' === $mis_env ) {
	return;
}

// 1. Ningún buscador indexa local ni pruebas.
add_filter(
	'wp_robots',
	static function ( array $robots ): array {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		return $robots;
	}
);
add_filter( 'pre_option_blog_public', '__return_zero' );

// 2. Correo saliente bloqueado: se anota en el log en vez de enviarse.
add_filter(
	'pre_wp_mail',
	static function ( $return, array $atts ) {
		$to = is_array( $atts['to'] ) ? implode( ', ', $atts['to'] ) : (string) $atts['to'];
		error_log( sprintf( '[mis-entorno] Correo bloqueado → %s | %s', $to, $atts['subject'] ?? '' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		return true;
	},
	10,
	2
);

// 3. Fuera de producción nunca se habla con marOS producción.
add_action(
	'admin_notices',
	static function () {
		$env  = defined( 'MAROS_ENV' ) ? MAROS_ENV : '(sin definir)';
		$base = defined( 'MAROS_API_BASE' ) ? MAROS_API_BASE : '';
		if ( 'production' === $env ) {
			echo '<div class="notice notice-error"><p><strong>Peligro:</strong> este entorno no es producción pero MAROS_ENV=production. Cámbialo en wp-config.php.</p></div>';
			return;
		}
		printf(
			'<div class="notice notice-info"><p>Entorno <strong>%s</strong> · marOS: <strong>%s</strong> %s · correo bloqueado · noindex</p></div>',
			esc_html( wp_get_environment_type() ),
			esc_html( $env ),
			$base ? '(' . esc_html( $base ) . ')' : ''
		);
	}
);
