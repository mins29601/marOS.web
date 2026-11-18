/**
 * Visor de vídeos de la ficha (D18): el reproductor de Instagram, YouTube o TikTok se carga solo
 * al pulsar una carátula, y se quita al cerrar (así el vídeo deja de sonar).
 * [data-mis-videos-open] (el botón «Videos · N» de la galería) abre el primero.
 */
( function () {
	var dialog = document.querySelector( '.mis-video-lb' );
	if ( ! dialog || typeof dialog.showModal !== 'function' ) {
		return;
	}
	var stage = dialog.querySelector( '.mis-video-lb__stage' );
	var out = dialog.querySelector( '.mis-video-lb__out' );
	var opener = null;

	function open( link ) {
		opener = link;
		var iframe = document.createElement( 'iframe' );
		iframe.src = link.getAttribute( 'data-mis-video' );
		iframe.title = link.getAttribute( 'aria-label' ) || '';
		iframe.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
		iframe.setAttribute( 'allowfullscreen', '' );
		iframe.setAttribute( 'scrolling', 'no' );
		iframe.loading = 'eager';
		stage.innerHTML = '';
		stage.appendChild( iframe );
		dialog.classList.toggle( 'is-vertical', link.hasAttribute( 'data-mis-video-vertical' ) );
		out.href = link.href;
		out.textContent = ( stage.getAttribute( 'data-out-label' ) || '%s' ).replace( '%s', link.getAttribute( 'data-mis-video-platform' ) || '' );
		dialog.showModal();
		document.documentElement.classList.add( 'mis-tour-open' );
	}

	dialog.addEventListener( 'close', function () {
		stage.innerHTML = '';
		document.documentElement.classList.remove( 'mis-tour-open' );
		if ( opener && opener.focus ) {
			opener.focus( { preventScroll: true } );
		}
	} );
	// Pulsar fuera del vídeo cierra.
	dialog.addEventListener( 'click', function ( e ) {
		if ( e.target === dialog || e.target.closest( '[data-mis-video-close]' ) ) {
			dialog.close();
		}
	} );

	document.addEventListener( 'click', function ( e ) {
		if ( e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey ) {
			return;
		}
		var link = e.target.closest( 'a[data-mis-video]' );
		if ( ! link && e.target.closest( '[data-mis-videos-open]' ) ) {
			link = document.querySelector( 'a[data-mis-video]' );
		}
		if ( link ) {
			e.preventDefault();
			open( link );
		}
	} );
} )();
