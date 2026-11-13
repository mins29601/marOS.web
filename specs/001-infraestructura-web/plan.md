# Implementation Plan: Infraestructura de la web (fase 1)

**Branch**: `001-infraestructura-web` | **Date**: 2026-11-11 | **Spec**: [spec.md](./spec.md)

**Estado**: Borrador para revisar con Julio. Todavía sin código.

## Summary

Poner Cloudflare delante del dominio sin cortes, crear producción y pruebas en IONOS, proteger
pruebas, activar APO, desplegar el código propio con GitHub Actions por SSH, copiar contenido de
producción a pruebas e instalar MCP Adapter en los dos entornos desde el inicio.

## Dominio temporal (decisión de Julio, 11 de noviembre de 2026, 22:36)

«El dominio no será el de marbellainstyle todavía. Usaremos uno temporal.» Por tanto:

- La fase 1 se monta sobre un **dominio temporal** (`privatevillacollection.com`, decidido por Julio el 13/11):
  producción en `www.privatevillacollection.com` y pruebas en `staging.privatevillacollection.com`, los dos detrás de Cloudflare.
  Producción también queda protegida con Access hasta el lanzamiento.
- Como el dominio temporal es nuevo, se da de alta en Cloudflare desde cero, sin riesgo de cortes.
- **marbellainstyle.com no se toca en la fase 1.** Los pasos 0 y 1 (pasar sus DNS a Cloudflare sin
  cortes, con el correo de Google Workspace) se hacen como preparación del lanzamiento (spec 002,
  M7), unas semanas antes.
- Todas las URLs se generan a partir de `home_url()`; el cambio de dominio en el lanzamiento es
  un `wp search-replace` más las redirecciones.

## Pruebas primero (13 de noviembre de 2026)

Pruebas se monta antes que producción para ver la maqueta real. Guía para los paneles:
[docs/runbooks/pruebas-ionos.md](../../docs/runbooks/pruebas-ionos.md). Ya está en el repo:

| Pieza | Archivo |
|---|---|
| Despliegue automático a pruebas al fusionar en `main` (o a mano con cualquier rama) | `.github/workflows/deploy-staging.yml`, `scripts/deploy.sh` |
| Arranque único: WordPress, Ollie y plugins de `.wp-env.json`, tema hijo, idiomas y contenido de ejemplo | `.github/workflows/bootstrap-staging.yml`, `scripts/bootstrap.sh`, `scripts/remote/bootstrap-remote.sh` |
| Comprobación tras desplegar (200, `noindex`, `robots.txt`, Access) | `scripts/smoke.sh` |
| `wp-config.php` de pruebas sin secretos | `config/wp-config.staging.example.php` |
| `.htaccess`: solo peticiones con la cabecera secreta de Cloudflare (`X-MIS-Origin`; IONOS tiene proxy y no sirve filtrar por IP), `X-Robots-Tag`, ficheros prohibidos | `config/htaccess/staging.htaccess` |
| `noindex` triple y correo bloqueado | `wp-content/mu-plugins/mis-entorno.php` |

Disposición en IONOS: WordPress en `~/staging/`; el código del repo en
`~/mis/staging/releases/<commit>/` con `~/mis/staging/current` apuntando a la versión activa y
enlaces simbólicos desde `wp-content` (o copia si IONOS no admite enlaces).

## Technical Context

**Language/Version**: PHP 8.3 (la más alta estable que ofrezca IONOS), WordPress ≥ 6.9 (Abilities
API en el núcleo, necesaria para MCP Adapter). JavaScript solo para bloques (`@wordpress/scripts`).

**Primary Dependencies**: WP-CLI (comprobar en IONOS; si no está, se sube el `.phar`), tar y rsync por
SSH, plugin oficial de Cloudflare (APO), MCP Adapter + Enable Abilities for MCP.

**Storage**: MySQL/MariaDB de IONOS, una base por entorno.

