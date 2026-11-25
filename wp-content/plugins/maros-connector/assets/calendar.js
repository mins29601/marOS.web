/**
 * Calendario de fechas de la web (un solo estilo en la reserva de la ficha, el buscador de
 * Alojamiento y el de la portada). Pinta un mes con «‹ ›», marca llegada, salida y las noches
 * entre ellas, y avisa de la estancia mínima.
 *
 * window.MisCalendar.range( {
 *   panel,               elemento donde se pinta
 *   start,               primer día que se puede elegir (AAAA-MM-DD, hoy por defecto)
 *   days,                disponibilidad desde start, un carácter por día (bits: 1 noche libre,
 *                        2 se puede llegar, 4 se puede salir); vacío = todo libre
 *   min,                 estancia mínima por tramos [[día desde start, noches], …]
 *   months,              meses que se pueden recorrer (18 por defecto)
 *   pair,                función que dice si se pintan dos meses juntos (buscador de Alojamiento
 *                        en escritorio, como Airbnb); sin ella, uno
 *   arrival, departure,  fechas de partida
 *   onChange( arrival, departure, done )   done = true al elegir la salida
 * } ) → { open( which ), close(), set( arrival, departure ), el }
 *
 * Los textos van en inglés o español según el idioma de la página (<html lang>).
 */
