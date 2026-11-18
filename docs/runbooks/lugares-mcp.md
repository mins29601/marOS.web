# Lugares recomendados por el MCP

Rebecca añade, edita y retira los lugares que recomendamos en cada zona (restaurantes, beach clubs,
golf, supermercados…) hablando con su asistente (ChatGPT o Claude). Decisión T47.

## Qué es un lugar

Un registro para los dos idiomas, en **Lugares** del panel. Lo usan las guías de zona: cada lugar
publicado sale bajo su sección («Comer y salir», «Golf y deporte» o «Servicios») como una tarjeta
con nombre, tipo, dónde está, la frase corta y el enlace a su web. El texto de la guía no cambia.

| Campo | Obligatorio | Nota |
|---|---|---|
| nombre | sí | Como lo escribe el local |
| zonas | sí | Una o varias (slug de `mis/list-zones`) |
| tipo | sí | restaurant, beach_club, chiringuito, bar, cafe → Comer y salir · golf, sport → Golf y deporte · supermarket, pharmacy, shopping, health, service → Servicios |
| resumen_en / resumen_es | no | Para qué lo recomendamos, 140 caracteres. Inglés británico; español de tú |
| donde | no | Playa, urbanización o calle. Sin distancias |
| web / web_fecha | no | Web oficial comprobada; la fecha es hoy si no se da |
| fuente | sí | `guia` (guía interna), `instagram` (con enlace y fecha del post) o `tripadvisor` (con fecha de consulta) |
| notas | no | Internas, no se publican |
| orden | no | Menor, antes |

La web rechaza teléfonos, distancias o tiempos y superlativos («el mejor») en los textos, como
pide la guía editorial de marOS.content (§4) y PC-17. Lo nuevo entra como **borrador** hasta que se
pida publicar (`publicar: true`). Para cambiar el estado por defecto: constante
`MIS_LUGARES_STATUS` (`draft` o `publish`).

## Herramientas

| Ability | Herramienta MCP | Qué hace |
|---|---|---|
| `mis/list-zones` | `mis-list-zones` | Zonas con slug, nombre en inglés y español y nº de lugares |
| `mis/list-places` | `mis-list-places` | Lugares con todos sus datos; filtros zona, tipo, estado, buscar |
| `mis/save-place` | `mis-save-place` | Crea uno, o con `id` cambia solo lo que se envía. No duplica: mismo nombre en la misma zona → error con el id |
| `mis/delete-place` | `mis-delete-place` | Lo manda a la papelera (se puede recuperar) |

Están en dos sitios:

- **Servidor propio de lugares**: `https://<web>/wp-json/mis/lugares`. Solo tiene estas cuatro.
- El MCP general (`/wp-json/mcp/mcp-adapter-default-server`), para quien ya lo usa (por ejemplo,
  `claude-mcp`). Si el plugin Enable Abilities for MCP no las deja activas solas, activarlas en
  **Ajustes → Enable Abilities for MCP**.

## Acceso de Rebecca

1. **Usuarios → Añadir nuevo**: usuario de Rebecca con el rol **Gestor de lugares**. Ese rol solo
   puede tocar lugares (ni casas, ni páginas, ni opiniones, ni zonas).
2. En su perfil, sección **Lugares desde ChatGPT**, marcar «Crear un enlace» y guardar. Sale una
   vez el enlace `https://<web>/wp-json/mis/lugares?clave=mis_…`. También por SSH:
   `wp mis-lugares clave <usuario>`.
3. Desde el perfil se ve cuándo se usó por última vez, se crea uno nuevo (el anterior deja de
   valer) o se revoca (`wp mis-lugares clave <usuario> --revocar`).

La clave también vale en la cabecera `x-api-key` o `Authorization: Bearer`, que es lo que usan
Claude, n8n o Claude Code. Límite: 60 peticiones por minuto y usuario (filtro
`mis_lugares_rate_limit`). Las altas, cambios y bajas quedan en el registro de marOS Connector con
el usuario.

### Conectar ChatGPT

ChatGPT solo conecta servidores MCP con OAuth o sin autenticación, y sin cabeceras propias; por
eso la clave va en el enlace. Las herramientas que escriben necesitan el **modo desarrollador**
de ChatGPT, que según la documentación de OpenAI está en los planes Business, Enterprise y Edu
(con Plus o Pro solo se pueden leer datos; comprobarlo en su cuenta).

1. ChatGPT → Ajustes → Aplicaciones y conectores → Ajustes avanzados → activar **Modo
   desarrollador**.
2. **Crear** conector: nombre «Marbella in Style · Lugares», URL del servidor MCP = el enlace con
   la clave, autenticación **Sin autenticación**.
3. En un chat nuevo, activar el conector en «+ → Modo desarrollador». Ejemplo: «Añade Tikitano en
   New Golden Mile, beach club, en Guadalmansa, lo recomendamos en este post de Instagram del
   3-10-2026: <enlace>. Web: <web>».

### Conectar Claude

Conector personalizado en claude.ai: URL `https://<web>/wp-json/mis/lugares` (sin la clave),
«Sin inicio de sesión» y encabezado `x-api-key` con la clave (como en marOS, research R7 de su
spec 013).

### En pruebas

Pruebas está detrás de Cloudflare Access: hace falta la misma excepción de ruta que D10 para
`/wp-json/mis/lugares` (o ampliar la de `/wp-json/mcp/*`).

## Riesgos de la clave en el enlace

Quien tenga el enlace puede añadir, cambiar o retirar lugares (nada más) hasta que se revoque.
Puede quedar en registros de Cloudflare o de IONOS y en capturas de pantalla. Por eso: un enlace
por persona, no compartirlo, revocarlo si se filtra y, si se quiere quitar este riesgo, pasar a
OAuth (pendiente; las herramientas no cambian).
