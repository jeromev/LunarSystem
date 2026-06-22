# Linked Data — turning LunarSystem into a real Semantic Web CMS

> **Status: the active direction (`main`, `0.8.47-alpha`).** The semantic-web work
> described here is the `main` line; the untouched archival CMS (`0.2.14-alpha`)
> is preserved on the `legacy` branch. The plan below runs Phase 0 → A → B → C, and
> **all are implemented**: Phase 0 JSON-LD, Phase A virtual SPARQL, Phase B
> triplestore swap, and **Phase C — the triplestore is authoritative for the
> read path by default**. Every content write mirrors into Oxigraph through a
> generic write-through in the model's CRUD; routing, ACL and texts are read from
> the triplestore by default (`?sparql=0` falls back to SQL). MySQL remains the
> system of record until the rename/URI decision unblocks P2 (see [roadmap.md](roadmap.md)).

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

> The pre-migration model used real HTTP node URIs (`{base}/node/{nid}`). Phase 0
> introduced the `nid`-free `/id/{slug}` form, and the legacy-model retirement is now
> **complete**: `/id/{slug}` is the identity everywhere a consumer sees — rendered,
> published, and in the triplestore. The integer `nid` survives only as the
> `schema:identifier` property and as the loaders' internal DB key.

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

The `luna:` namespace IRI is **`https://jeromev.github.io/LunarSystem/ontology#`**, defined
once as `lunaModel::LUNA_NS` and documented in [`ontology/`](../ontology/) (its README covers
publishing it via GitHub Pages so the IRI dereferences). Three URI roles stay distinct: this
**vocabulary** namespace (project-level, stable); **resource/instance** IRIs (`/id/{slug}`,
which follow the deployment host via `luna::$site_uri`); and the project **homepage**
(`https://github.com/jeromev/LunarSystem`). Only the vocabulary namespace is published RDF
identity — pointing it at the bare code repo would conflate it with the homepage and wouldn't
dereference to a vocabulary.

## What's built in Phase 0 (this branch)

A **JSON-LD projection** of the current page, generated from the same in-memory
model that drives the HTML view — the "one model, many representations" idea, but
this time the representation is correct, standards-based Linked Data:

- `?output=jsonld` on any page → compact **schema.org JSON-LD**
  (`application/ld+json`).
- The same block is embedded as `<script type="application/ld+json">` in every
  HTML page's `<head>` — the part of the Semantic Web that actually *won*
  (Google/Bing rich results, knowledge-graph ingestion).
- `?output=xml/n3/json` serve the **same clean projection**
  ([`lunaModel::build_schema_index()`](../luna/luna.classes/luna.model.class.php)) — slug
  IRIs, `schema:WebPage`/`Article`, the `luna:` terms — so the whole public RDF
  surface matches the triplestore. The legacy-model retirement is now complete, so
  there is effectively **one model**: the loaders build an `nid`-keyed in-memory graph,
  but [`lunaModel::project_to_schema()`](../luna/luna.classes/luna.model.class.php)
  re-keys it to `/id/{slug}` and maps the content vocabulary to schema.org at the
  `transform()` serialisation boundary, so **the XSLT renders from the schema.org /
  `/id/{slug}` graph too** — not from a legacy `/node/{nid}` view. The made-up
  `owl:isChildOf` is gone everywhere (now `schema:isPartOf`, including the internal
  graph), and the active flag is `luna:isActive` throughout. The `nid` survives only as
  the `schema:identifier` property and the loaders' internal DB key.

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

