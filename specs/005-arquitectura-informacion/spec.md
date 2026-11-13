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
   propiedad, ficha de tour, zona, listado). Se documenta en la [spec 006](../006-atomic-design/spec.md).
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

## Menús (versión 2, 12/11)

**Cabecera (escritorio)**: logo · Alojamiento · Servicios · Experiencias · Zonas · EN/ES ·
«Menú» (capa con Propietarios, Journal, Nosotros y contacto) · botón «Reservar».

**Móvil**: logo · «Reservar» · menú. El menú es una capa a pantalla completa: Alojamiento,
Servicios, Experiencias y Zonas en acordeón; Propietarios, Journal y Nosotros; la persona de
contacto con horario, WhatsApp y llamar; Instagram y TikTok; idioma.

**Pie**: Alojamiento, Servicios, Experiencias, Empresa (Nosotros, Propietarios, Opiniones, Trabaja
con nosotros, Legal); contacto y dirección; Instagram, TikTok y YouTube.

**Navegación transversal**: migas; subnavegación fija por anclas en las fichas; zona enlazada en
cada ficha; servicios como extras en la ficha de propiedad; «Habla con [Nombre]» en todas las
páginas; buscador en Inicio y Alojamiento.

## Pantallas con mockup (versión 2)

Móvil primero (390 px) y escritorio como ampliación. El lienzo es navegable: las pantallas marcadas tienen «Play» y enlaces entre
ellas (Inicio → Alquileres → Ficha → Reservar C1 → C2 → C3; menú; servicios). Los criterios están en
[research.md](research.md) (conclusiones 1–14).

| # | Pantalla | Qué decide |
|---|---|---|
| 1 | Mapa del sitio y menús | Secciones y jerarquía |
| M1 · E1 | Inicio | Alojamiento, servicios y experiencias al mismo nivel; gestión propia; vídeos de Instagram/TikTok; zonas |
| M2 · E2 | Alquileres | Colecciones en pestañas, distintivo «Exclusiva», nota junto al nombre; fechas con ±días, huéspedes y temas con número; precio total solo con fechas |
| M3 · E3 | Ficha de propiedad | Anclas, persona de contacto, vídeos, dormitorios, servicios incluidos y extras, estancia mínima, distancias, FAQ, valoraciones estilo Airbnb, «También en Airbnb, Booking y Vrbo» |
| M4 | Reservar en móvil, **flujo C** (propuesta de Julio, 12/11) | Una sola pantalla: cabecera de la casa (foto, estrellas, nombre), fechas y huéspedes, desglose que se actualiza, «Incluido», «¿Prefieres hablarlo?». Sin servicios extra |
| M4a | Flujo A, referencia (My Private Villas) | Barra fija → hoja inferior → calendario a pantalla completa → precio cerrado y «Reservar» |
| M4b | Flujo B, referencia (Le Collectionist) | Semanas con precio → servicios incluidos y a la carta → resumen, «Reservar» o «Programar llamada» |
| M5 · E4 | Servicios | Línea propia, solicitud sin alojamiento |
| M6 | Menú móvil | Capa a pantalla completa |
| E5 | Ficha de tour | Sin cambios respecto a la versión 1 |

## Clarifications

### Preguntas para Julio

- Q1: ~~Peso en Inicio~~ **Resuelta (12/11)**: alquileres y servicios al mismo nivel; los
  servicios también se venden a quien no se aloja con nosotros.
- Q2: **Resuelta (13/11)**: Propietarios en el menú desplegable y en el pie (T29). Antes: ¿«Owners» (captar propietarios) va en el menú principal o solo en el pie y en una landing?
  (mockup v2: en la capa «Menú» y en el pie)
- Q3: **Resuelta (13/11)**: dentro de Servicios (T30). Antes: ¿Eventos y bodas son una sección propia («Celebrate», como hoy) o parte de Concierge?
  (mockup: dentro de Concierge)
- Q5: **Resuelta (13/11)**: flujo C (T28). Antes, en revisión (12/11): Julio descarta la lista de semanas (no hay
  mínimo de 7 noches ni día fijo de entrada) y los servicios dentro de la reserva; propone el
  flujo C en una sola pantalla.
- Q4 (D19, recomendado «Guía de Marbella»): ¿Nombre del blog: Blog, Journal o Guía de Marbella? (mockup: Journal)
