<?php
/**
 * Contenido inicial de la primera versión: páginas, menús, zonas, tours, servicios y
 * formularios en inglés y español (spec 005, mapa del sitio y menús v2). Idempotente: lo que
 * ya existe no se toca.
 *
 * Uso: wp eval-file scripts/setup/contenido-inicial.php
 * (en local: npm run wp -- eval-file wp-content/mis-scripts/setup/contenido-inicial.php)
 *
 * Los textos son de ejemplo: se sustituyen en la migración de contenido (spec 002).
 *
 * @package MarbellaInStyle
 */

if ( ! function_exists( 'pll_set_post_language' ) || ! class_exists( 'Maros_Sync' ) ) {
	WP_CLI::error( 'Faltan Polylang o marOS Connector.' );
}

// ---------------------------------------------------------------- utilidades

function mis_setup_post( string $type, string $lang, string $slug, string $title, string $content = '', array $extra = array() ): int {
	$found = get_posts(
		array( 'post_type' => $type, 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1, 'lang' => $lang, 'fields' => 'ids' )
	);
	if ( $found ) {
		return (int) $found[0];
	}
	$id = wp_insert_post(
		array_merge(
			array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_name'    => $slug,
				'post_title'   => $title,
				'post_content' => $content,
			),
			$extra
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id->get_error_message() );
	}
	pll_set_post_language( $id, $lang );
	WP_CLI::log( "  + $type $lang /$slug/" );
	return (int) $id;
}

/** Crea el par en/es y lo enlaza como traducción. */
function mis_setup_pair( string $type, array $en, array $es, array $extra_en = array(), array $extra_es = array() ): array {
	$ids = array(
		'en' => mis_setup_post( $type, 'en', $en[0], $en[1], $en[2] ?? '', $extra_en ),
		'es' => mis_setup_post( $type, 'es', $es[0], $es[1], $es[2] ?? '', $extra_es ),
	);
	pll_save_post_translations( $ids );
	return $ids;
}

function mis_p( string $text, string $class = '' ): string {
	$attr = $class ? sprintf( ' {"className":"%s"}', $class ) : '';
	$cls  = $class ? sprintf( ' class="%s"', $class ) : '';
	return "<!-- wp:paragraph$attr --><p$cls>$text</p><!-- /wp:paragraph -->\n";
}

function mis_h( string $text, int $level = 2, string $size = 'large' ): string {
	return sprintf( '<!-- wp:heading {"level":%1$d,"fontSize":"%3$s"} --><h%1$d class="wp-block-heading has-%3$s-font-size">%2$s</h%1$d><!-- /wp:heading -->' . "\n", $level, $text, $size );
}

/** Sección a lo ancho con relleno vertical. */
function mis_section( string $inner, string $bg = '' ): string {
	$bg_attr  = $bg ? sprintf( ',"backgroundColor":"%s"', $bg ) : '';
	$bg_class = $bg ? sprintf( ' has-%s-background-color has-background', $bg ) : '';
	return '<!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1280px"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|x-large","bottom":"var:preset|spacing|x-large","left":"var:preset|spacing|medium","right":"var:preset|spacing|medium"}}}' . $bg_attr . '} -->'
		. '<div class="wp-block-group alignfull' . $bg_class . '" style="padding-top:var(--wp--preset--spacing--x-large);padding-right:var(--wp--preset--spacing--medium);padding-bottom:var(--wp--preset--spacing--x-large);padding-left:var(--wp--preset--spacing--medium)">' . "\n"
		. $inner
		. "</div><!-- /wp:group -->\n";
}

function mis_intro( string $eyebrow, string $title, string $text = '', int $level = 2 ): string {
	return mis_p( $eyebrow, 'mis-eyebrow' ) . mis_h( $title, $level, 1 === $level ? 'x-large' : 'large' ) . ( $text ? mis_p( $text, 'mis-muted' ) : '' );
}

function mis_pattern( string $slug ): string {
	return '<!-- wp:pattern {"slug":"marbellainstyle/' . $slug . '"} /-->' . "\n";
}

function mis_button( string $label, string $url ): string {
	return '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '">' . $label . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->' . "\n";
}

