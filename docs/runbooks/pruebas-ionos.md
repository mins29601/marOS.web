# Pruebas (staging) en IONOS, paso a paso

Cómo se monta **pruebas**, donde se ve la maqueta real de la web nueva: WordPress en IONOS
Unlimited Plus, detrás de Cloudflare, privado con Cloudflare Access, sin indexar y desplegado
desde GitHub. Es la parte de pruebas del [plan de la spec 001](../../specs/001-infraestructura-web/plan.md)
(pasos 1a, 2, 3 y 4); producción se monta igual más adelante.

Leyenda: **[Tú]** lo haces en un panel · **[Claude]** ya está en el repo o lo hago yo ·
**[Juntos]** lo hacemos con tu Mac conectado (Claude te guía o lo ejecuta).

## Cómo queda

```text
Persona autorizada ──► Cloudflare (DNS · SSL · Access: email + código) ──► IONOS ~/staging
                                                                             WordPress + Ollie
GitHub (main) ── Actions por SSH ──► ~/mis/staging/releases/<commit>  ──────► tema hijo, plugins
                                                                             propios, mu-plugins
```

| | Pruebas |
|---|---|
| URL | `https://staging.privatevillacollection.com` |
| Quién entra | cualquier email `@marbellainstyle.com` (Access envía un código de un solo uso) |
| Buscadores | `noindex` triple: ajuste de WordPress + cabecera `X-Robots-Tag` + `robots.txt` cerrado |
| Servidor de IONOS | solo acepta tráfico que venga de Cloudflare (`.htaccess`) |
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

1. **Carpeta**: IONOS → Hosting → **Webspace** → crear la carpeta `staging` en la raíz.
2. **Subdominio**: IONOS → `privatevillacollection.com` → Subdominios → crear `staging` → «Usar con
   hosting» → carpeta `staging`. Como el DNS está en Cloudflare, IONOS te enseña la **IP del
   servidor**: apúntala.
3. **PHP**: Hosting → **PHP** → para `staging`, la versión 8.3 o la más alta estable.
4. **Base de datos**: Hosting → **Bases de datos** → crear MySQL (8.x) con la descripción
   «MIS pruebas». Apunta **host, nombre, usuario y contraseña** solo en tu gestor de contraseñas.
5. **SSH**: Hosting → **SFTP y SSH** → apunta el **servidor** (`access-….webspace-host.com`) y el
   **usuario**. Si el plan permite claves SSH, mejor que contraseña (lo comprobamos en el paso 4).

## 3. DNS y certificado · [Tú] con mi guía

1. Cloudflare → **DNS** → registro `A` `staging` → la IP del paso 2.2 → **Proxied** (nube naranja).
2. Certificado entre Cloudflare e IONOS, por este orden de preferencia:
   - **Certificado de origen de Cloudflare** (SSL/TLS → Origin Server → Create, 15 años, para
     `*.privatevillacollection.com` y `privatevillacollection.com`) importado en IONOS → SSL → «Subir certificado», si tu plan lo
     permite. No caduca ni depende de que IONOS pueda validar el dominio.
   - Si IONOS no permite importarlo: certificado SSL de IONOS para `staging.privatevillacollection.com` emitido con
     la nube **gris** (solo DNS) y, una vez emitido, volver a naranja. Hay que vigilar su
     renovación anual.

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

1. **Settings → Authentication**: dejar activado **One-time PIN** (código al email).
2. **Access → Applications → Add → Self-hosted**: nombre «MIS pruebas», dominio
   `staging.privatevillacollection.com`, sesión de 7 días.
   - Política **Equipo** (Allow): **Emails ending in** `@marbellainstyle.com` (decisión de
     Julio, 13/11). Como el correo está en Google Workspace, se puede añadir además
     «Google» como forma de entrar (un clic, sin código); el código por email sirve igual.
   - Política **GitHub** (Service Auth): token de servicio del punto 3, para que la comprobación
     tras cada despliegue pueda entrar.
3. **Access → Service Auth → Create service token** «github-smoke»: copia el *Client ID* y el
   *Client Secret* (se ve una sola vez) directamente en GitHub (paso 7), no en un chat.
