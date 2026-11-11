# Research: inventario de la web actual

**Fecha**: 2026-11-11 · **Método**: lectura pública de `robots.txt`, sitemaps de WordPress
(`/wp-sitemap.xml`), portada, `/wp-json/` y una ficha de villa. Sin acceso al panel.
Los recuentos son aproximados (±2) y se cerrarán con un rastreo completo y una exportación desde
el panel (tareas de la spec).

## R1. Plataforma y plugins detectados

| Elemento | Hallazgo | Fiabilidad |
|---|---|---|
| WordPress | sí, sitemaps del núcleo (no hay plugin SEO generando sitemaps) | alta |
| Tema | probablemente **Uncode** (tipo de contenido `uncodeblock`, `?uncodeblock` en robots.txt) — que trae su maquetador (WPBakery) | media, confirmar en panel |
| Idiomas | **WPML 4.6.9**, idiomas `en` (por defecto, sin prefijo), `es`, `de`, `ru`, `fr` (`/es/`, `/de/`…) | alta |
| SEO | ninguno detectado (sin Yoast, Rank Math ni AIOSEO) | media |
| Caché | **WP Rocket** (namespace `wp-rocket/v1`) | alta |
| Cookies | **Complianz** | alta |
| Formularios | **Contact Form 7** (+ `cf7apps`) | alta |
| Opiniones | **Site Reviews** (tipo `site-review`) | alta |
| Analítica | Google Tag Manager `GTM-KN9RLWRH` | alta |
| Motor de reservas | `stay.marbellainstyle.com/en/<id>/<slug>` (formato del sitio web de Lodgify) | media, confirmar |

## R2. Tipos de contenido y URLs (sitemaps)

| Tipo | Nº URLs | Patrón | Notas |
|---|---|---|---|
| Entradas (`post`) | ~66 | `/<slug>/` en la raíz | Blog, guías y **páginas de zona** como entradas (`/sierra-blanca/`, `/milla-de-oro/`, `/guadalmina/`, `/nueva-andalucia/`, `/los-flamingos/`, `/la-alqueria/`, `/rio-real/`, `/estepona/`) |
| Páginas (`page`) | 22 | `/<slug>/` | Incluye restos: `/example-i/`, `/test/`, `/page-404-custom/`, `/no-access/`, `/vacation-rental-lp/` |
| Servicios (`portfolio`) | ~115 | `/services/<categoría>/<slug>/` | Mezcla villas, apartamentos, tours, transporte, catering, eventos, landings |
| — villas | — | `/services/villas/<slug>/` | p. ej. `villa-centauri`, `villa-concha-puerto-banus`, `villa-adelfa-beach-side` |
| — apartamentos | 6 | `/services/apartments/<slug>/` | casa-cifuentes, casa-sould, casa-paraiso, casa-brisa, casa-coral, casa-acosta |
| Categorías blog | 11 | `/category/<slug>/` | |
| Etiquetas | 40 | `/tag/<slug>/` | ya bloqueadas en robots.txt |
| Categorías de servicios | 32 | `/services_cat/<slug>/` | incluye zonas (`/services_cat/sierra-blanca/`…) y duplicados (`milla-de-oro-2`, `guia-zona-2`) |
| Categoría de página | 1 | `/page_category/area/` | |

**Total en sitemaps**: ~287 URLs, **solo en inglés**. Los sitemaps no listan las versiones
`/es/`, `/de/`, `/ru/`, `/fr/`; la ficha de muestra (Villa Centauro) solo existe en inglés.
Hay que rastrear para saber cuántas traducciones existen de verdad.

## R3. Señales SEO actuales

- Canonical de la portada: `https://marbellainstyle.com/` (**sin www**). La nueva web en `www.`
  supone también un cambio de host → 301 de todo el dominio desnudo a `www.`.
- `robots.txt` sin línea `Sitemap:`; bloquea `/tag/`, `/page/`, `/feed/`, `?attachment`, etc.
- `og:locale` en_US con alternativo zh_CN (resto de una configuración antigua).
- Enlaces del menú a `/villas/apartments` (no está en sitemaps; comprobar si da 404).

## R4. Ficha de villa actual (Villa Centauro)

- URL `/services/villas/villa-centauri/`, título «Villa Centauro – Luxury 5BR Golf Villa by
  Puerto Banus – Marbella» (el slug no coincide con el nombre).
- Datos en la página: 5 dormitorios, 10 huéspedes, 5,5 baños, depósito 3.000 €, 50 % al reservar,
  entrada 16:00, salida 11:00, 5 opiniones.
- Reservar: enlace «Book direct and save» a `stay.marbellainstyle.com`; formulario
  «Request More Information»; enlaces de texto a Airbnb, Booking.com y Vrbo.

## R5. Menú principal (para la arquitectura de información nueva)

Stay (Apartments, Luxury Vacation Villas, All Vacation Rentals → `stay.`) · Concierge (Tours,
Private Chef, Villa Catering, Chauffeur, Airport Transfers, Golf Transfers, Transport, Blog) ·
Celebrate (Weddings, Private Events, XMAS & NYE Menus, BBQ, Mobile Bar, Party Planner, Events
Gallery) · Property Management (Vacation Rental Management, Property Management, Homeowners).

## Pendiente de obtener

- Lista completa de URLs con tráfico y enlaces: Search Console (12 meses), GA4 (páginas de
  destino), rastreo completo (Screaming Frog o script) incluyendo `/es/ /de/ /ru/ /fr/`.
- Desde el panel: tema y versión, lista de plugins activos, licencias (Uncode, WPML, WP Rocket),
  traducciones por idioma, formularios y a dónde envían.