function mis_page_hero( string $eyebrow, string $title, string $text ): string {
	return mis_section( mis_intro( $eyebrow, $title, $text, 1 ) );
}

// ---------------------------------------------------------------- taxonomías

WP_CLI::log( 'Zonas y tipos…' );
$zones = array(
	'nueva-andalucia' => array( 'Nueva Andalucía', 'Nueva Andalucía', 'The golf valley: quiet villas a few minutes from Puerto Banús.', 'El valle del golf: villas tranquilas a pocos minutos de Puerto Banús.' ),
	'sierra-blanca'   => array( 'Sierra Blanca', 'Sierra Blanca', 'Gated hillside living with sea views above the Golden Mile.', 'Urbanización cerrada en la ladera, con vistas al mar sobre la Milla de Oro.' ),
	'puerto-banus'    => array( 'Puerto Banús', 'Puerto Banús', 'The marina, the beach clubs and everything within walking distance.', 'El puerto, los beach clubs y todo a un paseo.' ),
	'golden-mile'     => array( 'Golden Mile', 'Milla de Oro', 'Between Marbella old town and Puerto Banús, by the sea.', 'Entre el casco antiguo y Puerto Banús, junto al mar.' ),
);
foreach ( $zones as $slug => $z ) {
	$en = Maros_Sync::term( 'zona', $slug, array( $z[0], $z[1] ), 'en' );
	$es = Maros_Sync::term( 'zona', $slug, array( $z[0], $z[1] ), 'es' );
	wp_update_term( $en, 'zona', array( 'description' => $z[2] ) );
	wp_update_term( $es, 'zona', array( 'description' => $z[3] ) );
}

$service_types = array(
	'transport'       => array( 'Transport', 'Transporte' ),
	'chef-catering'   => array( 'Chef & catering', 'Chef y catering' ),
	'events-weddings' => array( 'Events & weddings', 'Eventos y bodas' ),
	'concierge'       => array( 'Concierge', 'Conserjería' ),
);
foreach ( $service_types as $slug => $names ) {
	Maros_Sync::term( 'tipo-servicio', $slug, $names, 'en' );
}

// ---------------------------------------------------------------- tours y servicios

WP_CLI::log( 'Experiencias y servicios…' );
$tours = array(
	array( 'granada-private-tour', 'Granada & the Alhambra, private tour', 'tour-privado-granada', 'Granada y la Alhambra, tour privado', 'Full day (11 h)', 'Día completo (11 h)', 'sierra-blanca', 490 ),
	array( 'ronda-wine-tour', 'Ronda wine tour', 'ruta-vino-ronda', 'Ruta del vino en Ronda', 'Full day (9 h)', 'Día completo (9 h)', 'nueva-andalucia', 0 ),
);
foreach ( $tours as $t ) {
	$ids = mis_setup_pair(
		'mis_tour',
		array( $t[0], $t[1], mis_p( 'A day with a local guide, private transport from your villa and time to enjoy it at your own pace.' ) ),
		array( $t[2], $t[3], mis_p( 'Un día con guía local, transporte privado desde tu villa y tiempo para disfrutarlo a tu ritmo.' ) ),
		array( 'post_excerpt' => 'Private, with pick-up at your door.' ),
		array( 'post_excerpt' => 'Privado, con recogida en la puerta.' )
	);
	foreach ( $ids as $lang => $id ) {
		update_post_meta( $id, 'tour_duration', 'en' === $lang ? $t[4] : $t[5] );
		update_post_meta( $id, 'tour_private', true );
		update_post_meta( $id, 'tour_languages', 'en' === $lang ? 'English, Spanish' : 'Inglés, español' );
		update_post_meta( $id, 'tour_pickup', 'en' === $lang ? 'Pick-up at your accommodation' : 'Recogida en tu alojamiento' );
		update_post_meta( $id, 'tour_price_from', $t[7] );
		update_post_meta( $id, 'tour_booking_mode', $t[7] ? 'fareharbor' : 'formulario' );
		wp_set_object_terms( $id, array( Maros_Sync::term( 'zona', $t[6], $zones[ $t[6] ], $lang ) ), 'zona' );
	}
}

