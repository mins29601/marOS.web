# Feature Specification: Infraestructura de la web (fase 1)

**Feature Branch**: `001-infraestructura-web`

**Created**: 2026-11-11

**Status**: Borrador. Pruebas lista para montar (código y guía) en `staging.privatevillacollection.com`.

**Fuentes**:

- [S1] [Arquitectura](../../docs/arquitectura-maros.md), secciones 1, 2, 4 y 6 (fase 1).
- [S2] Mensaje de Julio del 11 de noviembre de 2026: IONOS Unlimited Plus con SSH, Cloudflare
  delante (DNS, APO, WAF, Access), producción y pruebas, despliegue con GitHub Actions por SSH,
  MCP Adapter en pruebas.
- [S3] Análisis de la web pública actual del 11 de noviembre de 2026
  ([research de la spec 002](../002-migracion-web/research.md)).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - El dominio pasa a Cloudflare sin cortes (Priority: P1, antes del lanzamiento)

Los nameservers de marbellainstyle.com pasan a Cloudflare. La web actual, el correo, el motor de
reservas `stay.` y cualquier otro servicio siguen funcionando igual durante y después del cambio.

**Independent Test**: antes del cambio se exporta la zona actual; después, cada registro responde
igual desde Cloudflare, se envía y recibe correo, y la web actual carga por HTTPS.

**Acceptance Scenarios**:

1. **Given** la zona DNS actual exportada, **When** se importa en Cloudflare, **Then** cada
   registro (A, AAAA, CNAME, MX, TXT, SRV, CAA) existe en Cloudflare con el mismo valor, y los de
   correo están en «solo DNS».
2. **Given** el cambio de nameservers, **When** propaga, **Then** la web actual sigue respondiendo
   200 en `https://marbellainstyle.com/` y en `https://www.marbellainstyle.com/`, y el correo
   entra y sale.
3. **Given** el proxy de Cloudflare activado, **When** se pide la web, **Then** el certificado
   es válido de punta a punta (modo SSL «Full (strict)»).

### User Story 2 - Producción y pruebas existen en IONOS (Priority: P1)

Hay dos instalaciones de WordPress separadas en IONOS, cada una con su base de datos, su
subdominio y su usuario SSH: producción y pruebas.

**Acceptance Scenarios**:

1. **Given** las dos instalaciones, **When** se consultan, **Then** cada una tiene su base de
   datos, sus claves de `wp-config.php` y `WP_ENVIRONMENT_TYPE` (`production` o `staging`).
2. **Given** la nueva producción antes del lanzamiento, **When** alguien entra sin permiso,
   **Then** no puede verla: hasta el lanzamiento se protege igual que pruebas (ver Clarifications).
3. **Given** ambas instalaciones, **When** pasa la hora, **Then** un cron real de IONOS ejecuta
   las tareas programadas de WordPress (`DISABLE_WP_CRON` activo).

### User Story 3 - Pruebas es privada y no se indexa (Priority: P1)

**Acceptance Scenarios**:

1. **Given** una persona sin permiso, **When** abre `staging.privatevillacollection.com`, **Then**
   Cloudflare Access le pide identificarse y no ve la web.
2. **Given** cualquier página de pruebas, **When** se pide, **Then** lleva `noindex` y la
   cabecera `X-Robots-Tag: noindex, nofollow`, y `robots.txt` lo prohíbe todo.
3. **Given** alguien que llama directamente al servidor de IONOS saltándose Cloudflare,
   **When** pide pruebas, **Then** el servidor lo rechaza.
4. **Given** WordPress de pruebas, **When** intenta enviar un correo, **Then** no sale a
   clientes (se registra o se envía a una dirección interna).
5. **Given** marOS pruebas, **When** avisa a WordPress de pruebas de un cambio, **Then** el aviso
   pasa Access (token de servicio o excepción de ruta firmada).

### User Story 4 - La caché de Cloudflare (APO) sirve la web (Priority: P2)

**Acceptance Scenarios**:

1. **Given** una página pública de producción, **When** se pide dos veces, **Then** la segunda
   llega de la caché de Cloudflare (`cf-cache-status: HIT`).
2. **Given** una villa editada en WordPress, **When** se guarda, **Then** su página y las listas
   donde aparece se purgan.
3. **Given** las rutas del proxy de marOS y del webhook (`/wp-json/maros/…`), **When** se piden,
   **Then** nunca salen de caché.
4. **Given** una persona con sesión en WordPress, **When** navega, **Then** ve la web sin caché.

### User Story 5 - El código se despliega con Git (Priority: P1)

**Acceptance Scenarios**:

1. **Given** un pull request fusionado en `main`, **When** pasan las comprobaciones, **Then**
   GitHub Actions despliega el tema hijo, el plugin y los mu-plugins en pruebas por SSH.
