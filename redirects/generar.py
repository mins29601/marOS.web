#!/usr/bin/env python3
"""Genera el inventario y el mapa de redirecciones de la web actual a la nueva.

Entrada: urls-actuales.txt (URLs de /wp-sitemap.xml de la web actual).
Salida:
  inventario.csv  — una fila por URL actual: qué es, a dónde va y si hay que revisarla.
  redirects.csv   — formato Cloudflare Bulk Redirects (source_url,target_url,status_code).

Las rutas nuevas siguen la spec 005 (mapa del sitio) y la propuesta de Julio de `/en/str/`.
La estructura de URLs (decisión D6) sigue en estudio: si cambia, se cambian las constantes
de abajo y se vuelve a ejecutar el script.

Uso: python3 redirects/generar.py
"""
import csv
import os

AQUI = os.path.dirname(os.path.abspath(__file__))
HOST_ANTIGUO = "marbellainstyle.com"
HOST_NUEVO = "https://www.marbellainstyle.com"

# --- Secciones de la web nueva (inglés). El español está en mapa-del-sitio.md ---
INICIO = "/en/"
STR = "/en/str/"                       # alquileres (short term rentals)
AREAS = "/en/areas/"
EXP = "/en/experiences/"
SRV = "/en/services/"
OWN = "/en/owners/"
JOURNAL = "/en/journal/"
REVIEWS = "/en/reviews/"

# Tipos de servicio: tipo antiguo -> tipo nuevo
TIPO_SERVICIO = {
    "transport": "transport",
    "catering": "catering",
    "events": "events",
    "lifestyle": "concierge",
}

# Fichas de propiedad actuales -> propiedad en marOS (id) y slug nuevo.
# Slug nuevo = nombre de la propiedad en marOS; se puede alargar con la descripción
# (p. ej. villa-nido-modern-4br-flamingos) cuando se decidan los slugs (D6).
PROPIEDADES = {
    "villa-centauri": ("lodgify-802366", "villa-centauro", "alta", ""),
    "villa-feliz": ("lodgify-373016", "villa-feliz", "alta", ""),
    "villa-nido": ("lodgify-405724", "villa-nido", "alta", ""),
    "villa-michaela": ("lodgify-410050", "villa-michaela", "alta", ""),
    "villa-panda-sea-views-heated-pool-in-sierra-blanca": ("lodgify-732302", "villa-panda", "alta", ""),
    "villa-concha-puerto-banus": ("villa-concha", "villa-concha", "alta", "En marOS no viene de Lodgify (id villa-concha)"),
    "villa-flamingo-luxury-modern": ("villa-flamingos", "villa-flamingos", "media", "Confirmar que es Villa Flamingos (en marOS no viene de Lodgify)"),
    "casa-masari": ("lodgify-676451", "casa-masari", "alta", ""),
    "casa-dama": ("lodgify-780716", "casa-dama", "alta", ""),
    "casa-principe": ("lodgify-657962", "casa-principe", "alta", ""),
    "casa-samara": ("lodgify-791473", "casa-samara", "alta", ""),
    "casa-rodeo-puerto-banus": ("lodgify-847728", "casa-rodeo", "alta", ""),
    "casa-cifuentes": ("lodgify-658210", "casa-cifuentes", "alta", "En marOS se llama «Cifuentes»"),
    "casa-sould": ("lodgify-790411", "casa-soul", "alta", "El slug actual tiene una errata (sould)"),
    "casa-paraiso": ("lodgify-792097", "casa-paraiso", "alta", ""),
    "casa-brisa": ("lodgify-795663", "casa-brisa", "alta", ""),
    "casa-coral": ("lodgify-822651", "casa-coral", "alta", ""),
    "casa-acosta": ("lodgify-580341", "casa-acosta", "alta", ""),
}