$services = array(
	array( 'transport', 'private-driver', 'Private driver', 'chofer-privado', 'Chófer privado', 'Airport transfers and a driver at your disposal.', 'Traslados al aeropuerto y chófer a tu disposición.', 'From €90' , 'Desde 90 €' ),
	array( 'chef-catering', 'private-chef', 'Private chef', 'chef-privado', 'Chef privado', 'Breakfasts, dinners and BBQs cooked at your villa.', 'Desayunos, cenas y barbacoas en tu villa.', 'On request', 'A consultar' ),
	array( 'events-weddings', 'events-weddings', 'Events & weddings', 'eventos-bodas', 'Eventos y bodas', 'Celebrations planned end to end by our team.', 'Celebraciones organizadas de principio a fin.', 'On request', 'A consultar' ),
	array( 'concierge', 'concierge', 'Concierge', 'conserjeria', 'Conserjería', 'Restaurants, boats, beach clubs and anything you need.', 'Restaurantes, barcos, beach clubs y lo que necesites.', 'Included for our guests', 'Incluida para nuestros huéspedes' ),
);
foreach ( $services as $s ) {
	$ids = mis_setup_pair(
		'mis_service',
		array( $s[1], $s[2], mis_p( $s[5] . ' Also available if you are not staying with us.' ) ),
		array( $s[3], $s[4], mis_p( $s[6] . ' También si no te alojas con nosotros.' ) ),
		array( 'post_excerpt' => $s[5] ),
		array( 'post_excerpt' => $s[6] )
	);
	foreach ( $ids as $lang => $id ) {
		update_post_meta( $id, 'service_summary', 'en' === $lang ? $s[5] : $s[6] );
		update_post_meta( $id, 'service_price_from', 'en' === $lang ? $s[7] : $s[8] );
		wp_set_object_terms( $id, array( Maros_Sync::term( 'tipo-servicio', $s[0], $service_types[ $s[0] ], $lang ) ), 'tipo-servicio' );
	}
}

// Fotos de ejemplo para tours y servicios: las de las propiedades.
$photo_pool = array();
foreach ( get_posts( array( 'post_type' => 'mis_property', 'lang' => 'en', 'numberposts' => -1, 'fields' => 'ids' ) ) as $pid ) {
	$photo_pool = array_merge( $photo_pool, array_filter( explode( ',', (string) get_post_meta( $pid, 'maros_gallery', true ) ) ) );
}
$i = 0;
foreach ( get_posts( array( 'post_type' => array( 'mis_tour', 'mis_service', 'post' ), 'lang' => '', 'numberposts' => -1, 'fields' => 'ids' ) ) as $pid ) {
	if ( $photo_pool && ! has_post_thumbnail( $pid ) ) {
		set_post_thumbnail( $pid, (int) $photo_pool[ ( $i++ * 3 ) % count( $photo_pool ) ] );
	}
}

// ---------------------------------------------------------------- formularios (CF7, D4)

WP_CLI::log( 'Formularios…' );
$forms = array(
	'contact-en' => array( 'Contact (EN)', "<label>Name [text* your-name autocomplete:name]</label>\n<label>Email [email* your-email autocomplete:email]</label>\n<label>Phone [tel your-phone]</label>\n<label>How can we help? [textarea* your-message]</label>\n[acceptance privacy] I accept the privacy policy [/acceptance]\n[submit \"Send\"]", 'New enquiry from the website' ),
	'contact-es' => array( 'Contacto (ES)', "<label>Nombre [text* your-name autocomplete:name]</label>\n<label>Email [email* your-email autocomplete:email]</label>\n<label>Teléfono [tel your-phone]</label>\n<label>¿En qué podemos ayudarte? [textarea* your-message]</label>\n[acceptance privacy] Acepto la política de privacidad [/acceptance]\n[submit \"Enviar\"]", 'Nueva consulta desde la web' ),
	'request-en' => array( 'Service request (EN)', "<label>Name [text* your-name autocomplete:name]</label>\n<label>Email [email* your-email autocomplete:email]</label>\n<label>Phone [tel your-phone]</label>\n<label>Date [date your-date]</label>\n<label>Details [textarea your-message]</label>\n[acceptance privacy] I accept the privacy policy [/acceptance]\n[submit \"Request\"]", 'Service request: [_post_title]' ),
	'request-es' => array( 'Solicitud de servicio (ES)', "<label>Nombre [text* your-name autocomplete:name]</label>\n<label>Email [email* your-email autocomplete:email]</label>\n<label>Teléfono [tel your-phone]</label>\n<label>Fecha [date your-date]</label>\n<label>Detalles [textarea your-message]</label>\n[acceptance privacy] Acepto la política de privacidad [/acceptance]\n[submit \"Solicitar\"]", 'Solicitud de servicio: [_post_title]' ),
);
foreach ( $forms as $slug => $f ) {
	if ( get_posts( array( 'post_type' => 'wpcf7_contact_form', 'name' => $slug, 'numberposts' => 1, 'lang' => '' ) ) ) {
		continue;
	}
	$form = WPCF7_ContactForm::get_template( array( 'title' => $f[0] ) );
	$form->set_properties(
		array(
			'form' => $f[1],
			'mail' => array_merge(
				$form->prop( 'mail' ),
				array(
					'subject' => $f[2],
					'body'    => "[your-name] <[your-email]> [your-phone]\n[_post_title] [_url]\n\n[your-message]",
				)
			),
		)
	);
	$id = $form->save();
	wp_update_post( array( 'ID' => $id, 'post_name' => $slug ) );
	WP_CLI::log( "  + formulario $slug" );
}

