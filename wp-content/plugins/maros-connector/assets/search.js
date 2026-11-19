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
		var picker = window.MisCalendar.range( {
			panel: panel,
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
