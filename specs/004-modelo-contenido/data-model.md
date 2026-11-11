# Data Model: Tour (`mis_tour`)

**Fuentes**: análisis de las fichas actuales del 11 de noviembre de 2026 (Granada, Ronda wine
tour, Gibraltar y 3-day Best of Andalusia, más la lista `/services/tours/`, unos 25 tours) y lo
que se pudo leer de FareHarbor (la documentación pública de FareHarbor no se pudo leer entera;
lo marcado «por verificar» se comprueba en el panel de FareHarbor).

Todos los campos son de WordPress (los tours no vienen de marOS). «Trad.» = se traduce en/es.

## Datos rápidos (la caja «About» de las fichas actuales)

| Campo | Tipo | Trad. | Visto en la web actual |
|---|---|---|---|
| Duración | texto corto (p. ej. «10 horas», «3 noches / 4 días») | sí | todos |
| Horario de inicio | texto o lista de horas | sí | Ronda 9:00, Gibraltar 10:00, Granada «según entradas» |
| Modalidad | opción: privado / en grupo | — | todos «private» |
| Guía | opción: guía en vivo / anfitrión / sin guía | — | todos |
| Idiomas | lista (en, es, de, ru, fr…) | — | todos, varía por tour |
| Recogida incluida | sí/no + nota («en su hotel o alojamiento») | sí | todos |
| Confirmación inmediata | sí/no | — | Ronda, Gibraltar |
| Cancelación flexible | sí/no | — | todos |
| Precio desde | importe + moneda + unidad (por persona / por grupo) | sí (unidad) | **no se ve hoy**; opcional |
| Tamaño de grupo (mín./máx.) | números | — | no se ve hoy; opcional |
| Accesible en silla de ruedas, niños bienvenidos | sí/no | — | en «Know before you go» |

## Contenido

| Campo | Tipo | Trad. | Notas |
|---|---|---|---|
| Resumen | texto corto | sí | Para tarjetas y SEO |
| Puntos fuertes | lista | sí | 3–5 viñetas en todos |
| Descripción | bloques | sí | Secciones con H2 |
| Itinerario | lista repetible: hora o día + título + texto | sí | Por horas (tours de un día) o por días (varios días) |
| Incluye | lista | sí | |
| No incluye / extras | lista con precio opcional | sí | Hoy aparece sin título (p. ej. «guía oficial +250 €») |
| Punto de encuentro | texto | sí | Casi siempre «hotel o alojamiento» |
| Qué llevar | lista | sí | |
| No permitido | lista | sí | |
| Antes de ir | lista | sí | |
| Aviso temporal | texto + fecha de fin | sí | p. ej. obras en la ruta de Ronda; se oculta solo al caducar |
| Galería | imágenes | alt sí | 4–7 por tour |
| Tours relacionados | relación con otros tours | — | «You might also like» |
| Zona | taxonomía `zona` | — | |
| Tipo de tour | taxonomía `tipo-tour` | sí | día completo, varios días, vino, cultura, aventura… |

## Políticas (compartidas)

La cancelación y los reembolsos son iguales en todos los tours vistos (más de 15 días: 100 % menos
10 % de gestión; 5–15 días: 75 %; menos de 5 días: nada). Se guardan **una vez** como bloque
reutilizable (patrón sincronizado) y cada tour elige «política estándar» o un texto propio.

## Reserva con FareHarbor

| Campo | Tipo | Notas |
|---|---|---|
| Modo de reserva | opción: FareHarbor / solicitud por formulario | El 3-day tour se pide por formulario |
| Id de artículo de FareHarbor | número | Por verificar en el panel de FareHarbor |
| Enlace de reserva | URL (opcional) | Si se rellena, manda sobre el id |

- Compañía en FareHarbor: `marbella-in-style` (visto en el botón actual
  `https://fareharbor.com/embeds/book/marbella-in-style/?full-items=yes&flow=886185`). Se guarda
  como ajuste general, no por tour.
- El botón abre la ventana de FareHarbor (Lightframe) sin salir de la web. Requiere el script de
  Lightframe una vez por página; se carga solo en las páginas con botón de reserva y respetando
  el consentimiento de cookies.
- Por verificar: formato exacto del enlace a un artículo concreto y si conviene el plugin oficial
  de FareHarbor para WordPress o solo el enlace + script.
- Precios y disponibilidad de los tours viven en FareHarbor; la web no los copia (el «precio desde»
  es un texto editorial opcional). Su API es para afiliados y socios de software; no se usa en
  esta fase.

## Opiniones

Valoración media, número y reparto (excelente, muy bueno…) salen del plugin de opiniones (D11),
no de campos manuales.

## Por resolver en la migración

- Unos 25 tours; dos están fuera de `/services/tours/` (`/mountain-4x4-safari-4hours/`,
  `/canyoning-level1-4hours/`).
- Las fichas actuales tienen versiones en en/es/de/ru/fr; solo se migran en y es.
