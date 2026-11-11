# Registro de decisiones

Cada decisión lleva fecha, quién la tomó y dónde se aplica. Las pendientes llevan la
recomendación de Claude; Julio decide.

## Decisiones tomadas

| # | Fecha | Decisión | Quién | Fuente |
|---|---|---|---|---|
| T1 | 2026-11-11 | WordPress estándar, admite cualquier tema y plugin | Julio | arquitectura §1 |
| T2 | 2026-11-11 | IONOS Unlimited Plus con SSH; Cloudflare delante (DNS, APO, WAF, Access) | Julio | arquitectura §1 |
| T3 | 2026-11-11 | Producción (`www.`) y pruebas (`staging.`) separadas | Julio | arquitectura §1 |
| T4 | 2026-11-11 | Lodgify es la fuente de verdad; la web solo usa la API de marOS | Julio | arquitectura §1 |
| T5 | 2026-11-11 | MCP Adapter + Enable Abilities for MCP, primero en pruebas | Julio | arquitectura §1 |
| T6 | 2026-11-11 | Pagos fase 1: enlace de pago de Lodgify | Julio | arquitectura §1 |
| T7 | 2026-11-11 | Las decisiones se documentan con spec-kit en GitHub (`mins29601/marOS.web`) | Julio | mensaje del hilo |
| T8 | 2026-11-11 | Las cuestiones de la API se plantean como issues en `mins29601/marOS` | Julio | mensaje del hilo |
| T9 | 2026-11-11 | La web actual está en IONOS; el correo, en Google Workspace | Julio | mensaje del hilo |
| T10 | 2026-11-11 | Idiomas: inglés y español (antes D7) | Julio | mensaje del hilo |
| T11 | 2026-11-11 | Diseño nuevo con tema nuevo (antes D14) | Julio | mensaje del hilo |
| T12 | 2026-11-11 | No hay un tipo «Villa»: el tipo es Propiedad (villas, apartamentos…); habrá otros tipos como tours | Julio | specs 003 y 004 |
| T13 | 2026-11-11 | La migración de contenido tiene su propio plan | Julio | spec 002, plan.md |
| T14 | 2026-11-11 | La API de marOS carga propiedades (y su calendario) en WordPress; no se llama en cada búsqueda | Julio | spec 003 |
| T15 | 2026-11-11 | Dominio y DNS en IONOS | Julio | spec 001 |
| T16 | 2026-11-11 | Tours en WordPress; se reservan en FareHarbor | Julio | spec 004 |
| T17 | 2026-11-11 | De momento no hay más tipos de contenido | Julio | spec 004 |
| T18 | 2026-11-11 | Antes de reservar se confirma en tiempo real con la API (presupuesto y reserva) | Julio | spec 003 |
| T19 | 2026-11-11 | Una base de datos por entorno; Git solo despliega código | Julio | spec 001, plan |
| T20 | 2026-11-11 | Plugin MCP desde el principio, en producción y pruebas (cambia T5) | Julio | spec 001 |
| T21 | 2026-11-11 | El nombre corto de FareHarbor no hace falta por ahora | Julio | spec 004 |
| T22 | 2026-11-11 | Constitución 1.0.0 aprobada | Julio | constitución |
| T23 | 2026-11-11 | La fase 1 usa un dominio temporal; marbellainstyle.com se cambia en el lanzamiento | Julio | spec 001 |
| T24 | 2026-11-11 | Qué contenido se migra se decide página a página en la migración (plan M1) | Julio | spec 002 |
| D2 | 2026-11-11 | Plugin de idiomas: **WPML** | Julio («todo ok», a propuesta de Claude) | docs/decisiones.md |
| D3 | 2026-11-11 | Plugin SEO: **Yoast SEO** (+ el módulo de WPML para SEO) | Julio («todo ok», a propuesta de Claude) | docs/decisiones.md |
| D4 | 2026-11-11 | Formularios: **Gravity Forms** | Julio («todo ok», a propuesta de Claude) | docs/decisiones.md |
| D5 | 2026-11-11 | Caché: **APO solo**, sin WP Rocket | Julio («todo ok», a propuesta de Claude) | docs/decisiones.md |
| D8 | 2026-11-11 | Cookies / consentimiento: **Complianz** | Julio («todo ok», a propuesta de Claude) | docs/decisiones.md |
| D9 | 2026-11-11 | Copia producción → pruebas: **Script WP-CLI** en GitHub Actions; WPvivid Pro como respaldo y para copias de seguridad | Julio («todo ok», a propuesta de Claude) | docs/decisiones.md |
| D10 | 2026-11-11 | MCP de pruebas detrás de Access: **Excepción de ruta** solo en pruebas, protegida por contraseña de aplicación y límite de peticiones | Julio («todo ok», a propuesta de Claude) | docs/decisiones.md |
| D11 | 2026-11-11 | Opiniones: **Site Reviews** al lanzar | Julio («todo ok», a propuesta de Claude) | docs/decisiones.md |
| D12 | 2026-11-11 | Copias de seguridad: **WPvivid o UpdraftPlus a Google Drive**, diaria BD y semanal archivos | Julio («todo ok», a propuesta de Claude) | docs/decisiones.md |
| D13 | 2026-11-11 | Correo saliente de WordPress: **SMTP transaccional** con plugin (WP Mail SMTP o FluentSMTP) | Julio («todo ok», a propuesta de Claude) | docs/decisiones.md |
| D15 | 2026-11-11 | Antes de la fase 3, botón Reservar: **Enlace a `stay.`** | Julio («todo ok», a propuesta de Claude) | docs/decisiones.md |
| D16 | 2026-11-11 | Campos editables de tours y servicios: **Secure Custom Fields** sobre metadatos registrados | Julio («todo ok», a propuesta de Claude) | docs/decisiones.md |
| D17 | 2026-11-11 | Dónde se registran tours y servicios: **Plugin `mis-contenido`** | Julio («todo ok», a propuesta de Claude) | docs/decisiones.md |

## Decisiones pendientes

| # | Decisión | Opciones | Recomendación | Por qué |
|---|---|---|---|---|
| D1 | Tema base | (a) tema de bloques + hijo; (b) tema clásico con maquetador (Elementor, Uncode/WPBakery, Divi) | **(a) Tema de bloques**: Twenty Twenty-Five como padre (o Ollie si se quiere más diseño de serie) y tema hijo `marbellainstyle` con `theme.json` | Contenido en bloques nativos: lo puede editar el MCP, no hay lock-in de maquetador, es más rápido con APO, y nuestros bloques de reserva encajan sin adaptadores. El maquetador actual obliga a rehacer el contenido igualmente |
| D6 | URLs | Ver «Estudio de URLs» en la spec 002 | **En estudio.** Propuesta de Julio: alquileres en `/en/str/<slug>/` y `/es/vft/<slug>/`, sin tipología | Decidir con datos de tráfico: cuántas URLs cambian y cuánto tráfico tienen |

Las filas D… de «Decisiones tomadas» conservan su número original; su justificación está en el historial de este archivo.
