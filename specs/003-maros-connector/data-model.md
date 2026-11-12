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
| `{prefix}maros_rates` | `property_id`, `room_type_id`, `date`, `amount`, `currency`, `synced_at` | Para el precio «desde» y para ordenar; el precio final siempre lo da marOS |

Cada carga de una propiedad reemplaza su calendario entero en una transacción (todo o nada).

## Datos que NO se guardan en WordPress

- Presupuestos: se piden a marOS al reservar.
- Datos de huéspedes: se reenvían a marOS y se descartan.
- Reservas: solo `maros_booking_id`, estado y fecha, en una tabla propia con borrado a 90 días.

## Opciones del plugin (no secretas)

- Última sincronización completa, errores recientes, tamaño de cola.
- Mapeo de servicios marOS → términos.

## Constantes en `wp-config.php` (secretas o de entorno)

`MAROS_ENV` (`production` | `staging` | `mock`), `MAROS_API_BASE`, `MAROS_API_KEY`,
`MAROS_WEBHOOK_SECRET`.
