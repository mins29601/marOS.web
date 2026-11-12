# Research: webs de referencia

**Fecha**: 12 de noviembre de 2026 · **Pedido por**: Julio («analiza la web de Le Collectionist…
Vacation Marbella, myprivatevillas.com. Me gusta el minimalismo de aman.com. El diseño tiene que
ser mobile first»).

**Método**: lectura pública de las páginas (HTML estático). Lo que se carga con JavaScript
(filtros, mapas, barras fijas en móvil) puede no aparecer; se marca «no visto». Para My Private
Villas el servidor solo devolvió texto de la portada, sin enlaces: queda pendiente (Julio puede
pasar una URL de listado o de villa).

## R1. Le Collectionist (premium, referencia principal)

Páginas: `/fr`, `/fr/location-villas-luxe/marbella`, `/fr/location-luxe/villa-roma-marbella`,
`/fr/notre-conciergerie-de-luxe`.

- **Menú**: Destinos (megamenú país › región › destino, con nº de casas), Inspiración (temas),
  Sobre nosotros, Colecciones, Conserjería, Propietarios, Comprar, Agencias. Utilidades: teléfono
  con horario («abierto ahora»), contacto, idioma/moneda, app.
- **URLs**: `/fr/location-villas-luxe/marbella` (destino), `/…/marbella/piscine` (tema dentro del
  destino), `/fr/location-luxe/villa-roma-marbella` (ficha), `/…/photos` (galería aparte).
  Prefijo de idioma en todas.
- **Inicio**: foto grande + titular corto en dos líneas + buscador (destino, fechas, viajeros);
  categorías, colecciones, asesor personal, conserjería, destinos, propietarios, prensa y premios.
- **Listado**: migas, H1, buscador, tarjeta de «asesor» («recibe mi selección»), **temas con
  número** (vistas al mar 26, familias 31, golf 30, mascotas 13, piscina 36…). Tarjeta: nombre,
  lugar, viajeros · dormitorios · baños, 2 etiquetas, nivel de colección, **precio por semana en
  rango**. Filtros, orden y mapa: no vistos.
- **Ficha**: galería «1/45» + «ver todas»; datos en una línea («14 viajeros · 7 dormitorios ·
  7 baños · 748 m²»); descripción corta con «leer más»; 8 imprescindibles con icono; **una tarjeta
  por dormitorio**; equipamiento por zonas; **servicios incluidos y a la carta**; **calendario por
  semanas con precio y estancia mínima en cada fila**; bueno saber; asesor («programar llamada»);
  zona aproximada en mapa; condiciones y cancelación; recomendadas; opiniones con fecha de visita.
- **Conserjería**: 6 servicios estrella, presencia local, experiencias, niveles de servicio por
  colección, testimonios. Sin precios.
- **Copiar**: calendario con precio y mínimo de noches visible; tarjetas por dormitorio; servicios
  incluidos vs a la carta; temas con número; experto local («habla con un asesor»).
- **Evitar**: doble botón «Solicitar / Reservar» (confunde), precio «indicativo», megamenú de 3
  niveles, textos SEO muy largos en destinos, datos contradictorios (horas de entrada).

## R2. Vacation Marbella (competidor local)

Páginas: `/`, `/vacation-rentals/villas/`, `/vacation-rentals/guadalmina-golf-front-villa/`.

- **Menú**: Holiday rentals (megamenú con ~80 enlaces: tipo, zona, característica, dormitorios,
  tipo de estancia, tipo de viajero), Property management, Venta, Qué hacer, Servicios, Opiniones,
  Regalo, Inspiración, Nosotros, Contacto. Sin selector de idioma visible; 16 monedas.
- **URLs**: `/vacation-rentals/<slug>/` (ficha), `/vacation-rentals/<filtro>/`, `/rentals-in/<zona>/`.
- **Inicio**: H1 + buscador (no foto a pantalla completa), premios, Google 4,56 (947), prensa,
  tarjetas, colecciones, Trustpilot, ventajas («mejor precio garantizado», «gestión local»), FAQ.
- **Buscador**: fechas con **flexibilidad ±1/2/3/7 días**, zona, huéspedes y panel de filtros
  (precio/noche, dormitorios, baños, tipo, equipamiento).
