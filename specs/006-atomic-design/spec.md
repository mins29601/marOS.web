# Feature Specification: Sistema de diseño (Atomic Design)

**Feature Branch**: `006-atomic-design`

**Created**: 2026-11-13

**Status**: Borrador. Paso 2 del proceso de diseño (spec 005).

**Fuentes**:

- [S1] Proceso de diseño aprobado por Julio el 12/11/2026: wireframes → Atomic Design →
  `theme.json` y patrones revisados en pruebas, sin Figma ([spec 005](../005-arquitectura-informacion/spec.md)).
- [S2] Wireframes versión 2 y prototipo navegable del lienzo «Mockups web Marbella in Style»
  (12/11/2026), con el feedback de Julio: mobile first, servicios al mismo nivel, gestión propia,
  distintivo «Exclusiva», colecciones, valoraciones estilo Airbnb, «También en» portales, vídeo,
  reserva en una sola pantalla (flujo C).
- [S3] Conclusiones de las webs de referencia ([spec 005, research](../005-arquitectura-informacion/research.md)).
- [S4] Constitución: WordPress estándar (II), rápido, accesible y multilingüe (VII).

## Resumen

Inventario de las piezas que se repiten en los wireframes, de menor a mayor, para construirlas
una sola vez. Cada pieza indica **en qué se convierte en WordPress**: un ajuste de `theme.json`,
un estilo de bloque del núcleo, un patrón o, solo si no hay otra salida, un bloque propio.
Regla: **bloques del núcleo primero** (encaja con el tema base recomendado, Ollie, D1, y con el
MCP, que edita bloques del núcleo sin plugins extra).

Los valores visuales de este documento son **provisionales**: salen de los wireframes, que son
de media fidelidad. Se cierran al ver la maqueta real en pruebas (paso 3).

## 0. Fundamentos (tokens → `theme.json`)

| Token | Valor provisional | Uso | En WordPress |
|---|---|---|---|
| Color `base` | `#F3EEE7` (arena muy claro) | Fondo general | `settings.color.palette` |
| Color `contrast` | `#1C1B19` (casi negro) | Texto, botón principal, pie | palette |
| Color `surface` | `#FFFFFF` | Tarjetas, cajas, hojas | palette |
| Color `sand` | `#E9E2D7` | Bloques destacados («Por qué con nosotros», asesor) | palette |
| Color `line` | `#DDD5C9` | Líneas y bordes | palette |
| Color `muted` | `#6B655B` | Texto secundario y etiquetas (contraste ≥ 4,5:1 sobre `base`, a verificar) | palette |
| Acento | **Pendiente** | Hoy no hay: la foto pone el color (Aman). Decidir con la marca | palette |
| Tipografía | Inter (provisional): títulos 300, texto 400, énfasis 500–600 | **Pendiente**: tipografía de marca o serif para títulos | `settings.typography.fontFamilies` (fuente local, sin Google Fonts en producción) |
| Escala de texto | 11 · 13 · 15 · 18 · 22 · 30 · 34 · 52 px (móvil → escritorio con `clamp`) | | `fontSizes` fluidos |
| Etiqueta | 11 px, mayúsculas, espaciado 0,12 em | Únicas mayúsculas permitidas | estilo de bloque «Etiqueta» para Párrafo |
| Espaciado | 4 · 8 · 12 · 18 · 24 · 32 · 40 · 56 · 88 px | Márgenes 18 px en móvil, 56 px en escritorio | `spacing.spacingSizes` |
| Radio | 0 (esquinas rectas) | Botones, tarjetas, campos | `border.radius` |
| Sombra | Ninguna, salvo barra fija inferior | | |
| Ancho | Contenido 760 px · amplio 1328 px | | `layout.contentSize` / `wideSize` |
| Puntos de corte | 390 (diseño base) · 768 · 1024 · 1440 | Mobile first | CSS del tema |

## 1. Átomos