# Fichas de villas que no están en marOS: ya no se ofrecen (o tienen otro nombre).
# Van a su zona si se sabe; si no, al listado de alquileres.
VILLAS_SIN_MAROS = {
    "villa-palace-guadalmina": AREAS + "guadalmina/",
    "villa-resort-flamingos": AREAS + "los-flamingos/",
    "villa-resort-alqueria": AREAS + "la-alqueria/",
    "beachfront-villa-new-golden-mile": AREAS + "new-golden-mile/",
}

ZONAS = {  # entradas actuales que son guías de zona -> slug de zona
    "rio-real": "rio-real", "sierra-blanca": "sierra-blanca", "milla-de-oro": "milla-de-oro",
    "guadalmina": "guadalmina", "nueva-andalucia": "nueva-andalucia",
    "los-flamingos": "los-flamingos", "la-alqueria": "la-alqueria", "estepona": "estepona",
}
# Zonas que solo existen como categoría de servicios; no tienen guía hoy.
ZONAS_SIN_GUIA = {"benahavis": "benahavis", "soto-grande": "sotogrande",
                  "el-madronal": "el-madronal", "new-golden-mile": "new-golden-mile"}

# Entradas del blog que pasan a «Recursos para propietarios» (se quedan en el Journal,
# con la categoría Propietarios, y se listan en /en/owners/resources/).
POSTS_PROPIETARIOS = {
    "benefits-property-marbella", "maximize-rental-income-marbella-luxury-property",
    "guest-experience-guide-maximize-marbella-rental-income",
    "how-to-prepare-rental-property-peak-season-2026",
    "preparing-your-marbella-villa-for-spring-guests-what-really-matters",
    "new-10-vat-on-short-holiday-rentals-in-spain-what-marbella-villa-owners-should-know",
}
# Entradas antiguas o de temporada pasada: candidatas a retirar si no tienen tráfico (plan M1).
POSTS_REVISAR = {
    "nikki-beach-marbella-white-opening-party-2015", "marbella-champagne-party-2014",
    "the-view-open-house-christmas-2021", "our-guide-hotels-marbella-2022",
    "top-5-dishes-try-marbella-2017", "top-5-outfits-your-bbq-party-marbella",
    "marbella-in-spring-2026-why-march-may-is-the-perfect-time-to-visit",
}

PAGINAS = {
    "/": (INICIO, "mantener", "Portada; la raíz redirige al idioma por defecto"),
    "/contact-us/": ("/en/contact/", "mantener", ""),
    "/blog/": (JOURNAL, "mantener", "Nombre del blog pendiente (Q4 de la spec 005)"),
    "/privacy-policy/": ("/en/privacy-policy/", "mantener", ""),
    "/terms-and-conditions/": ("/en/terms-and-conditions/", "mantener", ""),
    "/legal-notice/": ("/en/legal-notice/", "mantener", ""),
    "/about-marbella-instyle/": ("/en/about/", "mantener", ""),
    "/work-with-us/": ("/en/work-with-us/", "mantener", ""),
    "/domestic-staff-marbella/": (SRV + "concierge/domestic-staff/", "mantener", "Pasa a ser un servicio de conserjería"),
    "/testimonials-from-our-customers/": (REVIEWS, "fusionar", "Opiniones de todo (Site Reviews)"),
    "/thank-you/": ("/en/thank-you/", "mantener", "noindex"),
    "/vacation-planner/": ("/en/contact/", "fusionar", "Revisar: si el formulario sigue siendo útil, página propia /en/plan-your-stay/"),
    "/areas/": (AREAS, "mantener", ""),
    "/vacation-rental-management/": (OWN + "vacation-rental-management/", "mantener", ""),
    "/new-vacation-rental-management/": (OWN + "vacation-rental-management/", "fusionar", "Versión nueva de la misma página; se queda la mejor"),
    "/vacation-rental-lp/": (OWN + "vacation-rental-management/", "fusionar", "Revisar: si es landing de anuncios, mantenerla como landing noindex"),
    "/property-management/": (OWN + "property-management/", "mantener", ""),
    "/homeowners/": (OWN, "mantener", "Portada de Propietarios"),
    "/example-i/": (None, "retirar", "Página de prueba"),
    "/test/": (None, "retirar", "Página de prueba"),
    "/page-404-custom/": (None, "retirar", "La 404 la pone el tema nuevo"),
    "/no-access/": (None, "retirar", "Página técnica"),
}

