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
```

- `wp maros sync` carga las propiedades del mock (ficha, tipo, zona, equipamiento y fotos) en
  inglés y español. Se puede repetir: solo actualiza lo que cambia (etag).
- `contenido-inicial.php` crea zonas, tipos de servicio, 2 experiencias, 4 servicios, las páginas
  del mapa del sitio (spec 005), los formularios de Contact Form 7 y los menús de cada idioma. Es
  idempotente y los textos son de ejemplo.

## 4. La API de marOS simulada

Base: `http://localhost:8888/wp-json/maros-mock` (desde dentro del contenedor:
`http://localhost/wp-json/maros-mock`, que es `MAROS_API_BASE`). Pide la clave como la real:

```bash
KEY=mock-local-no-secreta
curl -s -H "Authorization: Bearer $KEY" http://localhost:8888/wp-json/maros-mock/v1/properties
curl -s -H "Authorization: Bearer $KEY" http://localhost:8888/wp-json/maros-mock/v1/properties/lodgify-373016
curl -s -H "Authorization: Bearer $KEY" "http://localhost:8888/wp-json/maros-mock/v1/properties/villa-concha/calendar"
curl -s -H "Authorization: Bearer $KEY" "http://localhost:8888/wp-json/maros-mock/v1/availability?property_id=villa-concha&from=2027-03-01&to=2027-03-08"
curl -s -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"property_id":"villa-concha","arrival":"2027-03-01","departure":"2027-03-08","guests":{"adults":4}}' \
  http://localhost:8888/wp-json/maros-mock/v1/quotes
```

| Ruta | Qué devuelve |
|---|---|
| `GET /v1/properties` | Lista de propiedades publicables (3: Villa Feliz, Villa Concha, un apartamento de prueba) |
| `GET /v1/properties/{id}` | Ficha: tipo, capacidad, dormitorios, baños, zona aproximada, servicios, fotos, tipos de alojamiento |
| `GET /v1/properties/{id}/calendar` | 18 meses de disponibilidad, estancia mínima y tarifa por noche (lo que sincroniza WordPress) |
| `GET /v1/availability` | Días entre `from` y `to` y si están todos libres |
| `POST /v1/quotes` | Presupuesto desglosado (alojamiento, limpieza, IVA); 409 si hay días ocupados |
| `POST /v1/bookings` | Reserva `tentative` con `payment_url` simulada; no guarda datos del huésped |
| `GET /v1/bookings/{id}` | Estado simulado |

Los datos están en `mock/maros-api/v1/`. Para cambiar precios u ocupación, edita
`calendar/<id>.json` (los bloques ocupados son días contados desde hoy). Para añadir una
propiedad, crea `properties/<id>.json`, añádela a `properties.json` y, si quieres, su
`calendar/<id>.json`.

**Es una propuesta del contrato** (arquitectura 3.2 + spec 003), no el contrato de marOS. Cuando
marOS publique la API v1 (issue #89 de marOS), se ajustan estos JSON a lo que devuelva y se
cambia `MAROS_ENV`/`MAROS_API_BASE` a marOS pruebas.

## 5. Traer contenido de pruebas (cuando exista)

```bash
# en pruebas (por SSH): wp db export pruebas.sql && wp export (o WPvivid)
npm run wp -- db import /var/www/html/pruebas.sql   # copia el .sql a la raíz del repo antes
npm run wp -- search-replace 'https://staging.privatevillacollection.com' 'http://localhost:8888' --all-tables
```

Nunca se sube nada desde local a pruebas o producción: el código va por Git y PR.
