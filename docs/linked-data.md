# Linked Data — turning LunarSystem into a real Semantic Web CMS

> **Status: experiment.** This document and the code under it live on the
> `experiment/semantic-web` branch. The archival CMS release is `0.2.14-alpha` on
> `main`; nothing here changes that. This is Phase 0 of a staged plan.

## The problem we're fixing

Classic LunarSystem treats RDF as an **output format** bolted onto a hand-written
SQL app: the graph is rebuilt from relational joins on every request
([`lunaModel::load_nodes()`](../luna/luna.classes/luna.model.class.php)),
serialised by ARC2, transformed by XSLT, and discarded. It is *RDF-flavoured, not
RDF-native*. Everything that makes the Semantic Web actually work — querying the
graph, dereferenceable URIs, shared vocabularies, inference, linking out — was on
the side the project skipped.

The fix is a staged migration where **SPARQL is the stable seam**:

| Phase | What | Storage | Query |
|---|---|---|---|
| **0** (here) | Decide the things that are *forever* (URIs, vocabulary) and prove them with real output (JSON-LD) | MySQL (unchanged) | existing |
| **A** | Faithful evolution: expose MySQL as a **virtual RDF graph** via an R2RML mapping + Ontop | MySQL (unchanged) | **SPARQL** (read) |
| **B** | Clean rebuild: materialise the same mapping into a triplestore | **Oxigraph / Fuseki** | **SPARQL** (read + write) |

A → B is clean because the application only ever talks to a SPARQL endpoint, and
the **R2RML mapping written in A is the materialisation script for B**. The two
decisions below are written once in Phase 0 and reused unchanged by A and B —
which is why they must be right now.

## Decision 1 — URI policy (this is forever)

Linked Data's first principle: use **HTTP URIs as names**, and make them
dereferenceable. `nid` autoincrement ints and `#page_9` are the antithesis of
that. We separate the *resource* (the abstract thing) from the *documents* that
describe it (Cool URIs / the `303` pattern):

| Kind | Pattern | Example | Returns |
|---|---|---|---|
| **Resource** (identity) | `{base}/id/{slug}` | `https://site/id/root` | `303 See Other` → a document below, by `Accept` |
| **HTML document** | `{base}/{path}` | `https://site/` | `text/html` |
| **RDF document** | `{base}/data/{slug}` | `https://site/data/root` | `text/turtle`, `application/ld+json`, … |

Rules that must not change between phases:

- The **slug** (`luna_nodes.lid`) is the stable local name, *not* the numeric `nid`.
- `/id/{slug}` is the thing's identity everywhere in the graph (subject/object of
  triples), never the `nid`.
- Content negotiation, not `?output=`, is the long-term mechanism (the `?output=`
  query param stays as a convenience/debug alias).

> Today's model already uses real HTTP node URIs (`{base}/node/{nid}`). Phase 0
> introduces the `nid`-free `/id/{slug}` form; A and B inherit it verbatim.

## Decision 2 — Vocabulary mapping (reuse, don't invent)

The whole point of the Semantic Web is **shared** vocabularies. Classic
LunarSystem invents `luna:` for almost everything and even uses a term that does
not exist (`owl:isChildOf` — there is no such OWL term). We map to widely-used
vocabularies and keep `luna:` only for genuinely app-specific things.

| Classic term | → | Standard term | Vocabulary |
|---|---|---|---|
| `rdf:type luna:page` | → | `schema:WebPage` (`foaf:Document`) | schema.org / FOAF |
| `rdf:type luna:text` | → | `schema:Article` / `sioc:Item` | schema.org / SIOC |
| `rdf:type luna:user` | → | `foaf:Person` (kept) | FOAF |
| `rdfs:label` (title/name) | → | `schema:name` / `schema:headline` | schema.org |
| `luna:content` | → | `schema:text` / `schema:articleBody` / `sioc:content` | schema.org / SIOC |
| **`owl:isChildOf`** (invalid) | → | `schema:isPartOf` / `dcterms:isPartOf` | schema.org / DC Terms |
| (children) | → | `schema:hasPart` | schema.org |
| node lid as email | → | `foaf:mbox` | FOAF |
| `luna_actions` (audit) | → | `prov:Activity` / `prov:wasGeneratedBy` / `prov:Agent` | PROV-O |
| `luna_texts.lang` | → | `schema:inLanguage` / `dc:language` | schema.org / DC |
| taxonomy / `luna_types` | → | `rdf:type` against an **RDFS/OWL + SHACL** schema | RDFS / OWL / SHACL |
| access levels (`luna:level`) | → | Web Access Control (`acl:`) or keep `luna:` | WAC |

Namespaces in play: `schema:` `https://schema.org/`, `dcterms:`
`http://purl.org/dc/terms/`, `sioc:` `http://rdfs.org/sioc/ns#`, `foaf:`
`http://xmlns.com/foaf/0.1/`, `prov:` `http://www.w3.org/ns/prov#`, `skos:`
`http://www.w3.org/2004/02/skos/core#`. A small `luna:` survives for what nobody
else models.

## What's built in Phase 0 (this branch)

A **JSON-LD projection** of the current page, generated from the same in-memory
model that drives the HTML view — the "one model, many representations" idea, but
this time the representation is correct, standards-based Linked Data:

