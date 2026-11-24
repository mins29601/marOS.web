# Entorno local (Mac) con wp-env

WordPress en tu Mac que imita a **pruebas** (`staging.privatevillacollection.com`), con la API de marOS simulada.
Usa el mismo `.wp-env.json` que los tests de GitHub (spec 001, «Testing»).

| | Local | Pruebas | Producción |
|---|---|---|---|
| URL | http://localhost:8888 | `staging.privatevillacollection.com` | `www.privatevillacollection.com` |
| PHP / WordPress | 8.4 / última estable (hoy 7.1) | los de IONOS | los de IONOS |
| `WP_ENVIRONMENT_TYPE` | `local` | `staging` | `production` |
| marOS | **mock** (JSON de `mock/maros-api/`) | marOS pruebas | marOS producción |
| Correo | bloqueado (se anota en el log) | bloqueado | normal |
| Contenido | vacío; más adelante, copia de pruebas | copia de producción | real |

## 1. Instalar (una sola vez)

Necesita tu contraseña de administrador del Mac.

1. **Docker Desktop** para Mac con Apple Silicon: https://www.docker.com/products/docker-desktop/ ·
   ábrelo una vez y acepta. En Ajustes → Resources basta con 4 GB de memoria.
2. **Homebrew** (gestor de paquetes): sigue el comando de https://brew.sh y, al acabar, los dos
   comandos «Next steps» que imprime.
3. **Node 22** (para `wp-env`): `brew install node@22` y después `brew link --overwrite node@22`.

Comprueba: `docker --version` y `node --version` responden.

## 2. Arrancar

```bash
cd marOS.web
npm install          # instala @wordpress/env en el proyecto
npm run env:start    # la primera vez tarda unos minutos (descarga imágenes)
```

- Web: http://localhost:8888 · Admin: http://localhost:8888/wp-admin (`admin` / `password`,
  solo en local).
- Parar: `npm run env:stop` · Borrar todo y empezar de cero: `npm run env:reset`.
- WP-CLI: `npm run wp -- plugin list`, `npm run wp -- option get home`…
- Log de PHP y de correos bloqueados: `npm run env:logs`.

## 3. Qué monta

- Tema **Ollie** como padre y el tema hijo **`marbellainstyle`** del repo, activo (D1).
- Plugins gratuitos acordados: Polylang, Yoast, Contact Form 7, Complianz, Secure Custom Fields, Site Reviews, FluentSMTP,
  WPvivid, MCP Adapter y Enable Abilities for MCP. Cloudflare se instala, pero queda inactivo.
  Lista completa y versiones: [config/plugins.md](../../config/plugins.md).
- Idiomas con **Polylang**: inglés (por defecto) y español.
- Formularios con **Contact Form 7** (sin Flamingo: no guarda solicitudes).
- `wp-content/mu-plugins/` del repo:
  - `mis-entorno.php`: noindex, correo bloqueado y aviso si un entorno que no es producción
    apunta a marOS producción. Es el mismo que irá a pruebas.
  - `mis-maros-mock.php`: la API de marOS simulada (solo con `MAROS_ENV=mock`).
- Cuando existan, se añaden a `.wp-env.json` `./wp-content/plugins/maros-connector` y
  `./wp-content/plugins/mis-contenido`.

## 3b. Primera versión de la web (contenido de ejemplo)

Con el entorno arrancado, dos comandos dejan la web navegable en inglés y español:

```bash
npm run wp -- maros sync --publish
npm run wp -- eval-file wp-content/mis-scripts/setup/contenido-inicial.php
npm run wp -- eval-file wp-content/mis-scripts/setup/zonas.php
npm run wp -- eval-file wp-content/mis-scripts/setup/servicios.php
npm run wp -- eval-file wp-content/mis-scripts/setup/opiniones-tours.php
npm run wp -- eval-file wp-content/mis-scripts/setup/slugs-idioma.php
```

- `wp maros sync` carga las propiedades del mock (ficha, tipo, zona, equipamiento, fotos,
  calendario y tarifas) en inglés y español. Se puede repetir: solo actualiza lo que cambia (etag).
  Otros comandos: `wp maros status` (estado y últimos eventos), `wp maros tick` (la tarea de
  5 minutos ahora mismo), `wp maros sync --id=<id> --force` y `wp maros idiomas [--fix]` (cada
  propiedad en inglés y español, vinculadas; con `--fix` crea o vincula la que falte). El estado también se ve en
  **Propiedades → marOS**, con el botón «Sincronizar ahora».
- `contenido-inicial.php` crea zonas, tipos de servicio, el servicio de ejemplo de Eventos, las páginas
  del mapa del sitio (spec 005), los formularios de Contact Form 7 y los menús de cada idioma. Es
  idempotente y los textos son de ejemplo.
- `zonas.php` crea las zonas que no crea el contenido inicial (New Golden Mile y 8 más), escribe la guía en inglés y español
  de las 13 y las añade a los menús. No pisa una guía que ya tenga texto (`… zonas.php forzar` para reescribirla).
