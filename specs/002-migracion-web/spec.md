# Feature Specification: Migración de la web actual

**Feature Branch**: `002-migracion-web`

**Created**: 2026-11-11

**Status**: Borrador. Inventario inicial en [research.md](./research.md) y plan de migración de
contenido en [plan.md](./plan.md).

**Fuentes**:

- [S1] [Arquitectura](../../docs/arquitectura-maros.md), sección 4 (contenido).
- [S2] Inventario público de la web actual del 11 de noviembre de 2026 ([research.md](./research.md)).
- [S3] Constitución de la web, principio V (ninguna URL con valor SEO se pierde).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Inventario completo y decidido (Priority: P1)

Existe una hoja (`redirects/inventario.csv`) con cada URL actual en cada idioma: tipo, idioma,
tráfico y enlaces externos (Search Console/GA4), destino en la web nueva y acción
(`mantener`, `301`, `410`, `fusionar`). Julio decide lo que se elimina.

**Acceptance Scenarios**:

1. **Given** los sitemaps, el rastreo completo y Search Console, **When** se cruzan, **Then**
   cada URL con impresiones, clics o enlaces externos en 12 meses aparece en el inventario.
2. **Given** una URL de prueba o duplicada (`/test/`, `/example-i/`, `milla-de-oro-2`),
   **When** se revisa, **Then** tiene acción `410` o `301` a su equivalente.

### User Story 2 - Redirecciones 301 sin pérdidas (Priority: P1)

**Acceptance Scenarios**:

1. **Given** el mapa `redirects/redirects.csv`, **When** se lanza la web nueva, **Then** cada URL
   antigua responde con **un solo salto** 301 a una URL que da 200 (o 410 si se eliminó).
2. **Given** `https://marbellainstyle.com/<ruta>`, **When** se pide, **Then** redirige en un
   salto a `https://www.marbellainstyle.com/<ruta-nueva>`.
3. **Given** una URL de idioma (`/es/…`), **When** se pide, **Then** redirige a la misma página
   en ese idioma, o a la versión inglesa solo si no hay traducción.
4. **Given** el CI, **When** cambia el mapa, **Then** se comprueba que no hay cadenas ni bucles.

### User Story 3 - El contenido llega a la web nueva (Priority: P1)

Las entradas del blog, páginas, zonas y servicios que se conservan se trasladan al tema nuevo en
bloques de Gutenberg (sin los shortcodes del maquetador actual), con imágenes, texto alternativo,
fechas, autores y traducciones. Las villas llegan desde marOS (spec 003) y sus textos de
marketing se copian de las fichas actuales.

**Acceptance Scenarios**:

1. **Given** una entrada migrada, **When** se abre, **Then** conserva slug, fecha, imágenes y
   traducciones, y no contiene shortcodes del maquetador antiguo.
2. **Given** una villa publicable en marOS con ficha en la web actual, **When** se migra,
   **Then** conserva su slug antiguo (o redirige a él), sus textos de marketing y sus opiniones.

### User Story 4 - Lanzamiento y vigilancia (Priority: P2)

**Acceptance Scenarios**:

1. **Given** el día del lanzamiento, **When** `www.` pasa a la web nueva, **Then** se comprueba
   el mapa completo contra producción, se envía el sitemap nuevo a Search Console y se mantiene
   la web antigua accesible (solo para el equipo) 30 días.
2. **Given** las 8 semanas siguientes, **When** aparecen 404 en Search Console o en Cloudflare,
   **Then** se añaden al mapa en menos de una semana.

### Edge Cases

- Villas antiguas que ya no se gestionan: 301 a la zona o a la lista de villas, no a la portada.
- Villas de marOS que no tenían ficha: URL nueva, sin redirección.
- Idiomas que se abandonen (p. ej. ruso): 301 a la versión inglesa.
- Parámetros (`?uncodeblock`, `?attachment`): se ignoran o 301 a la URL limpia.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: MUST existir un inventario de URLs con origen del dato y decisión por URL.
- **FR-002**: Las redirecciones se aplican en tres capas: (1) Cloudflare Redirect Rules para
  host y patrones (dominio desnudo → `www.`, `/services/villas/*` → nueva ruta de villas);
  (2) Cloudflare Bulk Redirects para el mapa exacto URL → URL; (3) el plugin SEO para las que se
  creen después del lanzamiento al cambiar slugs.
