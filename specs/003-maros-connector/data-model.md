# Data Model: Propiedad (marOS Connector)

Una Propiedad es cualquier alojamiento que se alquila a través de marOS: villa, apartamento u
otro tipo que aparezca en marOS. El tipo es un dato (taxonomía), no un tipo de contenido aparte.

Leyenda de origen: **L** = Lodgify vía marOS (spec 025) · **E** = expediente de marOS (spec 003
de marOS) · **M** = marOS (estado) · **WP** = se edita en WordPress.
Los campos L, E y M son de solo lectura en WordPress y se guardan con prefijo `_maros_`.

## Tipo de contenido `propiedad` (`mis_property`)

| Campo | Origen | Traducible | Notas |
|---|---|---|---|
| `maros_id` | M | no | Clave de sincronización (`lodgify-373016`, `villa-concha`…) |
| `lodgify_id` | M | no | Solo informativo |
| `maros_updated_at` / `etag` | M | no | Para saltar actualizaciones iguales |
| Nombre comercial de marOS | M | no | Referencia; el título visible es de WP |
| Tipo de propiedad (villa, apartamento…) | M | no | Categoría de marOS → término de `tipo-propiedad`; no forma parte de la URL (propuesta `str`/`vft`) |
| Capacidad máxima | L | no | |
| Tipos de alojamiento (id, nombre, capacidad, unidades) | L | no | Para calendario y presupuesto |
| Fotos (URL origen, orden, hash) | L | no | Se descargan a Medios; alt y pie son WP |
| Equipamiento / amenities | L | no | Se mapean a la taxonomía `equipamiento` |
| Dormitorios, baños, camas | E | no | |
| Zona / ubicación aproximada | E | no | Sin dirección exacta (principio X de marOS) |
| Horario de entrada y salida, depósito, política | M | no | **Pendiente de confirmar** si marOS lo da; si no, WP |
| Publicable en la web | M | no | Si deja de serlo → borrador |
| Título visible (H1) | WP | sí | |
| Slug | WP | sí | Se conserva el de la web actual (spec 002) |
| Resumen y descripción larga | WP | sí | Bloques de Gutenberg |
| Puntos fuertes, FAQ, «ideal para» | WP | sí | |
| Orden de galería, imagen destacada, alt | WP | sí | |
| SEO (title, meta, OG, schema) | WP | sí | Plugin SEO (D3) |
| Opiniones destacadas | WP | sí | Pendiente Q1 |
| Gestión: exclusiva (gestión directa) o colaborador | WP | no | Distintivo «Exclusiva» en tarjetas y ficha (Julio, 12/11). **Pendiente**: si marOS sabe quién gestiona cada casa, pasa a origen M |
| Persona de contacto (nombre, foto) | WP | sí | «[Nombre] será tu contacto» |
| Vídeos (enlace, plataforma, carátula) | WP | sí | Hoy en Instagram y TikTok; dónde se alojan se decide al desarrollar (D18) |
| Valoración media, nº de opiniones y subvaloraciones (limpieza, exactitud, comunicación, ubicación, llegada, calidad-precio) | WP | no | Se calcula de las opiniones propias (Site Reviews, D11). Origen de las opiniones: **pendiente** |
| Presencia en portales: Airbnb, Booking.com, Vrbo (URL, nota, nº de opiniones, fecha de actualización) | WP | no | Se muestra «También en…» (Julio, 12/11). Los portales no dan API pública de notas: se actualizan a mano o con el MCP |

## Taxonomías

- `zona`: Sierra Blanca, Milla de Oro, Nueva Andalucía… (traducible; enlaza con las landings de zona).
- `tipo-propiedad`: villa, apartamento… Los términos los crea la sincronización a partir de la
  categoría de marOS, con su nombre traducido. Sirve para filtrar y para los listados, no para la URL.
- `coleccion`: grupos editoriales (Exclusivas, Familias, Golf, Grupos…), traducible, la edita el
  equipo en WP (Julio, 12/11). Una casa puede estar en varias.
- `equipamiento`: piscina climatizada, vistas al mar… (términos creados por la sincronización,
  nombres traducibles en WP).

## Calendario y tarifas (tablas propias)

| Tabla | Columnas | Notas |
|---|---|---|
| `{prefix}maros_calendar` | `property_id`, `room_type_id`, `date`, `available`, `min_stay`, `closed_arrival`, `closed_departure`, `synced_at` | Solo fechas futuras (hasta 18 meses, como marOS). Índice por `date, available` |
| `{prefix}maros_rates` | `property_id`, `room_type_id`, `date`, `min_stay`, `max_stay`, `amount`, `currency`, `synced_at` | Una fila por tramo de estancia, como Lodgify. Para ordenar y filtrar por precio; el precio final siempre lo da marOS |

Cada carga de una propiedad reemplaza su calendario entero en una transacción (todo o nada).
`property_id` es el id de marOS (igual en todos los idiomas).

## Cómo se mantiene al día (plugin 0.2)

| Cuándo | Qué hace |
|---|---|
| Cada 5 min (cron real de IONOS → `maros_tick`) | Procesa la cola. Cada 15 min además lee la lista de marOS y encola las propiedades cuyo `etag` (ficha) o `calendar_etag` haya cambiado |
| Aviso firmado de marOS (`/wp-json/maros/v1/webhook`) | Encola la propiedad; responde 202 |
| 03:30 de Madrid (`maros_nightly`) | Relee todo aunque no haya cambiado y borra los días pasados |
| Fallo | Reintento a los 5, 10, 20… min (máx. 6 h); la web sigue con los últimos datos; aviso en el escritorio si pasan 26 h sin respuesta |
| Propiedad que deja de estar en la lista | Pasa a borrador, se borra su calendario y, si estaba publicada, aviso para poner la redirección |
| Fotos | Como mucho 40 nuevas por pasada; el resto en la siguiente |

## Datos que NO se guardan en WordPress

- Presupuestos: se piden a marOS al reservar.
- Datos de huéspedes: se reenvían a marOS y se descartan.
- Reservas: solo `maros_booking_id`, estado y fecha, en una tabla propia con borrado a 90 días.
- Solicitudes de reserva (D4, Julio 12/11): el formulario de solicitud de una propiedad se envía a marOS,
  que la crea en Lodgify como reserva **Open**; WordPress no guarda la solicitud. Los demás
  formularios (Contact Form 7) solo envían email.

## Opciones del plugin (no secretas)

- Última sincronización completa, errores recientes, tamaño de cola.
- Mapeo de servicios marOS → términos.

## Constantes en `wp-config.php` (secretas o de entorno)

`MAROS_ENV` (`production` | `staging` | `mock`), `MAROS_API_BASE`, `MAROS_API_KEY`,
`MAROS_WEBHOOK_SECRET`.
