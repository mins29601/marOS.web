/**
 * Caja de reserva de la ficha ([maros_booking], includes/booking.php).
 *
 * Calendario con la copia local de disponibilidad (un carácter por día desde hoy; bits: 1 noche
 * libre, 2 se puede llegar, 4 se puede salir), huéspedes y presupuesto en vivo de marOS a través de
 * /wp-json/maros/v1/quote. En móvil la caja se abre como pantalla de reserva (flujo C) desde la
 * barra fija. Con la solicitud de reserva activa (data.request, spec 026 de marOS), «Reservar» abre
 * el formulario «Solicitar reserva» (C4) y, al enviarlo, «Solicitud enviada» (C5) en la misma página.
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
		var lastQuote = null;

		/* ---------------------------------------------------------- Calendario (assets/calendar.js) */

		var picker = window.MisCalendar.range( {
			panel: panel,
			start: cal.start,
			days: cal.days,
			min: cal.min,
			onChange: function ( a, d, done ) {
				requestId = '';
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
				requestId = '';
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
			lastQuote = q && q.available ? q : null;
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

		// Sin fechas, el botón abre el calendario en vez de ir a Lodgify; con precio, la solicitud (C4).
		cta.addEventListener( 'click', function ( e ) {
			if ( ! cta.hasAttribute( 'data-ready' ) ) {
				e.preventDefault();
				openCalendar( state.arrival && ! state.departure ? 'departure' : 'arrival' );
			} else if ( form && lastQuote ) {
				e.preventDefault();
				openRequest();
			}
		} );

		/* ---------------------------------------------------------- Solicitud de reserva (C4 y C5) */

		var req = data.request ? root.querySelector( '[data-req]' ) : null;
		var form = req && req.querySelector( '[data-req-form]' );
		var f = data.form || {};
		var requestId = '';
		var sending = false;
		var opener = null;
		if ( req ) {
			// Fuera de la caja: en el móvil la columna se oculta y la pantalla de reserva recorta.
			document.body.appendChild( req );
		}

		function uuid() {
			if ( window.crypto && crypto.randomUUID ) {
				return crypto.randomUUID();
			}
			var b = crypto.getRandomValues( new Uint8Array( 16 ) );
			b[ 6 ] = ( b[ 6 ] & 15 ) | 64;
			b[ 8 ] = ( b[ 8 ] & 63 ) | 128;
			var h = Array.prototype.map.call( b, function ( x ) {
				return ( x + 256 ).toString( 16 ).slice( 1 );
			} ).join( '' );
			return h.slice( 0, 8 ) + '-' + h.slice( 8, 12 ) + '-' + h.slice( 12, 16 ) + '-' + h.slice( 16, 20 ) + '-' + h.slice( 20 );
		}

		// Países con su nombre en el idioma de la ficha (Intl), ordenados; se propone el del navegador.
		var COUNTRIES = 'AD AE AF AG AI AL AM AO AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BW BY BZ CA CD CF CG CH CI CK CL CM CN CO CR CU CV CW CY CZ DE DJ DK DM DO DZ EC EE EG ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GT GU GW GY HK HN HR HT HU ID IE IL IM IN IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TG TH TJ TL TM TN TO TR TT TV TW TZ UA UG US UY UZ VA VC VE VG VI VN VU WF WS XK YE YT ZA ZM ZW'.split( ' ' );
		var PREFIX = { ES: '+34', GB: '+44', IE: '+353', DE: '+49', FR: '+33', NL: '+31', BE: '+32', LU: '+352', SE: '+46', NO: '+47', DK: '+45', FI: '+358', IT: '+39', PT: '+351', CH: '+41', AT: '+43', PL: '+48', CZ: '+420', US: '+1', CA: '+1', MX: '+52', AE: '+971', SA: '+966', QA: '+974', IL: '+972', RU: '+7', UA: '+380', AU: '+61', GI: '+350', MA: '+212' };
		function fillCountries() {
			var select = form.elements.country;
			var names = null;
			try {
				names = new Intl.DisplayNames( [ t.locale ], { type: 'region' } );
			} catch ( e ) {}
			var list = COUNTRIES.map( function ( c ) {
				return { code: c, name: names ? names.of( c ) || c : c };
			} );
			list.sort( function ( a, b ) {
				return a.name.localeCompare( b.name, t.locale );
			} );
			list.forEach( function ( c ) {
				var o = document.createElement( 'option' );
				o.value = c.code;
				o.textContent = c.name;
				select.appendChild( o );
			} );
			var guess = ( navigator.language || '' ).split( '-' )[ 1 ];
			if ( guess && COUNTRIES.indexOf( guess.toUpperCase() ) >= 0 ) {
				select.value = guess.toUpperCase();
				form.elements.prefix.value = PREFIX[ select.value ] || '+';
			}
			select.addEventListener( 'change', function () {
				var p = form.elements.prefix;
				if ( PREFIX[ select.value ] && ( p.value === '+' || p.value === '' || Object.keys( PREFIX ).some( function ( k ) {
					return PREFIX[ k ] === p.value;
				} ) ) ) {
					p.value = PREFIX[ select.value ];
				}
			} );
		}

		if ( req ) {
			fillCountries();
		}

		function setAll( sel, text ) {
			req.querySelectorAll( sel ).forEach( function ( el ) {
				el.textContent = text;
			} );
		}
		function fillSummary( q ) {
			setAll( '[data-req-dates]', range( q.arrival, q.departure ) + ' · ' + q.nights_label );
			setAll( '[data-req-guests]', root.querySelector( '[data-show=guests]' ).textContent );
			setAll( '[data-req-total]', q.total );
			setAll( '[data-req-nights]', q.nights_label );
			req.querySelectorAll( '[data-req-deposit]' ).forEach( function ( el ) {
				el.textContent = q.deposit || '';
				el.hidden = ! q.deposit;
			} );
		}

		function alertBox( text ) {
			var a = req.querySelector( '[data-req-alert]' );
			a.textContent = text || '';
			a.hidden = ! text;
		}
		function fieldError( name, text ) {
			var wrap = form.querySelector( '[data-field="' + name + '"]' );
			if ( ! wrap ) {
				return;
			}
			var p = wrap.querySelector( '.mis-req__error' );
			p.textContent = text || '';
			p.hidden = ! text;
			wrap.classList.toggle( 'has-error', !! text );
			var input = form.elements[ name ];
			if ( input && input.setAttribute ) {
				if ( text ) {
					input.setAttribute( 'aria-invalid', 'true' );
				} else {
					input.removeAttribute( 'aria-invalid' );
				}
			}
		}
		function showErrors( errors ) {
			var first = null;
			[ 'first_name', 'last_name', 'email', 'phone', 'country', 'terms' ].forEach( function ( k ) {
				fieldError( k, errors[ k ] );
				if ( errors[ k ] && ! first ) {
					first = form.elements[ k ];
				}
			} );
			if ( first ) {
				first.focus();
			}
			return ! first;
		}

		function phone() {
			var p = form.elements.prefix.value.replace( /[^\d+]/g, '' );
			var n = form.elements.phone.value.trim();
			if ( n.charAt( 0 ) === '+' ) {
				return n;
			}
			p = p.charAt( 0 ) === '+' ? p : '+' + p;
			// El 0 de los números nacionales sobra con el prefijo (salvo en Italia).
			return p + ' ' + ( p === '+39' ? n : n.replace( /^0+/, '' ) );
		}
		function values() {
			var el = form.elements;
			return {
				post: data.post,
				request_id: requestId,
				arrival: state.arrival,
				departure: state.departure,
				adults: state.adults,
				children: state.children,
				infants: state.infants,
				total: lastQuote ? lastQuote.amount : null,
				first_name: el.first_name.value.trim(),
				last_name: el.last_name.value.trim(),
				email: el.email.value.trim(),
				phone: phone(),
				country: el.country.value,
				arrival_time: ( form.querySelector( '[name=arrival_time]:checked' ) || {} ).value || 'unknown',
				message: el.message.value.trim(),
				terms: el.terms.checked,
				marketing: el.marketing.checked,
			};
		}
		// Lo mismo que comprueba el servidor, para avisar junto al campo sin esperar.
		function check( v ) {
			var e = {};
			var m = f.fields || {};
			if ( ! v.first_name ) {
				e.first_name = m.first_name;
			}
			if ( ! v.last_name ) {
				e.last_name = m.last_name;
			}
			if ( ! /^[^\s@]{1,100}@[^\s@]{1,100}\.[^\s@]{2,40}$/.test( v.email ) ) {
				e.email = m.email;
			}
			if ( ! /^\+\d[\d\s().-]{5,24}$/.test( v.phone ) || ! form.elements.phone.value.trim() ) {
				e.phone = m.phone;
			}
			if ( ! /^[A-Z]{2}$/.test( v.country ) ) {
				e.country = m.country;
			}
			if ( ! v.terms ) {
				e.terms = m.terms;
			}
			return e;
		}

		function openRequest() {
			opener = document.activeElement;
			requestId = requestId || uuid();
			fillSummary( lastQuote );
			alertBox( '' );
			form.hidden = false;
			req.querySelector( '[data-req-done]' ).hidden = true;
			req.classList.remove( 'is-done' );
			req.hidden = false;
			document.documentElement.classList.add( 'mis-req-open' );
			req.querySelector( '.mis-req__panel' ).scrollTop = 0;
			req.querySelector( '#mis-req-title' ).focus( { preventScroll: true } );
		}
		function closeRequest() {
			if ( ! req || req.hidden ) {
				return;
			}
			req.hidden = true;
			document.documentElement.classList.remove( 'mis-req-open' );
			if ( opener && opener.focus ) {
				opener.focus( { preventScroll: true } );
			}
		}
		if ( req ) {
			req.querySelectorAll( '[data-req-close]' ).forEach( function ( b ) {
				b.addEventListener( 'click', closeRequest );
			} );
			document.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' && ! req.hidden && ! sending ) {
					e.stopImmediatePropagation();
					closeRequest();
				}
			}, true );
			// El aviso de un campo se quita al corregirlo.
			form.addEventListener( 'input', function ( e ) {
				var wrap = e.target.closest( '[data-field]' );
				if ( wrap && wrap.classList.contains( 'has-error' ) ) {
					var name = wrap.getAttribute( 'data-field' );
					if ( ! check( values() )[ name ] ) {
						fieldError( name, '' );
					}
				}
			} );
			form.addEventListener( 'change', function ( e ) {
				if ( e.target.name === 'terms' && e.target.checked ) {
					fieldError( 'terms', '' );
				}
			} );
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				if ( sending ) {
					return;
				}
				var v = values();
				if ( ! showErrors( check( v ) ) ) {
					alertBox( '' );
					return;
				}
				send( v );
			} );
		}

		function busy( on ) {
			sending = on;
			var b = form.querySelector( '[data-req-send]' );
			b.disabled = on;
			b.textContent = on ? f.sending : f.send;
			form.setAttribute( 'aria-busy', on ? 'true' : 'false' );
		}

		function send( v ) {
			busy( true );
			alertBox( '' );
			fetch( data.request, {
				method: 'POST',
				headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
				credentials: 'same-origin',
				body: JSON.stringify( v ),
			} )
				.then( function ( r ) {
					return r.json().catch( function () {
						return {};
					} ).then( function ( body ) {
						return { status: r.status, body: body };
					} );
				} )
				.then( function ( res ) {
					busy( false );
					var b = res.body || {};
					if ( res.status === 201 && b.reference ) {
						done( b, v );
						return;
					}
					if ( res.status >= 400 && res.status < 500 && res.status !== 429 ) {
						// marOS no ha creado nada: el próximo envío es otra solicitud.
						requestId = uuid();
					}
					if ( b.error === 'invalid' && b.fields ) {
						showErrors( b.fields );
						alertBox( b.message || f.check );
					} else if ( b.error === 'price_changed' && b.quote ) {
						showQuote( b.quote );
						if ( lastQuote ) {
							fillSummary( lastQuote );
						}
						alertBox( b.message );
						req.querySelector( '.mis-req__panel' ).scrollTop = 0;
					} else if ( b.error === 'not_available' && b.quote ) {
						showQuote( b.quote );
						closeRequest();
					} else {
						alertBox( b.message || f.failed );
						req.querySelector( '.mis-req__panel' ).scrollTop = 0;
					}
				} )
				.catch( function () {
					busy( false );
					alertBox( f.failed );
				} );
		}

		function done( b, v ) {
			setAll( '[data-req-ref]', b.reference );
			setAll( '[data-req-thanks]', ( f.thanks || '' ).replace( '%1$s', b.first_name || v.first_name ).replace( '%2$s', b.email || v.email ) );
			if ( b.total ) {
				setAll( '[data-req-total]', b.total );
			}
			if ( b.deposit ) {
				req.querySelectorAll( '[data-req-deposit]' ).forEach( function ( el ) {
					el.textContent = b.deposit;
					el.hidden = false;
				} );
			}
			form.hidden = true;
			form.reset();
			requestId = '';
			req.classList.add( 'is-done' );
			var d = req.querySelector( '[data-req-done]' );
			d.hidden = false;
			req.querySelector( '.mis-req__panel' ).scrollTop = 0;
			d.querySelector( '[data-req-done-title]' ).focus( { preventScroll: true } );
		}

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