| Átomo | Variantes | Dónde aparece | En WordPress |
|---|---|---|---|
| Botón | Principal (negro) · Contorno · Contorno claro (sobre foto) · Desactivado | Reservar, Ver disponibilidad, WhatsApp, Programar llamada | Bloque Botón + estilos de bloque |
| Enlace de acción | Mayúsculas subrayado («Leer más», «Ver todas», «Descubrir») | Toda la web | Estilo de bloque de Párrafo/enlace |
| Etiqueta | Normal · Sobre foto (blanca) | Zona, tipo, sección | Estilo «Etiqueta» |
| Distintivo «Exclusiva» | Único distintivo permitido | Foto de tarjeta, cabecera de ficha | Bloque propio ligero o estilo de Párrafo, condicionado al campo «gestión» (spec 003) |
| Valoración | Compacta «★ 4,92 (38)» · Grande «4,92 ★★★★★» · Barra por apartado | Tarjeta, ficha, caja de reserva | Bloque de `maros-connector` (lee los campos de valoración) |
| Chip / filtro | Normal · Activo · Con número («Piscina climatizada 6») | Alquileres, horas de llamada | Bloque propio del buscador |
| Pestaña | Normal · Activa | Colecciones, meses, subnavegación | CSS del tema |
| Campo | Texto · Fecha · Huéspedes (− +) · Selección | Buscador, reserva, formularios | Gravity Forms (D4) y bloque de reserva |
| Icono | Línea fina en círculo | Destacados, «Por qué con nosotros» | Biblioteca de iconos **pendiente** (SVG en línea) |
| Avatar | Foto redonda 44 px | Persona de contacto | Bloque Imagen con estilo «redonda» |
| Botón de reproducción | ▶ sobre carátula | Vídeos | Parte de la molécula Vídeo |
| Nota de aviso | Fondo suave | Estancia mínima, condiciones | Estilo de Grupo |

## 2. Moléculas

| Molécula | Contenido | Pantallas | En WordPress |
|---|---|---|---|
| Tarjeta de propiedad | Foto (carrusel) · Exclusiva · ♡ · nº de vídeos · zona · nombre + valoración · huéspedes · dormitorios · baños · precio total solo con fechas | Inicio, Alquileres, Zona, similares | Bloque de `maros-connector` dentro de Bucle de consulta |
| Tarjeta de servicio | Foto o vídeo · etiqueta · nombre · frase · «Descubrir» | Inicio, Servicios | Patrón con Bucle de consulta de `mis_service` |
| Tarjeta de entrada (fila) | Miniatura · título · frase · › | Inicio (servicios), menú | Patrón |
| Mosaico de sección | Foto + caja blanca con etiqueta y título | Inicio («Qué hacemos», colecciones) | Patrón (Portada + Grupo) |
| Tarjeta de zona | Foto · nombre | Inicio, zonas | Patrón (Bucle de consulta de la taxonomía) |
| Vídeo vertical | Carátula 9:16 · ▶ · plataforma | Inicio, ficha, servicios | Patrón; cómo se aloja: D18 |
| Persona de contacto | Avatar · nombre · horario · WhatsApp · Llamar | Inicio, Alquileres, ficha, menú, reserva | Patrón sincronizado (se edita en un sitio) |
| Programar llamada | Avatar · «¿Prefieres hablarlo?» · horas · botón | Reserva C, ficha | Patrón + formulario (D4) |
| Destacado con icono | Icono · texto | Ficha, «Por qué con nosotros» | Patrón (Fila + Icono + Párrafo) |
| Gestión de la casa | «Gestionada por Marbella in Style · [Nombre] será tu contacto» o «Casa de un colaborador» | Ficha | Bloque de `maros-connector` (campo «gestión») |
| Fila de distancia | Lugar · minutos en coche | Ficha, zona | Bloque Tabla con estilo o campo repetible |
| Acordeón | Pregunta / respuesta | Normas, FAQ | Bloque Detalles del núcleo |
| Opinión | Avatar · nombre · país · fecha de estancia · estrellas · texto | Ficha, Inicio | Site Reviews (D11) con plantilla propia |
| Portal | Nombre del portal · nota · nº de opiniones · › | Ficha («También en») | Bloque de `maros-connector` (campos de portales) |
| Desglose de precio | Líneas · total · condiciones | Reserva C, caja de escritorio | Bloque de reserva (`maros-connector`) |
| Incluido | Lista ✓ | Reserva C, caja de escritorio | Campo de la propiedad, con valor por defecto |
| Calendario | Mes · días libres / tachados · selección | Ficha, reserva | Bloque de reserva |
| Campo de búsqueda | Fechas (± días) · huéspedes · botón | Inicio, Alquileres | Bloque de búsqueda (`maros-connector`) |
| Migas | Inicio › Sección › Zona | Fichas | Yoast (D3) o bloque del tema |

## 3. Organismos

