# marOS.web — web de marbellainstyle.com

WordPress estándar en IONOS con Cloudflare delante, conectado a la API de marOS.

- Arquitectura acordada: [docs/arquitectura-maros.md](docs/arquitectura-maros.md)
- Constitución (reglas del proyecto): [.specify/memory/constitution.md](.specify/memory/constitution.md)
- Decisiones: [docs/decisiones.md](docs/decisiones.md)

## Entorno local

WordPress en tu Mac con la API de marOS simulada: `npm install && npm run env:start` →
http://localhost:8888. Instalación y uso: [docs/runbooks/entorno-local.md](docs/runbooks/entorno-local.md).

## Specs (spec-kit)

| Spec | Estado |
|---|---|
| [001 Infraestructura](specs/001-infraestructura-web/spec.md) · [plan](specs/001-infraestructura-web/plan.md) | Borrador |
| [002 Migración](specs/002-migracion-web/spec.md) · [inventario](specs/002-migracion-web/research.md) · [plan de contenido](specs/002-migracion-web/plan.md) | Borrador |
| [003 marOS Connector](specs/003-maros-connector/spec.md) · [datos](specs/003-maros-connector/data-model.md) | Borrador |
| [004 Modelo de contenido](specs/004-modelo-contenido/spec.md) · [campos de tours](specs/004-modelo-contenido/data-model.md) | Borrador |
| [005 Arquitectura de información y mockups](specs/005-arquitectura-informacion/spec.md) | Borrador |

Flujo: `/speckit-specify` → `/speckit-clarify` → `/speckit-plan` → `/speckit-tasks` →
`/speckit-implement`, cada spec en su rama y su pull request.

La API se desarrolla en [mins29601/marOS](https://github.com/mins29601/marOS); las preguntas
sobre ella se abren como issues allí.