**Testing**: PHPCS (WordPress Coding Standards), PHPStan, PHPUnit con `wp-env` en CI, y un
«smoke» tras cada despliegue (HTTP 200, cabeceras de caché y `noindex` donde toca).

**Target Platform**: IONOS Unlimited Plus (Apache, hosting compartido) detrás de Cloudflare.

**Constraints**: sin root en el servidor; WP-Cron no fiable → cron real de IONOS.

## Constitution Check

| Principio | Cómo se cumple |
|---|---|
| II. WordPress estándar | El repo solo lleva código propio; plugins de terceros en `config/plugins.md` |
| III. marOS única puerta | Clave de marOS en `wp-config.php`; rutas `/wp-json/maros/*` sin caché |
| IV. Entornos separados | Dos instalaciones, Access + noindex, solo copia prod → pruebas |
| V. SEO | Fase 1 no cambia URLs; el mapa de 301 es de la spec 002 |

## Project Structure

### Estructura del repositorio `marOS.web`

El repositorio refleja `wp-content` solo en lo que es nuestro. Se despliega encima de una
instalación estándar de WordPress, sin tocar el núcleo ni los plugins de terceros.

```text
marOS.web/
├── .specify/                     # spec-kit: constitución, plantillas, scripts
├── .claude/skills/speckit-*      # comandos de spec-kit para Claude
├── .github/
│   ├── workflows/
│   │   ├── ci.yml                    # PR: PHPCS, PHPStan, PHPUnit, build de bloques, mapa de 301
│   │   ├── deploy-staging.yml        # push a main → pruebas
│   │   ├── deploy-production.yml     # manual + aprobación (environment "production")
│   │   ├── copy-prod-to-staging.yml  # manual: BD + uploads de producción → pruebas
│   │   └── check-redirects.yml       # manual / programado: valida el mapa contra un entorno
│   └── pull_request_template.md
├── docs/
│   ├── arquitectura-maros.md     # documento de arquitectura acordado (fuente S1)
│   ├── decisiones.md             # registro de decisiones (tomadas y pendientes)
│   └── runbooks/                 # pasos de panel: Cloudflare, IONOS, lanzamiento, rollback
├── specs/                        # una carpeta por spec (spec-kit)
├── config/
│   ├── plugins.md                # plugins y temas de terceros: versión, licencia, por qué
│   ├── wp-config.production.example.php   # plantilla sin secretos
│   ├── wp-config.staging.example.php
│   ├── htaccess/                 # reglas de Apache: cabecera secreta de Cloudflare, cabeceras
│   └── cloudflare/               # reglas de caché, WAF, Access y redirecciones documentadas
├── redirects/
│   └── redirects.csv             # mapa de URLs antiguas → nuevas (spec 002)
├── wp-content/
│   ├── themes/
│   │   └── marbellainstyle/      # tema hijo (padre según la decisión D1)
│   ├── plugins/
│   │   ├── maros-connector/      # plugin propio: propiedades desde marOS (spec 003)
│   │   └── mis-contenido/        # plugin propio: tours, servicios, zonas (spec 004)
│   └── mu-plugins/
│       └── mis-entorno.php       # por entorno: noindex y correo bloqueado en pruebas,
│                                 # comprueba que pruebas no apunte a marOS producción
├── mock/
│   └── maros-api/                # datos de ejemplo del contrato de la API de marOS
├── scripts/
│   ├── deploy.sh                 # tar por SSH a releases/<sha> + cambio atómico de enlace
│   ├── copy-prod-to-staging.sh   # WP-CLI: export, import, search-replace, ajustes de pruebas
│   └── smoke.sh                  # comprobaciones HTTP tras desplegar
├── composer.json                 # solo herramientas de desarrollo (PHPCS, PHPStan, PHPUnit)
├── package.json                  # build de bloques (@wordpress/scripts)
├── .wp-env.json                  # WordPress local y en CI
└── README.md
```

### Cómo se gestionan producción y pruebas

