#!/usr/bin/env python3
"""
Genera servicios.json (textos de servicios y experiencias para servicios.php) a partir de los
borradores de mins29601/marOS.content. Uso:

    python3 scripts/setup/servicios/generar.py ../maros.content

Reglas (guía editorial de marOS.content):
- Solo se copian las secciones «## EN» y «## ES»; la ficha y las notas de revisión no.
- Una línea o elemento de lista con «[confirmar…]» no se publica. «[precio]» se quita.
- «→ *Pide presupuesto*» en servicios enlaza al formulario (#solicitud); en experiencias se quita
  (la web nueva aún no tiene página de condiciones).
- El primer párrafo del cuerpo es el extracto; el resto, el contenido.
- En las experiencias, las secciones de la ficha (Highlights, Itinerary, What's included, Not
  included, Extras, What to bring, Not allowed, Good to know, Cancellation y sus equivalentes en
  español) pasan a campos propios que pinta la plantilla; el resto queda como descripción.
"""
import html
import json
import re
import sys
from pathlib import Path

ORIGEN = Path(sys.argv[1] if len(sys.argv) > 1 else "../maros.content") / "contenidos" / "borradores"
DESTINO = Path(__file__).with_name("servicios.json")

# Qué borrador va a qué página. «term»: la portada de un tipo de servicio (/services/<tipo>/).
SERVICIOS = [
    {"draft": "PC-35-traslados.md", "kind": "term", "type": "transport"},
    # En PC-37 (ES) las notas de revisión van pegadas al último párrafo: se corta ahí.
    {"draft": "PC-37-concierge.md", "kind": "term", "type": "concierge", "cortar": "- Alcance en la web actual"},
    {"draft": "PC-36-chef.md", "kind": "post", "type": "catering",
     "slug": {"en": "private-chef-in-marbella", "es": "chef-privado-en-marbella"}},
]
TOURS = ["granada", "cordoba", "sevilla", "gibraltar", "tanger", "ronda", "tapas-marbella",
         "malaga", "jerez", "mijas", "bicicleta", "puerto-banus-noche", "cata-vinos-marbella",
         "andalucia-3-dias", "andalucia-5-dias", "golf"]

# Campos del tipo Tour: el borrador los da en español; aquí su versión inglesa.
EN_CAMPOS = {
    "Incluida, en tu alojamiento": "Pick-up at your accommodation",
    "No incluida; se puede añadir como extra": "Not included; can be added as an extra",
    "No incluida; se puede organizar transporte privado aparte": "Not included; we can arrange private transport",
    "No incluida": "Not included",
    "Opcional, en tu alojamiento (extra)": "Optional, at your accommodation (extra)",
    "En el aeropuerto o la estación de Málaga": "At Málaga airport or train station",
    "Traslado desde el aeropuerto de Málaga o de Gibraltar": "Transfer from Málaga or Gibraltar airport",
    "Privado": "Private",
    # Salida
    "Según la hora de las entradas de la Alhambra (normalmente sobre las 8:00)": "Depends on your Alhambra ticket time (usually around 08:00)",
    "9:00 (ferry desde Tarifa, por lo general a las 11:00)": "09:00 (the ferry from Tarifa usually leaves at 11:00)",
    "12:00 y 16:00": "12:00 or 16:00",
    "Según disponibilidad": "Depending on availability",
    # Punto de encuentro
    "Fuente del Parque de la Alameda, Marbella": "The fountain in Parque de la Alameda, Marbella",
    "Fuente de la plaza principal de Mijas pueblo": "The fountain in the main square of Mijas village",
    "Tienda de surf del proveedor, Urb. Marbellamar 8, 29602 Marbella": "The provider's surf shop, Urb. Marbellamar 8, 29602 Marbella",
    "Delante del Sinatra Bar, en Puerto Banús, donde sale el ferry Fly Blue": "In front of Sinatra Bar in Puerto Banús, where the Fly Blue ferry leaves",
    "Parque de la Alameda, Marbella": "Parque de la Alameda, Marbella",
}
# Tipo de la ficha (cabecera del borrador) → tour_kind: decide la cancelación y la etiqueta.
KINDS = {"Tour": "tour", "Actividad": "activity", "Varios días": "multiday"}
# Secciones del borrador que pasan a campos de la ficha; el resto va a la descripción.
SECCIONES = {
    "Highlights": "highlights", "Lo más destacado": "highlights",
    "Itinerary": "itinerary", "Programa": "itinerary",
    "What's included": "includes", "Qué incluye": "includes",
    "Not included": "excludes", "No incluye": "excludes",
    "Extras": "extras",
    "What to bring": "bring", "Qué llevar": "bring",
    "Not allowed": "not_allowed", "No se permite": "not_allowed",
    "Good to know": "know", "Bueno saberlo": "know",
    "Cancellation": "cancellation", "Cancelación": "cancellation",
    # Lo que ya enseñan los datos rápidos de la ficha.
    "Practical details": None, "Datos prácticos": None,
}
EN_IDIOMAS = {"Inglés": "English", "español": "Spanish", "Español": "Spanish", "ruso": "Russian",
              "alemán": "German", "francés": "French", "árabe": "Arabic", "italiano": "Italian",
              "portugués": "Portuguese"}


