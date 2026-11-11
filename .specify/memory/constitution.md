<!--
Sync Impact Report
- Versión: — → 1.0.0 (BORRADOR, pendiente de aprobación de Julio)
- Origen: docs/arquitectura-maros.md (decisiones del 11 de noviembre de 2026) y constitución de
  marOS 1.4.0, principio X (API publicada para la web y los colaboradores).
- Plantillas: plan-template.md adaptado (la tabla de reutilización habla de WordPress, no de Mantine).
-->
# Constitución de la web marbellainstyle.com

La web pública de Marbella in Style es un WordPress estándar alojado en IONOS, con Cloudflare
delante. Vende las villas que gestiona la empresa y muestra su disponibilidad, presupuestos y
reservas a través de la API de marOS. Esta constitución fija las reglas que toda especificación,
plan e implementación de este repositorio deben cumplir.

**Estado**: BORRADOR 1.0.0. No rige hasta que Julio la apruebe.

## Principios fundamentales

### I. Las fuentes mandan y no se inventan datos

- Cada spec DEBE citar sus fuentes: `docs/arquitectura-maros.md`, decisiones de Julio con fecha,
  specs de marOS o la web actual.
- Cada dato de una villa tiene una sola fuente: Lodgify (vía marOS), el expediente de marOS o
  WordPress. La web nunca edita lo que manda marOS (spec 003).
- Está prohibido inventar villas, precios, disponibilidad, opiniones o identificadores. Lo que
  falta se marca como pendiente.

### II. WordPress estándar, sin atarse a nada

- La web DEBE admitir cualquier tema y plugin de WordPress. El repositorio solo contiene código
  propio: tema hijo, plugin marOS Connector, mu-plugins y scripts. El núcleo y los plugins de
  terceros se instalan por el panel o WP-CLI y se anotan en `config/plugins.md`.
- El código propio sigue los WordPress Coding Standards, usa las APIs del núcleo (bloques,
  REST, Abilities, Interactivity) antes que librerías externas, y funciona con la versión de PHP
  que ofrezca IONOS.

### III. marOS es la única puerta a reservas

- La web NUNCA llama a Lodgify. Propiedades, disponibilidad, presupuestos y reservas vienen de la
  API pública de marOS (constitución de marOS, principio X).
- La API se usa para **cargar** propiedades, calendario y tarifas en WordPress, no en cada
  búsqueda. Solo el presupuesto final y la reserva llaman a marOS en el momento.
- La clave de la API vive solo en el servidor (`wp-config.php`), nunca en la base de datos, el
  repositorio ni el navegador. El navegador habla con un proxy del plugin.
- Las búsquedas por fechas, los presupuestos y las reservas NUNCA se guardan en caché de página.
- Las preguntas o cambios del contrato de la API se plantean como issues en `mins29601/marOS`.

### IV. Dos entornos separados y promoción explícita

- Producción (`www.`) y pruebas (`staging.`) tienen instalación, base de datos, claves y
  secretos propios. Pruebas apunta a marOS pruebas; producción a marOS producción.
- Pruebas nunca es pública ni indexable: Cloudflare Access, `noindex` y cabecera
  `X-Robots-Tag`, y sin enviar correos a clientes.
- El contenido solo se copia de producción a pruebas. El código solo llega por Git: se despliega
  en pruebas al fusionar en `main` y en producción con una aprobación explícita.
- Nadie edita código en el servidor.

### V. Ninguna URL con valor SEO se pierde

- Toda URL indexada de la web actual DEBE acabar en un 200 o en una sola redirección 301 a su
  equivalente; las que desaparecen sin equivalente responden 410 y se anotan.
- El mapa de redirecciones vive en el repositorio y se comprueba automáticamente antes de cada
  lanzamiento.

### VI. Datos personales mínimos

- WordPress no guarda datos de huéspedes de reservas: los reenvía a marOS y solo conserva el id
  de la reserva y su estado.
- Los formularios de contacto guardan lo mínimo, con consentimiento (RGPD) y plazo de borrado.

### VII. Rápida, accesible y en varios idiomas

- Las páginas públicas DEBEN cumplir WCAG 2.2 AA y Core Web Vitals en verde (LCP < 2,5 s,
  INP < 200 ms, CLS < 0,1) en móvil.
- Cada idioma tiene su URL, `hreflang` y canonical. La documentación y las specs se escriben en
  español.

### VIII. Lo que hace la IA queda revisable

- El MCP está activo en los dos entornos desde el inicio. Sus Abilities solo actúan con un usuario
  de WordPress propio de cada entorno y con permisos limitados, y en producción crean borradores
  o revisiones; publicar en producción lo decide una persona. Cada llamada queda registrada.

## Flujo de trabajo

- Cada cambio empieza con una spec en `specs/NNN-nombre/` (spec-kit): spec → plan → tareas →
  implementación, en una rama y un pull request a `main`.
- Las decisiones de Julio se anotan, con fecha, en `docs/decisiones.md` y en la sección
  Clarifications de la spec que afecten.
- Las comprobaciones (lint, pruebas, mapa de redirecciones) se ejecutan en GitHub Actions y
  deben pasar antes de fusionar.

## Gobierno

Esta constitución prevalece sobre cualquier otra práctica del repositorio. Cambiarla requiere la
aprobación de Julio, una nota en el Sync Impact Report y subir la versión (MAJOR si se elimina o
contradice un principio, MINOR si se añade, PATCH si se aclara).

**Versión**: 1.0.0 (borrador) | **Ratificada**: pendiente | **Última modificación**: 2026-11-11