| | Pruebas | Producción |
|---|---|---|
| Host | `staging.privatevillacollection.com` | `www.privatevillacollection.com` hasta el lanzamiento; después `www.marbellainstyle.com` |
| Carpeta IONOS | `~/staging/` | `~/production/` |
| Base de datos | propia | propia |
| marOS | pruebas | producción |
| Despliegue | automático al fusionar en `main` | manual con aprobación de Julio, mismo commit probado |
| Acceso | Cloudflare Access + noindex | público con APO (Access hasta el lanzamiento) |
| Correo saliente | bloqueado / redirigido | normal |
| MCP Adapter | sí, usuario propio | sí desde el inicio, usuario propio; las escrituras crean borradores |

- **Configuración por entorno**: `wp-config.php` en el servidor (fuera del repo) define
  `WP_ENVIRONMENT_TYPE`, `MAROS_ENV`, `MAROS_API_BASE`, `MAROS_API_KEY`,
  `MAROS_WEBHOOK_SECRET`, `DISABLE_WP_CRON`. Las plantillas sin secretos están en `config/`.
- **Secretos de despliegue**: GitHub Environments `staging` y `production`, cada uno con
  `SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY`, `SSH_KNOWN_HOSTS`, `WP_PATH`. `production` exige
  aprobación de Julio.
- **Despliegue atómico**: el workflow sube por SSH (tar) a `~/mis/<entorno>/releases/<sha>/` y cambia los
  enlaces simbólicos de `wp-content/themes/marbellainstyle`, `plugins/maros-connector` y
  `mu-plugins`. Se guardan las 5 últimas versiones para volver atrás. Si IONOS no permite enlaces
  simbólicos, copia directa (`DEPLOY_MODE=copy`).
- **Ramas**: `main` siempre desplegable; una rama por spec o tarea; PR con CI en verde.

## Plan de la fase 1

Leyenda: **[Tú]** paso en un panel (IONOS, Cloudflare, registrador, GitHub) · **[Claude]** lo
preparo o automatizo yo (scripts, workflows, revisión) · **[Juntos]** lo haces tú con mi guía y
lo compruebo después.

### Paso 0. Preparación de marbellainstyle.com (se hace antes del lanzamiento, no en la fase 1)

| # | Quién | Qué |
|---|---|---|
| 0.1 | [Tú] | Responder Q1–Q5 de la spec (alojamiento actual, registrador, correo, www, quién entra en pruebas) |
| 0.2 | [Tú] | Exportar la zona DNS actual desde IONOS (o captura de todos los registros) y pasármela |
| 0.3 | [Claude] | Revisar la zona: correo de Google Workspace (MX, SPF, DKIM, DMARC), `stay.` (motor de reservas), verificaciones de Google/Meta, CAA |
| 0.4 | [Tú] | En el DNS actual, bajar el TTL de todos los registros a 300 s, 24–48 h antes del cambio |
| 0.5 | [Tú] | Si el registrador tiene DNSSEC activo, desactivarlo (esperar 24 h) |

### Paso 1a. Dominio temporal en Cloudflare (fase 1)

| # | Quién | Qué |
|---|---|---|
| 1a.1 | [Tú] | Registrar o elegir el dominio temporal (en IONOS) |
| 1a.2 | [Tú] | Darlo de alta en Cloudflare (plan Free) y poner sus nameservers en IONOS |
| 1a.3 | [Tú] | Registros `www`, `@` y `staging` hacia IONOS con proxy activo; SSL «Full (strict)» |

### Paso 1. marbellainstyle.com a Cloudflare sin cortes (antes del lanzamiento)