// ---------------------------------------------------------------- páginas

WP_CLI::log( 'Páginas…' );
$no_title = array( 'page_template' => 'page-no-title' );

$home_en = mis_section(
	mis_intro( 'Marbella · Villas, services & experiences', 'Your home in Marbella, cared for by people who live here', 'Hand-picked villas and apartments we manage ourselves, plus the chefs, drivers and experiences that make the stay.', 1 )
	. mis_button( 'Find your stay', '/stay/' ),
	'tertiary'
) . mis_section(
	'<!-- wp:columns {"className":"mis-pillars"} --><div class="wp-block-columns mis-pillars">'
	. '<!-- wp:column --><div class="wp-block-column">' . mis_h( 'Stay', 3, 'medium' ) . mis_p( 'Villas and apartments managed by our own team.', 'mis-muted' ) . mis_p( '<a href="/stay/">See the homes →</a>' ) . '</div><!-- /wp:column -->'
	. '<!-- wp:column --><div class="wp-block-column">' . mis_h( 'Concierge', 3, 'medium' ) . mis_p( 'Chefs, drivers, events. Also if you are not staying with us.', 'mis-muted' ) . mis_p( '<a href="/services/">See the services →</a>' ) . '</div><!-- /wp:column -->'
	. '<!-- wp:column --><div class="wp-block-column">' . mis_h( 'Experiences', 3, 'medium' ) . mis_p( 'Private days out in Andalusia with local guides.', 'mis-muted' ) . mis_p( '<a href="/experiences/">See the experiences →</a>' ) . '</div><!-- /wp:column -->'
	. '</div><!-- /wp:columns -->'
) . mis_section( mis_intro( 'Exclusive homes', 'Managed by us, known by us', 'We look after every home ourselves: we know the house, the neighbourhood and who to call.' ) . mis_pattern( 'property-grid' ) )
  . mis_section( mis_intro( 'Concierge', 'Everything around your stay' ) . mis_pattern( 'service-grid' ), 'tertiary' )
  . mis_section( mis_intro( 'Experiences', 'Andalusia, privately' ) . mis_pattern( 'tour-grid' ) )
  . mis_section( mis_intro( 'Areas', 'Where to stay in Marbella' ) . '<!-- wp:categories {"taxonomy":"zona","className":"mis-terms-list","showEmpty":true} /-->' . "\n", 'tertiary' )
  . mis_section( mis_intro( 'Talk to us', 'Prefer to plan it with a person?', 'Write to us or call: one person from our team will help you from the first message to check-out.' ) . mis_button( 'Contact us', '/contact/' ) );

