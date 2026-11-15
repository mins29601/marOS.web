# Tema hijo `marbellainstyle`

Padre: [Ollie](https://wordpress.org/themes/ollie/) (T32). Aquí van solo:

- `theme.json`: tokens de la [spec 006 §0](../../../specs/006-atomic-design/spec.md) (guía de marca, T33 y T36).
- `assets/css/mis.css`: piezas que `theme.json` no cubre (cabecera, capa «Menú», pie, tarjetas, ficha), mobile first.
- `assets/fonts/`: Josefin Sans (variable) y Poppins 300–600, subconjunto latino, servidas desde el propio tema (sin Google Fonts, RGPD). Licencia OFL junto a cada fuente.
- `patterns/`, `templates/` y `parts/`: solo lo que cambia respecto a Ollie.

Nada de tipos de contenido ni lógica: eso va en `maros-connector` y `mis-contenido`.

## Colores

La paleta conserva los nombres internos de Ollie (para que sus plantillas y patrones sigan funcionando)
con los valores de la marca:

| Ollie | Marca | Valor | Uso |
|---|---|---|---|
| `primary` | Oro | `#A67C36` | Solo texto ≥ 24 px, iconos, líneas |
| `primary-alt` | Oro para texto (`gold-text`, propuesta) | `#8A6528` | Enlaces y hover de botones |
| `primary-accent` | Beige | `#E5DFD5` | Fondos de sección |
| `primary-alt-accent` | Blanco | `#FFFFFF` | Tarjetas, caja de reserva |
| `main` | Negro | `#252728` | Texto, botón principal, pie, capa «Menú» |
| `main-accent` | Negro suave (propuesta) | `#3E4041` | Líneas sobre negro |
| `base` | Marfil | `#FBFBFB` | Fondo general |
| `secondary` | Gris texto (`muted`, propuesta) | `#6E6E6E` | Texto secundario |
| `tertiary` | Beige claro (propuesta, del mockup) | `#F3EFE9` | Fondos suaves alternos |
| `border-light` | Gris claro | `#DDDCDC` | Bordes |
| `border-dark` | Gris oscuro | `#777777` | Solo gráficos (no texto) |

## Estilos de bloque

Párrafo «Etiqueta» y «Enlace de acción» · Botón «Contorno» (núcleo) y «Contorno claro» (sobre foto) ·
Imagen «Redonda» · Grupo «Tarjeta» y «Nota de aviso». La clase antigua `mis-eyebrow` sigue funcionando como etiqueta.

Cabecera sobre foto: clase `mis-header--overlay` en el grupo de la cabecera.

## Logo

`[mis_logo variant="color|negro|oro|blanco"]`. Color en la cabecera, blanco en la capa «Menú», oro en el pie.
Los SVG pesan ~72 KB cada uno: pendiente optimizarlos con SVGO (Q4 de la spec 006).
