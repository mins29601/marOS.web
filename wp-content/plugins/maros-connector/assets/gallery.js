/**
 * Galería de la propiedad (marOS spec 041): abre la capa de fotos por estancias y el visor.
 * Una foto de la cabecera ([data-mis-photo]) abre el visor en esa foto; una de un dormitorio o de la
 * piscina ([data-mis-room-photo]) abre la galería en el bloque de su estancia (Julio 19-11-2026).
 */
( function () {
	var wrap = document.querySelector( '[data-mis-tour]' );
	var tour = wrap && document.getElementById( wrap.getAttribute( 'data-mis-tour' ) );
	if ( ! tour || typeof tour.showModal !== 'function' ) {
		return;
	}
	var items = Array.prototype.slice.call( tour.querySelectorAll( '.mis-tour__item' ) );
	var scroller = tour.querySelector( '.mis-tour__scroll' );
	var lb = tour.querySelector( '.mis-lb' );
	var img = lb.querySelector( '.mis-lb__img' );
	var count = lb.querySelector( '.mis-lb__count' );
	var caption = lb.querySelector( '.mis-lb__caption' );
	var index = 0;
	var opener = null;

	function open() {
		if ( ! tour.open ) {
			opener = document.activeElement;
			tour.showModal();
			document.documentElement.classList.add( 'mis-tour-open' );
		}
	}
	function close() {
		closeViewer();
		if ( tour.open ) {
			tour.close();
		}
	}
	tour.addEventListener( 'close', function () {
		document.documentElement.classList.remove( 'mis-tour-open' );
		lb.hidden = true;
		if ( opener && opener.focus ) {
			opener.focus();
		}
	} );

	function show( i ) {
		index = ( i + items.length ) % items.length;
		var item = items[ index ];
		var thumb = item.querySelector( 'img' );
		img.removeAttribute( 'srcset' );
		img.src = item.getAttribute( 'data-full' ) || ( thumb && thumb.currentSrc ) || '';
		if ( item.getAttribute( 'data-srcset' ) ) {
			img.srcset = item.getAttribute( 'data-srcset' );
		}
		img.alt = item.getAttribute( 'aria-label' ) || '';
		count.textContent = ( index + 1 ) + ' / ' + items.length;
		caption.textContent = item.getAttribute( 'data-section' ) || '';
		// Precarga la siguiente para que pasar de foto sea inmediato.
		var next = items[ ( index + 1 ) % items.length ];
		if ( next && next.getAttribute( 'data-full' ) ) {
			new Image().src = next.getAttribute( 'data-full' );
		}
	}
	function viewer( photo ) {
		var i = items.findIndex( function ( it ) {
			return it.getAttribute( 'data-mis-photo' ) === String( photo );
		} );
		open();
		lb.hidden = false;
		show( i < 0 ? 0 : i );
		lb.querySelector( '[data-mis-step="1"]' ).focus();
	}
	/** Abre la galería con el bloque de la estancia que tiene esta foto arriba del todo. */
	function room( photo ) {
		var item = items.find( function ( it ) {
			return it.getAttribute( 'data-mis-photo' ) === String( photo );
		} );
		var sec = item && item.closest( '.mis-tour__sec' );
		open();
		lb.hidden = true;
		scroller.scrollTop = 0;
		if ( sec ) {
			scroller.scrollTop = sec.getBoundingClientRect().top - scroller.getBoundingClientRect().top;
		}
	}
	function closeViewer() {
		if ( lb.hidden ) {
			return;
		}
		lb.hidden = true;
		var item = items[ index ];
		if ( item ) {
			item.focus( { preventScroll: true } );
			item.scrollIntoView( { block: 'center' } );
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var t = e.target.closest( '[data-mis-slide], [data-mis-open], [data-mis-close], [data-mis-lb-close], [data-mis-step], [data-mis-goto], [data-mis-room-photo], [data-mis-photo]' );
		if ( ! t ) {
			return;
		}
		if ( t.hasAttribute( 'data-mis-slide' ) ) {
			slide( Number( t.getAttribute( 'data-mis-slide' ) ) );
		} else if ( t.hasAttribute( 'data-mis-open' ) ) {
			open();
			scroller.scrollTop = 0;
		} else if ( t.hasAttribute( 'data-mis-close' ) ) {
			close();
		} else if ( t.hasAttribute( 'data-mis-lb-close' ) ) {
			closeViewer();
		} else if ( t.hasAttribute( 'data-mis-step' ) ) {
			show( index + Number( t.getAttribute( 'data-mis-step' ) ) );
		} else if ( t.hasAttribute( 'data-mis-room-photo' ) ) {
			room( t.getAttribute( 'data-mis-room-photo' ) );
		} else if ( t.hasAttribute( 'data-mis-goto' ) ) {
			var sec = document.getElementById( t.getAttribute( 'data-mis-goto' ) );
			if ( sec ) {
				scroller.scrollTop += sec.getBoundingClientRect().top - scroller.getBoundingClientRect().top;
			}
		} else {
			viewer( t.getAttribute( 'data-mis-photo' ) );
		}
	} );

	// Esc cierra primero el visor y después la galería; flechas para pasar de foto.
	tour.addEventListener( 'cancel', function ( e ) {
		if ( ! lb.hidden ) {
			e.preventDefault();
			closeViewer();
		}
	} );
	tour.addEventListener( 'keydown', function ( e ) {
		if ( lb.hidden ) {
			return;
		}
		if ( 'ArrowRight' === e.key ) {
			show( index + 1 );
		} else if ( 'ArrowLeft' === e.key ) {
			show( index - 1 );
		}
	} );

	// Carrusel de la cabecera: flechas, teclado y contador «1/N» (el deslizar es nativo, scroll-snap).
	var carousel = wrap.querySelector( '.mis-carousel' );
	var current = wrap.querySelector( '.mis-carousel__index' );
	function slide( d ) {
		if ( ! carousel ) {
			return;
		}
		var w = carousel.clientWidth;
		var n = Math.round( carousel.scrollLeft / w ) + d;
		var last = carousel.children.length - 1;
		n = n < 0 ? last : ( n > last ? 0 : n );
		carousel.scrollTo( { left: n * w, behavior: Math.abs( d ) > 0 && ( n === 0 || n === last ) && Math.abs( n * w - carousel.scrollLeft ) > 2 * w ? 'auto' : 'smooth' } );
	}
	if ( carousel ) {
		var raf = 0;
		carousel.addEventListener( 'scroll', function () {
			cancelAnimationFrame( raf );
			raf = requestAnimationFrame( function () {
				if ( current ) {
					current.textContent = String( Math.round( carousel.scrollLeft / carousel.clientWidth ) + 1 );
				}
			} );
		}, { passive: true } );
		carousel.addEventListener( 'keydown', function ( e ) {
			if ( 'ArrowRight' === e.key || 'ArrowLeft' === e.key ) {
				e.preventDefault();
				slide( 'ArrowRight' === e.key ? 1 : -1 );
			}
		} );
	}

	// Deslizar en móvil.
	var x0 = null;
	lb.addEventListener( 'touchstart', function ( e ) {
		x0 = e.touches[ 0 ].clientX;
	}, { passive: true } );
	lb.addEventListener( 'touchend', function ( e ) {
		if ( null === x0 ) {
			return;
		}
		var dx = e.changedTouches[ 0 ].clientX - x0;
		x0 = null;
		if ( Math.abs( dx ) > 40 ) {
			show( index + ( dx < 0 ? 1 : -1 ) );
		}
	}, { passive: true } );
}() );
