<?php
/**
 * Vídeos de la propiedad (D18): los reels que el equipo publica en Instagram (y los de YouTube o
 * TikTok) se enlazan en WordPress, en el campo `mis_videos`, igual en todos los idiomas.
 *
 * - Editor: caja «Videos» con el enlace, la carátula (de la biblioteca de medios) y el orden.
 * - Ficha: [maros_videos], tarjetas verticales 9:16 con ▶. No se carga nada de Instagram, YouTube
 *   ni TikTok hasta que el cliente pulsa (spec 006, «Vídeo»; principio VII). Al pulsar se abre un
 *   visor en la propia web con el vídeo y un enlace a la red social. Sin JavaScript, o con el filtro
 *   `maros_videos_inline` a false, la carátula abre el vídeo en la red social.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

/**
 * Vídeos guardados de una propiedad, con la dirección para insertarlos y la carátula.
 *
 * @return array<int, array{url:string, platform:string, cover:int, embed:string, vertical:bool}>
 */
function maros_videos( int $post_id ): array {
	$list = json_decode( (string) get_post_meta( $post_id, 'mis_videos', true ), true );
	$out  = array();
	foreach ( is_array( $list ) ? $list : array() as $video ) {
		$url      = (string) ( $video['url'] ?? '' );
		$platform = (string) ( $video['platform'] ?? '' );
		if ( '' === $url || '' === $platform ) {
			continue;
		}
		$embed = maros_video_embed( $url, $platform );
		$out[] = array(
			'url'      => $url,
			'platform' => $platform,
			'cover'    => (int) ( $video['cover'] ?? 0 ),
			'embed'    => $embed['src'],
			'vertical' => $embed['vertical'],
		);
	}
	return $out;
}

/**
 * Dirección del reproductor de cada red a partir del enlace que se pega en el editor. Si no se
 * reconoce (p. ej. un enlace corto de TikTok), src queda vacío y la carátula abre la red social.
 *
 * @return array{src:string, vertical:bool}
 */
function maros_video_embed( string $url, string $platform ): array {
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	switch ( $platform ) {
		case 'instagram':
			// instagram.com/reel/<código>/, /p/<código>/, /tv/<código>/ (también con el usuario delante).
			if ( preg_match( '#/(p|reels?|tv)/([A-Za-z0-9_-]+)#', $path, $m ) ) {
				return array( 'src' => 'https://www.instagram.com/' . ( 'p' === $m[1] || 'tv' === $m[1] ? 'p' : 'reel' ) . '/' . $m[2] . '/embed/', 'vertical' => true );
			}
			break;
		case 'youtube':
			$host = (string) wp_parse_url( $url, PHP_URL_HOST );
			$id   = '';
			$vert = false;
			if ( preg_match( '/(^|\.)youtu\.be$/', $host ) ) {
				$id = trim( $path, '/' );
			} elseif ( preg_match( '#^/(shorts|embed|live)/([A-Za-z0-9_-]{6,})#', $path, $m ) ) {
				$id   = $m[2];
				$vert = 'shorts' === $m[1];
			} else {
				parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $q );
				$id = (string) ( $q['v'] ?? '' );
			}
			if ( preg_match( '/^[A-Za-z0-9_-]{6,}$/', $id ) ) {
				// youtube-nocookie: sin cookies de seguimiento hasta que se reproduce.
				return array( 'src' => 'https://www.youtube-nocookie.com/embed/' . $id . '?autoplay=1&playsinline=1&rel=0', 'vertical' => $vert );
			}
			break;
		case 'tiktok':
			if ( preg_match( '#/video/(\d+)#', $path, $m ) ) {
				return array( 'src' => 'https://www.tiktok.com/player/v1/' . $m[1] . '?autoplay=1&rel=0', 'vertical' => true );
			}
			break;
	}
	return array( 'src' => '', 'vertical' => 'youtube' !== $platform );
}

/** Nombre visible de cada red. */
function maros_video_platform_label( string $platform ): string {
	return array(
		'instagram' => 'Instagram',
		'youtube'   => 'YouTube',
		'tiktok'    => 'TikTok',
	)[ $platform ] ?? '';
}

/**
 * Carátula del vídeo: la elegida en el editor o, si no hay, una foto de la casa (una distinta para
 * cada vídeo). No se piden carátulas a Instagram: caducan y cargarían a Meta antes de pulsar (D18).
 */
