# Mapa del sitio y correspondencia de URLs

**Fecha**: 2026-11-12 · **Estado**: propuesta para revisar con Julio

**Relacionado**: [spec 005](./spec.md) (menús y wireframes) · [spec 002](../002-migracion-web/spec.md)
(migración y estudio de URLs) · [spec 004](../004-modelo-contenido/spec.md) (tipos de contenido)

Este documento junta tres cosas:

1. **El mapa del sitio nuevo**, con la ruta de cada sección en inglés y en español, alineado
   con los wireframes de la versión 2 (alojamiento, servicios y experiencias al mismo nivel).
2. **El inventario de la web actual**: las 287 URLs de su sitemap, en
   [`redirects/inventario.csv`](../../redirects/inventario.csv).
3. **A dónde va cada URL actual** (redirección 301, o 410 si se retira), en el mismo inventario
   y, listo para Cloudflare, en [`redirects/redirects.csv`](../../redirects/redirects.csv).

Los dos CSV se generan con [`redirects/generar.py`](../../redirects/generar.py) a partir de
[`redirects/urls-actuales.txt`](../../redirects/urls-actuales.txt). Si cambia una ruta (la
decisión D6 sigue en estudio), se cambia una constante del script y se vuelven a generar.

## 1. Supuestos

- **Inglés sin prefijo de idioma y español en `/es/`** (decisión de Julio, 12/11), como hoy.
  Alquileres en `/str/` y `/es/vft/`, como propuso Julio. Así, 34 URLs actuales (páginas legales,
  transporte, catering y eventos) **no cambian** y no necesitan redirección.
- **Dominio final** `https://www.marbellainstyle.com` (hoy el canonical es sin `www`). El dominio
  temporal de la fase 1 no afecta a este mapa.
- **Idiomas con Polylang** (Julio, 12/11, en lugar de WPML). Polylang deja el idioma por defecto
  sin prefijo y el español en `/es/`, así que el mapa no cambia. Lo único que exige la versión
  **Pro** (o código propio en el plugin de contenido) es traducir las bases de las URLs:
  `/str/` ↔ `/es/vft/`, `/services/` ↔ `/es/servicios/`, `/areas/` ↔ `/es/zonas/`. Con Polylang
  gratis, el español tendría que usar las mismas bases que el inglés (`/es/str/…`).
- Solo **inglés y español**. Alemán, ruso y francés redirigen a la página equivalente en inglés.
- El sitemap actual solo lista inglés. Las URLs en `/es/` (y `/de/`, `/ru/`, `/fr/`) se añadirán
  al inventario con el rastreo completo (plan M0.3).

## 2. Mapa del sitio nuevo

Las rutas en español son traducciones de la sección; los slugs de cada ficha se traducen al
migrar el contenido.

```text
Inicio                                   /                          /es/
│
├── Alojamiento (Stay)                   /str/                      /es/vft/
│   ├── Ficha de propiedad               /str/<slug>/               /es/vft/<slug>/
│   ├── Reservar (flujo C)               /str/<slug>/book/          /es/vft/<slug>/reservar/
│   └── Colecciones (pestañas)           /str/?collection=<c>       (filtro, no indexable)
│       Exclusivas · Familias · Golf · Grupos · (candidatas: Primera línea, Villas para eventos)
│
├── Servicios (Concierge)                /services/                 /es/servicios/
│   ├── Transporte                       /services/transport/       /es/servicios/transporte/
│   │   └── chófer, traslados aeropuerto Málaga / Gibraltar / Marbella, traslados de golf
│   ├── Chef y catering                  /services/catering/        /es/servicios/catering/
│   │   └── chef privado, catering, barbacoa, coctelería móvil, menús de Navidad
│   ├── Eventos y bodas                  /services/events/          /es/servicios/eventos/
│   │   ├── organización de eventos, fiestas privadas, wedding planner
│   │   ├── Galería                      /services/events/gallery/
│   │   └── Eventos realizados           /services/events/<slug>/   (si tienen tráfico)
│   └── Conserjería                      /services/concierge/       /es/servicios/conserjeria/
│       └── personal shopper, jet privado, yate, paquete vacacional, personal doméstico
│
├── Experiencias (tours)                 /experiences/              /es/experiencias/
│   └── Tour                             /experiences/<slug>/       /es/experiencias/<slug>/
│
├── Zonas                                /areas/                    /es/zonas/
│   └── Zona                             /areas/<slug>/             /es/zonas/<slug>/
│       Sierra Blanca · Milla de Oro · Nueva Andalucía · Los Flamingos · La Alquería ·
│       Guadalmina · Río Real · Estepona · (sin guía hoy: Benahavís, Sotogrande,
│       El Madroñal, New Golden Mile)
│
├── Propietarios                         /owners/                   /es/propietarios/
│   ├── Gestión de alquiler vacacional   /owners/vacation-rental-management/
│   ├── Gestión de propiedades           /owners/property-management/
│   └── Recursos para propietarios       /owners/resources/   (artículos del Journal)
│
├── Journal                              /journal/                  /es/blog/
│   ├── Entrada                          /journal/<slug>/           /es/blog/<slug>/
│   └── Categoría                        /journal/category/<slug>/
│
├── Nosotros                             /about/                    /es/nosotros/
├── Opiniones                            /reviews/                  /es/opiniones/
├── Trabaja con nosotros                 /work-with-us/             /es/trabaja-con-nosotros/
├── Contacto                             /contact/                  /es/contacto/
├── Legal   /privacy-policy/ · /legal-notice/ · /terms-and-conditions/ · /cookie-policy/
└── Utilidades (noindex)   /thank-you/ · 404 · búsqueda
```