- **FR-003**: El mapa MUST vivir en `redirects/redirects.csv` y validarse en CI.
- **FR-004**: Se MUST mantener los slugs de entradas y páginas que se conservan.
- **FR-005**: Cada idioma MUST tener `hreflang`, canonical y sitemap propios.
- **FR-006**: Los datos de seguimiento (GTM, consentimiento) MUST seguir funcionando el día del
  lanzamiento.

## Success Criteria *(mandatory)*

- **SC-001**: 0 URLs del inventario con 404 tras el lanzamiento.
- **SC-002**: 100 % de redirecciones en un solo salto.
- **SC-003**: Clics orgánicos a las 8 semanas ≥ 90 % de la media de las 4 semanas previas.

## Assumptions

- Julio puede dar acceso de administrador a la web actual (o exportaciones) y a Search Console y GA4.
- El contenido del maquetador actual no se puede copiar tal cual; se reconstruye en bloques
  (parte con scripts, parte a mano, y con ayuda del MCP en pruebas).

## Clarifications

### Session 2026-11-11 (preguntas abiertas)

- Q1: ¿Qué idiomas tendrá la web nueva? Hoy hay en, es, de, ru y fr, pero muchas fichas solo están
  en inglés. → *pendiente* (recomendación: en + es al lanzar; de y fr después si hay demanda)
- Q2: ¿Se migran los ~115 servicios (tours, transporte, catering, eventos) y los ~66 posts, o se
  recorta? → *pendiente* (recomendación: conservar lo que tiene tráfico o enlaces; 301 el resto)
- Q3: ¿Las villas pasan a `/villas/<slug>/` (con 301 desde `/services/villas/`) o se mantiene la
  ruta actual? → *pendiente* (ver decisión D6)
- Q4: ¿`stay.marbellainstyle.com` se mantiene hasta la fase 3 (reservas en la web)? → *pendiente*
  (recomendación: sí, y los botones de reservar enlazan allí hasta entonces)
- Q5: ¿Diseño nuevo o se replica el actual? → *pendiente*

### Session 2026-11-11 (respuestas de Julio, 22:01)

- Q1 → «Inglés y español ok». Alemán, ruso y francés no se migran: 301 a la versión inglesa.
- Q5 → «Será un nuevo diseño, con nuevo tema». El contenido se reconstruye en bloques (plan M3).
- «Para la migración de contenido, necesitaremos un plan específico» → [plan.md](./plan.md).
- Tipos de destino: según la [spec 004](../004-modelo-contenido/spec.md) (propiedades, tours,
  servicios, zonas).

### Session 2026-11-11 (respuestas de Julio, 22:36)

- Propuesta de Julio para alquileres: un slug genérico, sin el tipo de propiedad en la URL:
  `str` (short term rentals) en inglés y `vft` en español, p. ej.
  `marbellainstyle.com/en/str/villa-nido-modern-4br-flamingos`. «La estructura de las URL es algo
  que habrá que estudiar» → **estudio de URLs** (ver más abajo), pendiente de decisión.

- «Cuando se haga la migración, habrá que revisar qué se pasa y qué no» (Julio, 22:38) → Q2 se
  decide página a página en el plan M1, con los datos de tráfico.

## Estudio de URLs (pendiente)

Preguntas que el estudio debe responder con datos de Search Console y del rastreo (plan M0–M1):

1. **Prefijo de idioma para inglés**: hoy el inglés va sin prefijo (`/sierra-blanca/`) y el
   español con `/es/`. La propuesta `/en/str/…` pone prefijo a los dos. Ventaja: estructura
   simétrica y clara. Coste: **todas** las URLs inglesas actuales cambian y necesitan 301
   (inglés es el idioma con todo el tráfico). Alternativa: inglés sin prefijo, `/str/<slug>/` y
   `/es/vft/<slug>/`.
2. **Base de alquileres**: `str`/`vft` (genérico, la URL no cambia si cambia la tipología) frente a
   palabras que la gente busca (`/villas/`, `/rentals/`, `/es/alquiler-vacacional/`). Impacto en
   SEO de la palabra en la URL: pequeño; impacto en confianza y clics: a medir.
3. **Tours y servicios**: mantener `/services/<tipo>/<slug>/` o simplificar.
4. **Zonas**: `/areas/<slug>/` frente a mantener los slugs actuales en la raíz.
5. **Blog**: mantener las entradas en la raíz o moverlas a `/blog/<slug>/`.

Para cada opción: número de URLs que cambian, tráfico afectado y redirecciones necesarias.