$home_es = mis_section(
	mis_intro( 'Marbella · Villas, servicios y experiencias', 'Tu casa en Marbella, cuidada por quienes viven aquí', 'Villas y apartamentos seleccionados que gestionamos nosotros, y los chefs, chóferes y experiencias que hacen la estancia.', 1 )
	. mis_button( 'Busca tu alojamiento', '/es/alojamiento/' ),
	'tertiary'
) . mis_section(
	'<!-- wp:columns {"className":"mis-pillars"} --><div class="wp-block-columns mis-pillars">'
	. '<!-- wp:column --><div class="wp-block-column">' . mis_h( 'Alojamiento', 3, 'medium' ) . mis_p( 'Villas y apartamentos gestionados por nuestro equipo.', 'mis-muted' ) . mis_p( '<a href="/es/alojamiento/">Ver las casas →</a>' ) . '</div><!-- /wp:column -->'
	. '<!-- wp:column --><div class="wp-block-column">' . mis_h( 'Servicios', 3, 'medium' ) . mis_p( 'Chefs, chóferes, eventos. También si no te alojas con nosotros.', 'mis-muted' ) . mis_p( '<a href="/es/servicios/">Ver los servicios →</a>' ) . '</div><!-- /wp:column -->'
	. '<!-- wp:column --><div class="wp-block-column">' . mis_h( 'Experiencias', 3, 'medium' ) . mis_p( 'Días privados por Andalucía con guías locales.', 'mis-muted' ) . mis_p( '<a href="/es/experiencias/">Ver las experiencias →</a>' ) . '</div><!-- /wp:column -->'
	. '</div><!-- /wp:columns -->'
) . mis_section( mis_intro( 'Casas exclusivas', 'Gestionadas y conocidas por nosotros', 'Cuidamos cada casa nosotros mismos: conocemos la casa, el barrio y a quién llamar.' ) . mis_pattern( 'property-grid' ) )
  . mis_section( mis_intro( 'Servicios', 'Todo lo que rodea tu estancia' ) . mis_pattern( 'service-grid' ), 'tertiary' )
  . mis_section( mis_intro( 'Experiencias', 'Andalucía, en privado' ) . mis_pattern( 'tour-grid' ) )
  . mis_section( mis_intro( 'Zonas', 'Dónde alojarse en Marbella' ) . '<!-- wp:categories {"taxonomy":"zona","className":"mis-terms-list","showEmpty":true} /-->' . "\n", 'tertiary' )
  . mis_section( mis_intro( 'Habla con nosotros', '¿Prefieres organizarlo con una persona?', 'Escríbenos o llámanos: una persona del equipo te ayudará desde el primer mensaje hasta la salida.' ) . mis_button( 'Contactar', '/es/contacto/' ) );

