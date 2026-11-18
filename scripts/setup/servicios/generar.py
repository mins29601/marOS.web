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
TOURS = ["granada", "cordoba", "sevilla", "gibraltar", "tanger", "ronda", "tapas-marbella"]

# Campos del tipo Tour: el borrador los da en español; aquí su versión inglesa.
EN_CAMPOS = {
    "Incluida, en tu alojamiento": "Pick-up at your accommodation",
    "No incluida; se puede añadir como extra": "Not included; can be added as an extra",
    "Privado": "Private",
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
    return {"en": f"Minimum {m.group(1)} people", "es": f"Mínimo {m.group(1)} personas"} if m else {"en": "", "es": ""}


def en_idiomas(es: str) -> str:
    return ", ".join(EN_IDIOMAS.get(x.strip(), x.strip()) for x in es.split(","))


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
        slugs = re.search(r"\| Slug \| `([^`]+)` \(EN\) · `([^`]+)` \(ES\)", md).groups()
        c = campos_tour(md)
        dur = c.get("Duración", "").split(" (")[0]
        item = {"draft": f"tours/{t}.md", "slug": {"en": slugs[0], "es": slugs[1]},
                "fields": {"duration": {"en": dur.replace("h", "hours") if dur.endswith(" h") else dur,
                                        "es": dur.replace(" h", " horas") if dur.endswith(" h") else dur},
                           "languages": {"en": en_idiomas(c.get("Idiomas", "")), "es": c.get("Idiomas", "")},
                           "pickup": {"en": EN_CAMPOS.get(c.get("Recogida", ""), c.get("Recogida", "")),
                                      "es": c.get("Recogida", "")},
                           "private": c.get("Formato", "").startswith("Privado"),
                           "group_size": grupo(c.get("Formato", ""))}}
        for lang, sec in (("en", "EN"), ("es", "ES")):
            meta, exc, blk, _ = bloques(seccion(md, sec), "tour")
            item[lang] = {"title": meta["H1"], "seo_title": meta.get("SEO title", ""),
                          "metadesc": meta.get("Meta description", ""), "excerpt": exc, "content": blk}
        data["tours"].append(item)
    DESTINO.write_text(json.dumps(data, ensure_ascii=False, indent=1) + "\n")
    print(f"✓ {DESTINO} ({len(data['servicios'])} servicios, {len(data['tours'])} experiencias)")


if __name__ == "__main__":
    main()