- `?output=jsonld` on any page → compact **schema.org JSON-LD**
  (`application/ld+json`).
- The same block is embedded as `<script type="application/ld+json">` in every
  HTML page's `<head>` — the part of the Semantic Web that actually *won*
  (Google/Bing rich results, knowledge-graph ingestion).

Implementation: [`lunaModel::to_jsonld()`](../luna/luna.classes/luna.model.class.php)
applies the Decision-2 mapping to the page node + its text blocks and emits a
`schema:WebPage` with `schema:hasPart` `schema:Article`s, `schema:isPartOf` for
the parent, and `/id/{slug}` resource URIs from Decision 1. It is deliberately a
*projection over the existing model* — in Phase A the very same shape comes from a
**SPARQL `CONSTRUCT`** over the R2RML-mapped graph instead.

```jsonc
{
  "@context": "https://schema.org/",
  "@type": "WebPage",
  "@id": "https://site/id/root",        // resource (Decision 1)
  "url": "https://site/",                // the HTML document
  "name": "Home",
  "inLanguage": "en",
  "hasPart": [
    { "@type": "Article", "headline": "Welcome", "articleBody": "…", "inLanguage": "en" }
  ]
}
```

## Phase A — a real SPARQL endpoint (running)

The existing MySQL is now queryable as RDF with **SPARQL**, with **no schema
change and no data migration**, via [Ontop](https://ontop-vkg.org/) +
an R2RML mapping. RDF stops being an output veneer and becomes a query surface.

Files (all under [`semantic/ontop/`](../semantic/ontop/)):

| File | Role |
|---|---|
| `mapping.ttl` | R2RML: `luna_nodes`/`luna_nodes_map`/`luna_texts`/`luna_users` → schema.org/FOAF, using the **same `/id/{lid}` URIs** as the JSON-LD |
| `ontop.properties` | JDBC connection to the `db` service |
| `Dockerfile` | `ontop/ontop` + the MySQL JDBC driver |

Run it (the `db` service must be up):

```bash
docker-compose up --build -d ontop      # SPARQL endpoint on http://localhost:8081/sparql
```

Example — pages and their article headlines, straight out of the relational
tables:

```sparql
PREFIX schema: <https://schema.org/>
SELECT ?page ?title WHERE {
  ?page a schema:WebPage ; schema:hasPart [ schema:headline ?title ] .
}
# → http://localhost:8080/id/root  "Welcome"
```

```bash
curl -s http://localhost:8081/sparql -H 'Accept: application/sparql-results+json' \
  --data-urlencode 'query=PREFIX schema: <https://schema.org/>
    SELECT ?child ?parent WHERE { ?child schema:isPartOf ?parent }'
# → the whole page tree as a graph (admin isPartOf root, …) — the fixed owl:isChildOf
```

**The proof that A → B will be clean:** the URI a SPARQL query returns
(`http://localhost:8080/id/root`) is byte-for-byte the `@id` the JSON-LD emits.
The virtual graph and the HTML/JSON-LD views describe the *same resources* — so
when Phase B materialises this mapping into a triplestore, every URI (and every
external `owl:sameAs` to it) keeps working.

> Hard-coded `luna_types` ids (`user=1 text=4 page=5`) keep the mapping SQL simple
> for Ontop's parser; `ontop.inferDefaultDatatype=true` defaults the string
> columns. Ontop is read-only (SPARQL `SELECT`/`CONSTRUCT`/`DESCRIBE`); writes
> stay relational until Phase B.

### Reading *through* SPARQL

The app can now populate its model **from the SPARQL endpoint** instead of the
hand-written joins. [`lunaModel::sparql_select()`](../luna/luna.classes/luna.model.class.php)
queries Ontop, and `load_texts_sparql()` rebuilds a page's text blocks through
the *same* `load_text()` index builder the SQL path uses. It's gated behind
`?sparql=1`:

```text
/?output=jsonld            # page texts from the hand-written SQL joins
/?output=jsonld&sparql=1   # page texts fetched from the SPARQL endpoint
```

Both produce a **byte-for-byte identical model** (verified — the JSON-LD output
matches exactly, and the HTML page renders the same). The mapping gained a
`schema:identifier` (the legacy `nid`) so the SPARQL loader can rebuild the exact
`/node/{nid}` index the XSLT expects — `nid` as a *property*, not as identity.

This is the move that makes SPARQL the **read boundary**: as each loader is
migrated to read this way, swapping Ontop for a triplestore (Phase B) changes
nothing in the application above the endpoint.

## Roadmap

- **Phase A (in progress — prototype):** R2RML + Ontop virtual SPARQL endpoint
  over the existing MySQL (above), and the page-text **read path now flows
  through SPARQL** (gated by `?sparql=1`). Next within A: migrate the remaining
  loaders (page nodes, mods) the same way so SPARQL is the *only* read path;
  expand the mapping to `luna_actions` (PROV-O) and access levels; add an
  LDP-style API.
- **Phase B:** materialise the same `mapping.ttl` into Oxigraph/Fuseki; point the
  endpoint at it (app read code unchanged); move writes to SPARQL `UPDATE`; turn
  on RDFS/OWL inference, SHACL validation, and named graphs for drafts/versions.

The cardinal rule across all phases: **freeze the URIs.** Same `/id/{slug}` in 0,
A, and B, so every external link and `owl:sameAs` keeps working.
