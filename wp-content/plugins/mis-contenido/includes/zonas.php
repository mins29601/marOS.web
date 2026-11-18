<?php
/**
 * Campos de las zonas (Propiedades → Zonas): foto, frase corta, perfiles y «De un vistazo».
 * Los usan el índice de Zonas y la ficha de zona (maqueta «Zonas de Marbella in Style», 18-11-2026).
 * Los textos son por idioma (cada traducción de la zona tiene los suyos); la foto y los perfiles se
 * copian a la traducción.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

/** Perfiles para filtrar zonas (PC-16): clave => [en, es]. */
function mis_zona_perfiles(): array {
	return array(
		'beach'    => array( 'Beach', 'Playa' ),
		'golf'     => array( 'Golf', 'Golf' ),
		'families' => array( 'Families', 'Familias' ),
		'walk'     => array( 'Walk everywhere', 'Todo a pie' ),
		'hills'    => array( 'Hills and views', 'Montaña y vistas' ),
	);
}

/** Campos de texto: clave => etiqueta en el panel. */
function mis_zona_textos(): array {
	return array(
		'zona_resumen' => 'Frase corta (tarjeta y portada, 1 línea)',
		'zona_ideal'   => 'De un vistazo · Ideal para',
		'zona_playas'  => 'De un vistazo · Playas',
		'zona_golf'    => 'De un vistazo · Golf',
		'zona_moverse' => 'De un vistazo · Cómo moverse',
	);
}

add_action(
	'init',
	static function () {
		$auth = static fn() => current_user_can( 'manage_categories' );
		register_term_meta( 'zona', 'zona_imagen', array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint', 'auth_callback' => $auth ) );
		register_term_meta(
			'zona',
			'zona_perfiles',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'description'       => 'Perfiles separados por comas: ' . implode( ', ', array_keys( mis_zona_perfiles() ) ),
				'sanitize_callback' => 'mis_zona_sanitize_perfiles',
				'auth_callback'     => $auth,
			)
		);
		foreach ( array_keys( mis_zona_textos() ) as $key ) {
			register_term_meta( 'zona', $key, array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => $auth ) );
		}
	},
	20
);

function mis_zona_sanitize_perfiles( $value ): string {
	$list = is_array( $value ) ? $value : explode( ',', (string) $value );
	$ok   = array_intersect( array_map( 'trim', array_map( 'strval', $list ) ), array_keys( mis_zona_perfiles() ) );
	return implode( ',', array_values( array_unique( $ok ) ) );
}

/** Perfiles de una zona como lista de claves. */
function mis_zona_perfiles_de( int $term_id ): array {
	$raw = (string) get_term_meta( $term_id, 'zona_perfiles', true );
	return '' === $raw ? array() : explode( ',', $raw );
}

// Polylang: la foto y los perfiles son iguales en los dos idiomas.
add_filter(
	'pll_copy_term_metas',
	static function ( array $metas ): array {
		return array_merge( $metas, array( 'zona_imagen', 'zona_perfiles' ) );
	}
);

// ------------------------------------------------------------------ panel

add_action( 'zona_add_form_fields', static fn() => mis_zona_campos( null ) );
add_action( 'zona_edit_form_fields', static fn( $term ) => mis_zona_campos( $term ) );
add_action( 'created_zona', 'mis_zona_guardar' );
add_action( 'edited_zona', 'mis_zona_guardar' );

