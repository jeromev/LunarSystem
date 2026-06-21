# Roadmap

Where LunarSystem is headed, and in what order. Two big arcs:

1. **Finish the RDF-native transition** — make the triplestore the system of record for content and retire MySQL for it (phases **P0–P3**; P0/P1 done).
2. **Become a data-first server** — emit data (RDF/XML + JSON-LD) under content negotiation (phase **P4**). *(P5, moving the XSLT transform into the browser, was dropped — see below.)*

Current state: **v0.8.22-alpha** on `main`. A 2026 security hardening pass (0.6.9–0.8.21, see [security.md](security.md)) is complete. **P0 and P1 are done**, and **decision #1 is resolved** (slugs are immutable — "forbid slug edits") and now **enforced** in `lunaModel::update()`. Every content write mirrors into Oxigraph through a generic write-through in the model's CRUD, the whole store can be rebuilt from MySQL with `rdf_resync_all()`, and the read path (routing, ACL, texts) is served from the triplestore **by default** — with MySQL as the system of record and an automatic SQL fallback (`?sparql=0`). What remains in Part 1 is the **rest of P2** — retiring the MySQL *content write* itself (a larger migration: it touches every admin mod's direct SQL, and needs must-succeed writes + an outbox) — and the optional **P3**. **Part 2's client-side-XSLT goal (P5) has been dropped** (see below); P4 — the data-first server — is where Part 2 now ends.

> The cardinal rule across every phase: **freeze the URIs.** `/id/{slug}` is identity; it must not change, or external links and `owl:sameAs` break.

---

## ⚠️ Decision taken: client-side XSLT (P5) is dropped

