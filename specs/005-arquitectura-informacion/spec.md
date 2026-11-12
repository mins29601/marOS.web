# Feature Specification: Arquitectura de información, menús y mockups

**Feature Branch**: `005-arquitectura-informacion`

**Created**: 2026-11-12

**Status**: Borrador para revisar con Julio. Va antes de cualquier decisión de código.

**Fuentes**:

- [S1] Julio, 12 de noviembre de 2026: «Antes de tomar decisiones sobre el código, deberíamos
  hacer primero unos mockups de las pantallas, mapa del sitio, menús, navegación».
- [S2] Inventario y menú actual de la web ([spec 002, research](../002-migracion-web/research.md), R5).
- [S3] Modelo de contenido ([spec 004](../004-modelo-contenido/spec.md)): propiedades, tours,
  servicios, zonas, blog.
- [S4] Decisiones: inglés y español; propiedades en una base genérica (`str` / `vft`, en estudio);
  tours con FareHarbor; reservas de alojamiento con marOS (fase 3; antes, `stay.`).

Mockups: lienzo de diseño «Mockups web Marbella in Style» (https://claude.ai/artifact/L4xhEP5zNB5F9KmJmpBS9W, privado; Julio puede compartirlo desde el menú Compartir). Son esquemas de
media fidelidad para decidir estructura y contenido, no el diseño visual final.

## Proceso de diseño (aprobado por Julio el 12 de noviembre de 2026)

Sin Figma. Tres pasos, cada uno documentado en spec-kit:

1. **Wireframes**: cerrar los esquemas del lienzo con el feedback de Julio (estructura y
   contenido, sin diseño visual). Referencia de estilo y estructura: Le Collectionist.
2. **Atomic Design**: inventario de las piezas que se repiten, de menor a mayor (átomos: botón,
   campo, etiqueta; moléculas: buscador, tarjeta de propiedad, tarjeta de tour, datos rápidos;
   organismos: cabecera, pie, caja de reserva, galería, listado con filtros; plantillas: ficha de
   propiedad, ficha de tour, zona, listado). Se documenta en una spec propia.
3. **WordPress real**: los átomos se convierten en `theme.json` (colores, tipografía, espaciado,
   estilos de bloques) y las moléculas y organismos en patrones y bloques. La «maqueta real» se
   revisa directamente en pruebas, sin pasar por una herramienta de diseño.



Las rutas son las del estudio de URLs (spec 002) y pueden cambiar. Se muestra en inglés y, entre
paréntesis, en español.

```text
Inicio  /en/  (/es/)
├── Stay · Alquileres            /en/str/            (/es/vft/)
│   ├── Ficha de propiedad       /en/str/<slug>/     (/es/vft/<slug>/)
│   └── Resultados de búsqueda   /en/str/?checkin=…  (mismo listado con filtros)
├── Areas · Zonas                /en/areas/          (/es/zonas/)
│   └── Zona                     /en/areas/<slug>/   — propiedades, tours y guía de la zona
├── Experiences · Experiencias   /en/experiences/    (/es/experiencias/)  [tours]
│   └── Tour                     …/<slug>/           — reserva con FareHarbor
├── Concierge · Servicios        /en/services/       (/es/servicios/)
│   ├── Transporte, Chef y catering, Eventos y bodas, Conserjería
│   └── Servicio                 …/<tipo>/<slug>/    — formulario de solicitud
├── Owners · Propietarios        /en/owners/         (/es/propietarios/)
│   ├── Gestión de alquiler vacacional
│   ├── Gestión de propiedades
│   └── Recursos para propietarios
├── Journal · Blog               /en/journal/        (/es/blog/)
│   └── Entrada, categorías
├── About · Nosotros, Opiniones, Trabaja con nosotros
├── Contact · Contacto
└── Legal: Privacidad, Aviso legal, Términos y condiciones, Cookies
Utilidades: Gracias, 404, Resultados de búsqueda general
```

## Menús

**Cabecera (escritorio)**: logo · Stay · Experiences · Concierge · Owners · Journal ·
selector EN/ES · WhatsApp · botón «Book a stay».

- Stay despliega: Todas las propiedades · Villas · Apartamentos · Por zona (lista de zonas).
- Experiences despliega: tipos de tour (día completo, varios días, vino, cultura, aventura).
- Concierge despliega: Transporte · Chef y catering · Eventos y bodas · Conserjería.
- Owners despliega: Gestión vacacional · Gestión de propiedades · Recursos.

**Móvil**: cabecera con logo, botón «Book» y menú. El menú se abre a pantalla completa con las
mismas secciones en acordeón, el idioma y los contactos (teléfono, WhatsApp, email) al final.

**Pie**: columnas Stay (zonas principales), Experiences, Concierge, Company (About, Opiniones,
Trabaja con nosotros, Contacto, Journal); contacto (teléfono, WhatsApp, email, dirección de
oficina); redes; legales; selector de idioma.

**Navegación transversal**: migas de pan en fichas; «También te puede interesar» (propiedades de
la misma zona, tours relacionados); zona enlazada desde cada propiedad y tour; buscador de
fechas presente en Inicio, Stay y Zona.

## Pantallas con mockup

| # | Pantalla | Qué decide |
|---|---|---|
| 1 | Mapa del sitio | Secciones y jerarquía |
| 2 | Inicio (escritorio) | Orden de bloques, buscador, peso de cada línea de negocio |
| 3 | Alquileres con búsqueda | Filtros, tarjeta de propiedad, mapa |
| 4 | Ficha de propiedad | Galería, datos clave, calendario, precio y reserva |
| 5 | Ficha de tour | Datos rápidos, itinerario, reserva FareHarbor |
| 6 | Menú móvil | Navegación en el teléfono |
| 7 | Ficha de propiedad (móvil) | Barra de reserva fija y orden del contenido |

## Clarifications

### Preguntas para Julio

- Q1: ¿El peso en Inicio es alquileres primero y luego experiencias y servicios, o las tres
  líneas al mismo nivel? (mockup: alquileres primero)
- Q2: ¿«Owners» (captar propietarios) va en el menú principal o solo en el pie y en una landing?
  (mockup: menú principal)
- Q3: ¿Eventos y bodas son una sección propia («Celebrate», como hoy) o parte de Concierge?
  (mockup: dentro de Concierge)
- Q4: ¿Nombre del blog: Blog, Journal o Guía de Marbella? (mockup: Journal)
