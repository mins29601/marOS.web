# Pruebas (staging) en IONOS, paso a paso

Cómo se monta **pruebas**, donde se ve la maqueta real de la web nueva: WordPress en IONOS
Unlimited Plus, detrás de Cloudflare, privado con Cloudflare Access, sin indexar y desplegado
desde GitHub. Es la parte de pruebas del [plan de la spec 001](../../specs/001-infraestructura-web/plan.md)
(pasos 1a, 2, 3 y 4); producción se monta igual más adelante.

Leyenda: **[Tú]** lo haces en un panel · **[Claude]** ya está en el repo o lo hago yo ·
**[Juntos]** lo hacemos con tu Mac conectado (Claude te guía o lo ejecuta).

## Cómo queda

```text
Persona autorizada ──► Cloudflare (DNS · SSL · Access: email + código) ──► IONOS ~/marbellainstyle/staging
                                                                             WordPress + Ollie
GitHub (main) ── Actions por SSH ──► ~/mis/staging/releases/<commit>  ──────► tema hijo, plugins
                                                                             propios, mu-plugins
```

| | Pruebas |
|---|---|
| URL | `https://staging.privatevillacollection.com` |
| Quién entra | cualquier email `@marbellainstyle.com`, con su cuenta de Google (un clic) o con un código de un solo uso al email |
| Buscadores | `noindex` triple: ajuste de WordPress + cabecera `X-Robots-Tag` + `robots.txt` cerrado |
| Servidor de IONOS | solo acepta peticiones con la cabecera secreta que añade Cloudflare (`X-MIS-Origin`, comprobada en `.htaccess`) |
| Correo de WordPress | bloqueado: se anota en el log, nunca sale a clientes |
| Datos de propiedades | al principio el **mock** del repo (las mismas propiedades de ejemplo que en local); cuando la API pública de marOS esté lista, **marOS pruebas** |
| Código | se despliega solo, al fusionar en `main` (workflow «Deploy pruebas») |
| WordPress, Ollie y plugins de terceros | se instalan una vez con el workflow «Arrancar pruebas»; la lista es la de `.wp-env.json`, igual que en local |
| Base de datos | propia, creada en IONOS; su contraseña solo vive en `wp-config.php` del servidor |

## Antes de empezar

- [x] **Dominio temporal**: `privatevillacollection.com` (Julio, 13/11).
- [x] **Quién entra**: todos los emails `@marbellainstyle.com` (Julio, 13/11).
- [ ] Acceso al panel de IONOS, a una cuenta de Cloudflare (gratis) y a GitHub como dueño del repo.
- [ ] Las ramas de la planificación y del entorno local fusionadas en `main` (PR #1, #3 y #4, y la de
      este documento): GitHub solo deja lanzar a mano los workflows que ya están en `main`.

## 1. Dominio temporal en Cloudflare · [Tú], 15 min + espera

1. IONOS → **Dominios y SSL** → comprar `privatevillacollection.com` (si aún no es tuyo). No hace falta correo ni web en él.
2. Cloudflare → **Add a site** → el dominio → plan **Free**. Cloudflare muestra dos nameservers.
3. IONOS → el dominio → **Nameservers** → «Usar nameservers propios» → pegar los dos de Cloudflare.
4. Esperar el correo de Cloudflare «Your domain is now active» (minutos u horas).
5. Cloudflare → **SSL/TLS** → modo **Full (strict)** (ver paso 3 si el certificado aún no está).
   **Always Use HTTPS**: activado.

## 2. Hosting en IONOS · [Tú], 20 min

1. **Carpeta**: IONOS → Hosting → **Webspace** → crear la carpeta `staging` (en esta cuenta: `marbellainstyle/staging`).
2. **Subdominio**: IONOS → `privatevillacollection.com` → Subdominios → crear `staging` → «Usar con
   hosting» → carpeta `staging`.
   **IP del servidor** (hace falta en el paso 3): el panel no siempre la enseña. Búscala en
   Cloudflare → DNS → Records (al añadir el sitio copió los registros `A` de IONOS), en IONOS →
   Hosting → detalles del contrato, o en el registro `A` de `@` de marbellainstyle.com si la web
   actual está en el mismo contrato.
