# Roadmap

Where LunarSystem is headed, and in what order. Two big arcs:

1. **Finish the RDF-native transition** — make the triplestore the system of record for content and retire MySQL for it (phases **P0–P3**).
2. **Become a data-first server with client-side rendering** — emit data (RDF/XML), move the XSLT transform into the browser (phases **P4–P5**).

Current state: **v0.3.2-alpha** on `main`. The triplestore (Oxigraph) is a live, queryable mirror; reads can flow through SPARQL (`?sparql=1`); and content **writes have just begun** dual-writing into the graph via SPARQL `UPDATE` (text create/modify). Everything below builds on that.

> The cardinal rule across every phase: **freeze the URIs.** `/id/{slug}` is identity; it must not change, or external links and `owl:sameAs` break.

---

## ⚠️ Reality check on the client-side-XSL plan (read this first)

The stated Part-2 idea — *"PHP delivers XML, a JavaScript layer transforms it to HTML with XSL in the browser"* — runs into a hard, time-sensitive fact, confirmed by current vendor docs:

**Native browser XSLT is being removed, not just deprecated.** Chromium drops **both** the `<?xml-stylesheet?>` processing instruction **and** the JavaScript `XSLTProcessor` API on Stable in **Chrome 158 (~Nov 17 2026)** — deprecation began Chrome 143 (Dec 2025); Origin-Trial/Enterprise-Policy escape hatches end Chrome 176 (Aug 2027). The driver is memory-safety risk in the effectively-unmaintained `libxslt`/`libxml2` C code (incl. CVE-2025-7425) plus ~0.02% usage. **Firefox and WebKit have signalled the same intent** (WHATWG removal, stage 3).

What this means for the plan:

- **You cannot rely on the browser's built-in XSLT.** Any client-side transform must ship its own engine.
- **Recommended engine: `xslt-polyfill`** (mfreed7's WASM build of libxslt+libxml2 — the *same C engine* PHP uses server-side today). BSD-3, zero-dep, drop-in `XSLTProcessor` (`importStylesheet` + `transformToFragment`), ~2.78 MB, actively maintained. Chrome's own migration guide points here. It's the lowest-risk choice because our 17 stylesheets are all XSLT **1.0** with `xsl:include` and `disable-output-escaping` and no EXSLT/`document()`/`key()` — exactly libxslt's sweet spot, and it reproduces `disable-output-escaping` faithfully (SaxonJS and the pure-JS engines do not).
  - *Rejected:* **SaxonJS** (XSLT 3.0-oriented, needs ahead-of-time SEF compilation, EE licensing/cost, only approximates 1.0) — shelve unless the presentation layer is ever rewritten in XSLT 3.0. **Pure-JS engines** (DesignLiquido/@tradik `xslt-processor`) — lighter, no WASM, but namespace gaps and unreliable `disable-output-escaping` risk breaking real stylesheets. **fontoxpath** — an XPath/XQuery engine, not an XSLT processor; doesn't apply.
- **The honest reframe:** "pure client-side rendering" isn't viable. The only sound architecture is a **hybrid** — a *permanent* server-side XSLTProcessor render (the canonical output for crawlers, no-JS clients, and any client-transform failure) with client-side rendering layered on as a **progressive enhancement**. The server render can never be dropped "to simplify," or SEO and accessibility collapse.

This reshapes Part 2 (P4–P5) below, and raises a genuine open question: **is moving rendering to the browser even worth a ~2.8 MB WASM payload bolted on top of a server render you have to keep anyway?** (See Open Decisions.)

---

## Part 1 — Finish the RDF-native transition

### P0 — Generalise the dual-write (from where we are)
**Goal:** make the graph a *complete* mirror of all content by centralising the SPARQL write-through inside the generic CRUD methods, so coverage is complete by construction — not wired in per-mod.

- Move the SPARQL mirror **inside** `lunaModel::insert`/`update`/`delete`/`link`/`unlink`/`insert_action`, reusing the `sparql_update` + `sparql_literal` pattern — instead of the per-mod hooks in `mod_edit_texts`.
- Map each op to the Decision-2 vocabulary against `<base/id/{lid}>`: `insert`→typed resource (`page`→`schema:WebPage`, `text`→`schema:Article`, `user`→`foaf:Person`, level/group/mod→`luna:`); `update`→`DELETE`/`INSERT` changed props; `delete`→remove outbound **and** inbound triples; `link`/`unlink`→typed edges (`schema:isPartOf`+`hasPart`, `luna:level`, …) resolving nids→slugs via `get_lid`; `insert_action`→a `prov:Activity`.
- Migrate `load_mods` to SPARQL so no content read stays pinned to SQL.
- **Verify:** after admin edits, diff the Oxigraph graph against the MySQL-derived Ontop graph; resolve every divergence before moving on.

**Risks:** best-effort `sparql_update` masks coverage gaps (the Ontop-vs-Oxigraph diff is the only safety net); concurrent saves with unguarded `DELETE/INSERT WHERE` can interleave; `insert_action` volume may bloat the graph (decide default vs. a named audit graph).

### P1 — Default reads to the graph; unify on Oxigraph
**Goal:** make `?sparql=1` the default and point reads at the **same** store that receives writes.

- Point `SPARQL_ENDPOINT` at **Oxigraph** (not Ontop — Ontop is read-only over MySQL and would diverge from graph writes).
- Invert the constructor branch and `purge_index` to read from SPARQL by default, with a `?sql=1` escape hatch for instant flip-back.
- Golden-diff server-rendered HTML (SQL path vs. graph path) across a representative page set; then delete the SQL page/text readers.

**Risks:** if P0 is incomplete, reads show holes (P0 verification is the gate); `purge_index`/`load_mods` are easy-to-miss SQL holdouts; the Cache_Lite layer survives only if purge and constructor read the same default.

### P2 — Retire the MySQL content write; triplestore = single source of truth
**Goal:** stop writing content to MySQL, mint identity from slugs, and harden the now-single write.

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

### P5 — Client-side XSL rendering (WASM libxslt), as progressive enhancement
**Goal:** move the transform into the browser via the `xslt-polyfill` WASM engine, **with the server render kept as the permanent canonical fallback** — so the site never regresses and SEO/accessibility hold.

- **Refactor first (server render unchanged):** extract the inline UI `<script>` blocks + bootstrap vars from `luna.header.html.xsl` into `js/` files and a JSON/`data-` config, so the loader has them before any XML arrives.
- Ship a tiny static **shell** (doctype, `<head>` with title/meta + the schema.org **JSON-LD we already emit** for crawlers, `<body id=app>` + loader); parallel-fetch the page RDF/XML (`/data/{slug}`) and the winning `.xsl`.
- Adopt **`xslt-polyfill`** behind a swappable engine interface (`importStylesheet` + `transformToFragment`), inject the fragment into `#app`, then run the UI JS (jQuery/luna.js, CKEditor) in a **post-injection** callback (it binds to XSL-produced DOM).
- **Lazy-load and cache** the ~2.78 MB WASM (service worker + immutable headers; preload/Early-Hints the XSL); serve `.xsl` same-origin so CORS-governed Fetch resolves `xsl:include` (pre-resolve/inline includes if a synchronous transform trips on async fetch).
- **Gate it** like `?sparql=1`/JSON-LD: opt-in cohort → golden-diff client vs. server output byte-for-byte → widen. Make client render the default for capable browsers; **server-side render stays mandatory** for bots/no-JS/failures. Explicit fallbacks for fetch failure, malformed XML, and transform exceptions → full server-rendered reload.
- *Bonus once stable:* SPA-style navigation (fetch next page's XML, re-transform, no full reload) — but admin POST/redirect forms must not be swallowed.

**Risks:** the ~2.78 MB WASM dwarfs today's zero-JS render (lazy-load + caching mandatory, mobile-sensitive); pure client render is invisible to crawlers/AT — **the server fallback is load-bearing**; inline scripts that bind to XSL-DOM (CKEditor) break without post-injection callbacks; first paint can regress on slow devices; errors/redirects/HTTP status must still be honoured or the client shows a blank frame.

---

## Open decisions (yours to make)

| # | Decision | Blocks |
|---|---|---|
| 1 | **Rename / URI-identity policy.** Once `<base/id/{slug}>` *is* identity, can a slug ever be edited? (a) forbid post-creation slug edits, or (b) on rename mint the new URI, copy triples, emit `owl:sameAs` + 303 redirect old→new. | P2 (cardinal-rule critical) |
| 2 | **Dual-write durability.** Confirm flipping the Oxigraph `UPDATE` to must-succeed + a relational **outbox** for replay/reconciliation (no 2PC exists). | P2 |
| 3 | **Keep or drop MySQL/Ontop** after P2 — retire entirely, or keep as a read-only SQL projection behind Ontop? | P2/P3 |
| 4 | **Fate of `nid`** — drop the `/node/{nid}` identity once XSLT no longer needs it, or keep `nid` as a non-identifying `schema:identifier` indefinitely? | P2 |
| 5 | **Triplestore for P3 semantics** — stay on Oxigraph + external SHACL/inference, or swap to Jena Fuseki / GraphDB for native support (endpoint swap)? | P3 |
| 6 | **Client-XSLT engine** — commit to `xslt-polyfill` WASM (same engine as server) and accept the ~2.8 MB + service-worker cost, vs. lighter-but-riskier pure-JS engines. | P5 |
| 7 | **Is P5 even worth it?** Given native XSLT is gone and the only client path is a heavy WASM polyfill *on top of a server render that must stay anyway*, does client rendering (SPA nav, offload) justify the payload/complexity — or is the **data-first server (P4) + JSON-LD** the right place to stop? | P5 |
| 8 | **Draft/version model (P3)** — per-resource named graphs promoted on publish; PROV-O audit in the default graph or a dedicated audit graph? | P3 |

---

## Suggested sequencing

P0 → P1 → P2 are the spine of "RDF as far as possible" and should go in order (each gates the next). P3 is optional polish you can defer. P4 is low-risk and valuable on its own (the server becomes a clean data API) — and it's the natural **decision point for #7**: ship P4, see how the JSON-LD + RDF endpoints feel, and only commit to P5 if browser rendering earns its weight. **My recommendation:** do P0–P2, then P4, then sit with decision #7 before touching P5.

> Browser-XSLT facts in this doc were verified against Chrome's deprecation guidance, LWN, and WHATWG/Mozilla tracking bugs (mid-2026). They are time-sensitive — re-check before P5.