def inline(text: str) -> str:
    text = html.escape(text, quote=False)
    text = re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", text)
    text = re.sub(r"(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)", r"<em>\1</em>", text)
    text = re.sub(r"`([^`]+)`", r"\1", text)
    return text


def cta(text: str, kind: str) -> str:
    """«… → *Pide presupuesto*»: enlace al formulario (servicios) o fuera (experiencias)."""
    def repl(m):
        label = m.group(1).strip()
        return "" if kind == "tour" else f' <a href="#solicitud">{label}</a>'
    text = re.sub(r"\s*→\s*\*([^*]+)\*", repl, text, flags=re.S)
    return text.strip()


def seccion(md: str, lang: str) -> str:
    m = re.search(rf"^## {lang}\s*$(.*?)(?=^---\s*$|^## |\Z)", md, flags=re.S | re.M)
    if not m:
        raise SystemExit(f"Falta la sección {lang}")
    return m.group(1)


def bloques(body: str, kind: str):
    """Markdown sencillo → (meta, extracto, bloques de Gutenberg, HTML plano)."""
    meta, blocks, plain, excerpt = {}, [], [], None
    paras = re.split(r"\n\s*\n", body.strip())
    for raw in paras:
        p = raw.strip()
        if not p:
            continue
        mm = re.match(r"^\*\*(SEO title|Título SEO|Meta description|Slug|H1)\*\*:\s*(.*)$", p, flags=re.S)
        if mm:
            # Un párrafo puede llevar varias líneas de metadatos seguidas.
            for part in re.split(r"\n(?=\*\*(?:SEO title|Título SEO|Meta description|Slug|H1)\*\*:)", p):
                k, v = re.match(r"^\*\*(.+?)\*\*:\s*(.*)$", part, flags=re.S).groups()
                meta[{"Título SEO": "SEO title"}.get(k, k)] = " ".join(v.split()).strip("`")
            continue
        if "[confirmar" in p and not p.lstrip().startswith(("-", "|")):
            continue
        if p.startswith("### "):
            t = inline(p[4:].strip())
            blocks.append(f'<!-- wp:heading --><h2 class="wp-block-heading">{t}</h2><!-- /wp:heading -->')
            plain.append(f"<h2>{t}</h2>")
            continue
        if p.startswith("|"):
            rows = [r.strip().strip("|").split("|") for r in p.splitlines() if r.strip().startswith("|")]
            rows = [[c.strip() for c in r] for r in rows if not re.match(r"^[\s|:-]+$", "|".join(r))]
            head, data = rows[0], rows[1:]
            th = "".join(f"<th>{inline(c)}</th>" for c in head)
            trs = "".join("<tr>" + "".join(f"<td>{inline(c)}</td>" for c in r) + "</tr>" for r in data)
            tbl = f"<table><thead><tr>{th}</tr></thead><tbody>{trs}</tbody></table>"
            blocks.append(f'<!-- wp:table --><figure class="wp-block-table">{tbl}</figure><!-- /wp:table -->')
            plain.append(tbl)
            continue
        if p.lstrip().startswith("- "):
            items, cur = [], None
            for line in p.splitlines():
                if line.lstrip().startswith("- "):
                    cur = [line.lstrip()[2:]]
                    items.append(cur)
                elif cur is not None:
                    cur.append(line.strip())
            lis = []
            for it in items:
                t = " ".join(it)
                if "[confirmar" in t:
                    continue
                t = re.sub(r"\s*`\[precio\]`", "", t)
                t = cta(t, kind)
                lis.append(inline(t).replace("&lt;a href=&quot;#solicitud&quot;&gt;", ""))
            if lis and lis[-1].endswith(";"):
                lis[-1] = lis[-1][:-1] + "."  # se quitó el último elemento de la lista
            lis_b = "".join(f"<!-- wp:list-item --><li>{t}</li><!-- /wp:list-item -->" for t in lis)
            blocks.append(f'<!-- wp:list --><ul class="wp-block-list">{lis_b}</ul><!-- /wp:list -->')
            plain.append("<ul>" + "".join(f"<li>{t}</li>" for t in lis) + "</ul>")
            continue
        text = " ".join(p.split())
        link = None
        m2 = re.search(r"→\s*\*([^*]+)\*", text)
        if m2:
            link = m2.group(1).strip()
        text = re.sub(r"\s*→\s*\*[^*]+\*", "", text).strip()
        t = inline(text)
        if link and kind != "tour":
            t += f' <a href="#solicitud">{html.escape(link)}</a>'
        if not t:
            continue
        if excerpt is None:
            excerpt = re.sub(r"<[^>]+>", "", t)
            continue
        blocks.append(f"<!-- wp:paragraph --><p>{t}</p><!-- /wp:paragraph -->")
        plain.append(f"<p>{t}</p>")
    return meta, excerpt, "\n".join(blocks), "\n".join(plain)


