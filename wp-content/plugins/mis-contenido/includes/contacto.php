<?php
/**
 * Datos de contacto de la web (Ajustes → Datos de contacto): teléfono, WhatsApp, email, persona de
 * contacto, horario y redes. Los usan el pie y la capa «Menú». Lo que esté vacío no se muestra,
 * así no salen datos de ejemplo («[Nombre]», «+34 600 000 000», «hello@example.com») en la web.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

/** Campos: clave => etiqueta en el panel. */
function mis_contact_fields(): array {
	return array(
		'person'    => 'Persona de contacto (nombre)',
		'phone'     => 'Teléfono (con prefijo, p. ej. +34 952 000 000)',
		'whatsapp'  => 'WhatsApp (con prefijo; vacío = el teléfono)',
		'email'     => 'Email',
		'hours_en'  => 'Horario (inglés), p. ej. Mon–Sat, 9:00–20:00',
		'hours_es'  => 'Horario (español), p. ej. Lun–sáb, 9:00–20:00',
		'instagram' => 'Instagram (URL del perfil)',
		'tiktok'    => 'TikTok (URL del perfil)',
		'youtube'   => 'YouTube (URL del canal)',
		'facebook'  => 'Facebook (URL de la página)',
	);
}

/**
 * Personas que dan la cara en la web (banners con foto): huéspedes y propietarios. Según la guía
 * editorial de marOS.content, Rebecca firma lo de huéspedes y Julio lo de propietarios.
 */
function mis_people(): array {
	return array(
		'guests' => 'Huéspedes, zonas, servicios y experiencias',
		'owners' => 'Propietarios',
	);
}

/** Campos de cada persona: sufijo => etiqueta. La foto es un id de Medios. */
function mis_person_fields(): array {
	return array(
		'name'    => 'Nombre',
		'role_en' => 'Cargo (inglés)',
		'role_es' => 'Cargo (español)',
		'photo'   => 'Foto',
	);
}

/** Un dato de contacto, o '' si no está. */
function mis_contact( string $key ): string {
	$all = get_option( 'mis_contact', array() );
	$val = is_array( $all ) ? trim( (string) ( $all[ $key ] ?? '' ) ) : '';
	if ( '' === $val && 'whatsapp' === $key ) {
		return mis_contact( 'phone' );
	}
	return $val;
}

/** Solo dígitos y «+», para tel: y wa.me. */
function mis_contact_digits( string $phone ): string {
	return preg_replace( '/[^\d+]/', '', $phone );
}

add_action(
	'admin_init',
	static function () {
		register_setting(
			'mis_contact',
			'mis_contact',
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => static function ( $in ): array {
					$out = array();
					foreach ( array_keys( mis_people() ) as $who ) {
						foreach ( array_keys( mis_person_fields() ) as $f ) {
							$v                     = trim( (string) ( is_array( $in ) ? ( $in[ "{$who}_{$f}" ] ?? '' ) : '' ) );
							$out[ "{$who}_{$f}" ] = 'photo' === $f ? (string) ( absint( $v ) ?: '' ) : sanitize_text_field( $v );
						}
					}
					foreach ( array_keys( mis_contact_fields() ) as $k ) {
						$v = trim( (string) ( is_array( $in ) ? ( $in[ $k ] ?? '' ) : '' ) );
						if ( in_array( $k, array( 'instagram', 'tiktok', 'youtube', 'facebook' ), true ) ) {
							$v = esc_url_raw( $v );
						} elseif ( 'email' === $k ) {
							$v = sanitize_email( $v );
						} else {
							$v = sanitize_text_field( $v );
						}
						$out[ $k ] = $v;
					}
					return $out;
				},
			)
		);
	}
);

add_action(
	'admin_enqueue_scripts',
	static function ( string $hook ) {
		if ( 'settings_page_mis-contacto' === $hook ) {
			wp_enqueue_media();
		}
	}
);