| Organismo | Composición | Notas |
|---|---|---|
| Cabecera | Logo · enlaces (escritorio) · EN/ES · Menú · Reservar | Transparente sobre foto o sólida; parte de plantilla |
| Menú a pantalla completa | Secciones en acordeón · persona de contacto · redes · idioma · Reservar | Bloque Navegación con capa |
| Pie | Columnas · contacto · redes · legales · idioma | Parte de plantilla |
| Portada de página | Foto o vídeo a sangre · etiqueta · H1 · subtítulo · buscador o botones | Bloque Portada |
| Buscador con filtros | Fechas · huéspedes · colecciones (pestañas) · temas (chips) · «Mapa» | Fijo arriba al desplazar |
| Listado de propiedades | Tarjetas + bloque asesor intercalado | Bucle de consulta + patrón |
| Galería de la ficha | Mosaico (escritorio) · foto con «Fotos · 45» y «Vídeos · 3» (móvil) | Patrón + bloque Galería |
| Subnavegación por anclas | Resumen · Vídeos · Dormitorios · Servicios · Zona · Normas · Opiniones | Fija al desplazar |
| Bloque de valoraciones | Nota grande · barras · opiniones · «También en» | |
| Barra de reserva fija (móvil) | Estado o total · «Ver disponibilidad» / «Reservar» | |
| Caja de reserva (escritorio) | Valoración · fechas · huéspedes · desglose · Incluido · persona de contacto · favoritos | Fija en la columna derecha |
| Pantalla de reserva (móvil, flujo C) | Cabecera de la casa · fechas y huéspedes · desglose · Incluido · Programar llamada · barra con Reservar | Hasta la fase 3, «Reservar» lleva a `stay.` (D15) |
| Franja de redes | Vídeos verticales · botones Instagram / TikTok | Inicio, zonas |
| Formulario de solicitud de servicio | Fecha · personas · ¿dónde te alojas? · contacto | Gravity Forms (D4) |

## 4. Plantillas

| Plantilla | Organismos (en orden, móvil) | WordPress |
|---|---|---|
| Inicio | Cabecera transparente · Portada con buscador · Qué hacemos · Nuestras casas · Colecciones · Por qué con nosotros · Servicios · Redes · Zonas · Opinión · Contacto · Pie | `front-page.html` |
| Alquileres | Cabecera · Título · Buscador con filtros · Listado · Pie | `archive-mis_property.html` |
| Ficha de propiedad | Cabecera transparente · Galería · Subnavegación · Resumen · Vídeos · Dormitorios · Servicios · Equipamiento · Disponibilidad · Zona · Normas y FAQ · Valoraciones · Similares · Pie · Barra fija | `single-mis_property.html` |
| Reserva (móvil) | Pantalla de reserva C | Capa o ruta propia del bloque de reserva |
| Servicios | Portada · Tarjetas de servicio · Cómo funciona · Contacto · Pie | Página + patrones |
| Ficha de servicio | Portada · texto · Incluye · vídeo · formulario | `single-mis_service.html` |
| Ficha de tour | Ver wireframe E5 (sin cambios) | `single-mis_tour.html` |
| Zona | Portada · guía · propiedades · tours · redes | `taxonomy-zona.html` |
| Entrada de blog, Página, 404 | Del tema base, con los tokens | |

## 5. Estados y comportamiento

- Tarjeta y ficha **sin fechas**: sin precio («Elige fechas…»). **Con fechas**: total cerrado de
  marOS; nunca «desde» ni rango.
- Reserva: «Reservar» desactivado sin fechas; error de disponibilidad con alternativa (otras
  fechas, hablar con nosotros).
- Distintivo «Exclusiva» solo si la casa es de gestión directa.
- Vídeo: carátula hasta que se pulsa (no carga nada de terceros antes; D18 y principio VII).
- Movimiento: solo transiciones cortas; respetar «reducir movimiento».

## 6. Requisitos

- **FR-001**: Cada átomo y molécula se define una sola vez y se reutiliza (patrones sincronizados
  o bloques), sin copiar estilos a mano en cada página.
- **FR-002**: Todo se diseña primero a 390 px; el escritorio es la ampliación.
- **FR-003**: Los tokens viven en `theme.json` del tema hijo; ningún color o tamaño fuera de la
  paleta y la escala.
- **FR-004**: Contraste AA en todo el texto, incluidas etiquetas de 11 px; objetivos táctiles
  ≥ 44 px (principio VII).
- **FR-005**: Los bloques propios se limitan a lo que necesita datos de marOS (tarjeta de
  propiedad, valoración, portales, gestión, buscador, calendario, reserva) y van en
  `maros-connector`, no en el tema.
- **FR-006**: El MCP puede componer páginas con los patrones del tema (patrones registrados con
  nombre y categoría).

## 7. Pendiente de decidir

- **Q1**: Tipografía de marca (¿Inter, una serif para títulos, la tipografía del logo actual?).
- **Q2**: Color de acento (¿ninguno, como Aman, o uno de la marca?).
- **Q3**: Biblioteca de iconos.
- **Q4**: Logo: ¿se mantiene el actual o se rediseña?
- **Q5**: Tema base (D1): la recomendación es Ollie; las piezas de este documento usan bloques del
  núcleo y servirían con cualquier tema de bloques.

## Siguiente paso

Con Q1–Q4 resueltas, paso 3: tema hijo `marbellainstyle` con `theme.json` (tokens), estilos de
bloque y patrones, montado en pruebas, donde se revisa la «maqueta real».