| # | Quién | Qué |
|---|---|---|
| 1.1 | [Tú] | Crear la cuenta/zona en Cloudflare (plan Free) y dejar que importe los registros |
| 1.2 | [Claude] | Comparar registro a registro con la zona exportada; lista de correcciones |
| 1.3 | [Tú] | Dejar **todos** los registros en «solo DNS» (nube gris) para el primer cambio: el tráfico sigue igual |
| 1.4 | [Tú] | En IONOS → Dominios → marbellainstyle.com → Nameservers: poner los de Cloudflare (IONOS lo llama «usar nameservers propios») |
| 1.5 | [Claude] | Comprobar la propagación y que web, `stay.` y correo responden igual |
| 1.6 | [Juntos] | SSL «Full (strict)»; activar el proxy (nube naranja) en `@` y `www` de la web actual; comprobar |
| 1.7 | [Tú] | Activar DNSSEC en Cloudflare y pegar el registro DS en el registrador |

### Paso 2. Producción y pruebas en IONOS

| # | Quién | Qué |
|---|---|---|
| 2.1 | [Tú] | En IONOS: crear las carpetas `production` y `staging`, dos bases de datos MySQL y asignar `www.privatevillacollection.com` y `staging.privatevillacollection.com` a sus carpetas |
| 2.2 | [Tú] | Elegir PHP 8.3 para ambas carpetas |
| 2.3 | [Tú] | Crear el usuario SSH/SFTP y añadir la clave pública que te daré (o pasarme usuario y método de acceso) |
| 2.4 | [Juntos] | Certificado: SSL de IONOS si se puede validar con Cloudflare delante; si no, certificado de origen de Cloudflare importado en IONOS |
| 2.5 | [Claude] | Workflow `bootstrap` (una vez): instalar WordPress con WP-CLI en cada carpeta, `wp-config.php` desde la plantilla, idioma, zona horaria, usuarios |
| 2.6 | [Tú] | Crear en IONOS el cron cada 5 minutos para cada entorno (comando que te daré) |
| 2.7 | — | (incluido en 1a.3) |

### Paso 3. Pruebas privada y sin indexar

| # | Quién | Qué |
|---|---|---|
| 3.1 | [Tú] | Cloudflare Zero Trust → Access → aplicación para `staging.privatevillacollection.com` y `www.privatevillacollection.com` con los emails autorizados: Access pide el email al entrar y envía un código de un solo uso; solo entran los emails de la lista |
| 3.2 | [Tú] | Crear un token de servicio de Access para marOS pruebas (avisos a WordPress) y para el MCP; pasarme los ids, no los secretos |
| 3.3 | [Claude] | mu-plugin `mis-entorno`: `noindex`, `X-Robots-Tag`, `robots.txt` cerrado y correo bloqueado en pruebas |
| 3.4 | [Claude] | `.htaccess` que solo acepta peticiones con la cabecera secreta de Cloudflare en los dos entornos |
| 3.5 | [Tú] | Regla de transformación en Cloudflare que añade `X-Robots-Tag: noindex` a `staging.` (segunda capa) |

### Paso 4. Despliegue con Git

| # | Quién | Qué |
|---|---|---|
| 4.1 | [Claude] | Esqueleto del repo, `ci.yml`, `deploy-staging.yml`, `deploy-production.yml`, `scripts/deploy.sh`, `smoke.sh` |
| 4.2 | [Tú] | En GitHub: crear los Environments `staging` y `production` (este con revisor obligatorio: tú) y pegar los secretos SSH |
| 4.3 | [Tú] | Proteger la rama `main` (PR obligatorio y CI en verde) |
| 4.4 | [Claude] | Primer despliegue a pruebas y a producción (dominio temporal); comprobar con el smoke |

### Paso 5. APO y WAF

| # | Quién | Qué |
|---|---|---|
| 5.1 | [Tú] | Contratar APO en Cloudflare (5 USD/mes) |
| 5.2 | [Claude] | Instalar el plugin oficial de Cloudflare en producción por WP-CLI; tú lo conectas con un token de API limitado a la zona |
| 5.3 | [Juntos] | Regla de caché: saltar caché en `/wp-json/maros/*`, `/wp-admin/*`, `wp-login.php`, previsualizaciones |
| 5.4 | [Juntos] | WAF: reglas gestionadas, desafío en `wp-login.php` y `xmlrpc.php`, límite de peticiones en `/wp-json/maros/v1/(quotes|bookings)` |
| 5.5 | [Claude] | Desactivar la caché de página de otros plugins (p. ej. WP Rocket) si se instalan, para no duplicar con APO |

