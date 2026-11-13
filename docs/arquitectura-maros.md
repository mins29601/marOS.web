# Nueva web marbellainstyle.com — Arquitectura y decisiones

Documento de traspaso. Resume la evaluación hecha el 2026-11-11 para migrar www.marbellainstyle.com
(WordPress actual) a una nueva web conectada con marOS.

## 1. Decisiones tomadas

| Tema | Decisión |
|---|---|
| CMS | **WordPress estándar** (cualquier tema y plugin: WPML/Polylang, SEO, maquetadores…) |
| Hosting WordPress | **IONOS Unlimited Plus** (hosting compartido, acceso SSH para Git) |
| CDN / seguridad | **Cloudflare delante de IONOS**: nameservers a Cloudflare, APO, WAF, Cloudflare Access para staging |
| Entornos | **Producción** (`www.`) y **staging** (`staging.`), instalaciones y bases de datos separadas |
| Fuente de verdad de reservas | **Lodgify** (conectado a Airbnb, Booking.com…). Reservas, calendario y tarifas viven ahí |
| Centro de integración | **marOS** (en Cloudflare). Extiende Lodgify con tareas, depósitos, MCP… |
| API | **API pública de marOS en marOS.App (Cloudflare)**. La consumen la web **y** los colaboradores (propiedades, disponibilidad, precios, reservas) |
| Web ↔ marOS | La web **nunca** llama a Lodgify directamente; solo a la API de marOS |
| MCP en WordPress | Plugin oficial **MCP Adapter** (WP ≥ 6.9, Abilities API en el núcleo) + *Enable Abilities for MCP*. Primero en staging |
| Pagos (fase 1) | **Enlace de pago de Lodgify** (no tocamos datos de tarjeta) |

## 2. Diagrama

```
 Airbnb · Booking.com ◄──► LODGIFY  (fuente de verdad)
                              ▲  │ webhooks (firmados) + reconciliación nocturna
           crear reserva /    │  ▼
           enlace de pago  ┌──────────────────────────────┐
                           │  marOS.App (Cloudflare)      │
                           │  Workers · D1 · Queues · Cron│
                           │  ┌────────────────────────┐  │
                           │  │ API pública v1         │  │
                           │  │ claves + scopes        │  │
                           │  └────────────────────────┘  │
                           └──────┬───────────────┬───────┘
                                  │               │
                   colaboradores ◄┘               └► plugin WP "marOS Connector"
                                                          │
                     Cloudflare (DNS · APO · WAF · Access)
                          │                         │
              WordPress PROD (IONOS)        WordPress STAGING (IONOS)
                                            (Access + noindex, apunta a marOS staging)
```

## 3. Lo que hay que construir en marOS.App

### 3.1 Integración Lodgify → marOS
- Suscribir webhooks de Lodgify y **verificar la firma** con el `secret` (solo se devuelve al suscribir; guardarlo).
- Procesar avisos vía **Queues** (no perder eventos), reconciliación completa nocturna con **Cron Triggers**.
- Copia en **D1**: propiedades, room types, calendario de disponibilidad, tarifas.
- Los avisos de reservas traen datos personales del huésped → tratamiento RGPD; **nunca** reenviarlos a colaboradores.

### 3.2 API pública v1 (propuesta inicial — contrastar con lo que ya existe en marOS.App)
- `GET  /v1/properties` — ficha completa (capacidad, dormitorios, baños, fotos, servicios, ubicación, textos)
- `GET  /v1/properties/{id}`
- `GET  /v1/availability?property_id&from&to&guests` — desde la copia en D1
- `POST /v1/quotes` — presupuesto en vivo contra Lodgify (`GET /v2/quote/{propertyId}`), desglosado
- `POST /v1/bookings` — revalida disponibilidad y crea la reserva **en Lodgify**; devuelve URL de pago
- `GET  /v1/bookings/{id}` — estado (solo reservas creadas por ese cliente)
- Webhooks salientes para clientes: `property.updated`, `availability.changed`, `booking.status_changed`
- Autenticación: **API key por cliente** (guardada con hash), **scopes** (`properties:read`, `availability:read`,
  `quotes:write`, `bookings:write`), rate limiting por cliente, tarifas/comisiones por colaborador (fase 4).
- Ids de propiedad: marOS usa `lodgify-<id>` (p. ej. `lodgify-373016` = Villa Feliz); `villa-concha` y
  `villa-flamingos` no vienen de Lodgify → revisar cómo tratarlas.

### 3.3 Flujo de reserva
1. Web → `GET /v1/availability` + `POST /v1/quotes` (marOS consulta el quote de Lodgify en vivo).
2. Huésped rellena datos → web → `POST /v1/bookings`.
3. marOS revalida disponibilidad → `POST /v1/reservation/booking` en Lodgify con `status: Tentative`,
   `total: null` (Lodgify calcula), `origin` = web/colaborador, `payment_website_id`.
