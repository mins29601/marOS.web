# Plugins y temas de terceros

Lista de lo que se instala en cada entorno (decisiones en [docs/decisiones.md](../docs/decisiones.md)).
En local se instalan solos con `.wp-env.json`; en pruebas y producción, desde el panel o WP-CLI,
con la misma versión que aquí.

| Plugin / tema | Decisión | Licencia | Local | Pruebas | Producción | Notas |
|---|---|---|---|---|---|---|
| Ollie (tema padre) | D1 | gratis (wp.org) | sí | sí | sí | Ollie Pro opcional, más patrones |
| `marbellainstyle` (tema hijo) | D1 | propio | sí | sí | sí | En este repo |
| Yoast SEO | D3 | gratis (wp.org) | sí | sí | sí | Exige WP ≥ 7.0 |
| Complianz | D8 | gratis (wp.org) | sí | sí | sí | |
| Secure Custom Fields | D16 | gratis (wp.org) | sí | sí | sí | Campos de tours y servicios |
| Site Reviews | D11 | gratis (wp.org) | sí | sí | sí | Se usa al lanzar |
| FluentSMTP | D13 | gratis (wp.org) | sí | sí | sí | Fuera de producción el correo lo bloquea `mis-entorno` |
| WPvivid Backup | D9, D12 | gratis (wp.org) | sí | sí | sí | WPvivid Pro: de pago, pendiente |
| Cloudflare (APO) | D5 | gratis (wp.org) | instalado, inactivo | sí | sí | En local no hay Cloudflare |
| MCP Adapter | T5, T20 | gratis (wp.org) | sí | sí | sí | Expone `/wp-json/mcp` |
| Enable Abilities for MCP | T5 | gratis (wp.org) | sí | sí | sí | |
| Polylang | D2 | gratis (wp.org) | sí | sí | sí | en (por defecto) + es; Polylang Pro opcional |
| **Gravity Forms** | D4 | **de pago** | con override | sí | sí | Falta la licencia o los zip |
| **WPvivid Pro** | D9 | **de pago** | no | sí | sí | Falta la licencia |

## Plugins de pago en local

Hoy solo aplica a Gravity Forms. Los zip de pago no se suben al repositorio. Guárdalos en `paid-plugins/` (ignorada por git) y
copia `.wp-env.override.example.json` a `.wp-env.override.json`. Después, `npm run env:start -- --update`.
