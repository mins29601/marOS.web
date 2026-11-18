/**
 * Caja de reserva de la ficha ([maros_booking], includes/booking.php).
 *
 * Calendario con la copia local de disponibilidad (un carácter por día desde hoy; bits: 1 noche
 * libre, 2 se puede llegar, 4 se puede salir), huéspedes y presupuesto en vivo de marOS a través de
 * /wp-json/maros/v1/quote. En móvil la caja se abre como pantalla de reserva (flujo C) desde la
 * barra fija.
 */
( function () {
	function iso( t ) {
		return new Date( t ).toISOString().slice( 0, 10 );
	}
	function parse( d ) {
		return Date.parse( d + 'T12:00:00Z' );
	}
	function fmt( n, pair ) {
		return pair[ n === 1 ? 0 : 1 ].replace( '%d', n );
	}

	document.querySelectorAll( '[data-mis-resv]' ).forEach( function ( root ) {
		var data = JSON.parse( root.querySelector( '.mis-resv__data' ).textContent );
		var t = data.i18n;
		var cal = data.calendar || { start: iso( Date.now() ), days: '', min: [] };
		var box = root.closest( '.mis-booking-box' ) || root;
		var panel = root.querySelector( '.mis-resv__cal' );
		var quoteEl = root.querySelector( '.mis-resv__quote' );
		var cta = root.querySelector( '[data-book-cta]' );
		var sum = root.querySelector( '[data-sum]' );
		var after = root.querySelector( '[data-after]' );
		var sticky = document.querySelector( '.mis-sticky-cta' );
		var stickyText = sticky && sticky.querySelector( 'p' );
		var stickyDefault = stickyText ? stickyText.innerHTML : '';
		var dayMonth = new Intl.DateTimeFormat( t.locale, { day: 'numeric', month: 'short', timeZone: 'UTC' } );
		function range( a, b ) {
			var x = new Date( parse( a ) );
			var y = new Date( parse( b ) );
			if ( x.getUTCMonth() === y.getUTCMonth() ) {
				return x.getUTCDate() + '–' + dayMonth.format( y );
			}
			return dayMonth.format( x ) + ' – ' + dayMonth.format( y );
		}

		var state = {
			arrival: '',
			departure: '',
			adults: data.adults || 2,
			children: 0,
			infants: 0,
		};
		var request = 0;
		var timer = null;

		/* ---------------------------------------------------------- Calendario (assets/calendar.js) */

		var picker = window.MisCalendar.range( {
			panel: panel,
			start: cal.start,
			days: cal.days,
			min: cal.min,
			onChange: function ( a, d, done ) {
				state.arrival = a;
				state.departure = d;
				showDates();
				markPick( done ? '' : a ? 'departure' : 'arrival' );
				if ( done ) {
					closeCalendar();
					fetchQuote();
				} else {
					showQuote( null );
				}
			},
		} );
		function markPick( which ) {
			root.querySelectorAll( '[data-pick]' ).forEach( function ( b ) {
				b.classList.toggle( 'is-active', b.getAttribute( 'data-pick' ) === which );
			} );
		}
		function openCalendar( which ) {
			picker.set( state.arrival, state.departure );
			picker.open( which );
			markPick( picker.picking() );
		}
		function closeCalendar() {
			picker.close();
			markPick( '' );
		}

		root.querySelectorAll( '[data-pick]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var which = b.getAttribute( 'data-pick' );
				if ( ! panel.hidden && picker.picking() === which ) {
					closeCalendar();
				} else {
					openCalendar( which );
				}
			} );
		} );

		function showDates() {
			root.querySelector( '[data-show=arrival]' ).textContent = state.arrival ? window.MisCalendar.format( state.arrival ) : t.add;
			root.querySelector( '[data-show=departure]' ).textContent = state.departure ? window.MisCalendar.format( state.departure ) : t.add;
		}

		/* ---------------------------------------------------------- Huéspedes */

		function showGuests() {
			[ 'adults', 'children', 'infants' ].forEach( function ( k ) {
				root.querySelector( '[data-count=' + k + ']' ).textContent = state[ k ];
			} );
			var guests = state.adults + state.children;
			root.querySelector( '[data-step=adults][data-by="-1"]' ).disabled = state.adults <= 1;
			root.querySelector( '[data-step=children][data-by="-1"]' ).disabled = state.children <= 0;
			root.querySelector( '[data-step=infants][data-by="-1"]' ).disabled = state.infants <= 0;
			root.querySelector( '[data-step=adults][data-by="1"]' ).disabled = guests >= data.max;
			root.querySelector( '[data-step=children][data-by="1"]' ).disabled = guests >= data.max;
			root.querySelector( '[data-step=infants][data-by="1"]' ).disabled = state.infants >= 5;
			var parts = [ fmt( state.adults, t.adult ) ];
			if ( state.children ) {
				parts.push( fmt( state.children, t.child ) );
			}
			if ( state.infants ) {
				parts.push( fmt( state.infants, t.infant ) );
			}
			root.querySelector( '[data-show=guests]' ).textContent = parts.join( ', ' );
		}

		root.querySelectorAll( '[data-step]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var k = b.getAttribute( 'data-step' );
				state[ k ] = Math.max( k === 'adults' ? 1 : 0, state[ k ] + +b.getAttribute( 'data-by' ) );
				showGuests();
				if ( state.arrival && state.departure ) {
					clearTimeout( timer );
					timer = setTimeout( fetchQuote, 350 );
				}
			} );
		} );

		/* ---------------------------------------------------------- Presupuesto */

		function esc( s ) {
			var d = document.createElement( 'div' );
			d.textContent = s == null ? '' : String( s );
			return d.innerHTML;
		}

		function fetchQuote() {
			var id = ++request;
			quoteEl.innerHTML = '<p class="mis-resv__loading">' + t.loading + '</p>';
			root.classList.add( 'is-loading' );
			var url = data.endpoint + ( data.endpoint.indexOf( '?' ) < 0 ? '?' : '&' ) + new URLSearchParams( {
				post: data.post,
				arrival: state.arrival,
				departure: state.departure,
				adults: state.adults,
				children: state.children,
				infants: state.infants,
			} ).toString();
			fetch( url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' } )
				.then( function ( r ) {
					return r.json().catch( function () {
						return {};
					} );
				} )
				.then( function ( q ) {
					if ( id === request ) {
						showQuote( q );
					}
				} )
				.catch( function () {
					if ( id === request ) {
						showQuote( { error: 'network' } );
					}
				} );
		}

		function showQuote( q ) {
			root.classList.remove( 'is-loading' );
			after.innerHTML = '';
			if ( ! q || q.error || ! q.available ) {
				sum.innerHTML = '<span class="mis-resv__sum-none">' + t.none + '</span>';
			}
			if ( ! q ) {
				quoteEl.innerHTML = '<p class="mis-resv__empty">' + t.empty + '</p>';
				cta.textContent = t.check;
				cta.href = data.stay;
				cta.removeAttribute( 'data-ready' );
				if ( stickyText ) {
					stickyText.innerHTML = stickyDefault;
				}
				return;
			}
			if ( q.error || typeof q.available === 'undefined' ) {
				quoteEl.innerHTML = '<p class="mis-resv__msg">' + esc( q.message || '' ) + '</p>';
				cta.textContent = t.check;
				cta.href = data.stay;
				return;
			}
			if ( ! q.available ) {
				quoteEl.innerHTML = '<ul class="mis-resv__reasons">' + q.reasons.map( function ( r ) {
					return '<li>' + esc( r ) + '</li>';
				} ).join( '' ) + '</ul><button type="button" class="mis-resv__other" data-other>' + t.other + '</button>';
				cta.textContent = t.check;
				cta.href = data.stay;
				cta.removeAttribute( 'data-ready' );
				if ( stickyText ) {
					stickyText.innerHTML = stickyDefault;
				}
				return;
			}
			var html = '<dl class="mis-resv__lines">';
			q.lines.forEach( function ( l ) {
				html += '<div class="mis-resv__line' + ( l.included ? ' is-included' : '' ) + '"><dt>' + esc( l.label ) + '</dt><dd>' + esc( l.amount ) + '</dd></div>';
			} );
			html += '<div class="mis-resv__line mis-resv__total"><dt>' + t.total + ' <small>' + esc( q.nights_label ) + '</small></dt><dd>' + esc( q.total ) + '</dd></div></dl>';
			quoteEl.innerHTML = html;
			// Fianza y aviso, debajo del botón «Book» (Julio, 18-11).
			after.innerHTML = ( q.deposit ? '<p class="mis-resv__note">' + esc( q.deposit ) + '</p>' : '' ) + '<p class="mis-resv__note">' + esc( q.note ) + '</p>';
			cta.textContent = t.book;
			cta.href = q.book_url;
			cta.setAttribute( 'data-ready', '' );
			sum.innerHTML = '<b>' + esc( q.total ) + '</b><span>' + esc( range( q.arrival, q.departure ) + ' · ' + q.nights_label ) + '</span>';
			if ( stickyText ) {
				stickyText.innerHTML = '<strong>' + esc( q.total ) + '</strong> <small>· ' + esc( q.nights_label ) + '</small>';
			}
		}

		quoteEl.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-other]' ) ) {
				openCalendar( 'arrival' );
			}
		} );

		// Sin fechas, el botón abre el calendario en vez de ir a Lodgify.
		cta.addEventListener( 'click', function ( e ) {
			if ( ! cta.hasAttribute( 'data-ready' ) ) {
				e.preventDefault();
				openCalendar( state.arrival && ! state.departure ? 'departure' : 'arrival' );
			}
		} );

		/* ---------------------------------------------------------- Pantalla de reserva (móvil) */

		var mobile = window.matchMedia( '(max-width: 1023px)' );
		function openSheet() {
			box.classList.add( 'is-sheet' );
			document.documentElement.classList.add( 'mis-resv-open' );
			var first = root.querySelector( '[data-pick]' );
			if ( first ) {
				first.focus( { preventScroll: true } );
			}
			if ( ! state.arrival ) {
				openCalendar( 'arrival' );
			}
		}
		function closeSheet() {
			box.classList.remove( 'is-sheet' );
			document.documentElement.classList.remove( 'mis-resv-open' );
		}
		root.querySelector( '[data-book-close]' ).addEventListener( 'click', closeSheet );
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && box.classList.contains( 'is-sheet' ) ) {
				closeSheet();
			}
		} );
		document.querySelectorAll( '[data-book-open]' ).forEach( function ( a ) {
			a.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				if ( mobile.matches ) {
					openSheet();
				} else {
					box.scrollIntoView( { behavior: 'smooth', block: 'center' } );
					openCalendar( 'arrival' );
				}
			} );
		} );
		mobile.addEventListener( 'change', function ( e ) {
			if ( ! e.matches ) {
				closeSheet();
			}
		} );

		/* ---------------------------------------------------------- Arranque */

		// Si viene de la búsqueda con fechas válidas, se pide el precio directamente.
		if ( data.arrival && data.departure && data.departure > data.arrival ) {
			state.arrival = data.arrival;
			state.departure = data.departure;
		}
		showDates();
		showGuests();
		if ( state.arrival && state.departure ) {
			fetchQuote();
		}
	} );
} )();
