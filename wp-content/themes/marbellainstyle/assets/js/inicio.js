/**
 * Inicio en el móvil (maqueta «home en móvil», Julio, 25-11-2026).
 *
 * - Barra del buscador: abre el buscador de la portada en un panel desde abajo, con el calendario
 *   ya abierto en la llegada, y resume las fechas y los huéspedes elegidos. Alojamiento usa la
 *   misma barra con el buscador completo y un botón de filtros que abre el panel en los filtros.
 * - Carruseles (tarjetas Stay / Concierge / Experiences y zonas): los puntos marcan la tarjeta
 *   que se ve.
 * - Experiencias: solo cuatro de la etiqueta elegida; «See all experiences» lleva a la página de
 *   experiencias con esa etiqueta puesta.
 */
( function () {
	var mobile = window.matchMedia( '(max-width: 767px)' );

	// ------------------------------------------------------------------ Buscador
	document.querySelectorAll( '.mis-qs' ).forEach( function ( qs ) {
		var bar = qs.querySelector( '.mis-qs__bar' );
		var sheet = qs.querySelector( '.mis-qs__sheet' );
		var form = qs.querySelector( 'form' );
		if ( ! bar || ! sheet || ! form ) {
			return;
		}
		var datesOut = bar.querySelector( '[data-qs=dates]' );
		var guestsOut = bar.querySelector( '[data-qs=guests]' );
		var emptyDates = datesOut.getAttribute( 'data-empty' ) || datesOut.textContent;
		var countOut = qs.querySelector( '[data-qs=count]' );
		var filtersBtn = qs.querySelector( '.mis-qs__filters' );

		function summary() {
			var a = form.querySelector( '[name=checkin]' ).value;
			var d = form.querySelector( '[name=checkout]' ).value;
			var f = window.MisCalendar && window.MisCalendar.format;
			datesOut.textContent = a && d && f ? f( a ) + ' – ' + f( d ) : emptyDates;
			var g = form.querySelector( '[name=guests]' );
			guestsOut.textContent = g.options[ g.selectedIndex ] ? g.options[ g.selectedIndex ].text : g.value;
			if ( countOut ) {
				var n = form.querySelectorAll( 'input[name="feat[]"]:checked' ).length;
				[ 'type', 'area', 'bedrooms' ].forEach( function ( k ) {
					var f = form.querySelector( '[name=' + k + ']' );
					n += f && f.value ? 1 : 0;
				} );
				countOut.textContent = n;
				countOut.hidden = ! n;
			}
		}
		function open( e, toFilters ) {
			sheet.classList.add( 'is-open' );
			sheet.setAttribute( 'role', 'dialog' );
			sheet.setAttribute( 'aria-modal', 'true' );
			sheet.setAttribute( 'aria-label', qs.querySelector( '.mis-qs__top strong' ).textContent );
			bar.setAttribute( 'aria-expanded', 'true' );
			document.documentElement.classList.add( 'mis-qs-open' );
			// El calendario de la llegada, ya abierto (search.js de marOS Connector).
			// Después de este clic: si no, search.js lo toma por un clic fuera y lo cierra.
			setTimeout( function () {
				var filters = toFilters && form.querySelector( '[data-qs-filters]' );
				if ( filters ) {
					filters.scrollIntoView( { block: 'start' } );
					return;
				}
				var arrival = form.querySelector( '.mis-search__field' );
				if ( arrival && arrival.querySelector( '.mis-search__date' ) ) {
					arrival.click();
				}
			} );
			qs.querySelector( '.mis-qs__close' ).focus( { preventScroll: true } );
		}
		function close() {
			sheet.classList.remove( 'is-open' );
			[ 'role', 'aria-modal', 'aria-label' ].forEach( function ( a ) {
				sheet.removeAttribute( a );
			} );
			bar.setAttribute( 'aria-expanded', 'false' );
			document.documentElement.classList.remove( 'mis-qs-open' );
			summary();
			bar.focus( { preventScroll: true } );
		}
		bar.addEventListener( 'click', open );
		if ( filtersBtn ) {
			filtersBtn.addEventListener( 'click', function ( e ) {
				open( e, true );
			} );
		}
		qs.querySelector( '.mis-qs__close' ).addEventListener( 'click', close );
		sheet.addEventListener( 'click', function ( e ) {
			if ( e.target === sheet ) {
				close();
			}
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && sheet.classList.contains( 'is-open' ) && ! form.querySelector( '.mis-search__cal:not([hidden])' ) ) {
				close();
			}
		} );
		form.addEventListener( 'change', summary );
		mobile.addEventListener( 'change', function () {
			if ( ! mobile.matches && sheet.classList.contains( 'is-open' ) ) {
				close();
			}
		} );
		summary();
	} );

	// ------------------------------------------------------------------ Carruseles con puntos
	function dots( track, box ) {
		var items = Array.prototype.filter.call( track.children, function ( el ) {
			return el.offsetParent !== null;
		} );
		if ( ! box ) {
			box = document.createElement( 'div' );
			box.className = 'mis-dots';
			box.setAttribute( 'aria-hidden', 'true' );
			box.innerHTML = new Array( items.length + 1 ).join( '<i></i>' );
			track.after( box );
		}
		var marks = box.querySelectorAll( 'i' );
		function update() {
			var best = 0;
			var dist = Infinity;
			items.forEach( function ( el, i ) {
				var d = Math.abs( el.getBoundingClientRect().left - track.getBoundingClientRect().left - parseFloat( getComputedStyle( track ).paddingLeft || 0 ) );
				if ( d < dist ) {
					dist = d;
					best = i;
				}
			} );
			if ( track.scrollLeft + track.clientWidth >= track.scrollWidth - 4 ) {
				best = items.length - 1;
			}
			marks.forEach( function ( m, i ) {
				m.classList.toggle( 'is-on', i === best );
			} );
		}
		track.addEventListener( 'scroll', update, { passive: true } );
		update();
	}
	document.querySelectorAll( '.mis-front .mis-pillars' ).forEach( function ( track ) {
		var box = track.nextElementSibling;
		dots( track, box && box.classList.contains( 'mis-dots' ) ? box : null );
	} );
	document.querySelectorAll( '.mis-front .mis-zgrid' ).forEach( function ( track ) {
		dots( track, null );
	} );

	// ------------------------------------------------------------------ Experiencias: cuatro
	document.querySelectorAll( '.mis-front .mis-tour-grid' ).forEach( function ( grid ) {
		var list = grid.querySelector( '.wp-block-post-template' );
		var filters = grid.querySelector( '.mis-tour-filters' );
		var all = grid.parentElement.querySelector( '[data-mis-tours-all]' ) || document.querySelector( '[data-mis-tours-all]' );
		if ( ! list ) {
			return;
		}
		var base = all ? all.getAttribute( 'href' ).split( '#' )[ 0 ] : '';
		function limit() {
			var shown = 0;
			list.querySelectorAll( ':scope > li' ).forEach( function ( li ) {
				var over = ! li.hidden && shown >= 4;
				if ( ! li.hidden ) {
					shown++;
				}
				li.classList.toggle( 'is-over', over );
			} );
			var active = filters && filters.querySelector( '.mis-chip.is-active' );
			if ( all ) {
				all.setAttribute( 'href', base + ( active && active.dataset.tipo ? '#' + active.dataset.tipo : '' ) );
			}
		}
		if ( filters ) {
			// Después del filtro de tours.php, que oculta las que no son de la etiqueta.
			filters.addEventListener( 'click', function () {
				setTimeout( limit );
			} );
		}
		limit();
	} );
} )();