# /services/<tipo>/<slug>/ que no siguen la regla general
SERVICIOS_ESPECIALES = {
    "/services/tours/": (EXP, "mantener", "Portada de Experiencias"),
    "/services/tours/reviews/": (REVIEWS, "fusionar", ""),
    "/services/tours/marbella-puerto-banus-night-life-walking-tour-2/": (EXP + "marbella-puerto-banus-night-life-walking-tour/", "fusionar", "Duplicado"),
    "/services/transport/": (SRV + "transport/", "mantener", ""),
    "/services/catering/": (SRV + "catering/", "mantener", ""),
    "/services/events/": (SRV + "events/", "mantener", ""),
    "/services/lifestyle/": (SRV + "concierge/", "mantener", "«Lifestyle» pasa a llamarse Conserjería"),
    "/services/lifestyle/concierge-marbella/": (SRV + "concierge/", "fusionar", "Es la portada de Conserjería"),
    "/services/events/events-gallery/": (SRV + "events/gallery/", "mantener", ""),
    "/services/events/marbella-events-finca-fantasia-2/": (SRV + "events/marbella-events-finca-fantasia/", "fusionar", "Duplicado"),
    "/services/villas/": (STR, "mantener", "Portada de Alquileres"),
    "/services/apartments/": (STR, "fusionar", "Sin tipología en la URL: filtro «Apartamentos» del listado"),
    "/services/villas/private-villas/": (STR, "fusionar", ""),
    "/services/villas/reviews/": (REVIEWS, "fusionar", ""),
    "/services/villas/marbella-villa-owners/": (OWN, "fusionar", "Es contenido para propietarios"),
    "/services/villas/marbella-property-management/": (OWN + "property-management/", "fusionar", ""),
    "/services/villas/property-management-in-marbella-with-local-care/": (OWN + "property-management/", "fusionar", ""),
    "/services/villas/marbella-villa-services/": (SRV, "fusionar", "Servicios para huéspedes de villas"),
    "/services/accommodation/": (STR, "fusionar", ""),
    "/services/hotels/": (STR, "fusionar", "Ya no ofrecemos hoteles; revisar si tiene tráfico"),
    "/services/holidays/": (SRV + "concierge/marbella-villa-holiday-package/", "fusionar", ""),
    "/services/beach-villas/": (STR, "fusionar", "Candidata a colección «Primera línea»"),
    "/services/wedding-villas/": (SRV + "events/wedding-planner-marbella/", "fusionar", "Revisar: o colección «Villas para eventos»"),
    "/services/event-venues/": (SRV + "events/", "fusionar", ""),
    "/services/mice/": (SRV + "events/", "fusionar", "Eventos de empresa"),
    "/services/wellness-and-fitness/": (SRV + "concierge/", "fusionar", ""),
    "/services/mountain-4x4-safari-4hours/": (EXP + "mountain-4x4-safari/", "mantener", "Revisar si sigue en venta; si no, 301 a Experiencias"),
    "/services/canyoning-level1-4hours/": (EXP + "canyoning/", "mantener", "Revisar si sigue en venta; si no, 301 a Experiencias"),
}