add_action(
	'admin_menu',
	static function () {
		add_options_page(
			'Datos de contacto',
			'Datos de contacto',
			'manage_options',
			'mis-contacto',
			static function () {
				echo '<div class="wrap"><h1>Datos de contacto</h1>';
				echo '<p>Se muestran en el pie de la web y en la capa «Menú». Lo que dejes vacío no aparece.</p>';
				echo '<form method="post" action="options.php">';
				settings_fields( 'mis_contact' );
				echo '<table class="form-table" role="presentation">';
				$saved = get_option( 'mis_contact', array() );
				$saved = is_array( $saved ) ? $saved : array();
				foreach ( mis_contact_fields() as $k => $label ) {
					printf(
						'<tr><th scope="row"><label for="mis-contact-%1$s">%2$s</label></th><td><input type="text" class="regular-text" id="mis-contact-%1$s" name="mis_contact[%1$s]" value="%3$s"></td></tr>',
						esc_attr( $k ),
						esc_html( $label ),
						esc_attr( (string) ( $saved[ $k ] ?? '' ) )
					);
				}
				echo '</table>';
				foreach ( mis_people() as $who => $title ) {
					echo '<h2>' . esc_html( 'Persona de contacto · ' . $title ) . '</h2><table class="form-table" role="presentation">';
					foreach ( mis_person_fields() as $f => $label ) {
						$k   = "{$who}_{$f}";
						$val = (string) ( $saved[ $k ] ?? '' );
						if ( 'photo' === $f ) {
							$img = $val ? wp_get_attachment_image( (int) $val, 'thumbnail', false, array( 'style' => 'border-radius:50%;width:96px;height:96px;object-fit:cover;display:block' ) ) : '';
							printf(
								'<tr><th scope="row">%1$s</th><td><div class="mis-person-img">%2$s</div><input type="hidden" name="mis_contact[%3$s]" value="%4$s"><p><button type="button" class="button mis-person-pick">Elegir foto</button> <button type="button" class="button-link mis-person-del">Quitar</button></p><p class="description">Cuadrada, de cara y con luz natural; 600 × 600 px o más.</p></td></tr>',
								esc_html( $label ),
								$img, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								esc_attr( $k ),
								esc_attr( $val )
							);
						} else {
							printf(
								'<tr><th scope="row"><label for="mis-contact-%1$s">%2$s</label></th><td><input type="text" class="regular-text" id="mis-contact-%1$s" name="mis_contact[%1$s]" value="%3$s"></td></tr>',
								esc_attr( $k ),
								esc_html( $label ),
								esc_attr( $val )
							);
						}
					}
					echo '</table>';
				}
				?>
				<script>
				document.querySelectorAll( '.mis-person-pick' ).forEach( function ( pick ) {
					var td = pick.closest( 'td' ), input = td.querySelector( 'input[type=hidden]' ), prev = td.querySelector( '.mis-person-img' ), frame;
					pick.addEventListener( 'click', function () {
						frame = frame || wp.media( { title: 'Foto', library: { type: 'image' }, multiple: false } );
						frame.off( 'select' ).on( 'select', function () {
							var a = frame.state().get( 'selection' ).first().toJSON(), s = ( a.sizes && ( a.sizes.thumbnail || a.sizes.full ) ) || a;
							input.value = a.id;
							prev.innerHTML = '<img src="' + s.url + '" alt="" style="border-radius:50%;width:96px;height:96px;object-fit:cover;display:block">';
						} );
						frame.open();
					} );
					td.querySelector( '.mis-person-del' ).addEventListener( 'click', function () { input.value = ''; prev.innerHTML = ''; } );
				} );
				</script>
				<?php
				submit_button();
				echo '</form></div>';
			}
		);
	}
);

/**
 * [mis_contact_line] Dirección, teléfono y email del pie, solo con lo que esté rellenado.
 */
add_shortcode(
	'mis_contact_line',
	static function (): string {
		$es    = function_exists( 'pll_current_language' ) && 'es' === pll_current_language();
		$line  = array();
		$phone = mis_contact( 'phone' );
		$email = mis_contact( 'email' );
		if ( '' !== $phone ) {
			$line[] = '<a href="tel:' . esc_attr( mis_contact_digits( $phone ) ) . '">' . esc_html( $phone ) . '</a>';
		}
		if ( '' !== $email ) {
			$line[] = '<a href="mailto:' . esc_attr( antispambot( $email ) ) . '">' . esc_html( antispambot( $email ) ) . '</a>';
		}
		$html = esc_html( $es ? 'Marbella, Málaga · España' : 'Marbella, Málaga · Spain' );
		return '<p class="mis-muted">' . $html . ( $line ? '<br>' . implode( ' · ', $line ) : '' ) . '</p>';
	}
);