- **Tarjeta**: tipo, m², huéspedes · dormitorios, etiquetas, «Desde 300 €/noche» + «incluye
  suministros, limpieza y seguro», valoración, insignias («Nuevo», «Exclusiva»).
- **Ficha**: galería con pestañas por estancia (piscina, salón, dormitorios…) y plano; valoración;
  datos clave; equipamiento incluido / con coste; «La promesa»; descripción larga; ubicación;
  normas; **lista de extras con precio** (cuna 10 €/día, entrada temprana 100 €…); cancelación;
  botón de WhatsApp fijo.
- **Copiar**: flexibilidad de fechas; qué incluye el precio; extras con precio; plano y tabla de
  dormitorios; galería por estancias; WhatsApp siempre a mano.
- **Evitar**: megamenú enorme, textos SEO densos, insignias y sellos por todas partes, «Book now»
  que no reserva, sin orden ni mapa en el listado.

## R3. Aman (referencia de minimalismo)

Páginas: `/`, `/resorts/amangiri`, `/villas`.

- **Navegación** escondida tras «Menu» (capa), un único **«Reserve» siempre visible** que pide
  destino y fechas primero.
- **Estructura**: una imagen o vídeo a sangre + titular corto; tarjetas con **una foto, un nombre,
  una frase y un enlace**; párrafos de ≤ 80 palabras; subnavegación de la ficha (Galería,
  Alojamiento, Experiencias, Bienestar, Cómo llegar, Contacto).
- **Verbos**: «Descubrir» para explorar; «Ver disponibilidad» / «Reservar» solo para comprometerse;
  «Solicitar información» para villas.
- **Lo que no hay**: precios en tarjetas, valoraciones, insignias, chats emergentes.
- **Color**: fondo arena muy claro (`#f3eee7`); la foto pone el color. Mayúsculas solo en
  etiquetas pequeñas.
- **Móvil**: recortes de imagen específicos para móvil; menú plegado.

## R4. My Private Villas

Solo se pudo leer el texto de la portada: «club» de propietarios que alquilan directo y más
barato, vídeo del mes, oferta especial (con fechas pasadas), boletín. Sin estructura legible.
**Pendiente**: URL de un listado o una villa para analizarla.

## Conclusiones para nuestra web (propuesta, sin cambiar aún los mockups)

**Mobile first**: se diseña primero a 390 px y se amplía. Cada pantalla de la spec 005 tendrá su
versión móvil como referencia principal; la de escritorio es la ampliación.

1. **Calma de Aman, información de Le Collectionist.** Portada y listados minimalistas (foto
   grande, poco texto, una acción por bloque); la ficha sí da toda la información, pero plegada
   en secciones («leer más», acordeones) para no abrumar en el móvil.
2. **Una sola acción principal fija.** En móvil, barra inferior fija con «Ver disponibilidad» (o
   «Reservar» cuando hay fechas). En escritorio, caja lateral fija. Nada de dos botones que
   compiten. WhatsApp como acción secundaria discreta, no un globo flotante.
3. **Menú sencillo**: cabecera con logo, «Reservar» y «Menú» (capa a pantalla completa, también
   en escritorio, como Aman). Máximo dos niveles; nada de megamenús de 80 enlaces.
4. **Tarjeta de propiedad mínima**: foto, nombre, zona, «huéspedes · dormitorios · baños» y, solo
   con fechas elegidas, el total de la estancia. Sin insignias ni sellos.
5. **Ficha**: galería por estancias, datos en una línea, tarjetas por dormitorio, servicios
   incluidos y extras con precio, calendario con precio y estancia mínima, zona aproximada,
   condiciones claras, opiniones con fecha. Precio cerrado (lo confirma marOS), nunca «indicativo».
6. **Buscador** con flexibilidad de fechas (±días) y **temas con número** en Stay y en cada zona
   (piscina climatizada, vistas al mar, familias, golf, mascotas).
7. **Texto corto**: párrafos breves; los textos largos de SEO van al final o en la guía de zona.
8. **Paleta neutra cálida** y la fotografía como protagonista; mayúsculas solo en etiquetas.
9. **Experto local** como hilo de marca: «habla con nosotros» con persona y horario, no un chat
   genérico.

Estas conclusiones se aplicarán a los wireframes cuando Julio dé su feedback (spec 005, paso 1
del proceso de diseño).