$pages = array(
	// clave => [ slug en, título en, contenido en, slug es, título es, contenido es, padre ]
	'home'        => array( 'home', 'Home', $home_en, 'inicio', 'Inicio', $home_es ),
	'stay'        => array( 'stay', 'Stay', mis_page_hero( 'Stay', 'Villas & apartments in Marbella', 'Every home is managed by our own team.' ) . mis_section( mis_pattern( 'property-grid' ) ), 'alojamiento', 'Alojamiento', mis_page_hero( 'Alojamiento', 'Villas y apartamentos en Marbella', 'Todas las casas las gestiona nuestro equipo.' ) . mis_section( mis_pattern( 'property-grid' ) ) ),
	'services'    => array( 'services', 'Concierge', mis_page_hero( 'Concierge', 'Services in Marbella', 'Transport, chefs, events and concierge. Also if you are not staying with us.' ) . mis_section( mis_pattern( 'service-grid' ) ), 'servicios', 'Servicios', mis_page_hero( 'Servicios', 'Servicios en Marbella', 'Transporte, chefs, eventos y conserjería. También si no te alojas con nosotros.' ) . mis_section( mis_pattern( 'service-grid' ) ) ),
	'experiences' => array( 'experiences', 'Experiences', mis_page_hero( 'Experiences', 'Private experiences in Andalusia', 'Book online in a few clicks.' ) . mis_section( mis_pattern( 'tour-grid' ) ), 'experiencias', 'Experiencias', mis_page_hero( 'Experiencias', 'Experiencias privadas en Andalucía', 'Reserva online en unos clics.' ) . mis_section( mis_pattern( 'tour-grid' ) ) ),
	'areas'       => array( 'areas', 'Areas', mis_page_hero( 'Areas', 'Where to stay in Marbella', 'Our guide to each area, written by the team.' ) . mis_section( '<!-- wp:categories {"taxonomy":"zona","className":"mis-terms-list","showEmpty":true} /-->' ), 'zonas', 'Zonas', mis_page_hero( 'Zonas', 'Dónde alojarse en Marbella', 'Nuestra guía de cada zona, escrita por el equipo.' ) . mis_section( '<!-- wp:categories {"taxonomy":"zona","className":"mis-terms-list","showEmpty":true} /-->' ) ),
	'owners'      => array( 'owners', 'Owners', mis_page_hero( 'Owners', 'We look after your home as if it were ours', 'Holiday rental management and property management in Marbella.' ) . mis_section( mis_button( 'Talk to us', '/contact/' ) ), 'propietarios', 'Propietarios', mis_page_hero( 'Propietarios', 'Cuidamos tu casa como si fuera nuestra', 'Gestión de alquiler vacacional y de propiedades en Marbella.' ) . mis_section( mis_button( 'Habla con nosotros', '/es/contacto/' ) ) ),
	'journal'     => array( 'journal', 'Journal', '', 'blog', 'Blog', '' ),
	'about'       => array( 'about', 'About us', mis_page_hero( 'About us', 'A local team in Marbella', 'Text pending (content migration, spec 002).' ), 'nosotros', 'Nosotros', mis_page_hero( 'Nosotros', 'Un equipo local en Marbella', 'Texto pendiente (migración de contenido, spec 002).' ) ),
	'reviews'     => array( 'reviews', 'Reviews', mis_page_hero( 'Reviews', 'What our guests say', 'Reviews will come from Site Reviews (D11).' ), 'opiniones', 'Opiniones', mis_page_hero( 'Opiniones', 'Lo que dicen nuestros huéspedes', 'Las opiniones vendrán de Site Reviews (D11).' ) ),
	'careers'     => array( 'careers', 'Work with us', mis_page_hero( 'Careers', 'Work with us', 'Text pending.' ), 'trabaja-con-nosotros', 'Trabaja con nosotros', mis_page_hero( 'Empleo', 'Trabaja con nosotros', 'Texto pendiente.' ) ),
	'contact'     => array( 'contact', 'Contact', mis_page_hero( 'Contact', 'Talk to us', 'One person from our team will answer you, usually within the hour.' ) . mis_section( '<!-- wp:shortcode -->[mis_form name="contact"]<!-- /wp:shortcode -->' ), 'contacto', 'Contacto', mis_page_hero( 'Contacto', 'Habla con nosotros', 'Te responderá una persona del equipo, normalmente en menos de una hora.' ) . mis_section( '<!-- wp:shortcode -->[mis_form name="contact"]<!-- /wp:shortcode -->' ) ),
	'thanks'      => array( 'thank-you', 'Thank you', mis_page_hero( 'Thank you', 'We have received your message', 'We will get back to you very soon.' ), 'gracias', 'Gracias', mis_page_hero( 'Gracias', 'Hemos recibido tu mensaje', 'Te responderemos muy pronto.' ) ),
	'privacy'     => array( 'privacy-policy', 'Privacy policy', mis_section( mis_p( 'Legal text pending.' ) ), 'politica-de-privacidad', 'Política de privacidad', mis_section( mis_p( 'Texto legal pendiente.' ) ) ),
	'legal'       => array( 'legal-notice', 'Legal notice', mis_section( mis_p( 'Legal text pending.' ) ), 'aviso-legal', 'Aviso legal', mis_section( mis_p( 'Texto legal pendiente.' ) ) ),
	'terms'       => array( 'terms-and-conditions', 'Terms and conditions', mis_section( mis_p( 'Legal text pending.' ) ), 'terminos-y-condiciones', 'Términos y condiciones', mis_section( mis_p( 'Texto legal pendiente.' ) ) ),
	'cookies'     => array( 'cookie-policy', 'Cookie policy', mis_section( mis_p( 'Managed with Complianz.' ) ), 'politica-de-cookies', 'Política de cookies', mis_section( mis_p( 'Se gestiona con Complianz.' ) ) ),
);
$with_hero = array( 'home', 'stay', 'services', 'experiences', 'areas', 'owners', 'about', 'reviews', 'careers', 'contact', 'thanks' );