3. **PHP**: Hosting → **PHP** → para `staging`, la **8.4** (la 8.5 es muy nueva para algunos plugins). Local usa la misma en `.wp-env.json`.
4. **Base de datos**: Hosting → **Bases de datos** → crear MySQL (8.x) con la descripción
   «MIS pruebas». Apunta **host, nombre, usuario y contraseña** solo en tu gestor de contraseñas.
5. **SSH**: Hosting → **SFTP y SSH** → apunta el **servidor** (`access-….webspace-host.com`) y el
   **usuario**. Si el plan permite claves SSH, mejor que contraseña (lo comprobamos en el paso 4).

## 3. DNS y certificado · [Tú] con mi guía

1. Cloudflare → **DNS** → registro `A` `staging` → la IP del paso 2.2 → **Proxied** (nube naranja).
2. **Comprobar la IP sin certificado**: Cloudflare → SSL/TLS → modo **Flexible** un momento y
   abrir un `prueba.html` subido a la carpeta `staging` (comprobado el 13/11: la IP de IONOS es
   `217.160.0.178`).
3. **Certificado de IONOS** (IONOS no permite importar el certificado de origen de Cloudflare,
   comprobado el 13/11): Dominios & SSL → SSL → «El certificado se instalará automáticamente en
   la infraestructura gestionada por IONOS» → `staging.privatevillacollection.com` (o el comodín).
   Si pide un registro DNS de validación, se añade en Cloudflare con la nube gris y se deja para
   las renovaciones.
4. Con el certificado activo: Cloudflare → SSL/TLS → **Full (strict)**. Vigilar la renovación
   anual (si fallara, Cloudflare daría error 526).

## 4. Clave SSH para GitHub · [Juntos], 10 min

GitHub despliega por SSH con una clave propia que solo sirve para esto. Con tu Mac conectado lo
hago yo; a mano son tres comandos en Terminal:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/mis_deploy_staging -N "" -C "github-deploy-staging"
ssh-copy-id -i ~/.ssh/mis_deploy_staging.pub <usuario>@<servidor>     # pide la contraseña SSH una vez
ssh-keyscan <servidor> > ~/.ssh/mis_staging_known_hosts
```

Prueba: `ssh -i ~/.ssh/mis_deploy_staging <usuario>@<servidor> 'php -v; which wp; ln --version | head -1'`
nos dice la versión de PHP, si IONOS trae WP-CLI y si admite enlaces simbólicos (si no,
`DEPLOY_MODE=copy` en el paso 7). Si IONOS no admite claves SSH en este plan, avísame:
el despliegue pasaría a usar la contraseña como secreto de GitHub.

## 5. Cloudflare Access · [Tú], 15 min

Cloudflare → **Zero Trust** (gratis hasta 50 personas; pide un nombre de equipo, p. ej. `mis`).

1. **Settings → Authentication**: entrar con **Google** (decisión de Julio, 13/11) y dejar
   **One-time PIN** (código al email) como alternativa.
   - Google Cloud Console (con tu cuenta de Workspace) → proyecto «MIS Access» → **APIs y
     servicios → Pantalla de consentimiento OAuth**: tipo **Interno** (solo cuentas de
     @marbellainstyle.com).
   - **Credenciales → Crear ID de cliente OAuth** → Aplicación web → URI de redirección
     autorizado: `https://<equipo>.cloudflareaccess.com/cdn-cgi/access/callback` (el `<equipo>`
     es el nombre de equipo de Zero Trust).
   - Cloudflare → Authentication → **Add new → Google** → pegar el *Client ID* y el *Client
     secret* → **Test**.
   - En la aplicación del punto 2, marcar Google y One-time PIN como formas de entrar (con
     «Instant Auth» desactivado, la pantalla ofrece las dos).
