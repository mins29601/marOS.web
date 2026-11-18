/**
 * Yoast SEO: añade al análisis lo que la plantilla de la ficha pinta alrededor del contenido del
 * editor (key features, dormitorios, comodidades, ubicación con enlace a la zona, FAQ y foto
 * principal). Lo genera includes/seo.php en window.marosSeoTemplate. API: YoastSEO.app.registerModification.
 */
( function () {
	var html = window.marosSeoTemplate || '';
	if ( ! html ) {
		return;
	}
	function register() {
		var app = window.YoastSEO && window.YoastSEO.app;
		if ( ! app || ! app.registerPlugin || register.done ) {
			return;
		}
		register.done = true;
		app.registerPlugin( 'marosFicha', { status: 'ready' } );
		app.registerModification( 'content', function ( content ) {
			return ( content || '' ) + html;
		}, 'marosFicha', 5 );
		if ( app.pluginReloaded ) {
			app.pluginReloaded( 'marosFicha' );
		}
	}
	if ( window.YoastSEO && window.YoastSEO.app ) {
		register();
	} else {
		window.addEventListener( 'YoastSEO:ready', register );
		document.addEventListener( 'YoastSEO:ready', register );
	}
} )();
