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

- **Rutas con prefijo de idioma** `/en/` y `/es/`, y alquileres en `/en/str/` y `/es/vft/`, como
  propuso Julio y como aparecen en la spec 005. Hoy el inglés va sin prefijo, así que **todas las
  URLs inglesas cambian** y necesitan 301. Es el punto principal de D6: si se prefiere el inglés
  sin prefijo, basta con cambiar las constantes del script.
- **Dominio final** `https://www.marbellainstyle.com` (hoy el canonical es sin `www`). El dominio
  temporal de la fase 1 no afecta a este mapa.
- Solo **inglés y español**. Alemán, ruso y francés redirigen a la página equivalente en inglés.
- El sitemap actual solo lista inglés. Las URLs en `/es/` (y `/de/`, `/ru/`, `/fr/`) se añadirán
  al inventario con el rastreo completo (plan M0.3).

## 2. Mapa del sitio nuevo

Las rutas en español son traducciones de la sección; los slugs de cada ficha se traducen al
migrar el contenido.

```text
Inicio                                   /en/                          /es/
│
├── Alojamiento (Stay)                   /en/str/                      /es/vft/
│   ├── Ficha de propiedad               /en/str/<slug>/               /es/vft/<slug>/
│   ├── Reservar (flujo C)               /en/str/<slug>/book/          /es/vft/<slug>/reservar/
│   └── Colecciones (pestañas)           /en/str/?collection=<c>       (filtro, no indexable)
│       Exclusivas · Familias · Golf · Grupos · (candidatas: Primera línea, Villas para eventos)
│
├── Servicios (Concierge)                /en/services/                 /es/servicios/
│   ├── Transporte                       /en/services/transport/       /es/servicios/transporte/
│   │   └── chófer, traslados aeropuerto Málaga / Gibraltar / Marbella, traslados de golf
│   ├── Chef y catering                  /en/services/catering/        /es/servicios/catering/
│   │   └── chef privado, catering, barbacoa, coctelería móvil, menús de Navidad
│   ├── Eventos y bodas                  /en/services/events/          /es/servicios/eventos/
│   │   ├── organización de eventos, fiestas privadas, wedding planner
│   │   ├── Galería                      /en/services/events/gallery/
│   │   └── Eventos realizados           /en/services/events/<slug>/   (si tienen tráfico)
│   └── Conserjería                      /en/services/concierge/       /es/servicios/conserjeria/
│       └── personal shopper, jet privado, yate, paquete vacacional, personal doméstico
│
├── Experiencias (tours)                 /en/experiences/              /es/experiencias/
│   └── Tour                             /en/experiences/<slug>/       /es/experiencias/<slug>/
│
├── Zonas                                /en/areas/                    /es/zonas/
│   └── Zona                             /en/areas/<slug>/             /es/zonas/<slug>/
│       Sierra Blanca · Milla de Oro · Nueva Andalucía · Los Flamingos · La Alquería ·
│       Guadalmina · Río Real · Estepona · (sin guía hoy: Benahavís, Sotogrande,
│       El Madroñal, New Golden Mile)
│
├── Propietarios                         /en/owners/                   /es/propietarios/
│   ├── Gestión de alquiler vacacional   /en/owners/vacation-rental-management/
│   ├── Gestión de propiedades           /en/owners/property-management/
│   └── Recursos para propietarios       /en/owners/resources/   (artículos del Journal)
│
├── Journal                              /en/journal/                  /es/blog/
│   ├── Entrada                          /en/journal/<slug>/           /es/blog/<slug>/
│   └── Categoría                        /en/journal/category/<slug>/
│
├── Nosotros                             /en/about/                    /es/nosotros/
├── Opiniones                            /en/reviews/                  /es/opiniones/
├── Trabaja con nosotros                 /en/work-with-us/             /es/trabaja-con-nosotros/
├── Contacto                             /en/contact/                  /es/contacto/
├── Legal   /en/privacy-policy/ · /en/legal-notice/ · /en/terms-and-conditions/ · /en/cookie-policy/
└── Utilidades (noindex)   /en/thank-you/ · 404 · búsqueda
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
| Portada y páginas | 22 | Su página equivalente; 4 de prueba se retiran (410) | mapa exacto |
| Entradas del blog | 57 | `/en/journal/<mismo slug>/` | patrón |
| Guías de zona (entradas) | 9 | `/en/areas/<zona>/` y `/en/areas/` | mapa exacto |
| Fichas de propiedad que siguen en marOS | 18 | `/en/str/<nombre>/` | mapa exacto |
| Fichas de villas que no están en marOS | 24 | Su zona si se sabe; si no, `/en/str/` | mapa exacto |
| Tours | 19 | `/en/experiences/<mismo slug>/` (un duplicado se fusiona) | patrón |
| Servicios (transporte, catering, eventos, lifestyle) | 17 | `/en/services/<tipo>/<mismo slug>/` | patrón |
| Eventos realizados (galerías de fiestas y bodas) | 11 | `/en/services/events/<slug>/` | patrón |
| Portadas de sección, landings y actividades sueltas de `/services/…` | 26 | Sección más cercana | mapa exacto |
| Categorías del blog | 11 | `/en/journal/category/<slug>/` (`vrm` → Recursos propietarios) | patrón |
| Etiquetas | 40 | `/en/journal/` | patrón |
| Categorías de servicios | 32 | Listado o zona equivalente | patrón + exactas |
| Categoría de páginas | 1 | `/en/areas/` | exacta |
| **Total** | **287** | 283 redirecciones 301 y 4 retiradas (410) | |

Cada fila del inventario tiene `estado`: **propuesta** (regla clara) o **revisar** (63 filas que
dependen de una decisión o de los datos de tráfico).

### Fichas de propiedad emparejadas con marOS (18)

| URL actual | Propiedad en marOS | Nueva |
|---|---|---|
| `/services/villas/villa-centauri/` | Villa Centauro | `/en/str/villa-centauro/` |
| `/services/villas/villa-feliz/` | Villa Feliz | `/en/str/villa-feliz/` |
| `/services/villas/villa-nido/` | Villa Nido | `/en/str/villa-nido/` |
| `/services/villas/villa-michaela/` | Villa Michaela | `/en/str/villa-michaela/` |
| `/services/villas/villa-panda-sea-views-heated-pool-in-sierra-blanca/` | Villa Panda | `/en/str/villa-panda/` |
| `/services/villas/villa-concha-puerto-banus/` | Villa Concha | `/en/str/villa-concha/` |
| `/services/villas/villa-flamingo-luxury-modern/` | Villa Flamingos (**confirmar**) | `/en/str/villa-flamingos/` |
| `/services/villas/casa-masari/` | Casa Masari | `/en/str/casa-masari/` |
| `/services/villas/casa-dama/` | Casa Dama | `/en/str/casa-dama/` |
| `/services/villas/casa-principe/` | Casa Principe | `/en/str/casa-principe/` |
| `/services/villas/casa-samara/` | Casa Samara | `/en/str/casa-samara/` |
| `/services/villas/casa-rodeo-puerto-banus/` | Casa Rodeo | `/en/str/casa-rodeo/` |
| `/services/apartments/casa-cifuentes/` | Cifuentes | `/en/str/casa-cifuentes/` |
| `/services/apartments/casa-sould/` | Casa Soul | `/en/str/casa-soul/` |
| `/services/apartments/casa-paraiso/` | Casa Paraiso | `/en/str/casa-paraiso/` |
| `/services/apartments/casa-brisa/` | Casa Brisa | `/en/str/casa-brisa/` |
| `/services/apartments/casa-coral/` | Casa Coral | `/en/str/casa-coral/` |
| `/services/apartments/casa-acosta/` | Casa Acosta | `/en/str/casa-acosta/` |

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
New Golden Mile) y al listado `/en/str/` en el resto, nunca a la portada. Si alguna es una casa
de marOS con otro nombre, o vuelve a ofrecerse, recupera ficha propia.

## 4. Reglas de redirección (orden de Cloudflare)

1. **Host**: `marbellainstyle.com/*` → `https://www.marbellainstyle.com/*`, aplicado junto con
   las reglas siguientes para que haya **un solo salto**.
2. **Mapa exacto** (Bulk Redirects, `redirects.csv`): las 283 URLs con destino concreto.
3. **Patrones** como red de seguridad para lo que no esté en el mapa (URLs con parámetros,
   traducciones no rastreadas): `/tag/*` → `/en/journal/`; `/services_cat/*` → `/en/services/`;
   `/services/tours/*` → `/en/experiences/*`; `/de/*`, `/ru/*`, `/fr/*` → equivalente en inglés.
4. **Retiradas**: `/example-i/`, `/test/`, `/page-404-custom/` y `/no-access/` responden 410.
5. **Raíz**: `/` → `/en/` (301). Detectar el idioma del navegador solo como sugerencia, nunca
   como redirección automática, para que Google vea las dos versiones.

`generar.py` ya comprueba que ningún destino es a la vez una URL antigua (sin cadenas). El CI de
la spec 002 (M6.2) añadirá que cada destino responda 200 en pruebas.

## 5. Para revisar con Julio

1. **Prefijo `/en/`** para el inglés (D6). Cambia todas las URLs que hoy tienen tráfico; la
   alternativa es dejar el inglés sin prefijo (`/str/…`, `/journal/…`) y solo `/es/` con prefijo.
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
