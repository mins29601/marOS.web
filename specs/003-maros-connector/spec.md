# Feature Specification: Plugin marOS Connector

**Feature Branch**: `003-maros-connector`

**Created**: 2026-11-11

**Status**: Borrador de diseño. Depende del contrato de la API de marOS (spec 013 fase C y spec
026 de `mins29601/marOS`); mientras no exista, se trabaja contra un mock.

**Fuentes**:

- [S1] [Arquitectura](../../docs/arquitectura-maros.md), secciones 3.2, 3.3, 3.4 y 4.
- [S2] marOS spec 025 «Catálogo de Lodgify»: qué datos de la villa vienen de Lodgify (capacidad,
  fotos, servicios, tipos de alojamiento, calendario, tarifas) y cuáles del expediente de marOS
  (dormitorios, baños, camas, ubicación); marca «publicable en la web»; solo fechas futuras.
- [S3] Constitución de marOS, principio X: la API nunca entrega direcciones exactas, códigos ni
  datos de propietarios o huéspedes.
- [S4] API v1 actual de marOS (`/api/v1`, clave en `Authorization: Bearer` o `x-api-key`, límite
  120 llamadas/minuto por clave).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Las villas publicables aparecen solas en WordPress (Priority: P1)

Cuando un administrador marca una villa como publicable en marOS, aparece en WordPress como
borrador del tipo Villa con sus datos operativos. Un editor añade los textos de marketing, las
traducciones y el SEO, y la publica.

**Acceptance Scenarios**:

1. **Given** una propiedad publicable nueva en marOS, **When** marOS avisa a WordPress (o pasa el
   cron de respaldo), **Then** se crea una Villa en **borrador** con sus campos operativos.
2. **Given** una Villa existente, **When** cambian en marOS su capacidad o sus fotos, **Then**
   WordPress actualiza solo los campos operativos y no toca textos, slug ni SEO.
3. **Given** una propiedad que deja de ser publicable, **When** se sincroniza, **Then** la Villa
   pasa a borrador (no se borra) y un editor recibe un aviso para poner la redirección.
4. **Given** el mismo aviso dos veces, **When** llega, **Then** no se hace nada la segunda vez.
5. **Given** un editor, **When** intenta cambiar un campo operativo, **Then** el campo se ve pero
   no se puede editar y dice «Viene de marOS».

### User Story 2 - La sincronización es fiable (Priority: P1)

**Acceptance Scenarios**:

1. **Given** un aviso firmado de marOS (`property.updated`), **When** llega a
   `/wp-json/maros/v1/webhook`, **Then** se verifica la firma HMAC y la hora (±5 min), se encola
   la relectura de esa villa y se responde 202 en menos de 1 s.
2. **Given** un aviso sin firma, con firma mala o repetido, **When** llega, **Then** se rechaza
   y se registra.
3. **Given** el cron real de IONOS, **When** se ejecuta, **Then** procesa la cola cada 5 minutos
   y, una vez por noche, reconcilia todas las villas con `/v1/properties`.
4. **Given** marOS caído, **When** falla la sincronización, **Then** se reintenta con espera
   creciente, la web sigue mostrando los últimos datos y el panel avisa si lleva más de 26 h sin
   sincronizar.
5. **Given** una Villa actualizada, **When** termina la sincronización, **Then** se purga su URL
   en Cloudflare (APO).

### User Story 3 - El huésped busca, consulta y pide presupuesto sin ver la clave (Priority: P1)

**Acceptance Scenarios**:

1. **Given** el bloque Buscador, **When** el huésped elige fechas, huéspedes y zona, **Then** ve
   las villas disponibles, consultadas a través del proxy del plugin.
2. **Given** el bloque Calendario en una villa, **When** se carga, **Then** muestra los días libres,
   la estancia mínima y los días cerrados a llegada o salida de los próximos meses.
3. **Given** el bloque Presupuesto, **When** el huésped elige fechas válidas, **Then** ve el
   desglose (estancia, limpieza, tasas, IVA, total) tal como lo devuelve marOS.
4. **Given** cualquier llamada desde el navegador, **When** se inspecciona, **Then** va a
   `/wp-json/maros/v1/…` del propio dominio y nunca contiene la clave de marOS.