# Páginas de eventos realizados (galerías de fiestas y bodas): se conservan como
# «Eventos realizados» dentro de Eventos, salvo que no tengan tráfico (plan M1).
EVENTOS_CASOS = {
    "30-birthday-party-marbella", "marbella-tropical-wedding", "40th-birthday-party-marbella",
    "marbella-gold-wedding", "60th-birthday-party-marbella", "marbella-summer-party",
    "40th-birthday-party-tropical", "corporate-white-party", "marbella-events-finca-fantasia",
    "great-gatsby-marbella", "white-party-marbella",
}

CATEGORIAS_SERVICIOS = {
    "tours": EXP, "day-tours": EXP, "nature-adventure": EXP,
    "transport": SRV + "transport/", "events": SRV + "events/", "gallery": SRV + "events/gallery/",
    "lifestyle": SRV + "concierge/", "catering": SRV + "catering/", "food-drink": SRV + "catering/",
    "holidays": SRV + "concierge/marbella-villa-holiday-package/",
    "villas": STR, "accommodation": STR, "beach-villas": STR, "wedding-villas": STR,
    "private-villa": STR, "modern-villas": STR, "6-bedroom": STR, "apartments": STR,
    "guide": AREAS, "guia-zona-2": AREAS,
    "alqueria": AREAS + "la-alqueria/", "flamingos": AREAS + "los-flamingos/",
    "rio-real": AREAS + "rio-real/", "sierra-blanca": AREAS + "sierra-blanca/",
    "guadalmina": AREAS + "guadalmina/", "milla-de-oro": AREAS + "milla-de-oro/",
    "milla-de-oro-2": AREAS + "milla-de-oro/", "nueva-andalucia": AREAS + "nueva-andalucia/",
}


def fila(url, tipo, seccion, destino, accion, estado="propuesta", nota="", maros=""):
    codigo = "410" if accion == "retirar" else "301"
    return dict(url_antigua=url, tipo_actual=tipo, seccion_nueva=seccion,
                destino=destino or "", codigo=codigo, accion=accion,
                propiedad_maros=maros, estado=estado, nota=nota)