2. **Access → Applications → Add → Self-hosted**: nombre «MIS pruebas», dominio
   `staging.privatevillacollection.com`, sesión de 7 días.
   - Política **Equipo** (Allow): **Emails ending in** `@marbellainstyle.com` (decisión de
     Julio, 13/11). La política se aplica entre por Google o por código.
   - Política **GitHub** (Service Auth): token de servicio del punto 3, para que la comprobación
     tras cada despliegue pueda entrar.
3. **Access → Service Auth → Create service token** «github-smoke»: copia el *Client ID* y el
   *Client Secret* (se ve una sola vez) directamente en GitHub (paso 7), no en un chat.
4. Más adelante, cuando conectemos el MCP de pruebas y los avisos de marOS: política **Bypass**
   solo para `/wp-json/mcp/*` y `/wp-json/maros/v1/webhook` (decisión D10), protegidas por
   contraseña de aplicación y firma.
5. **Cabecera secreta hacia IONOS** (sustituye al filtro por IPs, que no sirve porque IONOS
   tiene un proxy delante de Apache): genera un secreto de 32 letras y números
   (`LC_ALL=C tr -dc A-Za-z0-9 </dev/urandom | head -c 32 | pbcopy`) y:
   - Cloudflare → privatevillacollection.com → **Rules → Transform Rules → Modify Request
     Header → Create rule** «MIS origen»: si *Hostname* es igual a
     `staging.privatevillacollection.com` → **Set static** `X-MIS-Origin` = el secreto.
   - GitHub → environment `staging` → secreto **`ORIGIN_SECRET`** = el mismo secreto.
   El siguiente despliegue lo exige en `.htaccess`; sin la regla de Cloudflare la web daría 403.
6. Opcional, segunda capa de noindex en Cloudflare: **Rules → Transform Rules → Response headers**
   → si el host es `staging.privatevillacollection.com`, añadir `X-Robots-Tag: noindex, nofollow`.

## 6. `wp-config.php` en el servidor · [Tú] o [Juntos], 10 min

1. Copia [`config/wp-config.staging.example.php`](../../config/wp-config.staging.example.php).
2. Rellena lo que va entre `<>`: los datos de la base del paso 2.4 y la ruta
   absoluta de tu cuenta (`/kunden/homepages/29/d668976456/htdocs`, ya puesta en la plantilla). Las claves `AUTH_KEY…` se generan en
   https://api.wordpress.org/secret-key/1.1/salt/.
3. Súbelo como `marbellainstyle/staging/wp-config.php` con el **Webspace Explorer** de IONOS o por SFTP.
   Es el único sitio donde vive la contraseña de la base.

## 7. Entorno «staging» en GitHub · [Tú], 10 min

GitHub → `mins29601/marOS.web` → **Settings → Environments → New environment** → `staging`.

**Variables** (Environment variables):

| Nombre | Valor |
|---|---|
| `SSH_HOST` | `home668976456.1and1-data.host` |
| `SSH_USER` | `u88262191` |
| `SSH_PORT` | `22` |
| `WP_PATH` | `marbellainstyle/staging` |
| `MIS_PATH` | `mis/staging` |
| `SITE_URL` | `https://staging.privatevillacollection.com` |
| `WP_ADMIN_USER` | tu usuario de WordPress, p. ej. `julio` |
| `WP_ADMIN_EMAIL` | tu email |
| `MIS_PHP` | `php8.4-cli` (el PHP de la terminal de IONOS es 4.4) |
| `DEPLOY_MODE` | `copy` (en IONOS Apache no sirve los CSS y JS a través de enlaces simbólicos; comprobado el 13/11) |

**Secretos** (Environment secrets):

