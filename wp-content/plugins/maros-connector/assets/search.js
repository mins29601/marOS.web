/**
 * Buscadores ([maros_buscador] y [mis_search] de la portada): las fechas se eligen con el
 * calendario de la web. En Alojamiento, además: la salida va después de la llegada, las fechas van
 * juntas (las dos o ninguna) y los campos vacíos no se envían, para que la URL quede limpia.
 */
( function () {
	// Las fechas usan el calendario de la web (assets/calendar.js), el mismo de la reserva de la
	// ficha. Sin JavaScript quedan los campos de fecha del navegador.
	document.querySelectorAll( 'form.mis-search' ).forEach( function ( form ) {
		var inputs = { arrival: form.querySelector( 'input[name=checkin]' ), departure: form.querySelector( 'input[name=checkout]' ) };
		if ( ! inputs.arrival || ! inputs.departure || ! window.MisCalendar ) {
			return;
		}
		var es = /^es/i.test( document.documentElement.lang || '' );
		var empty = es ? 'Añadir' : 'Add date';
		var panel = document.createElement( 'div' );
		panel.className = 'mis-search__cal';
		panel.hidden = true;
		form.appendChild( panel );

		var buttons = {};
		Object.keys( inputs ).forEach( function ( which ) {
			var input = inputs[ which ];
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'mis-search__date';
			btn.id = input.id;
			input.removeAttribute( 'id' );
			input.type = 'hidden';
			input.after( btn );
			buttons[ which ] = btn;
			input.closest( '.mis-search__field' ).addEventListener( 'click', function () {
				if ( ! panel.hidden && picker.picking() === which ) {
					close();
				} else {
					open( which );
				}
			} );
		} );

		function show() {
			Object.keys( inputs ).forEach( function ( which ) {
				var v = inputs[ which ].value;
				buttons[ which ].textContent = v ? window.MisCalendar.format( v ) : empty;
				buttons[ which ].classList.toggle( 'is-empty', ! v );
			} );
		}
		function mark( which ) {
			Object.keys( buttons ).forEach( function ( k ) {
				buttons[ k ].closest( '.mis-search__field' ).classList.toggle( 'is-active', k === which );
			} );
		}
		// Alojamiento en escritorio: dos meses juntos, como Airbnb (Julio, 25-11-2026).
		var wide = window.matchMedia( '(min-width: 1024px)' );
		var picker = window.MisCalendar.range( {
			panel: panel,
			pair: function () {
				return form.classList.contains( 'mis-search--full' ) && wide.matches;
			},
			arrival: inputs.arrival.value,
			departure: inputs.departure.value,
			onChange: function ( a, d, done ) {
				inputs.arrival.value = a;
				inputs.departure.value = d;
				show();
				var err = form.querySelector( '.mis-search__error' );
				if ( err ) {
					err.hidden = true;
				}
				if ( done ) {
					close();
				} else {
					mark( a ? 'departure' : 'arrival' );
				}
			},
		} );
		function open( which ) {
			picker.set( inputs.arrival.value, inputs.departure.value );
			picker.open( which );
			// Justo debajo de las fechas (en el móvil, el buscador sigue debajo).
			var field = inputs.arrival.closest( '.mis-search__field' );
			panel.style.top = ( field.offsetTop + field.offsetHeight + 4 ) + 'px';
			mark( picker.picking() );
		}
		function close() {
			picker.close();
			mark( '' );
		}
		document.addEventListener( 'click', function ( e ) {
			if ( ! panel.hidden && ! form.contains( e.target ) ) {
				close();
			}
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && ! panel.hidden ) {
				close();
			}
		} );
		form.addEventListener( 'click', function ( e ) {
			// Fuera de las fechas y del calendario (huéspedes, botón…), se cierra.
			if ( ! panel.hidden && ! panel.contains( e.target ) && ! e.target.closest( '.mis-search__field' )?.querySelector( '.mis-search__date' ) ) {
				close();
			}
		} );
		show();
	} );

	document.querySelectorAll( 'form.mis-search--full' ).forEach( function ( form ) {
		var checkin = form.querySelector( '[name=checkin]' );
		var checkout = form.querySelector( '[name=checkout]' );
		var error = form.querySelector( '.mis-search__error' );

		// Huéspedes en escritorio: botón con «− 2 +» en una ventanita, como Airbnb. El desplegable
		// sigue en el formulario (es el que se envía) y es el que se ve en el panel del móvil.
		var guests = form.querySelector( 'select[name=guests]' );
		if ( guests && form.closest( '.mis-stay-search' ) ) {
			var es = /^es/i.test( document.documentElement.lang || '' );
			var field = guests.closest( '.mis-search__field' );
			var max = guests.options.length - 1;
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'mis-search__guests';
			btn.setAttribute( 'aria-expanded', 'false' );
			var pop = document.createElement( 'div' );
			pop.className = 'mis-search__pop';
			pop.hidden = true;
			pop.innerHTML = '<div class="mis-stepper"><span class="mis-stepper__name">' + ( es ? 'Huéspedes' : 'Guests' ) + '</span>'
				+ '<button type="button" data-step="-1" aria-label="' + ( es ? 'Menos' : 'Fewer' ) + '">−</button>'
				+ '<output aria-live="polite"></output>'
				+ '<button type="button" data-step="1" aria-label="' + ( es ? 'Más' : 'More' ) + '">+</button></div>';
			guests.after( btn );
			field.appendChild( pop );
			var paint = function () {
				var n = +guests.value || 0;
				btn.textContent = n ? n + ' ' + ( es ? ( 1 === n ? 'huésped' : 'huéspedes' ) : ( 1 === n ? 'guest' : 'guests' ) ) : guests.options[ 0 ].text;
				btn.classList.toggle( 'is-empty', ! n );
				pop.querySelector( 'output' ).textContent = n;
				pop.querySelector( '[data-step="-1"]' ).disabled = ! n;
				pop.querySelector( '[data-step="1"]' ).disabled = n >= max;
			};
			var show = function ( on ) {
				pop.hidden = ! on;
				btn.setAttribute( 'aria-expanded', on ? 'true' : 'false' );
				field.classList.toggle( 'is-active', on );
			};
			btn.addEventListener( 'click', function () {
				show( pop.hidden );
			} );
			pop.addEventListener( 'click', function ( e ) {
				var step = e.target.closest( '[data-step]' );
				if ( step && ! step.disabled ) {
					guests.value = String( Math.min( max, Math.max( 0, ( +guests.value || 0 ) + +step.getAttribute( 'data-step' ) ) ) || '' );
					guests.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					paint();
				}
			} );
			guests.addEventListener( 'change', paint );
			document.addEventListener( 'click', function ( e ) {
				if ( ! pop.hidden && ! field.contains( e.target ) ) {
					show( false );
				}
			} );
			document.addEventListener( 'keydown', function ( e ) {
				if ( 'Escape' === e.key && ! pop.hidden ) {
					show( false );
					btn.focus();
				}
			} );
			paint();
		}

		form.addEventListener( 'submit', function ( event ) {
			var some = checkin.value || checkout.value;
			var bad = ! checkin.value || ! checkout.value || checkout.value <= checkin.value;
			if ( some && bad ) {
				event.preventDefault();
				error.hidden = false;
				( checkin.value ? checkout : checkin ).focus();
				return;
			}
			error.hidden = true;
			form.querySelectorAll( 'input[name], select[name]' ).forEach( function ( field ) {
				if ( ! field.value || ( 'checkbox' === field.type && ! field.checked ) ) {
					field.disabled = true;
				}
			} );
		} );

		// Al volver atrás, el navegador puede restaurar los campos desactivados.
		window.addEventListener( 'pageshow', function () {
			form.querySelectorAll( '[disabled]' ).forEach( function ( field ) {
				field.disabled = false;
			} );
		} );
	} );
} )();