$page_ids = array();
foreach ( $pages as $key => $p ) {
	$meta             = in_array( $key, $with_hero, true ) ? $no_title : array();
	$page_ids[ $key ] = mis_setup_pair( 'page', array( $p[0], $p[1], $p[2] ), array( $p[3], $p[4], $p[5] ), $meta, $meta );
}

$owner_children = array(
	array( 'holiday-rental-management', 'Holiday rental management', 'gestion-alquiler-vacacional', 'Gestión de alquiler vacacional' ),
	array( 'property-management', 'Property management', 'gestion-de-propiedades', 'Gestión de propiedades' ),
	array( 'owner-resources', 'Owner resources', 'recursos-propietarios', 'Recursos para propietarios' ),
);
foreach ( $owner_children as $c ) {
	$page_ids[ $c[0] ] = mis_setup_pair(
		'page',
		array( $c[0], $c[1], mis_section( mis_p( 'Text pending.' ) ) ),
		array( $c[2], $c[3], mis_section( mis_p( 'Texto pendiente.' ) ) ),
		array( 'post_parent' => $page_ids['owners']['en'] ),
		array( 'post_parent' => $page_ids['owners']['es'] )
	);
}

mis_setup_pair(
	'post',
	array( 'marbella-guide-first-day', 'Your first day in Marbella', mis_p( 'A sample journal post: breakfast in the old town, a walk on the promenade and sunset in Puerto Banús.' ) ),
	array( 'guia-marbella-primer-dia', 'Tu primer día en Marbella', mis_p( 'Entrada de ejemplo: desayuno en el casco antiguo, paseo marítimo y atardecer en Puerto Banús.' ) )
);

// Portada y blog: Polylang usa la traducción de cada página.
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $page_ids['home']['en'] );
update_option( 'page_for_posts', $page_ids['journal']['en'] );
update_option( 'blogname', 'Marbella in Style' );
update_option( 'blogdescription', 'Villas, services & experiences in Marbella' );

// ---------------------------------------------------------------- menús por idioma

WP_CLI::log( 'Menús…' );

function mis_setup_menu( string $name, array $items ): int {
	$menu = wp_get_nav_menu_object( $name );
	if ( $menu ) {
		return (int) $menu->term_id;
	}
	$menu_id = wp_create_nav_menu( $name );
	foreach ( $items as $item ) {
		$parent = mis_setup_menu_item( $menu_id, $item, 0 );
		foreach ( $item['children'] ?? array() as $child ) {
			mis_setup_menu_item( $menu_id, $child, $parent );
		}
	}
	WP_CLI::log( "  + menú $name" );
	return (int) $menu_id;
}

function mis_setup_menu_item( int $menu_id, array $item, int $parent ): int {
	$args = array( 'menu-item-status' => 'publish', 'menu-item-parent-id' => $parent, 'menu-item-title' => $item['title'] ?? '' );
	if ( isset( $item['page'] ) ) {
		$args += array( 'menu-item-object' => 'page', 'menu-item-object-id' => $item['page'], 'menu-item-type' => 'post_type' );
	} elseif ( isset( $item['post'] ) ) {
		$args += array( 'menu-item-object' => get_post_type( $item['post'] ), 'menu-item-object-id' => $item['post'], 'menu-item-type' => 'post_type' );
	} elseif ( isset( $item['term'] ) ) {
		$args += array( 'menu-item-object' => $item['tax'], 'menu-item-object-id' => $item['term'], 'menu-item-type' => 'taxonomy' );
	} else {
		$args += array( 'menu-item-url' => $item['url'] ?? '#', 'menu-item-type' => 'custom' );
	}
	return (int) wp_update_nav_menu_item( $menu_id, 0, $args );
}