function maros_video_cover( int $post_id, array $video, int $index ): int {
	if ( $video['cover'] && wp_attachment_is_image( $video['cover'] ) ) {
		return $video['cover'];
	}
	$photos = array_values( array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $post_id, 'maros_gallery', true ) ) ) ) );
	if ( $photos ) {
		return $photos[ ( $index + 1 ) % count( $photos ) ];
	}
	return (int) get_post_thumbnail_id( $post_id );
}

/** Icono de reproducir (círculo con triángulo), del mismo trazo que el resto de la ficha. */
function maros_video_play_svg( int $size = 56 ): string {
	return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 56 56" fill="none" aria-hidden="true" focusable="false"><circle cx="28" cy="28" r="27" fill="rgba(14,15,16,.45)" stroke="#fff" stroke-width="1.5"/><path d="M23 19.5v17l14-8.5-14-8.5z" fill="#fff"/></svg>';
}

/**
 * [maros_videos] Sección «Videos» de la ficha: fila de tarjetas verticales que se desliza en el
 * móvil, y el visor (un único <dialog>) que carga el vídeo al pulsar.
 */
add_shortcode(
	'maros_videos',
	static function () {
		$post_id = (int) get_the_ID();
		$videos  = maros_videos( $post_id );
		if ( ! $videos ) {
			return '';
		}
		$es     = maros_is_es( $post_id );
		$name   = get_the_title( $post_id );
		$inline = (bool) apply_filters( 'maros_videos_inline', true, $post_id );
		$total  = count( $videos );

		$cards = '';
		foreach ( $videos as $i => $v ) {
			$label    = maros_video_platform_label( $v['platform'] );
			$cover    = maros_video_cover( $post_id, $v, $i );
			$img      = $cover ? wp_get_attachment_image( $cover, 'medium_large', false, array( 'class' => 'mis-video__img', 'alt' => '', 'loading' => 'lazy', 'sizes' => '(min-width: 768px) 260px, 62vw' ) ) : '<span class="mis-video__img mis-video__img--empty"></span>';
			$aria     = sprintf( $es ? 'Ver el vídeo %1$d de %2$d de %3$s en %4$s' : 'Watch video %1$d of %2$d of %3$s on %4$s', $i + 1, $total, $name, $label );
			$data     = $inline && '' !== $v['embed'] ? ' data-mis-video="' . esc_url( $v['embed'] ) . '" data-mis-video-platform="' . esc_attr( $label ) . '"' . ( $v['vertical'] ? ' data-mis-video-vertical' : '' ) : '';
			$cards   .= '<li class="mis-videos__item"><a class="mis-video" href="' . esc_url( $v['url'] ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( $aria ) . '"' . $data . '>' . $img . '<span class="mis-video__play">' . maros_video_play_svg() . '</span><span class="mis-video__platform">' . esc_html( $label ) . '</span></a></li>';
		}

		$title = $es ? 'Vídeos' : 'Videos';
		$html  = '<section id="videos" class="mis-videos" aria-label="' . esc_attr( $title ) . '">';
		$html .= '<h2 class="wp-block-heading has-large-font-size">' . esc_html( $title ) . '</h2>';
		$html .= '<ul class="mis-videos__track">' . $cards . '</ul>';
		if ( $inline ) {
			$html .= '<dialog class="mis-video-lb" aria-label="' . esc_attr( sprintf( $es ? 'Vídeo de %s' : 'Video of %s', $name ) ) . '">';
			$html .= '<div class="mis-video-lb__bar"><a class="mis-video-lb__out" href="#" target="_blank" rel="noopener"></a>';
			$html .= '<button type="button" class="mis-video-lb__close" data-mis-video-close aria-label="' . esc_attr( $es ? 'Cerrar vídeo' : 'Close video' ) . '"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>';
			$html .= '<div class="mis-video-lb__stage" data-out-label="' . esc_attr( $es ? 'Ver en %s' : 'Watch on %s' ) . '"></div>';
			$html .= '</dialog>';
			wp_enqueue_script( 'maros-videos', plugins_url( 'assets/videos.js', __DIR__ ), array(), (string) filemtime( dirname( __DIR__ ) . '/assets/videos.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		}
		return $html . '</section>';
	}
);

/* ------------------------------------------------------------------ Editor */

add_action(
	'add_meta_boxes_mis_property',
	static function () {
		add_meta_box( 'mis-videos', __( 'Videos', 'maros-connector' ), 'maros_videos_meta_box', 'mis_property', 'side', 'high' );
	}
);

function maros_videos_meta_box( WP_Post $post ): void {
	wp_nonce_field( 'mis_videos_save', 'mis_videos_nonce' );
	$videos = json_decode( (string) get_post_meta( $post->ID, 'mis_videos', true ), true );
	$videos = is_array( $videos ) ? $videos : array();
	$rows   = array_map(
		static function ( $v ) {
			$cover = (int) ( $v['cover'] ?? 0 );
			return array(
				'url'   => (string) ( $v['url'] ?? '' ),
				'cover' => $cover,
				'thumb' => $cover ? (string) wp_get_attachment_image_url( $cover, 'thumbnail' ) : '',
			);
		},
		$videos
	);
	?>
	<p class="description"><?php esc_html_e( 'Paste the link of each Instagram reel (YouTube and TikTok also work). Choose a cover from the media library: if you leave it empty, a photo of the property is used. The list is the same in every language.', 'maros-connector' ); ?></p>
	<div class="mis-videos-admin" data-rows="<?php echo esc_attr( (string) wp_json_encode( $rows ) ); ?>">
		<ol class="mis-videos-admin__list"></ol>
		<p><button type="button" class="button mis-videos-admin__add"><?php esc_html_e( 'Add video', 'maros-connector' ); ?></button></p>
		<input type="hidden" name="mis_videos_json" value="<?php echo esc_attr( (string) wp_json_encode( $videos ) ); ?>">
	</div>
	<?php
}

add_action(
	'admin_enqueue_scripts',
	static function ( string $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || 'mis_property' !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_media();
		$file = dirname( __DIR__ ) . '/assets/videos-admin.js';
		wp_enqueue_script( 'maros-videos-admin', plugins_url( 'assets/videos-admin.js', __DIR__ ), array( 'jquery' ), (string) filemtime( $file ), true );
		wp_localize_script(
			'maros-videos-admin',
			'marosVideosAdmin',
			array(
				'url'         => __( 'Link', 'maros-connector' ),
				'cover'       => __( 'Choose cover', 'maros-connector' ),
				'changeCover' => __( 'Change cover', 'maros-connector' ),
				'noCover'     => __( 'Property photo', 'maros-connector' ),
				'removeCover' => __( 'Remove cover', 'maros-connector' ),
				'remove'      => __( 'Remove', 'maros-connector' ),
				'up'          => __( 'Move up', 'maros-connector' ),
				'down'        => __( 'Move down', 'maros-connector' ),
				'frameTitle'  => __( 'Video cover', 'maros-connector' ),
				'invalid'     => __( 'Only Instagram, YouTube and TikTok links (https) are kept.', 'maros-connector' ),
			)
		);
		wp_add_inline_style(
			'wp-admin',
			'.mis-videos-admin__list{margin:0;list-style:none}.mis-videos-admin__row{display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:10px 0;border-bottom:1px solid #f0f0f1}.mis-videos-admin__url{flex:1 1 100%;min-width:0}.mis-videos-admin__thumb{width:27px;height:48px;object-fit:cover;background:#f0f0f1;display:block}.mis-videos-admin__bad{color:#b32d2e;flex-basis:100%;margin:0}'
		);
	}
);

/** Guarda la lista y la copia a las traducciones (los vídeos son iguales en todos los idiomas). */
add_action(
	'save_post_mis_property',
	static function ( int $post_id ) {
		if ( ! isset( $_POST['mis_videos_json'], $_POST['mis_videos_nonce'] ) || defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mis_videos_nonce'] ) ), 'mis_videos_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$value = maros_sanitize_videos( (string) wp_unslash( $_POST['mis_videos_json'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se sanea campo a campo.
		$ids   = function_exists( 'pll_get_post_translations' ) ? array_map( 'intval', (array) pll_get_post_translations( $post_id ) ) : array();
		foreach ( array_unique( array_merge( array( $post_id ), $ids ) ) as $id ) {
			if ( $id && ( $id === $post_id || current_user_can( 'edit_post', $id ) ) ) {
				update_post_meta( $id, 'mis_videos', wp_slash( $value ) );
			}
		}
	}
);
