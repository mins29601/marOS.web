# Feature Specification: Modelo de contenido de la web

**Feature Branch**: `004-modelo-contenido`

**Created**: 2026-11-11

**Status**: Borrador.

**Fuentes**:

- [S1] Respuestas de Julio del 11 de noviembre de 2026 (22:01): «el tipo de contenido villa no es
  correcto, pues también hay otros tipos de propiedades como apartamentos»; «habrá otros tipos de
  datos, como tours»; nuevo diseño con tema nuevo; inglés y español.
- [S2] Inventario de la web actual ([spec 002, research](../002-migracion-web/research.md)): hoy
  todo (villas, apartamentos, tours, transporte, catering, eventos, landings) está en un solo tipo
  «Services» (`portfolio`), y las zonas son entradas del blog.
- [S3] Spec 003 marOS Connector (tipo Propiedad).

## Resumen

La web nueva separa el contenido por lo que es, no por cómo se maquetaba. Cada tipo tiene sus
campos, su plantilla en el tema y su URL. El tipo Propiedad lo alimenta marOS (spec 003); el
resto se edita en WordPress. Los tipos se registran en un plugin propio, `mis-contenido`, para
que sobrevivan a un cambio de tema y no dependan de marOS.

## Tipos de contenido

| Tipo | Clave | Origen | Ejemplos | URL propuesta (en / es) |
|---|---|---|---|---|
| **Propiedad** | `mis_property` | marOS + WP (spec 003) | villas, apartamentos | `/villas/<slug>/`, `/apartments/<slug>/` · `/es/villas/…`, `/es/apartamentos/…` |
| **Tour / experiencia** | `mis_tour` | WP | Granada private tour, wine tour Ronda | `/services/tours/<slug>/` · `/es/servicios/tours/<slug>/` |
| **Servicio** | `mis_service` | WP | chófer, traslados, chef privado, catering, bodas, eventos, conserjería, gestión de propiedades | `/services/<tipo>/<slug>/` · `/es/servicios/<tipo>/<slug>/` |
| **Zona** | taxonomía `zona` con página de zona | WP | Sierra Blanca, Milla de Oro, Nueva Andalucía | `/areas/<slug>/` · `/es/zonas/<slug>/` |
| **Entrada** (blog) | `post` | WP | guías, eventos, noticias | `/<slug>/` (se mantiene) |
| **Página** | `page` | WP | contacto, sobre nosotros, legales, landings | `/<slug>/` (se mantiene) |
| **Opinión** | del plugin de opiniones | WP | opiniones de huéspedes y clientes | sin URL propia |

Mantener la base `/services/<tipo>/` para tours y servicios conserva la URL de la mayoría de las
~115 páginas actuales sin redirección; solo las propiedades y las zonas cambian de ruta (con 301).

## Taxonomías

| Taxonomía | Se aplica a | Notas |
|---|---|---|
| `tipo-propiedad` | Propiedad | villa, apartamento… desde marOS (spec 003) |
| `equipamiento` | Propiedad | amenities, desde marOS |
| `zona` | Propiedad, Tour, Servicio, Entrada | una sola lista de zonas para toda la web |
| `tipo-servicio` | Servicio | transporte, catering, eventos, bodas, conserjería, gestión |
| `tipo-tour` | Tour | día completo, varios días, vino, cultura… |
| `category` | Entrada | se limpian las 11 actuales |

Las etiquetas (`post_tag`) no se migran: hoy están bloqueadas en robots.txt.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Cada tipo tiene su plantilla y sus campos (Priority: P1)

**Acceptance Scenarios**:

1. **Given** un editor que crea un Tour, **When** lo rellena, **Then** ve los campos del tour
   (duración, recogida, idiomas, incluye/no incluye, precio desde, privado o en grupo, galería) y
   la web lo pinta con la plantilla de tour en inglés y español.
2. **Given** un Servicio, **When** se publica, **Then** aparece en la lista de su tipo de servicio
   y en las zonas que tenga marcadas.
3. **Given** una Zona, **When** se abre su página, **Then** muestra su texto y las propiedades,
   tours y entradas de esa zona.

### User Story 2 - Pedir un tour o un servicio (Priority: P2)

**Acceptance Scenarios**:

1. **Given** la ficha de un tour o servicio, **When** el visitante pide información, **Then** el
   formulario (plugin de formularios, D4) lleva el nombre del tour o servicio y llega al equipo.
2. **Given** que en el futuro los tours se gestionen en otro sistema, **When** llegue ese momento,
   **Then** el tipo Tour podrá sincronizarse como la Propiedad, sin cambiar sus URLs.

### Edge Cases

- Un servicio que pertenece a dos tipos (p. ej. «BBQ party»: catering y eventos): un tipo
  principal para la URL y los demás como términos extra.
- Landings de campaña (`/vacation-rental-lp/`): páginas, no un tipo nuevo.

## Requirements *(mandatory)*

- **FR-001**: Los tipos `mis_tour` y `mis_service` y las taxonomías `zona`, `tipo-servicio` y
  `tipo-tour` MUST registrarse en el plugin `mis-contenido`; `mis_property`, `tipo-propiedad` y
  `equipamiento` en marOS Connector.
- **FR-002**: Todos los tipos MUST ser traducibles (en + es) con slugs traducidos.
- **FR-003**: Los campos MUST registrarse como metadatos con esquema (`register_post_meta`) para
  que la REST, las Abilities del MCP y los bloques (Block Bindings) los usen. La edición en el
  panel se hace con la herramienta de campos que se decida (D16).
- **FR-004**: Cada tipo MUST tener su marcado schema.org: Propiedad → `LodgingBusiness` /
  `VacationRental`, Tour → `TouristTrip`, Servicio → `Service`.

## Success Criteria *(mandatory)*

- **SC-001**: Cada URL del inventario que se conserva tiene un tipo de destino asignado.
- **SC-002**: Un editor crea un tour completo en inglés y español sin tocar el diseño.

## Clarifications

### Session 2026-11-11 (preguntas abiertas)

- Q1: ¿Los tours (y otros servicios) vendrán algún día de un sistema (marOS, un proveedor) o se
  editan siempre en WordPress? → *pendiente* (supuesto actual: WordPress)
- Q2: ¿Hay otros tipos que no veo en la web actual (p. ej. yates, coches, eventos con fecha)?
  → *pendiente*
- Q3: ¿Los tours se pueden reservar y pagar en la web, o solo se piden por formulario?
  → *pendiente* (supuesto: formulario)