| Nombre | Valor |
|---|---|
| `SSH_PRIVATE_KEY` | contenido de `~/.ssh/mis_deploy_staging` |
| `SSH_KNOWN_HOSTS` | contenido de `~/.ssh/mis_staging_known_hosts` |
| `WP_ADMIN_PASSWORD` | contraseña larga para tu usuario de WordPress de pruebas |
| `CF_ACCESS_CLIENT_ID` / `CF_ACCESS_CLIENT_SECRET` | token «github-smoke» del paso 5.3 |
| `ORIGIN_SECRET` | la cabecera secreta del paso 5.5 |

Mientras `SSH_HOST` esté vacío, el despliegue automático no hace nada (no falla).

## 8. Arrancar · [Claude], 10 min

1. GitHub → **Actions → Arrancar pruebas → Run workflow** (con «contenido de ejemplo» marcado).
   Instala WordPress, Ollie y los plugins de `.wp-env.json`, despliega el código del repo, activa
   el tema hijo, configura Polylang (inglés sin prefijo, español en `/es/`) y carga las páginas,
   menús y propiedades de ejemplo, como en local.
2. **Cron real**: IONOS → Hosting → **Cron jobs** (o `crontab -e` por SSH), cada 5 minutos:
   `/usr/bin/php8.4-cli /kunden/homepages/29/d668976456/htdocs/bin/wp-cli.phar --path=/kunden/homepages/29/d668976456/htdocs/marbellainstyle/staging cron event run --due-now --quiet`
   (en IONOS el `php` de la terminal es PHP 4.4: hay que llamar al PHP 8 por su nombre; el
   arranque deja WP-CLI en `~/bin/wp-cli.phar`).
3. Abre `https://staging.privatevillacollection.com`: Access ofrece «Google» (un clic) o el código por email, y entras.

Desde aquí, cada PR fusionado en `main` se despliega solo en pruebas (workflow **Deploy pruebas**)
y al final comprueba que la web responde, que lleva `noindex` y que sin identificarse no se ve.
Para enseñar una rama antes de fusionarla: Actions → Deploy pruebas → Run workflow → `ref`.

## Comprobaciones (spec 001, US3)

| Comprobación | Cómo |
|---|---|
| Sin permiso no se ve | ventana privada → `staging.privatevillacollection.com` → pide email |
| No se indexa | `curl -sI https://staging.privatevillacollection.com` con token → `x-robots-tag: noindex, nofollow`; `/robots.txt` → `Disallow: /` |
| El servidor no responde sin Cloudflare | `curl -sk --resolve staging.privatevillacollection.com:443:<IP de IONOS> https://staging.privatevillacollection.com/` → 403 |
| El correo no sale | enviar un formulario → no llega; aparece «Correo bloqueado» en `debug.log` |
| marOS | aviso azul en el escritorio de WordPress: «Entorno staging · marOS: mock» |

## Volver atrás

Actions → **Deploy pruebas** → Run workflow → `ref` = el commit anterior. En el servidor se
guardan las 5 últimas versiones en `~/mis/staging/releases/`.

## Comprobado en la primera conexión (13/11)

- Claves SSH: sí. Enlaces simbólicos: se crean, pero Apache no sirve CSS/JS a través de ellos (por eso `DEPLOY_MODE=copy`). `crontab`: funciona (cron de WordPress puesto el 13/11, cada 5 minutos). rsync y WP-CLI: instalados.
- Home de la cuenta: `/kunden/homepages/29/d668976456/htdocs` (es la raíz del webspace; `~/mis/.htaccess` lo cierra a la web).
- El `php` de la terminal es PHP 4.4: los scripts buscan solos `php8.4-cli` (hay de la 4.4 a la 8.6; `scripts/remote/php-bin.sh`). Servidor SSH: `home668976456.1and1-data.host`, usuario `u88262191`. La carpeta de pruebas es `~/marbellainstyle/staging`.

## Pendiente de comprobar

- ~~Filtro por IPs de Cloudflare~~: IONOS tiene un proxy delante de Apache y daba 403 a todo (13/11); sustituido por la cabecera secreta `X-MIS-Origin` (paso 5.5).