def clasificar(tipo, url):
    partes = [p for p in url.strip("/").split("/") if p]

    if tipo == "page":
        destino, accion, nota = PAGINAS[url]
        seccion = "Legal/utilidades" if accion == "retirar" else "Páginas"
        estado = "revisar" if nota.startswith("Revisar") else "propuesta"
        return fila(url, tipo, seccion, destino, accion, estado, nota)

    if tipo == "post":
        slug = partes[0]
        if slug in ZONAS:
            return fila(url, tipo, "Zonas", AREAS + ZONAS[slug] + "/", "mantener",
                        nota="Hoy es una entrada; pasa a página de Zona")
        if slug == "marbella-area-guide":
            return fila(url, tipo, "Zonas", AREAS, "fusionar", nota="Pasa a ser la portada de Zonas")
        if slug in POSTS_PROPIETARIOS:
            return fila(url, tipo, "Journal (Propietarios)", JOURNAL + slug + "/", "mantener",
                        nota="Se lista también en /en/owners/resources/")
        if slug in POSTS_REVISAR:
            return fila(url, tipo, "Journal", JOURNAL + slug + "/", "mantener", "revisar",
                        "Antigua o de temporada pasada: retirar (301 al Journal) si no tiene tráfico")
        return fila(url, tipo, "Journal", JOURNAL + slug + "/", "mantener")

    if tipo == "portfolio":
        if url in SERVICIOS_ESPECIALES:
            destino, accion, nota = SERVICIOS_ESPECIALES[url]
            seccion = ("Alquileres" if destino.startswith(STR) else
                       "Experiencias" if destino.startswith(EXP) else
                       "Propietarios" if destino.startswith(OWN) else
                       "Opiniones" if destino == REVIEWS else "Servicios")
            estado = "revisar" if "Revisar" in nota or "revisar" in nota else "propuesta"
            return fila(url, tipo, seccion, destino, accion, estado, nota)
        cat, slug = partes[1], partes[2]
        if cat in ("villas", "apartments"):
            if slug in PROPIEDADES:
                maros, nuevo, confianza, nota = PROPIEDADES[slug]
                estado = "propuesta" if confianza == "alta" else "revisar"
                return fila(url, tipo, "Alquileres", STR + nuevo + "/", "mantener", estado, nota, maros)
            destino = VILLAS_SIN_MAROS.get(slug, STR)
            return fila(url, tipo, "Alquileres", destino, "fusionar", "revisar",
                        "No está en marOS: ¿ya no se ofrece o tiene otro nombre? Si vuelve, ficha propia")
        if cat == "tours":
            return fila(url, tipo, "Experiencias", EXP + slug + "/", "mantener")
        if cat == "events" and slug in EVENTOS_CASOS:
            return fila(url, tipo, "Servicios (eventos realizados)", SRV + "events/" + slug + "/", "mantener", "revisar",
                        "Galería de un evento: mantener si tiene tráfico; si no, 301 a Eventos")
        if cat in TIPO_SERVICIO:
            return fila(url, tipo, "Servicios", SRV + TIPO_SERVICIO[cat] + "/" + slug + "/", "mantener")
        raise ValueError(url)

    if tipo == "category":
        slug = partes[1]
        if slug == "vrm":
            return fila(url, tipo, "Propietarios", OWN + "resources/", "fusionar",
                        nota="Categoría de artículos para propietarios")
        return fila(url, tipo, "Journal", JOURNAL + "category/" + slug + "/", "mantener", "revisar",
                    "Revisar: reducir categorías (hoy 11) al lanzar")

    if tipo == "post_tag":
        return fila(url, tipo, "Journal", JOURNAL, "fusionar",
                    nota="La web nueva no publica etiquetas; regla por patrón /tag/*")

    if tipo == "services_cat":
        slug = partes[1]
        if slug in ZONAS_SIN_GUIA:
            return fila(url, tipo, "Zonas", AREAS + ZONAS_SIN_GUIA[slug] + "/", "fusionar", "revisar",
                        "Zona sin guía hoy: crear la página de zona o 301 a /en/areas/")
        return fila(url, tipo, "Listados", CATEGORIAS_SERVICIOS[slug], "fusionar",
                    nota="Las categorías de servicios desaparecen; regla por patrón /services_cat/*")

    if tipo == "page_category":
        return fila(url, tipo, "Zonas", AREAS, "fusionar")

    raise ValueError(tipo)


def main():
    filas = []
    with open(os.path.join(AQUI, "urls-actuales.txt"), encoding="utf-8") as f:
        for linea in f:
            if not linea.strip() or linea.startswith("#"):
                continue
            tipo, url = linea.rstrip("\n").split("\t")
            filas.append(clasificar(tipo, url))

    # Las zonas sin guía que llegan a una página que aún no existe deben resolverse antes del lanzamiento.
    columnas = list(filas[0].keys())
    with open(os.path.join(AQUI, "inventario.csv"), "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=columnas)
        w.writeheader()
        w.writerows(filas)

    with open(os.path.join(AQUI, "redirects.csv"), "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(["source_url", "target_url", "status_code"])
        for r in filas:
            if r["codigo"] == "301":
                w.writerow([HOST_ANTIGUO + r["url_antigua"], HOST_NUEVO + r["destino"], 301])

    # Comprobaciones: sin bucles ni cadenas (ningún destino es a la vez origen)
    origenes = {r["url_antigua"] for r in filas}
    for r in filas:
        assert r["destino"] not in origenes, f"cadena: {r['url_antigua']} -> {r['destino']}"

    from collections import Counter
    print("Total:", len(filas))
    print("Por acción:", dict(Counter(r["accion"] for r in filas)))
    print("Por sección:", dict(Counter(r["seccion_nueva"] for r in filas)))
    print("A revisar:", sum(r["estado"] == "revisar" for r in filas))


if __name__ == "__main__":
    main()
