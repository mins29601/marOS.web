# Implementation Plan: Migración de contenido

**Branch**: `002-migracion-web` | **Date**: 2026-11-11 | **Spec**: [spec.md](./spec.md)

**Estado**: Borrador para revisar con Julio. Todavía sin código.

## Summary

Pasar a la web nueva, con diseño y tema nuevos, en inglés y español, el contenido que tiene
valor, en el modelo de la [spec 004](../004-modelo-contenido/spec.md), sin perder posiciones.
El contenido no se copia tal cual: hoy está maquetado con los shortcodes del maquetador del tema
actual. Se extrae, se limpia con scripts, se carga en pruebas y se revisa a mano.

## Principios del plan

1. **Primero los datos, luego la decisión.** Cada URL tiene tráfico y enlaces medidos antes de
   decidir si se conserva.
2. **Scripts repetibles.** La extracción y la carga se pueden repetir desde cero en pruebas tantas
   veces como haga falta; el último paso antes del lanzamiento es una repetición con lo más nuevo.
3. **Texto sí, maquetación no.** Se migran textos, imágenes, fechas, autores, traducciones y
   metadatos. El diseño lo ponen el tema nuevo y sus patrones de bloques.
4. **Inglés y español.** Las versiones en alemán, ruso y francés no se migran; redirigen a la
   equivalente en inglés.

## Fases

Leyenda: **[Tú]** · **[Claude]** · **[Juntos]**.

### M0. Accesos y datos (semana 1)

| # | Quién | Qué |
|---|---|---|
| M0.1 | [Tú] | Darme un usuario administrador en la web actual (o exportaciones: WXR de todo, ajustes de WPML) |
| M0.2 | [Tú] | Dar acceso de lectura a Search Console y GA4 (o exportar 16 meses de páginas con clics, impresiones y sesiones) |
| M0.3 | [Claude] | Rastreo completo de la web actual en todos los idiomas (también `/es/ /de/ /ru/ /fr/`), con estado, canonical, hreflang y enlaces internos |
| M0.4 | [Claude] | `redirects/inventario.csv`: cada URL con tipo, idioma, tráfico, enlaces externos y tipo de destino propuesto |

### M1. Decidir qué se conserva (semana 1–2)

| # | Quién | Qué |
|---|---|---|
| M1.1 | [Claude] | Propuesta por URL: `mantener`, `fusionar` (con destino), `301` o `410`. Regla: se conserva todo lo que tenga clics, enlaces externos o valor comercial; lo demás se fusiona o se retira |
| M1.2 | [Tú] | Revisar y aprobar la propuesta (sobre todo servicios, tours y blog) |
| M1.3 | [Juntos] | Emparejar cada propiedad de marOS con su ficha actual (p. ej. Villa Centauro ↔ `villa-centauri`) |

### M2. Diseño y plantillas (en paralelo, con el tema nuevo)

| # | Quién | Qué |
|---|---|---|
| M2.1 | [Juntos] | Arquitectura de información y menú nuevos (Stay, Experiences, Services, Areas, Blog, Owners) |
| M2.2 | [Claude] | Plantillas por tipo (propiedad, tour, servicio, zona, entrada, página) y patrones de bloques a partir del diseño aprobado |

### M3. Extracción y transformación (semana 2–3)

| # | Quién | Qué |
|---|---|---|
| M3.1 | [Claude] | Extraer de la web actual por su API REST (o del WXR) entradas, páginas, «Services», categorías, medios y traducciones en/es, con su id antiguo |
| M3.2 | [Claude] | Convertir los shortcodes del maquetador a bloques nativos: títulos, párrafos, listas, imágenes, galerías, botones, citas. Lo que no se pueda convertir queda marcado para revisión, nunca se pierde en silencio |
| M3.3 | [Claude] | Repartir el tipo «Services» actual en Propiedad (solo textos de marketing), Tour y Servicio; las entradas de zona pasan a páginas de Zona |
| M3.4 | [Claude] | Medios: solo las imágenes usadas, con texto alternativo; nombres y tamaños limpios |

### M4. Carga en pruebas (semana 3)

| # | Quién | Qué |
|---|---|---|
| M4.1 | [Claude] | Script de carga con WP-CLI, idempotente (clave: URL o id antiguo), que enlaza las traducciones en/es en WPML y conserva slug, fecha y autor |
| M4.2 | [Claude] | Las propiedades llegan de marOS (spec 003, o del mock); el script les añade los textos de marketing emparejados en M1.3 |
| M4.3 | [Claude] | Opiniones (Site Reviews) exportadas e importadas, asignadas a su propiedad, tour o servicio |
| M4.4 | [Claude] | Menús, pie, páginas legales y formularios recreados (el texto legal lo revisa quien corresponda) |

### M5. Revisión editorial (semana 3–5)

| # | Quién | Qué |
|---|---|---|
| M5.1 | [Juntos] | Revisar cada página en pruebas con una lista: texto, imágenes, enlaces, traducción, SEO (title y meta), schema |
| M5.2 | [Juntos] | Completar el español donde falte; Claude puede proponer traducciones por MCP como borrador, y una persona las aprueba |
| M5.3 | [Tú] | Aprobar páginas clave (portada, propiedades, servicios principales) |

### M6. Redirecciones y comprobaciones (semana 5)

| # | Quién | Qué |
|---|---|---|
| M6.1 | [Claude] | `redirects/redirects.csv` final: reglas por patrón (dominio desnudo → `www.`, `/services/villas/*` y `/services/apartments/*` → nuevas rutas, `/de/ /ru/ /fr/` → inglés) y mapa exacto para el resto |
| M6.2 | [Claude] | CI que comprueba el mapa: sin cadenas, sin bucles, cada destino da 200 en pruebas |
| M6.3 | [Claude] | Enlaces internos reescritos a las URLs nuevas (no depender de redirecciones dentro de la web) |
| M6.4 | [Claude] | Comprobaciones: hreflang en/es, canonicals, sitemaps por idioma, Core Web Vitals, accesibilidad |

### M7. Congelación, lanzamiento y vigilancia

| # | Quién | Qué |
|---|---|---|
| M7.1 | [Tú] | Congelar la web actual (sin publicar) 48 h antes |
| M7.2 | [Claude] | Repetir extracción y carga de lo cambiado desde la última carga |
| M7.3 | [Juntos] | Lanzamiento: `www.` apunta a la web nueva, se activan las redirecciones en Cloudflare, se comprueba el inventario entero contra producción |
| M7.4 | [Tú] | Enviar los sitemaps nuevos en Search Console; guardar la web antigua solo para el equipo 30 días |
| M7.5 | [Claude] | Vigilar 8 semanas: 404 en Search Console y Cloudflare, caída de clics por página; corregir en menos de una semana |

## Riesgos

- Contenido muy acoplado al maquetador (secciones, columnas, carruseles): más revisión manual en M5.
- Traducciones al español incompletas: decidir por página si se publica solo en inglés al lanzar.
- Slugs distintos del nombre (`villa-centauri` para Villa Centauro): se mantienen o se redirigen,
  nunca se cambian sin 301.
- Contenido nuevo publicado en la web actual durante el proyecto: cubierto por M7.2.