$locations = array();
foreach ( array( 'en', 'es' ) as $lang ) {
	$pg      = static fn( string $k ) => $page_ids[ $k ][ $lang ];
	$es      = 'es' === $lang;
	$props   = get_posts( array( 'post_type' => 'mis_property', 'lang' => $lang, 'numberposts' => 6, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC' ) );
	$servs   = get_posts( array( 'post_type' => 'mis_service', 'lang' => $lang, 'numberposts' => 6, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC' ) );
	$tours_l = get_posts( array( 'post_type' => 'mis_tour', 'lang' => $lang, 'numberposts' => 6, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC' ) );
	$areas   = get_terms( array( 'taxonomy' => 'zona', 'hide_empty' => false, 'lang' => $lang ) );

	$primary = array(
		array( 'page' => $pg( 'stay' ), 'title' => $es ? 'Alojamiento' : 'Stay', 'children' => array_map( static fn( $id ) => array( 'post' => $id ), $props ) ),
		array( 'page' => $pg( 'services' ), 'title' => $es ? 'Servicios' : 'Concierge', 'children' => array_map( static fn( $id ) => array( 'post' => $id ), $servs ) ),
		array( 'page' => $pg( 'experiences' ), 'title' => $es ? 'Experiencias' : 'Experiences', 'children' => array_map( static fn( $id ) => array( 'post' => $id ), $tours_l ) ),
		array( 'page' => $pg( 'areas' ), 'title' => $es ? 'Zonas' : 'Areas', 'children' => array_map( static fn( $t ) => array( 'term' => $t->term_id, 'tax' => 'zona' ), $areas ) ),
	);
	$overlay = array(
		array( 'page' => $pg( 'owners' ), 'title' => $es ? 'Propietarios' : 'Owners' ),
		array( 'page' => $pg( 'journal' ), 'title' => $es ? 'Blog' : 'Journal' ),
		array( 'page' => $pg( 'about' ), 'title' => $es ? 'Nosotros' : 'About us' ),
		array( 'page' => $pg( 'contact' ), 'title' => $es ? 'Contacto' : 'Contact' ),
	);
	$f_stay = array( array( 'page' => $pg( 'stay' ), 'title' => $es ? 'Alojamiento' : 'Stay', 'children' => array_map( static fn( $t ) => array( 'term' => $t->term_id, 'tax' => 'zona' ), $areas ) ) );
	$f_serv = array(
		array( 'page' => $pg( 'services' ), 'title' => $es ? 'Servicios' : 'Concierge', 'children' => array_map( static fn( $id ) => array( 'post' => $id ), $servs ) ),
		array( 'page' => $pg( 'experiences' ), 'title' => $es ? 'Experiencias' : 'Experiences' ),
	);
	$f_comp = array(
		array(
			'url'      => '#',
			'title'    => $es ? 'Empresa' : 'Company',
			'children' => array(
				array( 'page' => $pg( 'about' ), 'title' => $es ? 'Nosotros' : 'About us' ),
				array( 'page' => $pg( 'owners' ), 'title' => $es ? 'Propietarios' : 'Owners' ),
				array( 'page' => $pg( 'reviews' ), 'title' => $es ? 'Opiniones' : 'Reviews' ),
				array( 'page' => $pg( 'careers' ), 'title' => $es ? 'Trabaja con nosotros' : 'Work with us' ),
				array( 'page' => $pg( 'journal' ), 'title' => $es ? 'Blog' : 'Journal' ),
				array( 'page' => $pg( 'contact' ), 'title' => $es ? 'Contacto' : 'Contact' ),
			),
		),
	);
	$f_legal = array(
		array( 'page' => $pg( 'privacy' ) ),
		array( 'page' => $pg( 'legal' ) ),
		array( 'page' => $pg( 'terms' ) ),
		array( 'page' => $pg( 'cookies' ) ),
	);

	foreach ( array( 'primary' => $primary, 'overlay' => $overlay, 'footer-stay' => $f_stay, 'footer-services' => $f_serv, 'footer-company' => $f_comp, 'footer-legal' => $f_legal ) as $loc => $items ) {
		$locations[ $loc ][ $lang ] = mis_setup_menu( "$loc ($lang)", $items );
	}
}

// Polylang guarda un menú por idioma y ubicación en su opción; la ubicación del tema lleva el inglés.
$options = get_option( 'polylang' );
$theme   = get_stylesheet();
$nav     = array();
foreach ( $locations as $loc => $by_lang ) {
	$options['nav_menus'][ $theme ][ $loc ] = $by_lang;
	$nav[ $loc ]                            = $by_lang['en'];
}
update_option( 'polylang', $options );
set_theme_mod( 'nav_menu_locations', $nav );

flush_rewrite_rules( true );
WP_CLI::success( 'Contenido inicial listo.' );
