/* Mapa de la zona aproximada de la casa (ficha, «Close to the Villa»). Solo un círculo, nunca la
   dirección: las coordenadas ya llegan redondeadas de marOS. Se carga al acercarse a la vista.

   Estilos (data-estilo, constante MIS_MAPA_ESTILO; para comparar en pruebas, ?mapa=<estilo>):
   - sobrio: OpenStreetMap con los colores apagados (CSS, .mis-cerca-mapa--sobrio), acorde a la
     marca. Mismo proveedor de siempre, sin alta ni clave.
   - positron / voyager: mapas base de CARTO (claro y gris / suave en color). Revisar sus
     condiciones de uso comercial antes de dejarlos en producción.
   - osm: OpenStreetMap tal cual. */
(function () {
	var OSM = '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors';
	var CARTO = OSM + ' &copy; <a href="https://carto.com/attributions" target="_blank" rel="noopener">CARTO</a>';
	var STYLES = {
		osm: { url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', attribution: OSM },
		sobrio: { url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', attribution: OSM },
		positron: { url: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', attribution: CARTO, subdomains: 'abcd' },
		voyager: { url: 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', attribution: CARTO, subdomains: 'abcd' }
	};
	function styleOf(el) {
		var asked = (location.search.match(/[?&]mapa=([a-z]+)/) || [])[1];
		return STYLES[asked] ? asked : (STYLES[el.dataset.estilo] ? el.dataset.estilo : 'sobrio');
	}
	function init(el) {
		var lat = parseFloat(el.dataset.lat), lng = parseFloat(el.dataset.lng);
		if (!window.L || !isFinite(lat) || !isFinite(lng)) return;
		var name = styleOf(el), style = STYLES[name];
		el.classList.add('mis-cerca-mapa--' + name);
		// En el móvil el mapa no se arrastra con un dedo: así no atrapa el desplazamiento de la página.
		var map = L.map(el, { scrollWheelZoom: false, dragging: !L.Browser.mobile, tap: false, zoomControl: true, attributionControl: true }).setView([lat, lng], 13);
		map.attributionControl.setPrefix('<a href="https://leafletjs.com" target="_blank" rel="noopener">Leaflet</a>');
		L.tileLayer(style.url, {
			maxZoom: 15,
			minZoom: 9,
			subdomains: style.subdomains || 'abc',
			attribution: style.attribution
		}).addTo(map);
		var css = getComputedStyle(el), color = css.getPropertyValue('--mis-mapa-color').trim() || '#A67C36';
		L.circle([lat, lng], { radius: 900, color: color, weight: 1.5, fillColor: color, fillOpacity: 0.22 }).addTo(map);
		el.classList.add('is-ready');
	}
	function start() {
		var maps = [].slice.call(document.querySelectorAll('.mis-cerca-mapa[data-lat]'));
		if (!maps.length) return;
		if (!('IntersectionObserver' in window)) { maps.forEach(init); return; }
		var o = new IntersectionObserver(function (es) {
			es.forEach(function (e) { if (e.isIntersecting) { o.unobserve(e.target); init(e.target); } });
		}, { rootMargin: '300px 0px' });
		maps.forEach(function (m) { o.observe(m); });
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