5. **Given** las respuestas del proxy, **When** se miran sus cabeceras, **Then** llevan
   `Cache-Control: no-store` y Cloudflare no las guarda.
6. **Given** un mismo visitante que lanza más de N consultas por minuto, **When** sigue, **Then**
   recibe 429 (límite en WordPress y en el WAF).

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

### User Story 5 - Claude edita villas y landings por MCP, con revisión (Priority: P2)

**Acceptance Scenarios**:

1. **Given** el MCP en pruebas, **When** Claude usa la Ability `maros/update-villa-copy`,
   **Then** cambia solo los textos de marketing de una villa en un idioma, como revisión
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

- Una villa con varios tipos de alojamiento (room types): el calendario y el presupuesto
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

- **FR-001**: El plugin MUST registrar el tipo de contenido `villa` y las taxonomías `zona`,
  `tipo-alojamiento` (villa/apartamento) y `servicio` (amenities). Campos en [data-model.md](./data-model.md).
- **FR-002**: Los campos operativos MUST ser de solo lectura en el editor y en la REST de
  WordPress; solo los escribe la sincronización.
- **FR-003**: La sincronización MUST combinar aviso de marOS (webhook firmado) + cola
  (Action Scheduler) + cron real de IONOS cada 5 min + reconciliación nocturna completa.
- **FR-004**: El proxy MUST exponer solo `GET availability`, `POST quotes`, `POST bookings` y
  `GET bookings/{id}` (de reservas creadas en esa sesión), validar y filtrar parámetros, y añadir
  la clave en el servidor.
- **FR-005**: Los bloques MUST ser bloques de Gutenberg dinámicos (render en servidor + Interactivity
  API), con equivalente en shortcode para cualquier tema o maquetador: `maros/buscador`,
  `maros/calendario`, `maros/presupuesto`, `maros/reservar`, y bloques de datos de villa
  (`maros/ficha`, `maros/galeria`).
- **FR-006**: El plugin MUST registrar Abilities (WordPress Abilities API) con esquema de entrada
  y salida y comprobación de permisos: `maros/list-villas`, `maros/get-villa`,
  `maros/update-villa-copy`, `maros/create-landing`, `maros/sync-status`, `maros/resync-villa`,
  `maros/check-availability`.
- **FR-007**: El entorno de marOS MUST venir de constantes de `wp-config.php`: `MAROS_ENV`,
  `MAROS_API_BASE`, `MAROS_API_KEY`, `MAROS_WEBHOOK_SECRET`.
- **FR-008**: El plugin MUST tener una pantalla «marOS» con estado: entorno, última
  sincronización, cola, errores recientes y botón «Sincronizar ahora».
- **FR-009**: WordPress MUST NOT guardar datos personales de huéspedes (solo id y estado de reserva).
- **FR-010**: Los textos de los bloques MUST ser traducibles (dominio `maros-connector`) y
  compatibles con WPML/Polylang.

## Success Criteria *(mandatory)*

- **SC-001**: Un cambio en marOS se ve en la web en menos de 5 minutos con aviso, y en menos de
  26 h en el peor caso.
- **SC-002**: La clave de marOS no aparece en ninguna respuesta, HTML, JS ni registro accesible.
- **SC-003**: Presupuesto mostrado al huésped = presupuesto de marOS al céntimo.
- **SC-004**: Los bloques funcionan con el tema base elegido y con al menos otro tema estándar.

## Assumptions

- marOS expone `/v1/properties`, `/v1/availability`, `/v1/quotes`, `/v1/bookings` según S1 3.2;
  las diferencias se resuelven con issues en `mins29601/marOS` (primera lista: mins29601/marOS#89).
- marOS puede enviar webhooks salientes a WordPress (fase 4 en S1; se necesita antes para la web,
  ver issue en marOS).

## Clarifications

### Session 2026-11-11 (preguntas abiertas)

- Q1: ¿Las opiniones (Site Reviews) siguen en WordPress o vendrán de marOS/Lodgify? → *pendiente*
- Q2: ¿Ruta de las villas: `/villas/<slug>/` o la actual `/services/villas/<slug>/`? → *pendiente* (D6)
- Q3: ¿Antes de la fase 3, el botón Reservar enlaza a `stay.marbellainstyle.com`? → *pendiente*