4. marOS crea enlace de pago (`Creates a payment link for a booking`) → redirige al huésped.
5. Webhook `booking_status_change_booked` → marOS confirma. Si no hay pago en X min → `declined` y se liberan fechas.

### 3.4 Entornos
- marOS **staging** y **production** separados (`wrangler` environments), con su propia D1 y claves.
- Lodgify no tiene sandbox → crear una **propiedad de prueba en Lodgify sin canales**; marOS staging solo
  puede **escribir** (crear reservas) sobre esa propiedad. Lectura de datos reales permitida.

## 4. Lo que hay que construir en WordPress (fuera de marOS.App)
- Plugin propio **"marOS Connector"**:
  - CPT **Villa** sincronizado desde `/v1/properties` (marOS avisa a WP al cambiar + cron real de IONOS como respaldo;
    WP-Cron no es fiable en hosting compartido).
  - Campos operativos de solo lectura (los manda marOS); textos de marketing, SEO y traducciones se editan en WP.
  - Bloques Gutenberg / shortcodes: buscador, calendario, presupuesto, botón reservar.
  - **Proxy servidor** hacia marOS: la API key nunca llega al navegador; respuestas de disponibilidad sin caché de página.
  - Registro de **Abilities** para el MCP Adapter (editar textos de villa, crear landings…).
  - Ajuste para apuntar a marOS producción o staging.
- Despliegue de tema hijo + plugin con Git (GitHub Actions por SSH a IONOS, o `git pull`).
- Contenido: copiar solo **producción → staging** (WP Migrate / WPvivid).

## 5. API de Lodgify — inventario verificado (docs.lodgify.com, 2026-11-11)
Auth: cabecera `X-ApiKey`, base `https://api.lodgify.com`.

| Área | Endpoint(s) |
|---|---|
| Propiedades | v1/v2 lista y ficha por id; v2 rooms disponibles; v2 propiedades borradas; v1 add-ons y métodos de pago |
| Disponibilidad | v1/v2 calendario por room type, por propiedad o de todas (rango de fechas); v1 actualizar unidades |
| Tarifas | v2 precios por noche y rate settings; v1 actualizar tarifas |
| Presupuesto | `GET /v2/quote/{propertyId}?arrival&departure&roomTypes[0].Id&roomTypes[0].guest_breakdown.adults…&addOns…&promotionCode` → `total_including_vat`, `total_excluding_vat`, `total_vat`, desglose por `price_types` (RoomRate, Promotion, Fee [Cleaning, ServiceFee…], AddOn, Tax) |
| Crear reserva | `POST /v1/reservation/booking` — `property_id`, `arrival`, `departure`, `rooms[]`, `guest`, `status` (Open/Booked/Declined/Tentative), `bookability` (InstantBooking/BookingRequest/EnquiryOnly), `total` (null = Lodgify calcula), `currency_code`, `payment_website_id`, `origin`, `source_text`, `messages[]`; `?from=<enquiryId>` convierte una consulta. Respuesta 201 = id (int) |
| Estados | PUT book / tentative / decline / reopen; update; trash / restore; check-in / check-out |
| Pagos | crear / obtener enlace de pago (v1 y v2); solicitar pago (email al huésped) |
| Webhooks | `POST /webhooks/v1/subscribe` (`target_url` único, `event`) → `{id, secret}`; listar; desuscribir. Eventos: `rate_change`, `availability_change`, `booking_new_any_status`, `booking_new_status_booked`, `booking_change`, `booking_status_change_booked/_tentative/_open/_declined`, `guest_message_received` |

**Pendiente de verificar con llamadas reales:**
- Rate limits de la API (no documentados donde miré).
- Qué devuelve exactamente la ficha de propiedad (textos por idioma, galería de fotos, servicios).
- Si una reserva `Tentative` bloquea el calendario y se propaga a los canales.
- Cómo se detecta el pago completado (¿pasa a `Booked` sola? ¿webhook específico?).

## 6. Fases
1. **Infraestructura web**: Cloudflare delante de IONOS, prod + staging, Git, MCP Adapter en staging.
2. **API marOS lectura**: propiedades + disponibilidad; plugin sincronizando villas.
3. **Reservas**: quote + booking desde la web contra Lodgify, cobro con enlace de pago de Lodgify.
4. **Colaboradores**: claves, scopes, tarifas por colaborador, documentación (OpenAPI), webhooks salientes.
5. **Opcional**: pagos/depósitos gestionados por marOS (Stripe) en lugar de Lodgify.
