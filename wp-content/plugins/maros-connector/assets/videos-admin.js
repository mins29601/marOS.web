/**
 * Caja «Videos» del editor de la propiedad: filas con enlace, carátula y orden. Guarda la lista
 * como JSON en un campo oculto (mis_videos_json), que se sanea en PHP al guardar.
 */
( function ( $ ) {
	var t = window.marosVideosAdmin || {};
	var box = document.querySelector( '.mis-videos-admin' );
	if ( ! box ) {
		return;
	}
	var list = box.querySelector( '.mis-videos-admin__list' );
	var field = box.querySelector( 'input[name="mis_videos_json"]' );
	var rows = [];
	try {
		rows = JSON.parse( box.getAttribute( 'data-rows' ) || '[]' ) || [];
	} catch ( e ) {
		rows = [];
	}
	var valid = /^https:\/\/([a-z0-9-]+\.)*(instagram\.com|youtube\.com|youtu\.be|tiktok\.com)\//i;

	function save() {
		field.value = JSON.stringify(
			rows
				.filter( function ( r ) {
					return r.url.trim() !== '';
				} )
				.map( function ( r ) {
					return { url: r.url.trim(), cover: r.cover || 0 };
				} )
		);
	}

	function button( label, onClick, cls ) {
		var b = document.createElement( 'button' );
		b.type = 'button';
		b.className = 'button button-small ' + ( cls || '' );
		b.textContent = label;
		b.addEventListener( 'click', onClick );
		return b;
	}

	function render() {
		list.innerHTML = '';
		rows.forEach( function ( row, i ) {
			var li = document.createElement( 'li' );
			li.className = 'mis-videos-admin__row';

			var thumb = document.createElement( row.thumb ? 'img' : 'span' );
			thumb.className = 'mis-videos-admin__thumb';
			if ( row.thumb ) {
				thumb.src = row.thumb;
				thumb.alt = '';
			} else {
				thumb.title = t.noCover || '';
			}
			li.appendChild( thumb );

			var input = document.createElement( 'input' );
			input.type = 'url';
			input.className = 'mis-videos-admin__url';
			input.placeholder = 'https://www.instagram.com/reel/…';
			input.setAttribute( 'aria-label', t.url || 'Link' );
			input.value = row.url;
			var bad = document.createElement( 'p' );
			bad.className = 'mis-videos-admin__bad';
			function check() {
				bad.textContent = row.url.trim() && ! valid.test( row.url.trim() ) ? t.invalid || '' : '';
			}
			input.addEventListener( 'input', function () {
				row.url = input.value;
				check();
				save();
			} );
			li.appendChild( input );

			li.appendChild(
				button( row.cover ? t.changeCover : t.cover, function () {
					var frame = wp.media( { title: t.frameTitle, library: { type: 'image' }, multiple: false } );
					frame.on( 'select', function () {
						var a = frame.state().get( 'selection' ).first().toJSON();
						row.cover = a.id;
						row.thumb = ( a.sizes && a.sizes.thumbnail && a.sizes.thumbnail.url ) || a.url;
						render();
					} );
					frame.open();
				} )
			);
			if ( row.cover ) {
				li.appendChild(
					button( t.removeCover, function () {
						row.cover = 0;
						row.thumb = '';
						render();
					}, 'button-link' )
				);
			}
			if ( i > 0 ) {
				li.appendChild(
					button( '↑', function () {
						rows.splice( i - 1, 0, rows.splice( i, 1 )[ 0 ] );
						render();
					}, 'mis-videos-admin__up' )
				).setAttribute( 'aria-label', t.up || 'Up' );
			}
			if ( i < rows.length - 1 ) {
				li.appendChild(
					button( '↓', function () {
						rows.splice( i + 1, 0, rows.splice( i, 1 )[ 0 ] );
						render();
					}, 'mis-videos-admin__down' )
				).setAttribute( 'aria-label', t.down || 'Down' );
			}
			li.appendChild(
				button( t.remove, function () {
					rows.splice( i, 1 );
					render();
				}, 'button-link-delete' )
			);
			li.appendChild( bad );
			check();
			list.appendChild( li );
		} );
		save();
	}

	box.querySelector( '.mis-videos-admin__add' ).addEventListener( 'click', function () {
		rows.push( { url: '', cover: 0, thumb: '' } );
		render();
		var inputs = list.querySelectorAll( '.mis-videos-admin__url' );
		inputs[ inputs.length - 1 ].focus();
	} );

	render();
} )( window.jQuery );
