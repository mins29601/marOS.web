# Feature Specification: Plugin marOS Connector

**Feature Branch**: `003-maros-connector`

**Created**: 2026-11-11

**Status**: Borrador de diseño. Depende del contrato de la API de marOS (spec 013 fase C y spec
026 de `mins29601/marOS`); mientras no exista, se trabaja contra un mock.

**Fuentes**:

- [S1] [Arquitectura](../../docs/arquitectura-maros.md), secciones 3.2, 3.3, 3.4 y 4.
- [S2] marOS spec 025 «Catálogo de Lodgify»: qué datos de la propiedad vienen de Lodgify (capacidad,
  fotos, servicios, tipos de alojamiento, calendario, tarifas) y cuáles del expediente de marOS
  (dormitorios, baños, camas, ubicación); marca «publicable en la web»; solo fechas futuras.
- [S3] Constitución de marOS, principio X: la API nunca entrega direcciones exactas, códigos ni
  datos de propietarios o huéspedes.
- [S4] API v1 actual de marOS (`/api/v1`, clave en `Authorization: Bearer` o `x-api-key`, límite
  120 llamadas/minuto por clave).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Las propiedades publicables aparecen solas en WordPress (Priority: P1)

Cuando un administrador marca una propiedad (villa, apartamento u otro tipo) como publicable en
marOS, aparece en WordPress como borrador del tipo Propiedad, con su tipo y sus datos operativos. Un editor añade los textos de marketing, las
traducciones y el SEO, y la publica.

**Acceptance Scenarios**:

1. **Given** una propiedad publicable nueva en marOS, **When** marOS avisa a WordPress (o pasa el
   cron de respaldo), **Then** se crea una Propiedad en **borrador**, con el término de `tipo-propiedad` que diga marOS, con sus campos operativos.
2. **Given** una Propiedad existente, **When** cambian en marOS su capacidad o sus fotos, **Then**
   WordPress actualiza solo los campos operativos y no toca textos, slug ni SEO.
3. **Given** una propiedad que deja de ser publicable, **When** se sincroniza, **Then** la Propiedad
   pasa a borrador (no se borra) y un editor recibe un aviso para poner la redirección.
4. **Given** el mismo aviso dos veces, **When** llega, **Then** no se hace nada la segunda vez.
5. **Given** un editor, **When** intenta cambiar un campo operativo, **Then** el campo se ve pero
   no se puede editar y dice «Viene de marOS».

### User Story 2 - La sincronización es fiable (Priority: P1)

**Acceptance Scenarios**:

1. **Given** un aviso firmado de marOS (`property.updated`), **When** llega a
   `/wp-json/maros/v1/webhook`, **Then** se verifica la firma HMAC y la hora (±5 min), se encola
   la relectura de esa propiedad y se responde 202 en menos de 1 s.
2. **Given** un aviso sin firma, con firma mala o repetido, **When** llega, **Then** se rechaza
   y se registra.
3. **Given** el cron real de IONOS, **When** se ejecuta, **Then** procesa la cola cada 5 minutos
   y, una vez por noche, reconcilia todas las propiedades con `/v1/properties`.
4. **Given** marOS caído, **When** falla la sincronización, **Then** se reintenta con espera
   creciente, la web sigue mostrando los últimos datos y el panel avisa si lleva más de 26 h sin
   sincronizar.
5. **Given** una Propiedad actualizada, **When** termina la sincronización, **Then** se purga su URL
   en Cloudflare (APO).

### User Story 3 - El huésped busca y consulta el calendario con datos de WordPress (Priority: P1)

La API de marOS sirve para **cargar** las propiedades en WordPress, no para cada búsqueda (Julio,
11 de noviembre de 2026). La sincronización guarda en WordPress, además de la ficha, el
calendario de disponibilidad y las tarifas por noche; el buscador y el calendario consultan
solo la base de datos de WordPress.

**Acceptance Scenarios**:

1. **Given** el bloque Buscador, **When** el huésped elige fechas, huéspedes, tipo y zona,
   **Then** ve las propiedades disponibles, calculadas en WordPress con el calendario
   sincronizado, sin ninguna llamada a marOS.
2. **Given** el bloque Calendario en una propiedad, **When** se carga, **Then** muestra los días
   libres, la estancia mínima y los días cerrados a llegada o salida, desde WordPress.
3. **Given** un listado o una ficha, **When** se muestra el precio «desde», **Then** sale de las
   tarifas sincronizadas, nunca calculado de otra forma.
4. **Given** un cambio de disponibilidad en Lodgify, **When** marOS avisa a WordPress
   (`availability.changed`), **Then** el calendario de esa propiedad se recarga en menos de 5
   minutos; sin aviso, la reconciliación nocturna lo corrige.
5. **Given** las búsquedas, **When** se miran sus cabeceras, **Then** llevan `Cache-Control:
   no-store` (o una caché de pocos minutos, a decidir) para no enseñar disponibilidad vieja.