/**
 * [mis_social] Iconos de las redes que tengan URL (bloque «Iconos sociales» del núcleo).
 * [mis_social style="text"] las mismas redes como enlaces de texto («Instagram · TikTok»).
 */
add_shortcode(
	'mis_social',
	static function ( $atts ): string {
		$atts     = shortcode_atts( array( 'style' => 'icons' ), $atts );
		$services = array(
			'instagram' => 'Instagram',
			'tiktok'    => 'TikTok',
			'youtube'   => 'YouTube',
			'facebook'  => 'Facebook',
		);
		$set = array();
		foreach ( $services as $k => $label ) {
			$url = mis_contact( $k );
			if ( '' !== $url ) {
				$set[ $k ] = array( $url, $label );
			}
		}
		if ( ! $set ) {
			return '';
		}
		if ( 'text' === $atts['style'] ) {
			$links = array();
			foreach ( $set as $s ) {
				$links[] = '<a href="' . esc_url( $s[0] ) . '" rel="noopener" target="_blank">' . esc_html( $s[1] ) . '</a>';
			}
			return '<p>' . implode( ' · ', $links ) . '</p>';
		}
		$inner = '';
		foreach ( $set as $k => $s ) {
			$inner .= '<!-- wp:social-link ' . wp_json_encode( array( 'url' => $s[0], 'service' => $k ) ) . ' /-->';
		}
		return do_blocks(
			'<!-- wp:social-links {"className":"is-style-logos-only","iconColor":"border-light","iconColorValue":"#DDDCDC","openInNewTab":true} -->'
			. '<ul class="wp-block-social-links has-icon-color is-style-logos-only">' . $inner . '</ul>'
			. '<!-- /wp:social-links -->'
		);
	}
);

/**
 * [mis_contact_card] Tarjeta «Habla con nosotros» de la capa «Menú»: persona, horario, WhatsApp,
 * llamar y redes, cada cosa solo si está rellenada.
 */
add_shortcode(
	'mis_contact_card',
	static function (): string {
		$es     = function_exists( 'pll_current_language' ) && 'es' === pll_current_language();
		$person = mis_contact( 'person' );
		$hours  = mis_contact( $es ? 'hours_es' : 'hours_en' );
		$phone  = mis_contact( 'phone' );
		$wa     = mis_contact( 'whatsapp' );
		$html   = '';
		if ( '' !== $person ) {
			$html .= '<p><strong>' . esc_html( $person ) . '</strong> · ' . esc_html( $es ? 'tu contacto en Marbella' : 'your contact in Marbella' ) . '</p>';
		}
		if ( '' !== $hours ) {
			$html .= '<p class="mis-muted">' . esc_html( $hours ) . '</p>';
		}
		$actions = '';
		if ( '' !== $wa ) {
			$actions .= '<a href="https://wa.me/' . esc_attr( ltrim( mis_contact_digits( $wa ), '+' ) ) . '" rel="noopener" target="_blank">WhatsApp</a>';
		}
		if ( '' !== $phone ) {
			$actions .= '<a href="tel:' . esc_attr( mis_contact_digits( $phone ) ) . '">' . esc_html( $es ? 'Llamar' : 'Call' ) . '</a>';
		}
		if ( '' !== $actions ) {
			$html .= '<p class="mis-contact-actions">' . $actions . '</p>';
		}
		$html .= do_shortcode( '[mis_social style="text"]' );
		if ( '' === $html ) {
			return '';
		}
		return '<div class="mis-contact-card"><p class="mis-eyebrow">' . esc_html( $es ? 'Habla con nosotros' : 'Talk to us' ) . '</p>' . $html . '</div>';
	}
);

/**
 * Formulario de contacto: si llega ?tour=… (botón «Book this experience»), el mensaje se rellena
 * con la experiencia. No hace falta cambiar los formularios de Contact Form 7.
 */
