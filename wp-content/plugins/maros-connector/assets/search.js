/**
 * Buscador de Alojamiento ([maros_buscador]): la salida va después de la llegada, las fechas van
 * juntas (las dos o ninguna) y los campos vacíos no se envían, para que la URL quede limpia.
 */
( function () {
	document.querySelectorAll( 'form.mis-search--full' ).forEach( function ( form ) {
		var checkin = form.querySelector( '[name=checkin]' );
		var checkout = form.querySelector( '[name=checkout]' );
		var error = form.querySelector( '.mis-search__error' );

		checkin.addEventListener( 'change', function () {
			if ( ! checkin.value ) {
				return;
			}
			var next = new Date( checkin.value + 'T12:00:00Z' );
			next.setUTCDate( next.getUTCDate() + 1 );
			checkout.min = next.toISOString().slice( 0, 10 );
			if ( ! checkout.value || checkout.value <= checkin.value ) {
				checkout.value = '';
				checkout.focus();
			}
		} );

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