**Qué cambia respecto a hoy**

- El tipo «Services» actual, que mezclaba villas, tours, servicios y landings, se reparte en
  Alojamiento, Experiencias, Servicios y Propietarios.
- Las 8 guías de zona, que hoy son entradas del blog en la raíz (`/sierra-blanca/`), pasan a
  páginas de Zona.
- «Lifestyle» pasa a llamarse **Conserjería** y «Tours» pasa a **Experiencias**, como en los
  menús de la spec 005.
- Desaparecen como páginas públicas las etiquetas (40), las categorías de servicios (32) y la
  categoría de páginas: se redirigen con reglas por patrón.

## 3. Correspondencias: resumen

| Lo que hay hoy | URLs | Destino en la web nueva | Cómo |
|---|---|---|---|
| Portada y páginas | 22 | 7 se quedan igual (portada, legales, gracias, zonas); 11 cambian; 4 de prueba se retiran (410) | mapa exacto |
| Entradas del blog | 57 | `/journal/<mismo slug>/` | patrón |
| Guías de zona (entradas) | 9 | `/areas/<zona>/` y `/areas/` | mapa exacto |
| Fichas de propiedad que siguen en marOS | 18 | `/str/<nombre>/` | mapa exacto |
| Fichas de villas que no están en marOS | 24 | Su zona si se sabe; si no, `/str/` | mapa exacto |
| Tours | 19 | `/experiences/<mismo slug>/` (un duplicado se fusiona) | patrón |
| Servicios (transporte, catering, eventos, lifestyle) | 17 | 13 se quedan igual; los 4 de lifestyle pasan a `/services/concierge/<slug>/` | patrón |
| Eventos realizados (galerías de fiestas y bodas) | 11 | Se quedan igual en `/services/events/<slug>/` | — |
| Portadas de sección, landings y actividades sueltas de `/services/…` | 26 | Sección más cercana (transporte, catering y eventos se quedan igual) | mapa exacto |
| Categorías del blog | 11 | `/journal/category/<slug>/` (`vrm` → Recursos propietarios) | patrón |
| Etiquetas | 40 | `/journal/` | patrón |
| Categorías de servicios | 32 | Listado o zona equivalente | patrón + exactas |
| Categoría de páginas | 1 | `/areas/` | exacta |
| **Total** | **287** | 34 sin cambio, 249 redirecciones 301 y 4 retiradas (410) | |

Cada fila del inventario tiene `estado`: **propuesta** (regla clara) o **revisar** (63 filas que
dependen de una decisión o de los datos de tráfico).

### Fichas de propiedad emparejadas con marOS (18)

| URL actual | Propiedad en marOS | Nueva |
|---|---|---|
| `/services/villas/villa-centauri/` | Villa Centauro | `/str/villa-centauro/` |
| `/services/villas/villa-feliz/` | Villa Feliz | `/str/villa-feliz/` |
| `/services/villas/villa-nido/` | Villa Nido | `/str/villa-nido/` |
| `/services/villas/villa-michaela/` | Villa Michaela | `/str/villa-michaela/` |
| `/services/villas/villa-panda-sea-views-heated-pool-in-sierra-blanca/` | Villa Panda | `/str/villa-panda/` |
| `/services/villas/villa-concha-puerto-banus/` | Villa Concha | `/str/villa-concha/` |
| `/services/villas/villa-flamingo-luxury-modern/` | Villa Flamingos (**confirmar**) | `/str/villa-flamingos/` |
| `/services/villas/casa-masari/` | Casa Masari | `/str/casa-masari/` |
| `/services/villas/casa-dama/` | Casa Dama | `/str/casa-dama/` |
| `/services/villas/casa-principe/` | Casa Principe | `/str/casa-principe/` |
| `/services/villas/casa-samara/` | Casa Samara | `/str/casa-samara/` |
| `/services/villas/casa-rodeo-puerto-banus/` | Casa Rodeo | `/str/casa-rodeo/` |
| `/services/apartments/casa-cifuentes/` | Cifuentes | `/str/casa-cifuentes/` |
| `/services/apartments/casa-sould/` | Casa Soul | `/str/casa-soul/` |
| `/services/apartments/casa-paraiso/` | Casa Paraiso | `/str/casa-paraiso/` |
| `/services/apartments/casa-brisa/` | Casa Brisa | `/str/casa-brisa/` |
| `/services/apartments/casa-coral/` | Casa Coral | `/str/casa-coral/` |
| `/services/apartments/casa-acosta/` | Casa Acosta | `/str/casa-acosta/` |