The existing MySQL is queryable as RDF with **SPARQL**, with **no schema
change and no data migration**, via [Ontop](https://ontop-vkg.org/) +
an R2RML mapping. RDF stops being an output veneer and becomes a query surface.

Files (all under [`semantic/ontop/`](../semantic/ontop/)):

| File | Role |
|---|---|
| `mapping.ttl` | R2RML: `luna_nodes`/`luna_nodes_map`/`luna_texts`/`luna_users` → schema.org/FOAF (incl. access **levels** → `luna:level`, which drive the SPARQL routing/ACL path), using the **same `/id/{lid}` URIs** as the JSON-LD |
| `ontop.properties` | JDBC connection to the `db` service |
| `Dockerfile` | `ontop/ontop` + the MySQL JDBC driver |

Run it (the `db` service must be up):

```bash
docker-compose up --build -d ontop      # SPARQL endpoint at http://ontop:8080/sparql (internal compose network only)
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
docker-compose exec -T app curl -s http://ontop:8080/sparql -H 'Accept: application/sparql-results+json' \
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
queries the endpoint, and `load_texts_sparql()` rebuilds a page's text blocks
through the *same* `load_text()` index builder the SQL path uses. **This is the
default** (`lunaModel::sparql_reads()`, constant
`SPARQL_READS`); `?sparql=0` forces the SQL path for one request:

```text
/?output=jsonld            # page texts from the SPARQL endpoint (default)
/?output=jsonld&sparql=0   # page texts from the hand-written SQL joins
```

Both produce a **byte-for-byte identical model** (verified — the JSON-LD output
matches exactly, and the HTML page renders the same). The mapping gained a
`schema:identifier` (the legacy `nid`) so the SPARQL loader can rebuild the exact
`/node/{nid}` index the XSLT expects — `nid` as a *property*, not as identity.

**Routing and access control go through SPARQL too.** `load_nodes_sparql()`
loads the whole page tree — scoped to the levels the current user holds — and
rebuilds it through the same `load_node()` + `calculate_aliases()` the SQL path
uses, so URL→page resolution *and* the level-based ACL are driven by the graph.
Verified by default: as a guest, `/` renders the public home but `/admin`
is **404** (admin pages aren't in a guest's level-filtered alias table); an
admin's levels resolve every protected page from the graph. The page's
**routing and content** are built from SPARQL; the only remaining SQL read on the
page path is the mod list (`load_mods`) — infrastructure, not content.

Both the routing loader and the text loader keep an **automatic SQL fallback**:
if the SPARQL path is off (`?sparql=0` / `SPARQL_READS=0`) or returns nothing,
they fall back to the hand-written joins, so a cold or unreachable endpoint can
never brick routing. (A *partial* mirror gap is not caught by the empty-result
fallback — reconcile with `rdf_resync_all()`; see below.)

This is the move that makes SPARQL the **read boundary**: with the loaders
reading this way, swapping Ontop for a triplestore (Phase B) changes nothing in
the application above the endpoint.

### Writing *through* SPARQL

**Every** content write now flows into the triplestore, through a generic
write-through wired into the model's CRUD (no longer a per-mod hook).
[`lunaModel::sparql_update()`](../luna/luna.classes/luna.model.class.php) POSTs a
SPARQL `UPDATE` to `SPARQL_UPDATE_ENDPOINT` (the authenticating `sparql-proxy` in
front of Oxigraph — the same HTTP basic auth `sparql_select()` sends on reads,
added by `sparql_auth_header()` from `SPARQL_AUTH_USER`/`SPARQL_AUTH_PASS`;
best-effort, so a failed mirror never breaks a save). On top of it:

- **`rdf_sync_node($nid)`** re-projects a node's *whole* description into the
  graph — `DELETE { <uri> ?p ?o } INSERT { …the triples the R2RML mapping derives… }`
  — typing the resource (`page`→`schema:WebPage`, `text`→`schema:Article`,
  `user`→`foaf:Person`; level/group/mod stay untyped, carrying only
  `schema:name`/`schema:identifier`/`luna:isActive`) and emitting its edges
  (`schema:isPartOf`/`hasPart`, `luna:level`) as `/id/{lid}` resource URIs, plus
  scalar properties (`schema:identifier`, `luna:isActive`) typed `xsd:integer` to
  match the materialisation. `insert`/`update`/`link`/`unlink`
  all call it; `mod_edit_texts` calls it after writing a text body (it replaced
  the old `rdf_put_article`).
- **`rdf_delete_node($nid)`** drops every triple mentioning the resource — as
  subject *and* as object — and runs inside `delete()` before the rows go.
- **`rdf_resync_all($prune = false)`** re-projects *every* node from MySQL: the
  pure-PHP bootstrap/repair of the store, replacing the Ontop "materialise" step.
  Run it to seed Oxigraph or to reconcile after any out-of-band change — runnable
  as **`make resync-triplestore`** (→ `bin/resync-triplestore.php`). The default
  upserts MySQL→graph and leaves graph-only orphans in place; `$prune = true`
  (what the make target passes) first clears the whole store via `rdf_clear()`,
  for a full rebuild that drops orphans too. This pure-PHP projection is *fuller*
  than the Ontop materialise — it types every text `schema:Article` (not just the
  page-linked ones) and projects the level/group/mod nodes — so a rebuilt store
  holds ~152 triples to the materialise's 104.

So any content change in the admin UI **dual-writes**: the existing SQL to MySQL,
plus a SPARQL `DELETE`/`INSERT` to the graph for `<base/id/{lid}>`.

Verified end-to-end through the real CRUD: insert→link→delete of a throwaway page
produced and then fully removed its `schema:WebPage` + `luna:level` projection in
Oxigraph; an Oxigraph-only sentinel on a text rendered by default and disappeared
under `?sparql=0`; `rdf_resync_all()` closed a real drift gap (a page that
predated the dual-write) to exact count parity with MySQL. Dual-write keeps the
two stores in sync while the write path migrates; once the MySQL write retires
(P2), the graph is the single source of truth and the drift window closes.

## Phase B — the swap (demonstrated)

The point of the whole plan: **swap the engine, not the application.** The
`mapping.ttl` written for Phase A doubles as the materialisation script for B.

```bash
# 1. materialise the SAME mapping into a static RDF dump (104 triples)
docker exec lunarsystem-ontop-1 /opt/ontop/ontop materialize \
  -m /opt/ontop/input/mapping.ttl -p /opt/ontop/input/ontop.properties \
  -o /opt/ontop/input/dump.nt -f ntriples

# 2. load it into the triplestore THROUGH the authenticating proxy. Oxigraph sits on an
#    internal-only network and is never reachable directly, so the load presents the
#    proxy credentials (the app container has them in its env).
docker-compose up -d oxigraph sparql-proxy
docker-compose exec -T app sh -c 'curl -X POST -u "$SPARQL_AUTH_USER:$SPARQL_AUTH_PASS" \
  "http://sparql-proxy:7878/store?default" \
  -H "Content-Type: application/n-triples" --data-binary @semantic/ontop/dump.nt'

# 3. point the app at the triplestore — NO code change. This is now the DEFAULT
#    (SPARQL_ENDPOINT defaults to the proxy in front of Oxigraph); the override goes the
#    other way: SPARQL_ENDPOINT=http://ontop:8080/sparql docker-compose up -d app  # read via Ontop
```

> The Ontop materialise above is optional: `rdf_resync_all()`
> re-projects the whole store from MySQL in pure PHP (see *Writing through SPARQL*),
> which is how Oxigraph is seeded and reconciled — run it with
> **`make resync-triplestore`**.

The default read path is served by Oxigraph. The proof it's genuinely the
triplestore and not MySQL-via-Ontop: **stop Ontop** and the read path keeps
working —

```text
docker stop lunarsystem-ontop-1
guest  /              -> 200   (home renders from the triplestore)
guest  /admin         -> 404   (level-based ACL preserved in the graph)
admin  /admin/journal -> 200   (deep alias resolved from the graph)
```

Routing, access control, and content — all served by the triplestore, with the
PHP unchanged. `mapping.ttl` (Phase A) → `dump.nt` (Phase B) is the only moving
part. The dump is generated (gitignored); regenerate it with the command above.

## Roadmap

- **Phase A (done — prototype):** R2RML + Ontop virtual SPARQL endpoint over the
  existing MySQL; the page read path (routing, ACL, text content) flows through
  SPARQL. Ontop is now the *opt-in* override (`SPARQL_ENDPOINT=…/sparql`).
- **Phase B (done — demonstrated):** materialised `mapping.ttl` into Oxigraph and
  flipped the app at it by env var (above) — now the default endpoint.
- **Phase C — triplestore authoritative for the read/write loop (done):**
  *every* content write mirrors to the graph via the generic `rdf_sync_node` /
  `rdf_delete_node` write-through (see *Writing through SPARQL*), and reads default
  to the graph with a SQL fallback (see *Reading through SPARQL*). MySQL is still
  the system of record.

What remains is tracked in **[roadmap.md](roadmap.md)**: **P2** — retire the MySQL
content write so the triplestore is the *single* source of truth (blocked on the
rename/URI decision; needs must-succeed writes + an outbox since there's no 2PC),
minting URIs from slugs not `nid` sequences; and the optional **P3** — RDFS/OWL
inference, SHACL validation, and named graphs for drafts/versions. Still-SQL
holdouts: the mod list (`load_mods`) and the `luna_actions` audit trail (PROV-O).
**Boundary:** sessions and cache stay relational/native — a triplestore is the
wrong tool for ephemeral, high-churn data.

The cardinal rule across all phases: **freeze the URIs.** Same `/id/{slug}` in 0,
A, B, and C, so every external link and `owl:sameAs` keeps working.
