/**
 * Caja de reserva de la ficha ([maros_booking], includes/booking.php).
 *
 * Calendario con la copia local de disponibilidad (un carácter por día desde hoy; bits: 1 noche
 * libre, 2 se puede llegar, 4 se puede salir), huéspedes y presupuesto en vivo de marOS a través de
 * /wp-json/maros/v1/quote. En móvil la caja se abre como pantalla de reserva (flujo C) desde la
 * barra fija.
 */
( function () {
	var DAY = 86400000;

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
		var start = parse( cal.start );
		var box = root.closest( '.mis-booking-box' ) || root;
		var panel = root.querySelector( '.mis-resv__cal' );
		var quoteEl = root.querySelector( '.mis-resv__quote' );
		var cta = root.querySelector( '[data-book-cta]' );
		var sum = root.querySelector( '[data-sum]' );
		var sticky = document.querySelector( '.mis-sticky-cta' );
		var stickyText = sticky && sticky.querySelector( 'p' );
		var stickyDefault = stickyText ? stickyText.innerHTML : '';
		var long = new Intl.DateTimeFormat( t.locale, { day: 'numeric', month: 'short', timeZone: 'UTC' } );
		var dayMonth = new Intl.DateTimeFormat( t.locale, { day: 'numeric', month: 'short', timeZone: 'UTC' } );
		function range( a, b ) {
			var x = new Date( parse( a ) );
			var y = new Date( parse( b ) );
			if ( x.getUTCMonth() === y.getUTCMonth() ) {
				return x.getUTCDate() + '–' + dayMonth.format( y );
			}
			return dayMonth.format( x ) + ' – ' + dayMonth.format( y );
		}
		var monthName = new Intl.DateTimeFormat( t.locale, { month: 'long', year: 'numeric', timeZone: 'UTC' } );

		var state = {
			arrival: '',
			departure: '',
			adults: data.adults || 2,
			children: 0,
			infants: 0,
			picking: 'arrival',
			month: 0,
		};
		var request = 0;
		var timer = null;

		/* ---------------------------------------------------------- Disponibilidad local */

		function index( d ) {
			return Math.round( ( parse( d ) - start ) / DAY );
		}
		function bits( d ) {
			if ( ! cal.days ) {
				return index( d ) >= 0 ? 7 : 0; // Sin copia local: decide el presupuesto.
			}
			var i = index( d );
			return i < 0 || i >= cal.days.length ? 0 : +cal.days.charAt( i );
		}
		function minStay( d ) {
			var i = index( d );
			var m = 0;
			( cal.min || [] ).forEach( function ( r ) {
				if ( r[ 0 ] <= i ) {
					m = r[ 1 ];
				}
			} );
			return m;
		}
		function canArrive( d ) {
			return ( bits( d ) & 3 ) === 3;
		}
		/** 0 se puede salir; 1 no; 2 se puede salir pero no llega a la estancia mínima. */
		function departureState( d ) {
			if ( ! state.arrival || d <= state.arrival || ! ( bits( d ) & 4 ) ) {
				return 1;
			}
			for ( var x = parse( state.arrival ); x < parse( d ); x += DAY ) {
				if ( ! ( bits( iso( x ) ) & 1 ) ) {
					return 1;
				}
			}
			var nights = Math.round( ( parse( d ) - parse( state.arrival ) ) / DAY );
			return nights > 90 ? 1 : nights < minStay( state.arrival ) ? 2 : 0;
		}

		/* ---------------------------------------------------------- Calendario */

		function renderCalendar() {
			var first = new Date( start );
			first.setUTCDate( 1 );
			first.setUTCMonth( first.getUTCMonth() + state.month );
			var y = first.getUTCFullYear();
			var m = first.getUTCMonth();
			var lead = ( first.getUTCDay() + 6 ) % 7;
			var days = new Date( Date.UTC( y, m + 1, 0 ) ).getUTCDate();
			var maxMonth = cal.days ? Math.ceil( cal.days.length / 30 ) : 18;
			var hint = state.picking === 'arrival' ? t.pickIn : t.pickOut;
			if ( state.picking === 'departure' && minStay( state.arrival ) > 1 ) {
				hint += ' · ' + t.minStay.replace( '%d', minStay( state.arrival ) );
			}

			var html = '<div class="mis-cal__bar"><button type="button" data-month="-1" aria-label="' + t.prev + '"' + ( state.month <= 0 ? ' disabled' : '' ) + '>‹</button>'
				+ '<strong>' + monthName.format( first ) + '</strong>'
				+ '<button type="button" data-month="1" aria-label="' + t.next + '"' + ( state.month >= maxMonth ? ' disabled' : '' ) + '>›</button></div>'
				+ '<p class="mis-cal__hint">' + hint + '</p><div class="mis-cal__grid" role="grid">';
			t.weekdays.forEach( function ( w ) {
				html += '<span class="mis-cal__wd" aria-hidden="true">' + w + '</span>';
			} );
			for ( var i = 0; i < lead; i++ ) {
				html += '<span></span>';
			}
			for ( var d = 1; d <= days; d++ ) {
				var date = iso( Date.UTC( y, m, d, 12 ) );
				var cls = [];
				var ok;
				var title = '';
				if ( state.picking === 'arrival' ) {
					ok = canArrive( date );
				} else {
					var ds = departureState( date );
					ok = ds === 0 || ( ds !== 0 && canArrive( date ) && date < state.arrival );
					if ( ds === 2 ) {
						title = t.minStay.replace( '%d', minStay( state.arrival ) );
						cls.push( 'is-short' );
					}
				}
				if ( index( date ) >= 0 && ! ( bits( date ) & 1 ) ) {
					cls.push( 'is-busy' );
				}
				if ( date === state.arrival ) {
					cls.push( 'is-start' );
				}
				if ( date === state.departure ) {
					cls.push( 'is-end' );
				}
				if ( state.arrival && state.departure && date > state.arrival && date < state.departure ) {
					cls.push( 'is-range' );
				}
				html += '<button type="button" data-date="' + date + '" class="' + cls.join( ' ' ) + '"' + ( ok ? '' : ' disabled' ) + ( title ? ' title="' + title + '"' : '' ) + ' aria-label="' + long.format( parse( date ) ) + '">' + d + '</button>';
			}
			html += '</div>';
			if ( state.arrival ) {
				html += '<button type="button" class="mis-cal__clear" data-clear>' + t.clear + '</button>';
			}
			panel.innerHTML = html;
		}

		function openCalendar( which ) {
			state.picking = which === 'departure' && state.arrival ? 'departure' : 'arrival';
			var ref = state.picking === 'departure' ? state.arrival : state.arrival || '';
			state.month = ref ? monthOffset( ref ) : 0;
			panel.hidden = false;
			renderCalendar();
			root.querySelectorAll( '[data-pick]' ).forEach( function ( b ) {
				b.classList.toggle( 'is-active', b.getAttribute( 'data-pick' ) === state.picking );
			} );
		}
		function closeCalendar() {
			panel.hidden = true;
			root.querySelectorAll( '[data-pick]' ).forEach( function ( b ) {
				b.classList.remove( 'is-active' );
			} );
		}
		function monthOffset( d ) {
			var a = new Date( start );
			var b = new Date( parse( d ) );
			return Math.max( 0, ( b.getUTCFullYear() - a.getUTCFullYear() ) * 12 + b.getUTCMonth() - a.getUTCMonth() );
		}

		panel.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( 'button' );
			if ( ! btn || btn.disabled ) {
				return;
			}
			if ( btn.hasAttribute( 'data-month' ) ) {
				state.month += +btn.getAttribute( 'data-month' );
				renderCalendar();
				return;
			}
			if ( btn.hasAttribute( 'data-clear' ) ) {
				state.arrival = state.departure = '';
				showDates();
				showQuote( null );
				openCalendar( 'arrival' );
				return;
			}
			var date = btn.getAttribute( 'data-date' );
			if ( state.picking === 'departure' && departureState( date ) === 0 ) {
				state.departure = date;
				showDates();
				closeCalendar();
				fetchQuote();
				return;
			}
			state.arrival = date;
			state.departure = '';
			showDates();
			showQuote( null );
			state.picking = 'departure';
			renderCalendar();
			root.querySelectorAll( '[data-pick]' ).forEach( function ( b ) {
				b.classList.toggle( 'is-active', b.getAttribute( 'data-pick' ) === 'departure' );
			} );
		} );

		root.querySelectorAll( '[data-pick]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var which = b.getAttribute( 'data-pick' );
				if ( ! panel.hidden && state.picking === which ) {
					closeCalendar();
				} else {
					openCalendar( which );
				}
			} );
		} );

		function showDates() {
			root.querySelector( '[data-show=arrival]' ).textContent = state.arrival ? long.format( parse( state.arrival ) ) : t.add;
			root.querySelector( '[data-show=departure]' ).textContent = state.departure ? long.format( parse( state.departure ) ) : t.add;
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
			if ( q.deposit ) {
				html += '<p class="mis-resv__note">' + esc( q.deposit ) + '</p>';
			}
			html += '<p class="mis-resv__note">' + esc( q.note ) + '</p>';
			quoteEl.innerHTML = html;
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