4. Más adelante, cuando conectemos el MCP de pruebas y los avisos de marOS: política **Bypass**
   solo para `/wp-json/mcp/*` y `/wp-json/maros/v1/webhook` (decisión D10), protegidas por
   contraseña de aplicación y firma.
5. Opcional, segunda capa de noindex en Cloudflare: **Rules → Transform Rules → Response headers**
   → si el host es `staging.privatevillacollection.com`, añadir `X-Robots-Tag: noindex, nofollow`.

## 6. `wp-config.php` en el servidor · [Tú] o [Juntos], 10 min

1. Copia [`config/wp-config.staging.example.php`](../../config/wp-config.staging.example.php).
2. Rellena lo que va entre `<>`: los datos de la base del paso 2.4 y la ruta
   absoluta de tu cuenta (la da `pwd` por SSH). Las claves `AUTH_KEY…` se generan en
   https://api.wordpress.org/secret-key/1.1/salt/.
3. Súbelo como `staging/wp-config.php` con el **Webspace Explorer** de IONOS o por SFTP.
   Es el único sitio donde vive la contraseña de la base.

## 7. Entorno «staging» en GitHub · [Tú], 10 min

GitHub → `mins29601/marOS.web` → **Settings → Environments → New environment** → `staging`.

**Variables** (Environment variables):

| Nombre | Valor |
|---|---|
| `SSH_HOST` | `access-….webspace-host.com` |
| `SSH_USER` | usuario SSH de IONOS |
| `SSH_PORT` | `22` |
| `WP_PATH` | `staging` |
| `MIS_PATH` | `mis/staging` |
| `SITE_URL` | `https://staging.privatevillacollection.com` |
| `WP_ADMIN_USER` | tu usuario de WordPress, p. ej. `julio` |
| `WP_ADMIN_EMAIL` | tu email |
| `DEPLOY_MODE` | `symlink` (o `copy` si el paso 4 dijo que no hay enlaces) |
| `DEPLOY_CF_ONLY` | `1` (`0` solo si hay que probar sin Cloudflare) |

**Secretos** (Environment secrets):

| Nombre | Valor |
|---|---|
| `SSH_PRIVATE_KEY` | contenido de `~/.ssh/mis_deploy_staging` |
| `SSH_KNOWN_HOSTS` | contenido de `~/.ssh/mis_staging_known_hosts` |
| `WP_ADMIN_PASSWORD` | contraseña larga para tu usuario de WordPress de pruebas |
| `CF_ACCESS_CLIENT_ID` / `CF_ACCESS_CLIENT_SECRET` | token «github-smoke» del paso 5.3 |

Mientras `SSH_HOST` esté vacío, el despliegue automático no hace nada (no falla).

## 8. Arrancar · [Claude], 10 min

1. GitHub → **Actions → Arrancar pruebas → Run workflow** (con «contenido de ejemplo» marcado).
   Instala WordPress, Ollie y los plugins de `.wp-env.json`, despliega el código del repo, activa
   el tema hijo, configura Polylang (inglés sin prefijo, español en `/es/`) y carga las páginas,
   menús y propiedades de ejemplo, como en local.
2. **Cron real**: IONOS → Hosting → **Cron jobs** (o `crontab -e` por SSH), cada 5 minutos:
   `php $HOME/bin/wp --path=$HOME/staging cron event run --due-now --quiet`
   (si IONOS trae WP-CLI propio, `wp` en lugar de `php $HOME/bin/wp`).
3. Abre `https://staging.privatevillacollection.com`: Access te pide el email, llega un código y entras.

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

## Pendiente de comprobar en la primera conexión

- Si IONOS admite claves SSH, enlaces simbólicos y cron en este plan (paso 4).
- Si IONOS permite importar el certificado de origen de Cloudflare (paso 3).
- Que IONOS no tenga otro proxy delante de Apache: si lo tuviera, el filtro por IPs de Cloudflare
  bloquearía todo; se cambiaría por una cabecera secreta que añade Cloudflare.