- `servicios.php` carga los servicios (Transporte, Chef privado, Conserjería) y las dieciséis experiencias de
  la web actual con los textos de los borradores de marOS.content (`scripts/setup/servicios/servicios.json`,
  que genera `servicios/generar.py`): destacados, programa, qué incluye, punto de encuentro, cancelación e
  id de FareHarbor en campos propios, y las fotos de la galería de cada ficha actual
  (`servicios/fotos-tours.json`, que se bajan de marbellainstyle.com la primera vez). Pone las URLs de la
  web actual (`/services/<tipo>/<slug>/`) y ordena los menús. No pisa lo editado en WordPress
  (`… servicios.php forzar` para reescribirlo; `… servicios.php forzar-experiencias` reescribe solo
  las experiencias, y hace falta una vez para pasar a la ficha nueva las siete cargadas antes del
  24-11-2026).
- `opiniones-tours.php` importa las 117 opiniones de las fichas de experiencias de la web actual
  (`opiniones-tours.json`) y las asigna a cada experiencia en los dos idiomas. Se puede repetir.
- `slugs-idioma.php` cambia «-2» por «-es» en el slug de las traducciones que chocaban con el inglés
  (p. ej. `/es/stay/villa-concha-2/` → `/es/stay/villa-concha-es/`); la URL vieja redirige con 301. Lo
  nuevo ya nace así (mis-contenido, `includes/slugs.php`).

## 4. La API de marOS simulada

Base: `http://localhost:8888/wp-json/maros-mock` (desde dentro del contenedor:
`http://localhost/wp-json/maros-mock`, que es `MAROS_API_BASE`). Pide la clave como la real:

```bash
KEY=mock-local-no-secreta
curl -s -H "Authorization: Bearer $KEY" http://localhost:8888/wp-json/maros-mock/v1/catalog/properties
curl -s -H "Authorization: Bearer $KEY" http://localhost:8888/wp-json/maros-mock/v1/catalog/properties/lodgify-373016
curl -s -H "Authorization: Bearer $KEY" "http://localhost:8888/wp-json/maros-mock/v1/catalog/properties/villa-concha/calendar"
curl -s -H "Authorization: Bearer $KEY" "http://localhost:8888/wp-json/maros-mock/v1/availability?property_id=villa-concha&from=2027-03-01&to=2027-03-08"
curl -s -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"property_id":"villa-concha","arrival":"2027-03-01","departure":"2027-03-08","guests":{"adults":4}}' \
  http://localhost:8888/wp-json/maros-mock/v1/quotes
```

| Ruta | Qué devuelve |
|---|---|
| `GET /v1/catalog/properties` | Lista de propiedades publicables con su `etag` y `calendar_etag` (3: Villa Feliz, Villa Concha, un apartamento de prueba) |
| `GET /v1/catalog/properties/{id}` | Ficha: tipo, capacidad, dormitorios, baños, zona aproximada, servicios, fotos, tipos de alojamiento, precio «desde» |
| `GET /v1/catalog/properties/{id}/calendar` | 18 meses de disponibilidad, estancia mínima y tarifas por tramo de estancia (lo que sincroniza WordPress) |
| `GET /v1/availability` | Días entre `from` y `to` y si están todos libres |
| `POST /v1/quotes` | Presupuesto desglosado (alojamiento, limpieza, IVA); 409 si hay días ocupados |
| `POST /v1/bookings` | Reserva `tentative` con `payment_url` simulada; no guarda datos del huésped |
| `GET /v1/bookings/{id}` | Estado simulado |

Los datos están en `mock/maros-api/v1/`. Para cambiar precios u ocupación, edita
`calendar/<id>.json` (los bloques ocupados son días contados desde hoy). Para añadir una
propiedad, crea `properties/<id>.json`, añádela a `properties.json` y, si quieres, su
`calendar/<id>.json`.

Las tres rutas del catálogo tienen la misma forma que las de marOS (`/api/v1/catalog/…`, spec 030
de `mins29601/marOS`). Presupuesto y reservas siguen siendo una propuesta (issue #89 de marOS).
Para probar contra marOS pruebas en local, pon en `.wp-env.override.json` `MAROS_ENV=staging`,
`MAROS_API_BASE` y `MAROS_API_KEY` (ese archivo no se sube).

## 5. Traer contenido de pruebas

Con wp-env arrancado y [`gh`](https://cli.github.com/) conectado a GitHub (`brew install gh && gh auth login`):

```bash
scripts/bajar-pruebas.sh
```

- Lanza en GitHub la acción **Bajar pruebas a local** (`.github/workflows/bajar-pruebas.yml`), que
  exporta por SSH la base de datos y `wp-content/uploads` de pruebas (solo lee) y los deja como
  descarga del run durante un día. También se puede lanzar a mano en Actions y pasar la carpeta
  descargada: `scripts/bajar-pruebas.sh ~/Downloads/pruebas`.
- En local: guarda la base actual en `scripts/.pruebas/local-<fecha>.sql.gz`, importa la de
  pruebas, cambia el prefijo de tablas (`mis_` → `wp_`) y las URLs a `http://localhost:8888`,
  copia las fotos y deja el usuario `admin` / `password`.
- **Pausa la sincronización con marOS** (opción `maros_sync_paused`): la web local habla con el
  mock y la tarea de cada 5 minutos pisaría las fichas de pruebas con los datos simulados. Para
  reactivarla: `npm run wp -- option delete maros_sync_paused`. `wp maros sync` a mano sigue
  funcionando, con el mismo problema.
- Volver a la base anterior: `gunzip scripts/.pruebas/local-<fecha>.sql.gz` y
  `npm run wp -- db import wp-content/mis-scripts/.pruebas/local-<fecha>.sql`.

Nunca se sube nada desde local a pruebas o producción: el código va por Git y PR.