add_action(
	'admin_enqueue_scripts',
	static function ( string $hook ) {
		if ( in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) && 'zona' === ( $_GET['taxonomy'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_enqueue_media();
		}
	}
);

function mis_zona_campos( ?WP_Term $term ): void {
	$id      = $term ? (int) $term->term_id : 0;
	$img     = $id ? (int) get_term_meta( $id, 'zona_imagen', true ) : 0;
	$thumb   = $img ? wp_get_attachment_image( $img, 'medium', false, array( 'style' => 'max-width:240px;height:auto;display:block' ) ) : '';
	$perfils = $id ? mis_zona_perfiles_de( $id ) : array();
	$edit    = (bool) $term;
	$row     = static function ( string $label, string $field ) use ( $edit ) {
		if ( $edit ) {
			printf( '<tr class="form-field"><th scope="row">%s</th><td>%s</td></tr>', $label, $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			printf( '<div class="form-field"><label>%s</label>%s</div>', $label, $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	};

	wp_nonce_field( 'mis_zona', 'mis_zona_nonce' );

	$row(
		esc_html__( 'Foto de la zona', 'mis-contenido' ),
		'<div class="mis-zona-img">' . $thumb . '</div>'
		. '<input type="hidden" name="zona_imagen" value="' . esc_attr( (string) ( $img ?: '' ) ) . '">'
		. '<p><button type="button" class="button mis-zona-img-pick">' . esc_html__( 'Elegir foto', 'mis-contenido' ) . '</button> '
		. '<button type="button" class="button-link mis-zona-img-del"' . ( $img ? '' : ' hidden' ) . '>' . esc_html__( 'Quitar', 'mis-contenido' ) . '</button></p>'
		. '<p class="description">Horizontal, 2400 px de ancho o más. Se usa en la portada de la zona y, recortada en vertical, en su tarjeta.</p>'
	);

	foreach ( mis_zona_textos() as $key => $label ) {
		$val = $id ? (string) get_term_meta( $id, $key, true ) : '';
		$row( esc_html( $label ), '<input type="text" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" maxlength="140">' );
	}

	$boxes = '';
	foreach ( mis_zona_perfiles() as $key => $names ) {
		$boxes .= sprintf(
			'<label style="display:inline-block;margin:0 16px 6px 0"><input type="checkbox" name="zona_perfiles[]" value="%s"%s> %s</label>',
			esc_attr( $key ),
			checked( in_array( $key, $perfils, true ), true, false ),
			esc_html( $names[1] )
		);
	}
	$row( esc_html__( 'Perfiles (filtros del índice)', 'mis-contenido' ), $boxes );
	?>
	<script>
	( function () {
		var box = document.currentScript.closest( 'form' ) || document;
		var pick = box.querySelector( '.mis-zona-img-pick' ), del = box.querySelector( '.mis-zona-img-del' );
		var input = box.querySelector( 'input[name="zona_imagen"]' ), prev = box.querySelector( '.mis-zona-img' );
		if ( ! pick || ! window.wp || ! wp.media ) { return; }
		var frame;
		pick.addEventListener( 'click', function () {
			frame = frame || wp.media( { title: 'Foto de la zona', library: { type: 'image' }, multiple: false } );
			frame.off( 'select' ).on( 'select', function () {
				var a = frame.state().get( 'selection' ).first().toJSON(), s = ( a.sizes && ( a.sizes.medium || a.sizes.full ) ) || a;
				input.value = a.id;
				prev.innerHTML = '<img src="' + s.url + '" alt="" style="max-width:240px;height:auto;display:block">';
				del.hidden = false;
			} );
			frame.open();
		} );
		del.addEventListener( 'click', function () { input.value = ''; prev.innerHTML = ''; del.hidden = true; } );
	} )();
	</script>
	<?php
}

function mis_zona_guardar( int $term_id ): void {
	if ( ! isset( $_POST['mis_zona_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mis_zona_nonce'] ) ), 'mis_zona' ) || ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	$img = absint( $_POST['zona_imagen'] ?? 0 );
	$img ? update_term_meta( $term_id, 'zona_imagen', $img ) : delete_term_meta( $term_id, 'zona_imagen' );

	foreach ( array_keys( mis_zona_textos() ) as $key ) {
		$val = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) );
		'' === $val ? delete_term_meta( $term_id, $key ) : update_term_meta( $term_id, $key, $val );
	}

	$perfiles = mis_zona_sanitize_perfiles( array_map( 'sanitize_key', (array) wp_unslash( $_POST['zona_perfiles'] ?? array() ) ) );
	'' === $perfiles ? delete_term_meta( $term_id, 'zona_perfiles' ) : update_term_meta( $term_id, 'zona_perfiles', $perfiles );
}