def campos_tour(md: str):
    m = re.search(r"## Campos del tipo Tour\s*\n(.*?)\n\s*\n", md, flags=re.S)
    out = {}
    for row in m.group(1).splitlines():
        cells = [c.strip() for c in row.strip().strip("|").split("|")]
        if len(cells) == 2 and not set(cells[0]) <= set("-: "):
            out[cells[0]] = cells[1]
    return out


def grupo(formato: str) -> dict:
    m = re.search(r"mínimo (\d+) personas", formato)
    if m:
        return {"en": f"Minimum {m.group(1)} people", "es": f"Mínimo {m.group(1)} personas"}
    m = re.search(r"máximo (\d+) personas", formato)
    if m:
        return {"en": f"Small group, up to {m.group(1)} people", "es": f"Grupo reducido, hasta {m.group(1)} personas"}
    return {"en": "", "es": ""}


def en_idiomas(es: str) -> str:
    return ", ".join(EN_IDIOMAS.get(x.strip(), x.strip()) for x in es.split(","))


def dos(es: str) -> dict:
    """Valor de un campo del borrador (en español) en los dos idiomas."""
    es = es.strip()
    if es in EN_CAMPOS:
        return {"en": EN_CAMPOS[es], "es": es}
    if re.fullmatch(r"\d{1,2}:\d{2}", es):
        return {"en": es.zfill(5), "es": es}
    return {"en": es, "es": es}


def duracion(es: str) -> dict:
    d = es.split(" (")[0].strip()
    m = re.fullmatch(r"(\d+) noches / (\d+) días", d)
    if m:
        return {"en": f"{m.group(1)} nights / {m.group(2)} days", "es": d}
    if d.endswith(" h"):
        n = d[:-2]
        return {"en": f"{n} hours", "es": f"{n} horas"}
    return {"en": d, "es": d}


def plano(text: str) -> str:
    """Texto de un campo: sin marcas de Markdown ni enlaces «→ *…*»."""
    text = re.sub(r"\s*`\[precio\]`", "", text)
    text = re.sub(r"\s*→\s*\*[^*]+\*", "", text)
    text = re.sub(r"\*\*(.+?)\*\*", r"\1", text)
    text = re.sub(r"(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)", r"\1", text)
    return " ".join(text.split()).strip()


