/* Mapa de la zona aproximada de la casa (ficha, «Close to the Villa»). Solo un círculo, nunca la
   dirección: las coordenadas ya llegan redondeadas de marOS. Se carga al acercarse a la vista. */
(function () {
	function init(el) {
		var lat = parseFloat(el.dataset.lat), lng = parseFloat(el.dataset.lng);
		if (!window.L || !isFinite(lat) || !isFinite(lng)) return;
		var map = L.map(el, { scrollWheelZoom: false, zoomControl: true, attributionControl: true }).setView([lat, lng], 13);
		map.attributionControl.setPrefix('<a href="https://leafletjs.com" target="_blank" rel="noopener">Leaflet</a>');
		L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 15,
			minZoom: 9,
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'
		}).addTo(map);
		var css = getComputedStyle(el), color = css.getPropertyValue('--mis-mapa-color').trim() || '#A67C36';
		L.circle([lat, lng], { radius: 900, color: color, weight: 2, fillColor: color, fillOpacity: 0.18 }).addTo(map);
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