6. **Given** un dato de calendario con más de 26 h sin actualizar, **When** se busca, **Then** la
   propiedad sigue saliendo pero el panel «marOS» avisa a los administradores.

### User Story 3b - El presupuesto final se pide a marOS (Priority: P2, fase 3)

La búsqueda es local, pero el precio exacto y la reserva no se pueden dar sin marOS: el
presupuesto final y la reserva sí llaman a la API, una vez por huésped que va a reservar, a
través del proxy del plugin.

**Acceptance Scenarios**:

1. **Given** el bloque Presupuesto, **When** el huésped elige fechas y pulsa «Ver precio»,
   **Then** WordPress pide `POST /v1/quotes` y muestra el desglose tal como lo devuelve marOS.
2. **Given** cualquier llamada desde el navegador, **When** se inspecciona, **Then** va a
   `/wp-json/maros/v1/…` del propio dominio y nunca contiene la clave de marOS.
3. **Given** un mismo visitante que pide más de N presupuestos por minuto, **When** sigue,
   **Then** recibe 429 (límite en WordPress y en el WAF).

### User Story 4 - El huésped reserva y paga con el enlace de Lodgify (Priority: P2, fase 3)

**Acceptance Scenarios**:

1. **Given** el bloque Reservar con un presupuesto válido, **When** el huésped rellena sus datos,
   acepta las condiciones y pasa Turnstile, **Then** WordPress llama a `POST /v1/bookings` y
   redirige a la URL de pago que devuelve marOS.
2. **Given** esa reserva, **When** termina, **Then** WordPress solo guarda el id de reserva de
   marOS y su estado; no guarda nombre, email ni teléfono del huésped.
3. **Given** marOS responde que las fechas ya no están libres, **When** ocurre, **Then** el
   huésped ve un mensaje claro y fechas alternativas, sin cobro.
4. **Given** el huésped vuelve de pagar, **When** llega a la página de gracias, **Then** ve el
   estado consultado a `GET /v1/bookings/{id}`.

### User Story 5 - Claude edita propiedades y landings por MCP, con revisión (Priority: P2)

**Acceptance Scenarios**:

1. **Given** el MCP (en pruebas o en producción), **When** Claude usa la Ability `maros/update-property-copy`,
   **Then** cambia solo los textos de marketing de una propiedad en un idioma, como revisión
   pendiente o borrador, nunca los campos operativos.
2. **Given** la Ability `maros/create-landing`, **When** se usa, **Then** crea una página en
   borrador con un patrón de bloques aprobado.
3. **Given** un usuario sin permiso, **When** llama a una Ability, **Then** se rechaza.

### User Story 6 - Elegir el entorno de marOS (Priority: P1)

**Acceptance Scenarios**:

1. **Given** `MAROS_ENV` en `wp-config.php` (`production`, `staging` o `mock`), **When** se carga
   el plugin, **Then** usa esa base y esa clave, y la pantalla de ajustes lo muestra sin poder
   cambiarlo desde el panel.
2. **Given** una instalación con `WP_ENVIRONMENT_TYPE` distinto de `production`, **When** se
   configura `MAROS_ENV=production`, **Then** el plugin se niega a llamar a marOS y lo avisa.
3. **Given** `MAROS_ENV=mock`, **When** se usa la web, **Then** el plugin responde con los datos
   de ejemplo del contrato, sin red, y lo indica en una franja visible para los editores.

### Edge Cases

- Una propiedad con varios tipos de alojamiento (room types): el calendario y el presupuesto
  piden el tipo; si solo hay uno, se elige solo.
- Fotos: se descargan a la biblioteca de medios (para texto alternativo, traducciones y URLs
  propias); se identifican por hash para no duplicarlas; una foto que desaparece en marOS se quita
  de la galería, no de la biblioteca.
- Precio «desde»: solo si marOS lo da; nunca se calcula en WordPress.
- WPML: los campos operativos son iguales en todos los idiomas («copiar» en WPML); los textos se
  traducen.
- Villa Concha y Villa Flamingos tienen ids de marOS que no son `lodgify-<id>`: el plugin usa
  siempre el id de marOS como clave, sea cual sea.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El plugin MUST registrar el tipo de contenido `propiedad` (clave `mis_property`) y
  las taxonomías `tipo-propiedad` (villa, apartamento… según la categoría que dé marOS, nunca
  fijada en el código), `zona` y `equipamiento` (amenities). La URL pública depende del tipo
  (propuesta de Julio: base genérica sin tipología, `/en/str/<slug>/` y `/es/vft/<slug>/`;
  pendiente del estudio de URLs de la spec 002, decisión D6). Los tours y servicios no son de este
  tipo: ver spec 004. Campos en [data-model.md](./data-model.md).
- **FR-002**: Los campos operativos MUST ser de solo lectura en el editor y en la REST de
  WordPress; solo los escribe la sincronización.