def ficha_tour(body: str) -> dict:
    """Cuerpo EN o ES de un borrador de tour → título, SEO, extracto, descripción y campos."""
    partes = re.split(r"^### (.+?)\s*$", body, flags=re.M)
    intro, resto = partes[0], list(zip(partes[1::2], partes[2::2]))
    campos = {k: [] for k in ("highlights", "itinerary", "includes", "excludes", "extras", "bring",
                              "not_allowed", "know")}
    campos.update({"itinerary_note": "", "cancellation": ""})
    descripcion = [intro]
    for titulo, texto in resto:
        clave = SECCIONES.get(titulo.strip(), "")
        if clave is None:
            continue
        if not clave:
            descripcion.append(f"### {titulo}\n\n{texto}")
            continue
        sueltos = []
        for p in re.split(r"\n\s*\n", texto.strip()):
            p = p.strip()
            if not p or ("[confirmar" in p and not p.startswith(("-", "|"))):
                continue
            if p.startswith("|") and clave == "itinerary":
                for fila in p.splitlines()[2:]:
                    celdas = [x.strip() for x in fila.strip().strip("|").split("|")]
                    if len(celdas) >= 2 and "[confirmar" not in fila:
                        campos["itinerary"].append([plano(celdas[0]), plano(celdas[1])])
            elif p.startswith("- ") and clave != "cancellation":
                for li in re.split(r"\n(?=- )", p):
                    li = " ".join(li[2:].split())
                    if "[confirmar" not in li:
                        campos[clave].append(plano(li))
            elif clave == "cancellation":
                campos["cancellation"] = (campos["cancellation"] + " " + plano(p)).strip()
            elif clave == "itinerary":
                campos["itinerary_note"] = plano(p)
            else:
                sueltos.append(p)
        if sueltos:
            # Párrafos bajo «Highlights»: son descripción.
            descripcion.append("\n\n".join(sueltos))
    meta, exc, blk, _ = bloques("\n\n".join(descripcion), "tour")
    return {"title": meta["H1"], "seo_title": meta.get("SEO title", ""),
            "metadesc": meta.get("Meta description", ""), "excerpt": exc, "content": blk,
            "campos": campos}


def main():
    data = {"origen": "mins29601/marOS.content · contenidos/borradores", "servicios": [], "tours": []}
    for s in SERVICIOS:
        md = (ORIGEN / s["draft"]).read_text()
        item = dict(s)
        for lang, sec in (("en", "EN"), ("es", "ES")):
            body = seccion(md, sec)
            if s.get("cortar") and s["cortar"] in body:
                body = body.split(s["cortar"])[0]
            meta, exc, blk, plain = bloques(body, "service")
            item[lang] = {"title": meta["H1"], "seo_title": meta.get("SEO title", ""),
                          "metadesc": meta.get("Meta description", ""), "excerpt": exc,
                          "content": blk, "html": f"<p>{exc}</p>\n{plain}"}
        data["servicios"].append(item)
    for t in TOURS:
        md = (ORIGEN / "tours" / f"{t}.md").read_text()
        slugs = re.search(r"\| Slug \| `([^`]+)` \(EN[^)]*\) · `([^`]+)` \(ES", md).groups()
        tipo = re.search(r"^\| Tipo \| \*\*(.+?)\*\*", md, flags=re.M).group(1)
        c = campos_tour(md)
        fh = re.search(r"`(\d+)`", c.get("Id de FareHarbor", ""))
        item = {"draft": f"tours/{t}.md", "slug": {"en": slugs[0], "es": slugs[1]},
                "kind": KINDS.get(tipo, "tour"),
                "fareharbor": fh.group(1) if fh else "",
                "fields": {"duration": duracion(c.get("Duración", "")),
                           "start_time": dos(c.get("Salida", c.get("Salidas", ""))),
                           "meeting_point": dos(c.get("Punto de encuentro", "")),
                           "languages": {"en": en_idiomas(c.get("Idiomas", "")), "es": c.get("Idiomas", "")},
                           "pickup": dos(c.get("Recogida", "")),
                           "private": c.get("Formato", "").startswith("Privado"),
                           "group_size": grupo(c.get("Formato", ""))}}
        for lang, sec in (("en", "EN"), ("es", "ES")):
            item[lang] = ficha_tour(seccion(md, sec))
        data["tours"].append(item)
    DESTINO.write_text(json.dumps(data, ensure_ascii=False, indent=1) + "\n")
    print(f"✓ {DESTINO} ({len(data['servicios'])} servicios, {len(data['tours'])} experiencias)")


if __name__ == "__main__":
    main()