### Bases de datos

- **Una por entorno**, creadas por Julio en el panel de IONOS. Sus credenciales viven solo en el
  `wp-config.php` de cada instalación, nunca en el repositorio ni en GitHub.
- **Git solo lleva código.** El contenido y los ajustes viven en la base de cada entorno.
- **Esquema propio versionado**: las tablas del plugin (calendario, tarifas, registro) las crea y
  actualiza el plugin con un número de versión de esquema. Tras cada despliegue, el workflow
  ejecuta por SSH `wp maros migrate`, que aplica solo lo que falte y nunca borra datos.
- **Ajustes repetibles**: los ajustes preparados en pruebas que deban pasar a producción (Polylang,
  plugin SEO, permalinks…) se guardan como scripts de WP-CLI en `scripts/settings/` o como lista
  en `docs/runbooks/`; nunca se copia la base de pruebas sobre producción.
- **Copias**: `wp db export` antes de cada despliegue a producción (se guardan las 10 últimas en
  el servidor, fuera de la carpeta pública) y copia diaria fuera de IONOS (D12).
- **Acceso**: MySQL de IONOS solo acepta conexiones desde el propio hosting, así que todas las
  operaciones las hace WP-CLI por SSH; GitHub nunca se conecta a la base directamente.

### Paso 6. Copiar producción a pruebas

| # | Quién | Qué |
|---|---|---|
| 6.1 | [Claude] | `copy-prod-to-staging.yml` + script: `wp db export` en producción, importar en pruebas, `wp search-replace` de host, rsync de `uploads`, reponer ajustes de pruebas (marOS pruebas, noindex, correo bloqueado), vaciar cachés |
| 6.2 | [Tú] | Lanzarlo a mano cuando haga falta (botón en GitHub) |
| 6.3 | — | Alternativa sin SSH: WPvivid o WP Migrate (decisión D9) |

### Paso 7. MCP Adapter en producción y pruebas

| # | Quién | Qué |
|---|---|---|
| 7.1 | [Claude] | Instalar MCP Adapter y Enable Abilities for MCP en los dos entornos por WP-CLI (WordPress ≥ 6.9), primero en pruebas y, tras probarlo, en producción |
| 7.2 | [Tú] | Crear en cada entorno el usuario `claude-mcp` (rol propio con permisos de editor, sin publicar en producción) y su contraseña de aplicación |
| 7.3 | [Juntos] | Dar paso al MCP a través de Access: excepción de ruta solo para `/wp-json/mcp/*` (protegida por la contraseña de aplicación) o token de servicio (decisión D10). En producción no hay Access: la ruta queda protegida por la contraseña de aplicación y un límite de peticiones en el WAF |
| 7.4 | [Tú] | Añadir en Claude dos conectores, «web» y «web pruebas», cada uno con su URL y su credencial |
| 7.5 | [Claude] | Probar: listar Abilities, leer contenido, comprobar que lo no permitido se rechaza |

## Riesgos y comprobaciones pendientes

- WP-CLI, Git, enlaces simbólicos y claves SSH en IONOS Unlimited Plus: se comprueban en el paso 2.3.
- Si IONOS permite cron en el panel para este plan; si no, alternativa: Cron Trigger de Cloudflare
  o el ejecutor de marOS llamando a `wp-cron.php` con un secreto.
- Certificado SSL en IONOS con DNS en Cloudflare (paso 2.4).
- Los avisos de marOS a pruebas y el MCP de pruebas deben pasar Cloudflare Access (pasos 3.2 y 7.3).
- El MCP de producción es público en la red: contraseña de aplicación larga, rol limitado, límite en el WAF y registro de cada llamada.
- APO + Polylang: APO cachea por URL; con idiomas por carpeta (`/es/`) funciona sin cookies.