Propiedades de marOS **sin ficha en la web actual** (URL nueva, sin redirección): Casa Ambar,
Casa Torre y Villa Mares.

El slug nuevo es el nombre de la propiedad. Julio propuso slugs descriptivos
(`villa-nido-modern-4br-flamingos`); se puede hacer así al cerrar D6, sin tocar las
redirecciones más que en el destino.

### Fichas de villas que no están en marOS (24)

`villa-mirador`, `modern-villa-marbella`, `marbella-modern-villa-oliva`,
`marbella-banus-modern-villa`, `luxury-modern-villa-monteros-marbella`,
`luxury-modern-villa-marbella-golf`, `beachfront-villa-new-golden-mile`,
`villa-palace-guadalmina`, `villa-soule`, `villa-roma`, `villa-mocca`,
`villa-resort-flamingos`, `finca-fantasia`, `villa-rio`, `casa-sotogrande`, `villa-goya`,
`villa-resort-alqueria`, `villa-alpha`, `villa-dorada`, `villa-cala`, `villa-paraiso`,
`villa-one`, `villa-banus`, `villa-adelfa-beach-side`.

Propuesta: 301 a su zona cuando se deduce del nombre (Guadalmina, Los Flamingos, La Alquería,
New Golden Mile) y al listado `/str/` en el resto, nunca a la portada. Si alguna es una casa
de marOS con otro nombre, o vuelve a ofrecerse, recupera ficha propia.

## 4. Reglas de redirección (orden de Cloudflare)

1. **Host**: `marbellainstyle.com/*` → `https://www.marbellainstyle.com/*`, aplicado junto con
   las reglas siguientes para que haya **un solo salto**.
2. **Mapa exacto** (Bulk Redirects, `redirects.csv`): las 249 URLs que cambian.
3. **Patrones** como red de seguridad para lo que no esté en el mapa (URLs con parámetros,
   traducciones no rastreadas): `/tag/*` → `/journal/`; `/services_cat/*` → `/services/`;
   `/services/tours/*` → `/experiences/*`; `/de/*`, `/ru/*`, `/fr/*` → equivalente en inglés.
4. **Retiradas**: `/example-i/`, `/test/`, `/page-404-custom/` y `/no-access/` responden 410.
5. **Raíz**: `/` es la portada en inglés, sin redirección. Detectar el idioma del navegador solo
   como sugerencia, nunca como redirección automática, para que Google vea las dos versiones.

`generar.py` ya comprueba que ningún destino de un 301 es a su vez una URL redirigida (sin cadenas). El CI de
la spec 002 (M6.2) añadirá que cada destino responda 200 en pruebas.

## 5. Para revisar con Julio

1. ~~Prefijo para el inglés~~ **Decidido (12/11)**: inglés sin prefijo. Queda abierto si las
   entradas del blog se quedan en la raíz (`/<slug>/`, como hoy: 57 redirecciones menos) o pasan a
   `/journal/<slug>/`, y si los tours se quedan en `/services/tours/` (19 menos) o pasan a
   `/experiences/`.
2. **Las 24 villas sin marOS**: ¿alguna sigue activa con otro nombre? ¿Villa Flamingos es
   `villa-flamingo-luxury-modern`?
3. **Eventos realizados** (11 galerías de fiestas y bodas): ¿se mantienen como casos dentro de
   Eventos, o se juntan en la galería?
4. **Entradas antiguas** (fiestas de 2014–2015, guía de hoteles 2022…): retirar con 301 si no
   tienen tráfico.
5. **Zonas sin guía** (Benahavís, Sotogrande, El Madroñal, New Golden Mile): ¿se crean páginas
   de zona?
6. **`/vacation-rental-lp/`** y **`/vacation-planner/`**: ¿siguen usándose en anuncios o
   campañas?

Todo esto se cierra con los datos de Search Console y GA4 (plan M0–M1 de la spec 002): ninguna
URL con clics o enlaces externos se retira sin redirección.
