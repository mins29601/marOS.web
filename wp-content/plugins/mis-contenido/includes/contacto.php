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
