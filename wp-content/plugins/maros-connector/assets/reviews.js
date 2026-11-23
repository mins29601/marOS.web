/**
 * Opiniones de la ficha ([maros_reviews]): «Ver más» en las que pasan de unas cinco líneas y la
 * ventana con todas las opiniones («Ver todas las opiniones»).
 */
( function () {
	'use strict';

	function setupMore( btn ) {
		var text = btn.previousElementSibling;
		if ( ! text || ! text.classList.contains( 'is-clamped' ) ) {
			return;
		}
		var more = btn.textContent;
		var check = function () {
			if ( ! text.classList.contains( 'is-clamped' ) ) {
				return;
			}
			btn.hidden = text.scrollHeight <= text.clientHeight + 2;
		};
		check();
		window.addEventListener( 'resize', check );
		btn.addEventListener( 'click', function () {
			var open = text.classList.toggle( 'is-clamped' ) === false;
			btn.textContent = open ? btn.getAttribute( 'data-less' ) : more;
			btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
		btn.setAttribute( 'aria-expanded', 'false' );
	}

	function setupModal( section ) {
		var open = section.querySelector( '[data-mis-reviews-open]' );
		var dialog = section.querySelector( '.mis-reviews-modal' );
		if ( ! open || ! dialog || typeof dialog.showModal !== 'function' ) {
			if ( open ) {
				open.hidden = true;
			}
			return;
		}
		open.addEventListener( 'click', function () {
			dialog.showModal();
			document.documentElement.classList.add( 'mis-no-scroll' );
			var body = dialog.querySelector( '.mis-reviews-modal__body' );
			if ( body ) {
				body.scrollTop = 0;
			}
		} );
		dialog.addEventListener( 'close', function () {
			document.documentElement.classList.remove( 'mis-no-scroll' );
			open.focus();
		} );
		dialog.addEventListener( 'click', function ( e ) {
			// Pulsar fuera de la caja (en el fondo) o en la X cierra.
			if ( e.target === dialog || e.target.closest( '[data-mis-reviews-close]' ) ) {
				dialog.close();
			}
		} );
	}

	function init() {
		document.querySelectorAll( '[data-mis-review-more]' ).forEach( setupMore );
		document.querySelectorAll( '.mis-reviews' ).forEach( setupModal );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
	// Las fuentes cambian el alto de las líneas: se vuelve a medir cuando terminan de cargar.
	if ( document.fonts && document.fonts.ready ) {
		document.fonts.ready.then( function () {
			document.querySelectorAll( '[data-mis-review-more]' ).forEach( function ( btn ) {
				var text = btn.previousElementSibling;
				if ( text && text.classList.contains( 'is-clamped' ) ) {
					btn.hidden = text.scrollHeight <= text.clientHeight + 2;
				}
			} );
		} );
	}
}() );
