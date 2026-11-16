# Opiniones de Airbnb por el MCP

Un agente lee las opiniones de cada casa en Airbnb y las publica en la web con el MCP de WordPress
(spec 003, FR-006b; decisión T38).

## Qué necesita el agente

- El MCP de la web: MCP Adapter en `/wp-json/mcp/mcp-adapter-default-server`, con el usuario
  `claude-mcp` y su contraseña de aplicación (plan de la spec 001, paso 7). En pruebas, además, la
  excepción de Access para `/wp-json/mcp/*` (D10).
- En **Ajustes → Enable Abilities for MCP**, activar `maros/list-properties` y `maros/publish-review`
  (si el plugin no las deja activas por su `meta.mcp.public`).
- Site Reviews activo.

## Cómo se usan

1. `maros/list-properties` (opcional `search`) devuelve cada propiedad con `id`, `maros_id`,
   `lodgify_id`, idioma, URL y número de opiniones. Basta una de las traducciones: la opinión se
   asigna a todas.
2. `maros/publish-review`, una llamada por opinión:

```json
{
  "lodgify_id": 373016,
  "source": "airbnb",
  "source_review_id": "1234567890",
  "author": "Anna",
  "author_location": "Stockholm, Sweden",
  "date": "2026-08-14",
  "stay_date": "2026-08",
  "rating": 5,
  "content": "Texto tal cual aparece en Airbnb",
  "language": "en"
}
```

Responde `review_id`, `created` (false si ya existía: se puede reenviar todo sin duplicar),
`status` y las propiedades a las que se asignó.

## Reglas

- El texto va tal cual: sin traducir, resumir ni corregir. Solo el nombre que muestra Airbnb, nunca
  emails, teléfonos ni fotos del huésped; si el texto lleva un email o un teléfono, se rechaza.
- Estado al llegar: `MIS_REVIEWS_STATUS` en `wp-config.php` (`publish` por defecto, o `pending` para
  que una persona las apruebe en **Opiniones**). Una opinión se retira desde Opiniones.
- Cada opinión guardada queda en **Propiedades → marOS** (registro) con el usuario que la envió.
- La ficha las muestra con `[maros_reviews]` (nota media, número y las últimas 6). Si la casa no
  tiene opiniones, la sección no aparece.
