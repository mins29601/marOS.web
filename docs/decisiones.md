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

## Decisiones pendientes

| # | Decisión | Opciones | Recomendación | Por qué |
|---|---|---|---|---|
| D1 | Tema base | (a) tema de bloques + hijo; (b) tema clásico con maquetador (Elementor, Uncode/WPBakery, Divi) | **(a) Tema de bloques**: Twenty Twenty-Five como padre (o Ollie si se quiere más diseño de serie) y tema hijo `marbellainstyle` con `theme.json` | Contenido en bloques nativos: lo puede editar el MCP, no hay lock-in de maquetador, es más rápido con APO, y nuestros bloques de reserva encajan sin adaptadores. El maquetador actual obliga a rehacer el contenido igualmente |
| D2 | Plugin de idiomas | WPML · Polylang Pro · TranslatePress | **WPML** | Ya está en uso (en/es/de/ru/fr), mantiene `/es/` etc., buen soporte de tipos de contenido y campos, y facilita migrar las traducciones existentes |
| D3 | Plugin SEO | Yoast · Rank Math · SEOPress | **Yoast SEO** (+ el módulo de WPML para SEO) | La compatibilidad más probada con WPML (hreflang, sitemaps por idioma); hoy no hay ningún plugin SEO. Rank Math es buena alternativa si se quiere schema avanzado gratis |
| D4 | Formularios | Contact Form 7 (actual) · Gravity Forms · Fluent Forms | **Gravity Forms** | Guarda entradas, add-on oficial de WPML, webhooks a n8n/Streak, anti-spam con Turnstile. El formulario de reserva es del plugin propio, no de este |
| D5 | Caché | APO solo · APO + WP Rocket (actual) | **APO solo**, sin WP Rocket | Dos cachés de página se pisan; APO cachea en el borde y purga con el plugin de Cloudflare |
| D6 | URLs | Mantener todo bajo `/services/` · Propiedades por tipo (`/villas/`, `/apartments/`) y tours/servicios en `/services/<tipo>/` | **Propiedades por tipo con 301; tours y servicios conservan `/services/<tipo>/<slug>/`** | Las propiedades son un tipo nuevo y merecen URLs limpias; los ~100 tours y servicios no cambian de URL |
| D8 | Cookies / consentimiento | Complianz (actual) · Cookiebot · CookieYes | **Complianz** | Ya está, Consent Mode v2 con GTM y WPML |
| D9 | Copia producción → pruebas | Script WP-CLI por SSH · WP Migrate Pro · WPvivid | **Script WP-CLI** en GitHub Actions; WPvivid Pro como respaldo y para copias de seguridad | Repetible con un botón y sin licencia; depende de que IONOS tenga WP-CLI |
| D10 | MCP detrás de Access | Excepción de ruta `/wp-json/mcp/*` · token de servicio | **Excepción de ruta** solo en pruebas, protegida por contraseña de aplicación y límite de peticiones | Los conectores de Claude no envían cabeceras de Access; pruebas no tiene datos personales |
| D11 | Opiniones | Site Reviews (actual) · desde Lodgify/Airbnb vía marOS | **Site Reviews** al lanzar | No bloquea; se puede pasar a marOS cuando la API las dé |
| D12 | Copias de seguridad | IONOS · WPvivid/UpdraftPlus a almacenamiento externo | **WPvivid o UpdraftPlus a Google Drive**, diaria BD y semanal archivos | No depender solo del hosting |
| D13 | Correo saliente de WordPress | PHP mail de IONOS · SMTP transaccional (Brevo, Postmark, Google) | **SMTP transaccional** con plugin (WP Mail SMTP o FluentSMTP) | Entregabilidad y registro de envíos |
| D15 | Antes de la fase 3, botón Reservar | Enlace a `stay.` (Lodgify) · formulario de solicitud | **Enlace a `stay.`** | Mantiene las reservas actuales sin esperar a la API |
| D16 | Campos editables de tours y servicios | Secure Custom Fields (gratis, mantenido por WordPress.org) · ACF Pro · solo bloques | **Secure Custom Fields** sobre metadatos registrados | Pantallas de edición cómodas, compatible con WPML, y los datos quedan como metadatos estándar (sirven a REST, MCP y Block Bindings) |
| D17 | Dónde se registran tours y servicios | En el tema · en un plugin propio `mis-contenido` | **Plugin `mis-contenido`** | El contenido sobrevive a un cambio de tema |