2. **Given** una versión lista, **When** Julio aprueba el despliegue a producción en GitHub,
   **Then** se despliega exactamente el mismo commit que se probó en pruebas.
3. **Given** un despliegue que falla a mitad, **When** termina, **Then** la versión anterior
   sigue funcionando (cambio atómico de carpeta) y se puede volver atrás con un clic.
4. **Given** el repositorio, **When** se revisa, **Then** no contiene claves, contraseñas ni
   archivos subidos.

### User Story 6 - Pruebas se puede rellenar con el contenido de producción (Priority: P2)

**Acceptance Scenarios**:

1. **Given** el flujo «Copiar producción a pruebas» lanzado a mano en GitHub, **When** termina,
   **Then** pruebas tiene la base de datos y los archivos subidos de producción, con las URLs
   cambiadas a `staging.`, `noindex` activo, correos bloqueados y la configuración de marOS de
   pruebas intacta.
2. **Given** ese flujo, **When** se intenta en sentido contrario, **Then** no existe: nunca se
   copia de pruebas a producción.

### User Story 7 - El MCP de WordPress funciona desde el inicio en los dos entornos (Priority: P1)

**Acceptance Scenarios**:

1. **Given** WordPress 6.9 o superior (producción y pruebas) con MCP Adapter y «Enable Abilities for MCP»,
   **When** Claude se conecta con un usuario dedicado y una contraseña de aplicación, **Then**
   lista las Abilities y puede leer contenido.
2. **Given** ese usuario, **When** intenta algo fuera de sus permisos (rol Editor o uno propio),
   **Then** WordPress lo rechaza.
3. **Given** producción, **When** Claude usa una Ability que escribe, **Then** crea un borrador
   o una revisión pendiente; publicar lo hace una persona (constitución, principio VIII).
4. **Given** cada entorno, **When** se conecta Claude, **Then** usa un usuario y una contraseña
   de aplicación propios de ese entorno, y un conector distinto en Claude («web» y «web pruebas»).

### Edge Cases

- El dominio tiene DNSSEC activo en el registrador: hay que desactivarlo antes del cambio de
  nameservers y activarlo de nuevo en Cloudflare después.
- El certificado de IONOS no se puede renovar con los DNS en Cloudflare: se usa un certificado de
  origen de Cloudflare (Origin CA) en IONOS, si el panel permite importarlo.
- IONOS no ofrece WP-CLI o Git por SSH en este plan: los scripts usan `rsync` y PHP; se comprueba
  en la primera conexión.
- La IP de salida de IONOS cambia o está bloqueada por algún servicio: el proxy a marOS va por
  HTTPS a un dominio de Cloudflare, sin listas de IPs.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Cloudflare MUST ser el DNS autoritativo de marbellainstyle.com con todos los
  registros previos, y el correo MUST quedar en «solo DNS».
- **FR-002**: El modo SSL MUST ser «Full (strict)», con «Always Use HTTPS» y HSTS activado solo
  tras comprobar todos los subdominios.
- **FR-003**: MUST existir dos instalaciones de WordPress independientes en IONOS (producción y
  pruebas), con `WP_ENVIRONMENT_TYPE`, `DISABLE_WP_CRON` y un cron real de IONOS cada 5 minutos.
- **FR-004**: Pruebas MUST estar detrás de Cloudflare Access, con `noindex` triple (ajuste de
  WordPress, cabecera y robots.txt), correo bloqueado y el origen aceptando solo tráfico de
  Cloudflare.
- **FR-005**: Producción MUST usar APO, con las rutas `/wp-json/maros/*`, `wp-admin`, el carrito
  de reserva y las sesiones excluidas de la caché.
- **FR-006**: El despliegue MUST hacerse solo desde GitHub Actions por SSH: automático a pruebas
  desde `main`, y a producción con aprobación en un GitHub Environment protegido.
- **FR-007**: Los secretos (SSH, claves de marOS) MUST vivir en GitHub Environments o en
  `wp-config.php` del servidor, nunca en el repositorio.
- **FR-008**: MUST existir un flujo manual «Copiar producción a pruebas» y MUST NOT existir el
  inverso.
- **FR-009**: El WAF de Cloudflare MUST tener las reglas gestionadas gratuitas, protección de
  `wp-login.php` y `xmlrpc.php` y límite de peticiones en las rutas del proxy de reservas.
- **FR-010**: MCP Adapter MUST instalarse desde el inicio en producción y pruebas, cada uno con un usuario dedicado de permisos
  limitados.

### Key Entities

- **Entorno**: producción o pruebas; host, ruta en IONOS, base de datos, entorno de marOS al que
  apunta, secretos.
- **Despliegue**: commit, entorno, quién lo aprobó, resultado, carpeta de versión.

## Success Criteria *(mandatory)*

