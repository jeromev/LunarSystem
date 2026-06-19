# Templating (XSLT)

LunarSystem's view layer is **XSLT 1.0**. The RDF model is serialised to RDF/XML
and a stylesheet transforms it into XHTML (or any text output). There are no PHP
templates and no template engine — the entire presentation layer is XSL.

## Where stylesheets live

| Location | Role |
|---|---|
| [luna/luna.xsl/luna.html.xsl/](../luna/luna.xsl/luna.html.xsl/) | The built-in HTML stylesheets (the default theme) |
| `luna.domains/<domain>/xsl/html.xsl/` | Per-domain overrides (optional; none ship in the repo) |
| `luna.domains/luna.default/xsl/` | Empty — the default domain falls back to the built-ins |

> **Naming:** built-in stylesheets carry a `luna.` prefix (e.g.
> `luna.admin.html.xsl`); per-domain override stylesheets **drop** it (e.g.
> `admin.html.xsl`). This matches the cascade below — the `SITEPATH` (domain)
> branches look for the un-prefixed name, the `XSL_PATH` (built-in) branches for
> the `luna.`-prefixed one.

Each output format has its own directory convention: `<format>.xsl/` (so
`html.xsl/`, and by extension the XML/JSON/N3 paths, though non-HTML formats are
serialised directly by ARC2 rather than via XSLT — see below).

## Stylesheet selection (the cascade)

`luna::transform()` ([luna.php:518](../luna/luna.php#L518)) picks a stylesheet by
trying paths in order, first hit wins (the cascade itself is
[luna.php:586-619](../luna/luna.php#L586)). Roughly, for output format `html` and a
page whose `lid` is `$lid`:

1. `SITEPATH/xsl/html.xsl/<lid>.html.xsl` — domain, page-specific
2. `SITEPATH/xsl/html.xsl/default.html.xsl` — domain, default
3. `SITEPATH/xsl/<lid>.xsl` — domain, legacy flat
4. `SITEPATH/xsl/default.xsl` — domain, legacy default
5. `XSL_PATH/luna.html.xsl/luna.<lid>.html.xsl` — built-in, page-specific
6. `XSL_PATH/luna.<lid>.xsl` — built-in, legacy flat
7. `XSL_PATH/luna.html.xsl/luna.default.html.xsl` — built-in, default

This is why most pages need no stylesheet of their own: they fall through to
`luna.default.html.xsl`. Admin pages each have a matching
`luna.<lid>.html.xsl`.

## How the built-in stylesheets are organised

The built-ins use a shared base + includes pattern. Sampling
[luna/luna.xsl/luna.html.xsl/](../luna/luna.xsl/luna.html.xsl/):

| File | Role |
|---|---|
| `luna.header.html.xsl` | The XHTML skeleton (`<html>`/`<head>`/`<body>`), namespace declarations, global variables (`$site_uri`, `$lang`, `$masternodelid`, `$masternodenid`, `$cleanurls`, …), and client-side JS bootstrap vars. Matches the `/rdf:RDF` root. |
| `luna.common.html.xsl` | Shared utility templates: string truncation (`cutstring`), form-input rendering (`forminput`, renders inputs/selects/textareas), message rendering (matches `luna:message`), pagination URL building (`buildSortURL`), loops. |
| `luna.common_admin.html.xsl` | Admin UI helpers: list renderers (`groupslist`, `levelslist`), `online_users`. |
| `luna.default.html.xsl` | The fallback content page — iterates `luna:text` nodes whose `luna:page` matches the current page and emits `<div class="box text">` with the title and unescaped `luna:content`. |
| `luna.<page>.html.xsl` | One per admin page: `admin`, `admin_groups`, `admin_levels`, `admin_mods`, `admin_pages`, `admin_users`, `edit_texts`, `journal`, `login`, `logout`, `root`. Each sets a `$mod_lid` and includes the header/common templates. |

A page-specific stylesheet typically declares the `$mod_lid` it renders, includes
`luna.header.html.xsl`, and provides `xsl:template match=…` rules for the nodes
that mod merges into the model.

## The input XML

Templates consume the RDF/XML serialisation of the model. Expect a flat
`<rdf:RDF>` document of `<luna:*>`, `<foaf:*>`, and `<rdf:Description>` elements.
A representative fragment:

```xml
<rdf:RDF
    xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
    xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
    xmlns:luna="http://lunarsystem.org/ontology#"
    xmlns:foaf="http://xmlns.com/foaf/0.1/"
    xmlns:owl="http://www.w3.org/2002/07/owl#">

  <luna:data luna:lid="clean_urls"><luna:value>1</luna:value></luna:data>
  <luna:data luna:lid="lang"><luna:value>en</luna:value></luna:data>

  <luna:page rdf:about="#page_9">
    <luna:nid>9</luna:nid>
    <luna:lid>root</luna:lid>
    <rdfs:label xml:lang="en">Home</rdfs:label>
  </luna:page>

  <luna:text>
    <luna:page rdf:resource="#page_9"/>
    <rdfs:label xml:lang="en">Welcome</rdfs:label>
    <luna:content xml:lang="en"><![CDATA[<p>Hello.</p>]]></luna:content>
  </luna:text>

  <foaf:Person luna:is_current="1">
    <luna:is_guest>0</luna:is_guest>
  </foaf:Person>

  <luna:message>
    <luna:code>warning</luna:code>
    <luna:value>Something went wrong.</luna:value>
  </luna:message>
</rdf:RDF>
```

Stylesheets match on these element/attribute patterns — e.g. the default page
template selects `luna:text` whose `luna:page/@rdf:resource` equals the current
page node, and `luna.common.html.xsl` matches `luna:message` to render flash
messages.

## Non-HTML output

Appending `?output=xml|json|n3` bypasses XSLT entirely:
`luna::transform()` calls `lunaModel::dump($format)`, which serialises the model
with **ARC2** (RDF/XML, RDF/JSON, or N-Triples) and exits with the appropriate
content type. This is the same model the HTML view consumes — so any page is also
a machine-readable RDF endpoint. See [rdf-model.md](rdf-model.md).

> **Linked Data (Phase 0):** a fifth format `?output=jsonld`
> is registered in `luna::$output_formats` and serialised by
> `lunaModel::to_jsonld()` (a schema.org JSON-LD projection), *not* by ARC2. The
> same JSON-LD is also embedded in every HTML page: after the XSLT transform,
> `luna::transform()` injects a `<script type="application/ld+json">` block before
> `</head>` ([luna.php:625](../luna/luna.php#L625)) — done in PHP post-processing,
> not in the stylesheets, so `luna.header.html.xsl` is unchanged. See
> [linked-data.md](linked-data.md).

## Caching

Transformed HTML is cached via **Cache_Lite**, keyed on a hash of the serialised
model. Admin users bypass the cache (the `IS_ADMIN` path in bootstrap disables
it), so edits are seen immediately. Caching can be toggled per domain with the
`CACHE` constant in `luna.ini` and flushed via `lunaTools::purge_cache()`.