**The "render XSL in the browser" goal has been abandoned** (decision #7, resolved June 2026: *"finish RDF and forget front-end XSLT"*). The reasoning below is kept as the record of *why*. The net: Part 2 now ends at **P4** — a clean, content-negotiated data-first server (RDF/XML + the JSON-LD already emitted) — and the server-side XSLTProcessor render stays the one and only renderer.

The stated Part-2 idea — *"PHP delivers XML, a JavaScript layer transforms it to HTML with XSL in the browser"* — ran into a hard, time-sensitive fact, confirmed by current vendor docs:

**Native browser XSLT is being removed, not just deprecated.** Chromium drops **both** the `<?xml-stylesheet?>` processing instruction **and** the JavaScript `XSLTProcessor` API on Stable in **Chrome 158 (~Nov 17 2026)** — deprecation began Chrome 143 (Dec 2025); Origin-Trial/Enterprise-Policy escape hatches end Chrome 176 (Aug 2027). The driver is memory-safety risk in the effectively-unmaintained `libxslt`/`libxml2` C code (incl. CVE-2025-7425) plus ~0.02% usage. **Firefox and WebKit have signalled the same intent** (WHATWG removal, stage 3).

What this means for the plan:

- **You cannot rely on the browser's built-in XSLT.** Any client-side transform must ship its own engine.
- **Recommended engine: `xslt-polyfill`** (mfreed7's WASM build of libxslt+libxml2 — the *same C engine* PHP uses server-side today). BSD-3, zero-dep, drop-in `XSLTProcessor` (`importStylesheet` + `transformToFragment`), ~2.78 MB, actively maintained. Chrome's own migration guide points here. It's the lowest-risk choice because our 17 stylesheets are all XSLT **1.0** with `xsl:include` and `disable-output-escaping` and no EXSLT/`document()`/`key()` — exactly libxslt's sweet spot, and it reproduces `disable-output-escaping` faithfully (SaxonJS and the pure-JS engines do not).
  - *Rejected:* **SaxonJS** (XSLT 3.0-oriented, needs ahead-of-time SEF compilation, EE licensing/cost, only approximates 1.0) — shelve unless the presentation layer is ever rewritten in XSLT 3.0. **Pure-JS engines** (DesignLiquido/@tradik `xslt-processor`) — lighter, no WASM, but namespace gaps and unreliable `disable-output-escaping` risk breaking real stylesheets. **fontoxpath** — an XPath/XQuery engine, not an XSLT processor; doesn't apply.
- **The honest reframe:** "pure client-side rendering" isn't viable. The only sound architecture is a **hybrid** — a *permanent* server-side XSLTProcessor render (the canonical output for crawlers, no-JS clients, and any client-transform failure) with client-side rendering layered on as a **progressive enhancement**. The server render can never be dropped "to simplify," or SEO and accessibility collapse.

This reshapes Part 2 (P4–P5) below, and raises a genuine open question: **is moving rendering to the browser even worth a ~2.8 MB WASM payload bolted on top of a server render you have to keep anyway?** (See Open Decisions.)

---

## Part 1 — Finish the RDF-native transition

### P0 — Generalise the dual-write ✅ done (0.3.3-alpha)
**Goal:** make the graph a *complete* mirror of all content by centralising the SPARQL write-through inside the generic CRUD methods, so coverage is complete by construction — not wired in per-mod.

- **Done:** the SPARQL mirror lives **inside** `lunaModel::insert`/`update`/`delete`/`link`/`unlink` via two generic methods — `rdf_sync_node($nid)` (re-projects a node's whole description, matching the R2RML mapping) and `rdf_delete_node($nid)` (drops every triple mentioning the resource, as subject *and* object). The per-mod `rdf_put_article` hook in `mod_edit_texts` was replaced by `rdf_sync_node`.
- **Done:** each op maps to the Decision-2 vocabulary against `<base/id/{lid}>`: `page`→`schema:WebPage`, `text`→`schema:Article`, `user`→`foaf:Person`, level/group/mod→`luna:`; edges resolve nids→slugs into `schema:isPartOf`/`hasPart` and `luna:level`; numeric columns are typed `xsd:integer` to match the materialisation.
- **Done:** `rdf_resync_all()` re-projects every node from MySQL — the pure-PHP bootstrap/repair of the store, replacing the Ontop "materialise" step. Used to close a real drift gap (a page created before the dual-write existed) and verified by count parity (14 pages / 2 texts / 3 levels / 2 users) and a write→read round-trip.
- **Deferred (not blocking):** `insert_action`→`prov:Activity` projection and migrating `load_mods` to SPARQL — mods and the audit trail still read/write SQL only.

**Risks (carried):** best-effort `sparql_update` still masks coverage gaps — `rdf_resync_all()` is the reconciliation tool; concurrent saves with unguarded `DELETE/INSERT WHERE` can still interleave (addressed properly in P2 with optimistic concurrency).

### P1 — Default reads to the graph; unify on Oxigraph ✅ done (0.3.3-alpha)
**Goal:** make the SPARQL read path the default and point reads at the **same** store that receives writes.

- **Done:** `SPARQL_ENDPOINT` now defaults to **Oxigraph** — reached through the authenticating `sparql-proxy` (`http://sparql-proxy:7878/query`; since 0.8.21 Oxigraph is internal-only and fronted by the proxy, see security.md); Ontop becomes the opt-in override for reading live over MySQL.
- **Done:** the model constructor (routing) and the `luna.php` text load read from SPARQL by default, gated by `lunaModel::sparql_reads()` (constant `SPARQL_READS`, default on; `?sparql=0` forces SQL for one request). Both keep an **automatic SQL fallback** when the SPARQL path is off or returns nothing — a routing safety net.
- **Verified:** an Oxigraph-only sentinel rendered by default and vanished under `?sparql=0` (proving the source); routing (`/`, `/about`, `/login`), guest ACL (`/admin`→404), and admin multi-level ACL all resolve from the graph.
- **Deliberate deviation:** the SQL page/text readers were **kept** (as the fallback), not deleted — MySQL is still the system of record until P2. (Gotcha found & documented: `sanitize_inputs()` turns `'0'`→`false`, so the opt-out is read straight from `$_GET` with a `(bool)` cast, not via `lunaTools::request()`.)

**Risks (carried):** a *partial* mirror gap (some-but-not-all triples) is **not** caught by the empty-result fallback — run `rdf_resync_all()` after out-of-band changes; the dual-write is still best-effort, so reads can serve a stale mirror until the next successful sync (eliminated only by P2).

### P2 — Retire the MySQL content write; triplestore = single source of truth ⬅ next (decision #1 resolved)
**Goal:** stop writing content to MySQL, mint identity from slugs, and harden the now-single write.

- **Done (the URI-policy piece):** decision #1 is **"forbid slug edits"** — `lunaModel::update()` now refuses any change to a node's `lid`, so `<base/id/{lid}>` is frozen by construction (rename = create-new + delete-old). This holds for every node type, since all share the `/id/{lid}` identity scheme.
- **First, harden the write:** promote the Oxigraph `UPDATE` from best-effort to **must-succeed** (fail the save on mirror failure) and add a relational **outbox** table for at-least-once replay/reconciliation — there is *no 2-phase commit* across MySQL and an HTTP SPARQL endpoint.
- Add **optimistic concurrency** (version/etag in the `WHERE`) to replace the row locking MySQL gave for free.
- **Atomic cutover:** freeze writes → final MySQL→graph materialisation → switch off SQL writes (replay any in-window edits from the outbox).
- Mint identity from the slug: `<base/id/{lid}>` replaces `luna_nodes_seq`; `lid_is_taken` becomes an `ASK` over the graph; keep `nid` only as a graph-side `schema:identifier` while the XSLT `/node/{nid}` index still needs it.
- Re-express lost relational invariants (unique lid, required level/type, single parent) as `ASK` pre-checks (full SHACL comes in P3).

**Risks:** **slug-as-identity makes renames a URI change** — violates "freeze the URIs" (see Open Decisions); no 2PC means a crash mid-dual-write diverges the stores (outbox is mandatory); without ASK/SHACL the graph will accept duplicate slugs / dangling parents / untyped nodes.

### P3 — Semantics: named graphs, inference, SHACL
**Goal:** unlock what a triplestore is *for* — drafts/versions, entailment, validation. (Oxigraph ships none of these natively in 2025–26, so they're done by materialisation / external tooling.)

- **Named graphs** (native in Oxigraph) for drafts/versions: write drafts to `<base/graph/draft/{slug}>`, promote on publish; pairs with the PROV-O audit trail.
- **Forward-materialise** RDFS/OWL entailments on write (inverse `hasPart`/`isPartOf`, the `luna_types` taxonomy as `rdfs:subClassOf`) — re-derive on every write or the closure goes stale.
- **SHACL validation before accepting an UPDATE** via an external validator (pySHACL / a Jena step) — encodes the invariants retired in P2.
- If native reasoning/SHACL becomes a hard requirement, swapping Oxigraph for **Apache Jena Fuseki** or **GraphDB** is an *endpoint swap*, not a rewrite (the app only speaks SPARQL).

**Risks:** materialised inference/SHACL are bolt-ons with real operational cost and new failure modes (validation latency, stale closures if a write path forgets to re-materialise).

---

## Part 2 — Data-first server & client-side rendering

### P4 — Data-first server (no rendering moves yet)
**Goal:** turn the server into a pure, content-negotiated **data** surface emitting the same RDF/XML `lunaModel::transform()` already builds — so HTML becomes one representation among JSON-LD/Turtle/N-Triples/SPARQL. Nothing visible changes for users.

- Add real **HTTP `Accept` content negotiation** in `set_output_format` (today it only keys on path/`?output=`); expose canonical `/id/{slug}` (identity) and `/data/{slug}` (RDF/XML) URIs, keeping `?output=` as debug aliases.
- Emit the page's RDF/XML graph (byte-identical to `?output=xml`) as the negotiated representation; ultimately back it with a SPARQL `CONSTRUCT`/`DESCRIBE` so PHP shrinks to *negotiate + construct + serialise*.
- Serve `luna/luna.xsl/` as **static, long-cached, same-origin** assets under a stable `/xsl/`; have the server tell the client **which stylesheet won the 7-level cascade** rather than re-deriving it.
- Split the Cache_Lite key into a per-request XML/CONSTRUCT key (invalidated on edits) + the static XSL.
- **Keep the server-side XSLTProcessor render fully working and canonical throughout.**

**Risks:** cache-keying the XML wrong serves stale graphs; `xsl:include` resolution differs server (filesystem) vs. browser (URL) — the static `/xsl/` tree needs correct relative URLs and CORS care *now*; errors/404/redirect/auth must be expressible as a `luna:message` RDF graph a stylesheet can render.

### P5 — Client-side XSL rendering (WASM libxslt) — ❌ dropped
**Decision #7, June 2026: not doing this.** With native browser XSLT being removed, the only path was a ~2.8 MB WASM polyfill bolted on top of a server render that must stay anyway — not worth the payload and complexity for this project. The plan is preserved below as a record of the analysis, should the trade-off ever change.

**Original goal:** move the transform into the browser via the `xslt-polyfill` WASM engine, **with the server render kept as the permanent canonical fallback** — so the site never regresses and SEO/accessibility hold.

- **Refactor first (server render unchanged):** extract the inline UI `<script>` blocks + bootstrap vars from `luna.header.html.xsl` into `js/` files and a JSON/`data-` config, so the loader has them before any XML arrives.
- Ship a tiny static **shell** (doctype, `<head>` with title/meta + the schema.org **JSON-LD we already emit** for crawlers, `<body id=app>` + loader); parallel-fetch the page RDF/XML (`/data/{slug}`) and the winning `.xsl`.
- Adopt **`xslt-polyfill`** behind a swappable engine interface (`importStylesheet` + `transformToFragment`), inject the fragment into `#app`, then run the UI JS (jQuery/luna.js) in a **post-injection** callback (it binds to XSL-produced DOM).
- **Lazy-load and cache** the ~2.78 MB WASM (service worker + immutable headers; preload/Early-Hints the XSL); serve `.xsl` same-origin so CORS-governed Fetch resolves `xsl:include` (pre-resolve/inline includes if a synchronous transform trips on async fetch).
- **Gate it** like `?sparql=1`/JSON-LD: opt-in cohort → golden-diff client vs. server output byte-for-byte → widen. Make client render the default for capable browsers; **server-side render stays mandatory** for bots/no-JS/failures. Explicit fallbacks for fetch failure, malformed XML, and transform exceptions → full server-rendered reload.
- *Bonus once stable:* SPA-style navigation (fetch next page's XML, re-transform, no full reload) — but admin POST/redirect forms must not be swallowed.

**Risks:** the ~2.78 MB WASM dwarfs today's zero-JS render (lazy-load + caching mandatory, mobile-sensitive); pure client render is invisible to crawlers/AT — **the server fallback is load-bearing**; inline scripts that bind to XSL-DOM break without post-injection callbacks; first paint can regress on slow devices; errors/redirects/HTTP status must still be honoured or the client shows a blank frame.

---

## Open decisions (yours to make)

| # | Decision | Blocks |
|---|---|---|
| 1 | ✅ **Resolved (June 2026): (a) forbid post-creation slug edits.** Enforced in `lunaModel::update()` — a rename is create-new + delete-old. (Option (b), mint-new-URI + `owl:sameAs` + 303, was rejected as more machinery than this project needs.) | — |
| 2 | **Dual-write durability.** Confirm flipping the Oxigraph `UPDATE` to must-succeed + a relational **outbox** for replay/reconciliation (no 2PC exists). | P2 |
| 3 | **Keep or drop MySQL/Ontop** after P2 — retire entirely, or keep as a read-only SQL projection behind Ontop? | P2/P3 |
| 4 | **Fate of `nid`** — drop the `/node/{nid}` identity once XSLT no longer needs it, or keep `nid` as a non-identifying `schema:identifier` indefinitely? | P2 |
| 5 | **Triplestore for P3 semantics** — stay on Oxigraph + external SHACL/inference, or swap to Jena Fuseki / GraphDB for native support (endpoint swap)? | P3 |
| 6 | ~~**Client-XSLT engine.**~~ Moot — P5 dropped. | ~~P5~~ |
| 7 | ✅ **Resolved (June 2026): P5 is not worth it.** The data-first server (P4) + JSON-LD is where rendering stops; the server-side XSLTProcessor render stays canonical. | — |
| 8 | **Draft/version model (P3)** — per-resource named graphs promoted on publish; PROV-O audit in the default graph or a dedicated audit graph? | P3 |

---

## Suggested sequencing

**P0 and P1 are done** (0.3.3-alpha), and **decision #1 is resolved + enforced** (immutable slugs, 0.3.4-alpha). The remaining spine is the **rest of P2** — actually retiring the MySQL *content write* so the triplestore is the single source of truth and dual-write drift disappears. That's a larger migration (it touches every admin mod's direct SQL reads/writes, and needs must-succeed writes + an outbox since there's no 2PC), worth doing deliberately. **P3** is optional polish. **P4** (data-first server) is low-risk, valuable on its own, and is now the **end of Part 2** — P5 (client-side XSLT) has been dropped. **Recommended next:** the P2 write-retirement migration when you want to commit to it; P4 whenever a clean RDF/data API is wanted.

> The browser-XSLT analysis in this doc was the basis for dropping P5; it was verified against Chrome's deprecation guidance, LWN, and WHATWG/Mozilla tracking bugs (mid-2026).
