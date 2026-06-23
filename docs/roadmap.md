# Roadmap

Where LunarSystem is headed. The arc: **finish the RDF-native transition** — make the
triplestore the system of record for content and retire MySQL for it — then **become a
data-first server** that emits RDF/XML + JSON-LD under content negotiation.

**Done so far:** the SPARQL write-through lives inside the model's generic CRUD, so every
content write mirrors into Oxigraph (P0); reads are served from the triplestore **by
default**, with MySQL as the system of record and an automatic `?sparql=0` SQL fallback
(P1); and slugs are immutable — `lunaModel::update()` refuses any `lid` change, so
`<base/id/{slug}>` is frozen by construction (a rename is create-new + delete-old). See the
[CHANGELOG](../CHANGELOG.md) for how each landed.

> **Cardinal rule across every phase: freeze the URIs.** `/id/{slug}` is identity; it must
> not change, or external links and `owl:sameAs` break.

## P2 — Retire the MySQL content write *(next)*

Make the triplestore the single source of truth for content, so dual-write drift
disappears. The large piece: it touches every admin mod's direct SQL, and there is **no
2-phase commit** across MySQL and an HTTP SPARQL endpoint.

- **Harden the write first:** promote the Oxigraph `UPDATE` from best-effort (today
  `sparql_update()` returns `false` on failure) to **must-succeed** — fail the save on
  mirror failure — and add a relational **outbox** table for at-least-once replay /
  reconciliation.
- **Optimistic concurrency** (version / etag in the `WHERE`) to replace the row locking
  MySQL gave for free.
- **Atomic cutover:** freeze writes → final MySQL→graph materialisation → switch off SQL
  content writes (replay any in-window edits from the outbox).
- **Mint identity from the slug:** `<base/id/{slug}>` replaces `luna_nodes_seq`;
  `lid_is_taken` becomes an `ASK` over the graph; keep `nid` only as a graph-side
  `schema:identifier` and the loaders' internal key.
- **Re-express lost relational invariants** (unique lid, required level/type, single
  parent) as `ASK` pre-checks (full SHACL comes in P3).

**Risks:** slug-as-identity makes renames a URI change (tension with "freeze the URIs" —
see decisions); no 2PC means a crash mid-write diverges the stores (the outbox is
mandatory); without ASK/SHACL the graph will accept duplicate slugs / dangling parents /
untyped nodes.

## P3 — Semantics: named graphs, inference, SHACL

Unlock what a triplestore is *for*. Oxigraph ships none of these natively (2025–26), so
they're done by materialisation / external tooling.

- **Named graphs** for drafts/versions: write drafts to `<base/graph/draft/{slug}>`,
  promote on publish; pairs with the PROV-O audit trail.
- **Forward-materialise** RDFS/OWL entailments on write (inverse `hasPart`/`isPartOf`, the
  `luna_types` taxonomy as `rdfs:subClassOf`) — re-derive on every write or the closure
  goes stale.
- **SHACL validation before accepting an UPDATE** (pySHACL / a Jena step) — encodes the
  invariants retired in P2.
- If native reasoning/SHACL becomes a hard requirement, swapping Oxigraph for **Jena
  Fuseki** or **GraphDB** is an *endpoint swap*, not a rewrite (the app only speaks SPARQL).

## P4 — Data-first server

Turn the server into a pure, content-negotiated **data** surface emitting the RDF/XML
`lunaModel::transform()` already builds — HTML becomes one representation among
JSON-LD / Turtle / N-Triples. Nothing visible changes for users.

- Add real **HTTP `Accept` content negotiation** in `set_output_format` (today it only keys
  on path / `?output=`); expose canonical `/id/{slug}` (identity) and `/data/{slug}`
  (RDF/XML) URIs, keeping `?output=` as debug aliases.
- Back the RDF/XML representation with a SPARQL `CONSTRUCT` / `DESCRIBE` so PHP shrinks to
  *negotiate + construct + serialise*.
- Serve `luna/luna.xsl/` as **static, long-cached, same-origin** assets under a stable
  `/xsl/`; the server tells the client which stylesheet won the cascade.
- **The server-side XSLTProcessor render stays canonical** — for crawlers, no-JS clients,
  and any failure path.

**Risks:** mis-keying the XML cache serves stale graphs; `xsl:include` resolution differs
server (filesystem) vs. any client (URL); errors / 404 / redirect / auth must be
expressible as a `ui:message` RDF graph a stylesheet can render.

> **Client-side XSLT (the former P5) is dropped** (June 2026). Native browser XSLT is being
> *removed* — Chromium drops `<?xml-stylesheet?>` and the `XSLTProcessor` API on Stable in
> Chrome 158 (~Nov 2026); Firefox and WebKit have signalled the same. The only path was a
> ~2.8 MB WASM libxslt polyfill bolted on top of a server render that must stay anyway —
> not worth the payload for this project. Part 2 ends at P4; the server render is the one
> and only renderer.

## Open decisions

| # | Decision | Blocks |
|---|---|---|
| 2 | **Dual-write durability** — confirm flipping the Oxigraph `UPDATE` to must-succeed + a relational outbox for replay (no 2PC exists). | P2 |
| 3 | **Keep or drop MySQL / Ontop** after P2 — retire entirely, or keep as a read-only SQL projection behind Ontop? | P2/P3 |
| 4 | **Fate of `nid`** — drop the `/node/{nid}` identity entirely, or keep `nid` as a non-identifying `schema:identifier`? | P2 |
| 5 | **Triplestore for P3** — stay on Oxigraph + external SHACL/inference, or swap to Jena Fuseki / GraphDB for native support? | P3 |
| 8 | **Draft/version model (P3)** — per-resource named graphs promoted on publish; PROV-O audit in the default graph or a dedicated audit graph? | P3 |

*Resolved: slugs are immutable (rename = create-new + delete-old); client-side XSLT (P5) is dropped.*

## Sequencing

The spine is the **rest of P2** — retiring the MySQL content write so the triplestore is the
single source of truth and dual-write drift disappears (a deliberate migration:
must-succeed writes + outbox, then the atomic cutover). **P3** is optional polish. **P4** is
low-risk and valuable on its own. **Recommended next:** the P2 write-retirement when you're
ready to commit to it; P4 whenever a clean RDF / data API is wanted.