- **SC-001**: Cero minutos de corte de la web actual y del correo durante el cambio de DNS.
- **SC-002**: Ninguna URL de pruebas aparece en Google (comprobado con `site:` y Search Console).
- **SC-003**: Un despliegue a pruebas tarda menos de 5 minutos desde la fusión.
- **SC-004**: TTFB de una página en caché < 200 ms desde España.

## Assumptions

- Julio tiene acceso al registrador del dominio, al panel de IONOS y a una cuenta de Cloudflare.
- Cloudflare plan Free + APO (5 USD/mes); Access gratis hasta 50 usuarios.
- La web actual sigue en marcha hasta el lanzamiento (spec 002); la fase 1 no la cambia salvo el
  DNS.

## Clarifications

### Session 2026-11-11 (preguntas abiertas)

- Q1: ¿Dónde está alojada hoy la web y quién es el registrador del dominio? ¿Quién gestiona hoy
  los DNS? → *pendiente*
- Q2: ¿El correo de @marbellainstyle.com está en Google Workspace, IONOS u otro? → *pendiente*
- Q3: Hoy el canonical es `https://marbellainstyle.com/` (sin www). ¿Confirmas que la nueva web
  usa `www.` y el dominio desnudo redirige? → *pendiente* (recomendación: sí, `www.`)
- Q4: Mientras se construye, ¿dónde vive la nueva producción? Recomendación: en un subdominio
  temporal protegido por Access (`nueva.marbellainstyle.com`) que el día del lanzamiento pasa a
  servir `www.`. → *pendiente*
- Q5: ¿Quién debe entrar en pruebas (emails para Access)? → *pendiente*

### Session 2026-11-11 (respuestas de Julio, 22:01)

- Q1 → La web actual está alojada en IONOS. *Falta*: ¿el dominio está registrado también en IONOS
  y sus DNS se gestionan allí? ¿La web actual está en el mismo contrato Unlimited Plus?
- Q2 → El correo está en Google Workspace: hay que conservar los MX de Google, SPF
  (`include:_spf.google.com`), la clave DKIM (`google._domainkey`) y DMARC, todos en «solo DNS».
- Q4 → «Necesitaremos un entorno/dominio de pruebas y otro de producción»: `staging.` y `www.`
  (con `nueva.` como nombre temporal de producción hasta el lanzamiento, si se confirma).

### Session 2026-11-11 (respuestas de Julio, 22:09)

- Q1 → El dominio está registrado en IONOS y la web y los DNS se gestionan allí. El cambio de
  nameservers a Cloudflare se hace en el panel de dominios de IONOS (paso 1.4 del plan).

### Session 2026-11-11 (respuestas de Julio, 22:15 y 22:19)

- Bases de datos: una por entorno, creadas en IONOS; Git solo lleva código (plan, «Bases de datos»).
- «Tendremos un plugin MCP desde el principio» → MCP Adapter en producción y pruebas desde la
  fase 1 (US7, FR-010). Lectura de Claude: «desde el principio» incluye producción; las Abilities
  que escriben crean borradores.

### Session 2026-11-11 (respuestas de Julio, 22:36)

- Constitución 1.0.0 aprobada.
- «El dominio no será el de marbellainstyle todavía. Usaremos uno temporal.» → la fase 1 usa un
  dominio temporal (`www.` y `staging.`), los dos protegidos con Access hasta el lanzamiento. US1
  (DNS de marbellainstyle.com a Cloudflare) pasa a la preparación del lanzamiento. Q3 y Q4 quedan
  sustituidas. *Pendiente*: nombre del dominio temporal.
- Q5 (emails de Access): aclarado qué significa; *pendiente* la lista.

### Session 2026-11-13 (pruebas primero)

- Julio pide preparar ya la infraestructura de **pruebas** en IONOS para ver la maqueta real
  (tema Ollie, Polylang con inglés sin prefijo y español en `/es/`). Se monta pruebas antes que
  producción, con la guía [docs/runbooks/pruebas-ionos.md](../../docs/runbooks/pruebas-ionos.md).
- Mientras la API pública de marOS no exista, pruebas usa `MAROS_ENV=mock` con los JSON del repo
  (los mismos que en local); al estar lista, `MAROS_ENV=staging` y marOS pruebas. El mu-plugin
  `mis-entorno` impide en cualquier caso apuntar a marOS producción.
- Q6: ¿Qué dominio temporal? → **`privatevillacollection.com`** (Julio, 13/11, 13:55): pruebas en
  `staging.privatevillacollection.com` y producción hasta el lanzamiento en
  `www.privatevillacollection.com`.
- Q5 (emails de Access) → **todos los emails `@marbellainstyle.com`** (Julio, 13/11, 13:55):
  política de Access «Emails ending in `@marbellainstyle.com`».
- Nada se cambia en las cuentas de IONOS ni de Cloudflare sin confirmación de Julio: los pasos de
  panel los hace Julio con la guía.