( function () {
	var DAY = 86400000;
	var ES = /^es/i.test( document.documentElement.lang || '' );
	var T = ES
		? { pickIn: 'Elige el día de llegada', pickOut: 'Elige el día de salida', minStay: 'Estancia mínima: %d noches', clear: 'Borrar fechas', prev: 'Mes anterior', next: 'Mes siguiente', weekdays: [ 'L', 'M', 'X', 'J', 'V', 'S', 'D' ], locale: 'es-ES' }
		: { pickIn: 'Choose your check-in date', pickOut: 'Choose your check-out date', minStay: 'Minimum stay: %d nights', clear: 'Clear dates', prev: 'Previous month', next: 'Next month', weekdays: [ 'M', 'T', 'W', 'T', 'F', 'S', 'S' ], locale: 'en-GB' };

	function iso( t ) {
		return new Date( t ).toISOString().slice( 0, 10 );
	}
	function parse( d ) {
		return Date.parse( d + 'T12:00:00Z' );
	}
	function today() {
		var n = new Date();
		return iso( Date.UTC( n.getFullYear(), n.getMonth(), n.getDate(), 12 ) );
	}

	var short = new Intl.DateTimeFormat( T.locale, { day: 'numeric', month: 'short', timeZone: 'UTC' } );
	var monthName = new Intl.DateTimeFormat( T.locale, { month: 'long', year: 'numeric', timeZone: 'UTC' } );

	function range( o ) {
		var panel = o.panel;
		var start = parse( o.start || today() );
		var days = o.days || '';
		var mins = o.min || [];
		var months = o.months || 18;
		var s = { arrival: o.arrival || '', departure: o.departure || '', picking: 'arrival', month: 0 };

		panel.classList.add( 'mis-cal' );

		function index( d ) {
			return Math.round( ( parse( d ) - start ) / DAY );
		}
		function bits( d ) {
			var i = index( d );
			if ( i < 0 ) {
				return 0;
			}
			if ( ! days ) {
				return 7;
			}
			return i >= days.length ? 0 : +days.charAt( i );
		}
		function minStay( d ) {
			var i = index( d );
			var m = 0;
			mins.forEach( function ( r ) {
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
			if ( ! s.arrival || d <= s.arrival || ! ( bits( d ) & 4 ) ) {
				return 1;
			}
			for ( var x = parse( s.arrival ); x < parse( d ); x += DAY ) {
				if ( ! ( bits( iso( x ) ) & 1 ) ) {
					return 1;
				}
			}
			var nights = Math.round( ( parse( d ) - parse( s.arrival ) ) / DAY );
			return nights > 90 ? 1 : nights < minStay( s.arrival ) ? 2 : 0;
		}
		function offset( d ) {
			var a = new Date( start );
			var b = new Date( parse( d ) );
			return Math.max( 0, ( b.getUTCFullYear() - a.getUTCFullYear() ) * 12 + b.getUTCMonth() - a.getUTCMonth() );
		}

		function month( k, prev, next, last ) {
			var first = new Date( start );
			first.setUTCDate( 1 );
			first.setUTCMonth( first.getUTCMonth() + k );
			var y = first.getUTCFullYear();
			var m = first.getUTCMonth();
			var lead = ( first.getUTCDay() + 6 ) % 7;
			var count = new Date( Date.UTC( y, m + 1, 0 ) ).getUTCDate();
			var html = '<div class="mis-cal__month"><div class="mis-cal__bar">'
				+ ( prev ? '<button type="button" data-month="-1" aria-label="' + T.prev + '"' + ( s.month <= 0 ? ' disabled' : '' ) + '>‹</button>' : '<span></span>' )
				+ '<strong>' + monthName.format( first ) + '</strong>'
				+ ( next ? '<button type="button" data-month="1" aria-label="' + T.next + '"' + ( k >= last ? ' disabled' : '' ) + '>›</button>' : '<span></span>' )
				+ '</div><div class="mis-cal__grid">';
			T.weekdays.forEach( function ( w ) {
				html += '<span class="mis-cal__wd" aria-hidden="true">' + w + '</span>';
			} );
			for ( var i = 0; i < lead; i++ ) {
				html += '<span></span>';
			}
			for ( var d = 1; d <= count; d++ ) {
				var date = iso( Date.UTC( y, m, d, 12 ) );
				var cls = [];
				var ok;
				var title = '';
				if ( s.picking === 'arrival' ) {
					ok = canArrive( date );
				} else {
					var ds = departureState( date );
					ok = ds === 0 || ( canArrive( date ) && date < s.arrival );
					if ( ds === 2 ) {
						title = T.minStay.replace( '%d', minStay( s.arrival ) );
						cls.push( 'is-short' );
					}
				}
				if ( index( date ) >= 0 && ! ( bits( date ) & 1 ) ) {
					cls.push( 'is-busy' );
				}
				if ( date === s.arrival ) {
					cls.push( 'is-start' );
				}
				if ( date === s.departure ) {
					cls.push( 'is-end' );
				}
				if ( s.arrival && s.departure && date > s.arrival && date < s.departure ) {
					cls.push( 'is-range' );
				}
				html += '<button type="button" data-date="' + date + '" class="' + cls.join( ' ' ) + '"' + ( ok ? '' : ' disabled' ) + ( title ? ' title="' + title + '"' : '' ) + ' aria-label="' + short.format( parse( date ) ) + '"' + ( date === s.arrival || date === s.departure ? ' aria-pressed="true"' : '' ) + '>' + d + '</button>';
			}
			return html + '</div></div>';
		}

		function render() {
			var last = days ? Math.ceil( days.length / 30 ) : months;
			var pair = !! ( o.pair && o.pair() );
			var hint = s.picking === 'arrival' ? T.pickIn : T.pickOut;
			if ( s.picking === 'departure' && minStay( s.arrival ) > 1 ) {
				hint += ' · ' + T.minStay.replace( '%d', minStay( s.arrival ) );
			}
			var hintHtml = '<p class="mis-cal__hint' + ( hint.indexOf( ' · ' ) > -1 ? ' has-min' : '' ) + '">' + hint + '</p>';
			var html;
			if ( pair ) {
				// Dos meses: ‹ en el primero y › en el segundo; la pista, encima.
				s.month = Math.min( s.month, Math.max( 0, last - 1 ) );
				html = hintHtml + '<div class="mis-cal__months">' + month( s.month, true, false, last ) + month( s.month + 1, false, true, last ) + '</div>';
			} else {
				html = month( s.month, true, true, last );
				// La pista va entre la cabecera del mes y la rejilla, como siempre.
				html = html.replace( '</div><div class="mis-cal__grid">', '</div>' + hintHtml + '<div class="mis-cal__grid">' );
			}
			panel.classList.toggle( 'is-pair', pair );
			if ( s.arrival ) {
				html += '<button type="button" class="mis-cal__clear" data-clear>' + T.clear + '</button>';
			}
			panel.innerHTML = html;
		}

		panel.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( 'button' );
			if ( ! btn || btn.disabled ) {
				return;
			}
			e.stopPropagation();
			if ( btn.hasAttribute( 'data-month' ) ) {
				s.month += +btn.getAttribute( 'data-month' );
				render();
				return;
			}
			if ( btn.hasAttribute( 'data-clear' ) ) {
				s.arrival = s.departure = '';
				s.picking = 'arrival';
				render();
				o.onChange( '', '', false );
				return;
			}
			var date = btn.getAttribute( 'data-date' );
			if ( s.picking === 'departure' && departureState( date ) === 0 ) {
				s.departure = date;
				o.onChange( s.arrival, s.departure, true );
				return;
			}
			s.arrival = date;
			s.departure = '';
			s.picking = 'departure';
			render();
			o.onChange( s.arrival, '', false );
		} );

		return {
			el: panel,
			picking: function () {
				return s.picking;
			},
			open: function ( which ) {
				s.picking = which === 'departure' && s.arrival ? 'departure' : 'arrival';
				s.month = s.arrival ? offset( s.arrival ) : 0;
				panel.hidden = false;
				render();
			},
			close: function () {
				panel.hidden = true;
			},
			set: function ( a, d ) {
				s.arrival = a || '';
				s.departure = d || '';
			},
		};
	}

	window.MisCalendar = { range: range, format: function ( d ) {
		return d ? short.format( parse( d ) ) : '';
	} };
} )();