- **FR-003**: La sincronización MUST cargar en WordPress la ficha, el calendario (por día y tipo
  de alojamiento: disponible, estancia mínima, cerrado a llegada/salida) y las tarifas por noche
  de cada propiedad publicable, solo fechas futuras. Combina aviso de marOS (webhook firmado,
  `property.updated` y `availability.changed`) + cola (Action Scheduler) + cron real de IONOS
  cada 5 min + reconciliación nocturna completa.
- **FR-003b**: El calendario y las tarifas MUST guardarse en tablas propias indexadas
  (`{prefix}maros_calendar`, `{prefix}maros_rates`), no en metadatos, para que la búsqueda por
  fechas sea rápida. La búsqueda MUST NOT llamar a marOS.
- **FR-004**: El proxy MUST exponer solo `POST quotes`, `POST bookings` y `GET bookings/{id}`
  (de reservas creadas en esa sesión), validar y filtrar parámetros, y añadir la clave en el
  servidor.
- **FR-005**: Los bloques MUST ser bloques de Gutenberg dinámicos (render en servidor + Interactivity
  API), con equivalente en shortcode para cualquier tema o maquetador: `maros/buscador`,
  `maros/calendario`, `maros/presupuesto`, `maros/reservar`, y bloques de datos de la propiedad
  (`maros/ficha`, `maros/galeria`).
- **FR-006**: El plugin MUST registrar Abilities (WordPress Abilities API) con esquema de entrada
  y salida y comprobación de permisos: `maros/list-properties`, `maros/get-property`,
  `maros/update-property-copy`, `maros/create-landing`, `maros/sync-status`, `maros/resync-property`,
  `maros/check-availability` (sobre los datos de WordPress).
  Además `maros/update-portal-ratings` (Julio, 13/11): un agente actualiza por el MCP la nota, el
  nº de opiniones y la URL de cada portal (Airbnb, Booking.com, Vrbo) de una propiedad. Solo toca
  esos campos, se publica directamente (no es contenido editorial) y queda registrado (principio VIII).
- **FR-007**: El entorno de marOS MUST venir de constantes de `wp-config.php`: `MAROS_ENV`,
  `MAROS_API_BASE`, `MAROS_API_KEY`, `MAROS_WEBHOOK_SECRET`.
- **FR-008**: El plugin MUST tener una pantalla «marOS» con estado: entorno, última
  sincronización, cola, errores recientes y botón «Sincronizar ahora».
- **FR-009**: WordPress MUST NOT guardar datos personales de huéspedes (solo id y estado de reserva).
- **FR-010**: Los textos de los bloques MUST ser traducibles (dominio `maros-connector`) y
  compatibles con WPML/Polylang.

## Success Criteria *(mandatory)*

- **SC-001**: Un cambio en marOS (ficha o calendario) se ve en la web en menos de 5 minutos con
  aviso, y en menos de 26 h en el peor caso.
- **SC-001b**: Una búsqueda por fechas responde en menos de 300 ms en el servidor, sin llamadas
  externas.
- **SC-002**: La clave de marOS no aparece en ninguna respuesta, HTML, JS ni registro accesible.
- **SC-003**: Presupuesto mostrado al huésped = presupuesto de marOS al céntimo.
- **SC-004**: Los bloques funcionan con el tema base elegido y con al menos otro tema estándar.

## Assumptions

- marOS expone `/v1/properties` y una descarga de calendario y tarifas por propiedad (o
  `/v1/availability` por rango largo), además de `/v1/quotes` y `/v1/bookings`, según S1 3.2;
  las diferencias se resuelven con issues en `mins29601/marOS` (primera lista: mins29601/marOS#89).
- marOS puede enviar webhooks salientes a WordPress (fase 4 en S1; se necesita antes para la web,
  ver issue en marOS).

## Clarifications

### Session 2026-11-11 (preguntas abiertas)

- Q1: ¿Las opiniones (Site Reviews) siguen en WordPress o vendrán de marOS/Lodgify? → *pendiente*
- Q2: ¿Ruta por tipo (`/villas/<slug>/`, `/apartments/<slug>/`) o la actual `/services/villas/<slug>/`? → *pendiente* (D6)

### Session 2026-11-11 (respuestas de Julio, 22:01)

- «El tipo de contenido villa no es correcto, pues también hay otros tipos de propiedades como
  apartamentos» → el tipo pasa a ser `propiedad`, con la taxonomía `tipo-propiedad`.
- «Habrá otros tipos de datos, como tours» → modelo de contenido en la spec 004.
- Q3: ¿Antes de la fase 3, el botón Reservar enlaza a `stay.marbellainstyle.com`? → *pendiente*

### Session 2026-11-11 (respuestas de Julio, 22:09)

- «La API se usará para cargar las propiedades en el WordPress, no cada vez que haya que hacer
  una búsqueda» → la búsqueda y el calendario usan datos sincronizados en WordPress (US3,
  FR-003, FR-003b).
- «Antes de la reserva habrá que confirmar en tiempo real con la API» (Julio, 22:13) → el
  presupuesto final y la reserva (fase 3) llaman a marOS en el momento (US3b, US4).
