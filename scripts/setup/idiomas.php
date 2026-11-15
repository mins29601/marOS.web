<?php
/**
 * Ajuste repetible: idiomas con Polylang (D2). Idempotente.
 *
 * Uso: wp eval-file scripts/setup/idiomas.php
 * (en local: npm run wp -- eval-file wp-content/mis-scripts/setup/idiomas.php)
 *
 * Inglés por defecto sin prefijo y español en /es/, como la web actual. Provisional hasta
 * cerrar la estructura de URLs (D6, propuesta /en/ y /es/): cambiar hide_default a 0.
 *
 * @package MarbellaInStyle
 */

if ( ! function_exists( 'PLL' ) ) {
	WP_CLI::error( 'Polylang no está activo.' );
}

$mis_languages = PLL()->model->languages;
$mis_wanted    = array(
	array( 'name' => 'English', 'slug' => 'en', 'locale' => 'en_US', 'rtl' => false, 'term_group' => 0, 'flag' => 'gb' ),
	array( 'name' => 'Español', 'slug' => 'es', 'locale' => 'es_ES', 'rtl' => false, 'term_group' => 1, 'flag' => 'es' ),
);

foreach ( $mis_wanted as $mis_lang ) {
	if ( $mis_languages->get( $mis_lang['slug'] ) ) {
		continue;
	}
	$mis_result = $mis_languages->add( $mis_lang );
	if ( is_wp_error( $mis_result ) ) {
		WP_CLI::error( $mis_result->get_error_message() );
	}
	WP_CLI::log( 'Idioma añadido: ' . $mis_lang['slug'] );
}

$mis_options                 = get_option( 'polylang' );
$mis_options['force_lang']   = 1; // Idioma por carpeta (/es/).
$mis_options['hide_default'] = 1; // Inglés sin prefijo (provisional, D6).
$mis_options['rewrite']      = 1; // Sin /language/ en la URL.
$mis_options['browser']      = 0; // Sin redirección por idioma del navegador (mejor para SEO y caché APO).
$mis_options['redirect_lang'] = 1; // Portada de cada idioma en /es/, no en /es/inicio/.
update_option( 'polylang', $mis_options );

$mis_languages->update_default( 'en' );
$mis_languages->clean_cache();
PLL()->model->set_language_in_mass();
flush_rewrite_rules( true );

WP_CLI::success( 'Idiomas: ' . implode( ', ', wp_list_pluck( $mis_languages->get_list(), 'slug' ) ) );