add_action(
	'wp_footer',
	static function () {
		$tour = isset( $_GET['tour'] ) ? sanitize_text_field( wp_unslash( $_GET['tour'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' === $tour ) {
			return;
		}
		$es   = function_exists( 'pll_current_language' ) && 'es' === pll_current_language();
		$text = sprintf( $es ? "Hola, me interesa la experiencia «%s».\nFechas:\nPersonas:" : "Hi, I'm interested in the experience “%s”.\nDates:\nGuests:", $tour );
		printf(
			'<script>(function(){var t=document.querySelector(".wpcf7 textarea[name=\'your-message\']");if(t&&!t.value){t.value=%s;}})();</script>',
			wp_json_encode( $text )
		);
	}
);


/**
 * [mis_persona para="guests|owners" en="Questions about the area?" es="¿Dudas sobre la zona?" estilo="banner|compacto"]
 * Banner con la foto, el nombre y el cargo de la persona de contacto, y los botones de WhatsApp y
 * contacto. Sin nombre no se muestra; sin foto, sale sin ella.
 */
function mis_person( string $who, string $key ): string {
	$all = get_option( 'mis_contact', array() );
	return is_array( $all ) ? trim( (string) ( $all[ "{$who}_{$key}" ] ?? '' ) ) : '';
}

add_shortcode(
	'mis_persona',
	static function ( $atts ): string {
		$atts = shortcode_atts( array( 'para' => 'guests', 'en' => '', 'es' => '', 'estilo' => 'banner' ), $atts );
		$who  = array_key_exists( $atts['para'], mis_people() ) ? $atts['para'] : 'guests';
		$es   = function_exists( 'pll_current_language' ) && 'es' === pll_current_language();
		$name = mis_person( $who, 'name' );
		if ( '' === $name && 'guests' === $who ) {
			$name = mis_contact( 'person' );
		}
		if ( '' === $name ) {
			return '';
		}
		$role  = mis_person( $who, $es ? 'role_es' : 'role_en' );
		$photo = (int) mis_person( $who, 'photo' );
		$img   = $photo ? wp_get_attachment_image( $photo, 'medium', false, array( 'class' => 'mis-person__photo', 'alt' => $name, 'loading' => 'lazy' ) ) : '';
		$lead  = $es ? $atts['es'] : $atts['en'];
		if ( '' === $lead ) {
			$lead = $es ? '¿Hablamos?' : 'Shall we talk?';
		}
		$first = strtok( $name, ' ' );
		$text  = $es
			? sprintf( '%s y el equipo te ayudan a elegir, sin compromiso.', $first )
			: sprintf( '%s and the team will help you choose, with no commitment.', $first );
		$wa      = mis_contact( 'whatsapp' );
		$contact = function_exists( 'mis_page_url' ) ? mis_page_url( 'contact' ) : home_url( $es ? '/es/contacto/' : '/contact/' );
		$buttons = '<a class="wp-element-button" href="' . esc_url( $contact ) . '">' . esc_html( $es ? 'Escríbenos' : 'Write to us' ) . '</a>';
		if ( '' !== $wa ) {
			$buttons .= '<a class="wp-element-button mis-person__wa" href="https://wa.me/' . esc_attr( ltrim( mis_contact_digits( $wa ), '+' ) ) . '" rel="noopener" target="_blank">WhatsApp</a>';
		}
		return sprintf(
			'<div class="mis-person mis-person--%1$s%2$s">%3$s<div class="mis-person__body"><p class="mis-person__lead">%4$s</p><p class="mis-person__text">%5$s</p><p class="mis-person__who"><strong>%6$s</strong>%7$s</p><div class="mis-person__actions">%8$s</div></div></div>',
			esc_attr( 'compacto' === $atts['estilo'] ? 'compact' : 'banner' ),
			$img ? '' : ' mis-person--sin-foto',
			$img,
			esc_html( $lead ),
			esc_html( $text ),
			esc_html( $name ),
			'' !== $role ? ' · ' . esc_html( $role ) : '',
			$buttons
		);
	}
);
