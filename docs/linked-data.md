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

## Roadmap

- **Phase A** — author the R2RML mapping for `luna_nodes`/`luna_nodes_map`/
  `luna_types`/`luna_texts`/`luna_users`/`luna_actions` using the URIs and terms
  above; stand up Ontop → a real SPARQL endpoint; move the read path off
  hand-written SQL onto SPARQL `CONSTRUCT`/`SELECT`; expose the endpoint + an
  LDP-style API. Writes stay relational, routed through one swappable DAO.
- **Phase B** — materialise the same mapping into Oxigraph/Fuseki; point the
  endpoint at it (app read code unchanged); move writes to SPARQL `UPDATE`; turn
  on RDFS/OWL inference, SHACL validation, and named graphs for drafts/versions.

The cardinal rule across all phases: **freeze the URIs.** Same `/id/{slug}` in 0,
A, and B, so every external link and `owl:sameAs` keeps working.
