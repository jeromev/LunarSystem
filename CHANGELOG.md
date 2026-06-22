# Changelog

## [0.8.39-alpha] - 2026-06-22
- **Legacy-model retirement, Phase 1 — vocab oddities.** Renamed the two embarrassing legacy terms in the *internal* in-memory model and the 15 XSLT stylesheets that read them: snake-case `luna:is_active` → `luna:isActive` (matching the triplestore), and the **non-existent** `owl:isChildOf` → the standard `schema:isPartOf` (added `schema:` to the model's namespace set). Only the RDF *predicates* were renamed — never the `is_active` DB column. Verified strictly output-neutral by the new render-diff harness (every page byte-identical after each rename) plus the regression + admin-lockout suites; `owl:isChildOf` and `luna:is_active` are now **gone from all RDF output** (xml/n3/json/jsonld), and the RDF/XML serialisation gained the proper `schema:` prefix (was the auto-generated `ns0:`). Harness refinements: normalise version strings + relative times; drop the externally-polluted journal log-viewer from the strict diff. (`/node/{nid}` identity → Phase 2; full schema.org typing → Phase 3.)

## [0.8.38-alpha] - 2026-06-22
- **Render-diff harness — Phase 0 of retiring the legacy in-memory model.** New [`test/render_diff.sh`](test/render_diff.sh) (`make render-capture` / `make render-check`) snapshots the normalised HTML + RDF render of 19 page types (public pages, all admin screens, the four `?output=` formats, guest + logged-in) and asserts byte-identical output across runs — normalising CSRF tokens, timings, relative dates, log ids and live online-session state. It's the safety net for the upcoming model migration: every step that changes the in-memory model or the XSLT must keep the render byte-identical (verified stable: 19/19 pages SAME across repeated runs). Baselines are gitignored (regenerate with `make render-capture`). Goal of the migration: the XSLT renders from the same clean schema.org/slug graph as the triplestore — one model, `/id/{slug}` identity, no `owl:isChildOf`/`luna:is_active`/`/node/{nid}`.

## [0.8.37-alpha] - 2026-06-22
- **RDF — `?output=xml/n3/json` now serve the clean schema.org projection.** They used to dump the raw in-memory model — `/node/{nid}` integer-identity URIs, `owl:isChildOf` (a non-existent OWL term) and snake-case `luna:is_active`, plus render scaffolding. New [`lunaModel::build_schema_index()`](luna/luna.classes/luna.model.class.php) projects the page + its text blocks into the **same** standards-based shape as `?output=jsonld` and the triplestore: `/id/{slug}` IRIs, `schema:WebPage`/`Article`, `schema:isPartOf`/`hasPart`, typed literals, and just the three custom terms (`luna:isActive`/`level`/`content`). `dump()` serialises that for the three RDF formats; the legacy in-memory model (which drives the XSLT navigation via `owl:isChildOf`) and the `mod_node` node-dump path are untouched. Verified: the legacy terms are gone from all three formats, the published predicate set is **parity-exact** with the triplestore, the HTML still renders, and the regression + admin-lockout suites pass. (The full internal-model rename stays a deliberate P2.) Updated the ontology + docs notes that described the old behaviour.

## [0.8.36-alpha] - 2026-06-22
- **GitHub Pages root landing.** The vocabulary already dereferenced at `jeromev.github.io/LunarSystem/ontology/`, but the bare Pages root 404'd. Added a static `index.html` landing (links to the ontology, repo, docs, changelog) plus `DirectoryIndex index.php` in `.htaccess` so the app keeps serving `index.php` for `/` — GitHub Pages serves the static landing, Apache never does. Verified the app still renders `/` correctly.

## [0.8.35-alpha] - 2026-06-22
- **Ontology review + GitHub Pages prep.** Reviewed the aged `luna:` vocabulary. Finding: the persisted/queryable ontology (triplestore + R2RML) is already modern and minimal — only **three** custom terms (`luna:isActive`, `luna:level`, `luna:content`); everything else is schema.org + FOAF, with slug IRIs and `schema:isPartOf`. What is dated is the legacy `?output=xml/n3/json` serialization, which mirrors the internal XSLT model and still emits the old conventions — `owl:isChildOf` (a non-existent OWL term), `/node/{nid}` integer-identity URIs, and snake-case `luna:is_active`. Those terms are load-bearing for the XSLT navigation (the menu is built from `luna:page[owl:isChildOf …]`), so aligning that path with the clean triplestore projection is a roadmap-scale change (part of P2), not a quick rename. Corrected the published `ontology.ttl` + `index.html` to document the three canonical terms accurately (the initial scaffold had over-listed internal/legacy terms); validated the Turtle parses (18 triples). Added `.nojekyll` so GitHub Pages serves the vocabulary as static files (no Jekyll build to fail).

## [0.8.34-alpha] - 2026-06-22
- **RDF — moved the `luna:` ontology namespace to GitHub Pages and centralised it.** With `lunarsystem.org` gone, the vocabulary namespace needed a new base. Chose **`https://jeromev.github.io/LunarSystem/ontology#`** (GitHub Pages) over the bare repo URL: a namespace IRI should be a stable identifier you can *serve a vocabulary from*, and the repo URL doesn't dereference to RDF and conflates "where the code lives" with "what the vocabulary is." Three URI roles are now kept distinct — the **vocabulary** namespace (project-level, this change), **resource/instance** IRIs (`/id/{slug}`, which already follow the deployment host via `luna::$site_uri`), and the project **homepage** (`github.com/jeromev/LunarSystem`, the `@link` tags). Centralised the namespace to a single source of truth, `lunaModel::LUNA_NS` — the `$lunaNameSpace` property and all three inline SPARQL `PREFIX luna:` reads now derive from it (was four scattered literals). Migrated all 21 sites that repeat it (the 16 XSLT `xmlns:luna` declarations, `semantic/ontop/mapping.ttl`, `examples/queries.sparql`, four docs) and re-projected the triplestore (`make resync-triplestore`). Verified: every output format (`xml`/`n3`/`json`/`jsonld`) carries the new IRI with zero stale refs, the HTML renders (XSLT still matches the model's RDF), and SPARQL vs SQL read parity is identical (17/17). Added a starter vocabulary under [`ontology/`](ontology/) (`ontology.ttl` + `index.html` + a README on enabling Pages so the IRI dereferences); documented the three-roles principle in `docs/linked-data.md`.

## [0.8.33-alpha] - 2026-06-22
- **Docs — cleaned to present-state; `CHANGELOG.md` is the canonical history.** A multi-agent audit inventoried historical archaeology and facts left stale by the night's work; the fixes were applied across 16 files. Removed the removal-narrative and version-pinned asides the docs had accumulated — e.g. "jQuery has been removed", the CKEditor / PEAR-MDB2 / Cache_Lite removal trails, and "as of 0.5.0-alpha" / "since 0.3.3-alpha" pins — so the docs now describe how things ARE, not how they changed. Corrected facts that went stale earlier tonight: `Cache_Lite` → `lunaCache`, `luna/luna.lib/` → `luna/luna.lib/arc/`, the moved locale path, the vendored-libs list, and a stale `v0.8.22` in the roadmap. THIRD-PARTY-NOTICES dropped paragraphs for assets that no longer exist (jQuery, jQuery TreeView, CKEditor) and now lists only what ships: the locally-patched in-tree ARC2 and the one Composer dependency, HTMLPurifier. **History-file decision:** no new history file — `CHANGELOG.md` is the chronological record and the docs point to it. The one doc that deliberately keeps a historical narrative is `docs/security.md`: its hardening tables are a security **audit trail** (what was fixed and how — genuinely useful for review), the justified case for keeping history; it now leads with current posture and its present-state sections were de-pinned.

## [0.8.32-alpha] - 2026-06-22
- **i18n — locale catalogs moved under `luna.domains/<domain>/locale/` (per-domain local data).** Localization is site-local data, so it now lives beside the other per-domain data (`ini/`, `cache/`) rather than in a shared engine directory. The gettext tree moved from `luna/luna.locale/` to `luna/luna.domains/luna.default/locale/`; `set_language()` now binds both the engine `luna` catalog and the git-ignored, site-specific `local` overrides to the **active** domain's `locale/` when it ships one, falling back to the default domain's catalogs (the `LOCALE_PATH` ini default) otherwise — so a new domain can localize itself by dropping in its own `locale/`, with no engine change. The `local` catalog (`local.po`/`local.mo`) stays git-ignored, now matched per-domain. Verified: `en_US` and `fr_FR` both still translate from the new path (the French login page renders “Mot de passe”/“Connexion”, English renders “Password”/“Login”), and the regression suite is green. Docs (README tree, configuration.md, the locale README) updated.

## [0.8.31-alpha] - 2026-06-22
- **Dependencies — evaluated moving ARC2 to Composer; kept it vendored, with corrected provenance.** The bundled-libs cleanup left one in-tree library, **semsol/arc2**; the obvious next step was to source it via Composer like HTMLPurifier. Tried exactly that (installed Packagist `semsol/arc2 3.1.0`, autoloaded it, dropped `luna.lib/`) and it **broke `?output=n3` (HTTP 500)**: the vendored copy is *not* pristine 3.1.0. It carries a local **PHP-8 patch** — stock 3.1.0's N-Triples serializer calls `preg_match()` on an array term and throws a `TypeError` on luna's index — plus a **UTF-8** escaping fix (the stock ISO-8859-1 round-trip mangles multibyte characters). Neither is upstream, and ARC2 is dormant (3.1.0 is the latest release), so the patches will not be upstreamed. **Conclusion: ARC2 is the textbook case *for* vendoring** — a dead dependency we must patch — the opposite of HTMLPurifier (maintained + unmodified, rightly on Composer). It stays in `luna/luna.lib/arc/`; the Composer move was reverted (no code change). The `VENDOR.txt` that wrongly claimed the copy was unmodified was corrected to document the two patches, and THIRD-PARTY-NOTICES / security.md now flag ARC2 as locally PHP-8/UTF-8 patched. The lesson: the principle is “Composer for maintained + unmodified deps, vendor for patched + dormant ones,” not “everything via Composer.”

## [0.8.30-alpha] - 2026-06-22
- **Security — admin-lockout guardrails.** The admin tier reaches the admin UI through a single chain — a user in `group_admin` -> which grants `level_admin` -> which gates the admin pages/modules — so one careless edit can lock **every** administrator out of a running site. This happened: the sole admin unticked their own `group_admin` membership in the user editor and lost all access (restored by re-inserting the membership edge + resyncing the triplestore). A multi-agent audit enumerated 20 candidate self-harm scenarios and adversarially verified each; **four were real, reachable lockouts**, now closed: (1) `mod_admin_users::submit_modify` refuses to drop the actor's own admin membership or the last **active** administrator; (2) `submit_delete` refuses to delete the last admin user; (3) `mod_admin_pages::submit_delete` and (4) `mod_admin_mods::submit_delete`/`submit_modify` gained the protected-lid guard their sibling handlers already had (the pages/mods that power the admin UI can no longer be deleted, relevelled or deactivated). As **defence-in-depth**, `lunaModel::delete()` now refuses to delete any node whose lid is in the central `luna::$protected_lids` set — extended from the admin pages to also cover `group_admin`/`group_default`, `level_admin`/`level_public` and the admin modules — so even a future handler that forgets the check cannot remove a structural node. New `lunaTools::active_admin_count()` backs the count-based 'never leave zero admins' invariant. Verified by a new [`test/admin_lockout.sh`](test/admin_lockout.sh) (`make test-lockout`, also folded into `make test`): it asserts all four vectors are blocked for the shipped single admin while ordinary user create/delete still works, and the regression + delegated-admin suites stay green.

## [0.8.29-alpha] - 2026-06-22
- **Dependencies — replace PEAR Cache_Lite with a native file cache; retire PEAR base (libs cleanup, phase 3).** The page/model cache backend is now a small native [`lunaCache`](luna/luna.classes/luna.cache.class.php) (a keyed file store under the cache dir with a `filemtime` TTL and atomic-rename writes) instead of the abandoned PEAR **Cache_Lite**. The app only ever used Cache_Lite as a dumb string store (`get`/`save`/`clean`, with its own `serialize`/`unserialize(..., ['allowed_classes'=>false])` guard around values), so `lunaCache` is a drop-in for that exact surface — including Cache_Lite's quirk that `save()` without an id reuses the last `get()`'s id. This removes the **last consumer of PEAR base**, so `PEAR.php`/`PEAR5.php` are deleted too — **ARC2 is now the only vendored in-tree library**. Also dropped the now-dead `magic_quotes_runtime` polyfill in `luna.php` (it existed solely for Cache_Lite; nothing else calls those PHP-8-removed functions). A native cache is the right call here — unlike the HTMLPurifier swap — because a file store is trivial and stable, not a security boundary that benefits from a maintained Composer dependency. Verified: `lunaCache` round-trips (miss / hit / last-id save / TTL expiry / clean); with `CACHE=1` a guest page load writes 4 cache entries and the warm load serves **byte-identical** HTML; the ARC2 `?output=xml` path still works without the polyfill; and the regression + delegated-admin suites pass. Docs (README, security.md, THIRD-PARTY-NOTICES) updated.

## [0.8.28-alpha] - 2026-06-22
- **Security — replace the legacy HTML_Safe sanitiser with HTMLPurifier (libs cleanup, phase 2; a real XSS-hardening upgrade).** `lunaTools::sanitize()` — which `sanitize_inputs()` runs over every scalar in `$_GET/$_POST/$_COOKIE/$_SESSION/$_REQUEST` on every request — now uses **HTMLPurifier 4.19** (via Composer) instead of the abandoned ~2005 PEAR **HTML_Safe** denylist parser. HTMLPurifier parses to a real DOM and emits only an explicit allowlist of safe tags/attributes/URI schemes, so `<script>`, SVG/MathML, `on*` event handlers and `javascript:` URIs are dropped **by construction** — the SVG-SMIL `<animate>` bypass HTML_Safe had to be hand-patched against (0.7.5/0.8.1) is closed for free, along with unknown future denylist-evasion vectors. A single HTMLPurifier instance is built once and cached (its serializer cache in a writable temp dir), so the blanket per-input sanitisation stays fast (warm requests ~13 ms; a multi-field login POST ~80 ms). This adds the project's **first Composer dependency** (`composer.json` + committed `vendor/`; HTMLPurifier has no runtime deps, so the app stays clone-and-run). Removed the vendored **HTML_Safe** and **XML_HTMLSax3** (its now-orphaned SAX parser); `PEAR.php`/`PEAR5.php` are kept — still Cache_Lite's lazy error-path fallback (→ phase 3). Verified: the real `sanitize()` strips script / SVG-SMIL / `on*` / `javascript:` while preserving formatting and safe links, the app boots, and the regression + delegated-admin suites pass. The stored-content/WYSIWYG XSS residual is upgraded from a hand-maintained denylist to an allowlist parser. Docs (README, security.md, THIRD-PARTY-NOTICES) updated.

## [0.8.27-alpha] - 2026-06-21
- **Dependencies — drop the vendored PEAR Log library + dead code (libs cleanup, phase 0–1).** Following the bundled-library audit: removed `luna/luna.lib/Log.php` (834 lines) entirely. The app only ever used PEAR Log's ~8 `PEAR_LOG_*` integer priority constants (the severity codes across ~120 call sites) plus `Log::priorityToString()` (two call sites in the journal) — never the Log factory/handlers (logging has gone straight to `luna_logs` via PDO since the MDB2 removal). Those constants are now defined inline in [`luna.classes/luna.log.class.php`](luna/luna.classes/luna.log.class.php) (the stable syslog levels 0–7), and a new `lunaLog::priorityToString()` (identical labels, passed through `_()` for i18n) replaces the PEAR call in [`mod_journal`](luna/luna.mods/luna.mod_journal.php). Also removed the **dead Turtle serializer branch** in `luna.model.class.php` (`turtle` is not in `$output_formats`). PEAR base (`PEAR.php`/`PEAR5.php`) is **kept for now** — it is the lazy error-path fallback that Cache_Lite and XML_HTMLSax3 `include` on failure, so it goes away with them (phases 2–3), not here. Verified: pages render with no undefined-constant errors, `priorityToString` returns the exact legacy labels (emergency…debug / unknown), the journal shows priority labels, and the regression + delegated-admin suites pass. Docs (README, security.md, THIRD-PARTY-NOTICES) updated.

## [0.8.26-alpha] - 2026-06-21
- **Security — audited the hand-rolled SPARQL string assembly; no injection path found.** Closes the one remaining "audit before trusting" caution in [docs/security.md](docs/security.md). The write-through (`rdf_sync_node`/`rdf_delete_node`/`rdf_resync_all`) and the read builders (`load_nodes_sparql`/`load_texts_sparql`) assemble SPARQL by interpolation, but every user-controlled value flows through `rdf_str()` (= `sparql_literal()`, escaping `\ " \n \r \t` with backslash first — the characters that break a double-quoted literal — wrapped in quotes), `rdf_uri()` (`rawurlencode`d lid, so no IRIREF-breaking character can escape `<…>`), or `rdf_int()` (`intval`), around an otherwise static query. Verified both by static review of every call site and by **live break-out probes** against the running triplestore using the actual escapers: a value engineered to close the literal and smuggle a second `INSERT DATA`, and a lid engineered to break the IRI, were each stored as inert data — the injected `<urn:INJECTED>` / `<urn:INJECTED2>` triples never appeared (`ASK → false`), while the payload itself round-tripped verbatim. No code change required; the security note is updated from "treat as injection surface / audit before trusting" to **audited injection-safe**.

## [0.8.25-alpha] - 2026-06-21
- **Layout/typography — regularise the responsive type scales and land the box-handle baseline fix.** All seven per-breakpoint font-size scales in `scss/_scales.scss` now follow the **same progression as `$m`** (14/16/18/21/25/32/42): each is `round(base × $m / 14)` anchored on its existing `note` size (12→18, +1 per breakpoint), so every row increases and every size grows monotonically `xs→ul` — replacing the previous ad-hoc, non-monotonic values. The `.box-handle` collapse/expand triangle is now a **background image** instead of a `::before` glyph (committed earlier in source): the baseline grid (`bg.set`/`bg.scale`) reserves both `::before` and `::after` on every heading for its empty-content grid struts, so the glyph in `::before` had broken the snap — the compiled CSS catches up here and headings sit on the grid again. Also in this pass: the header language/format switchers use `display: flex; align-items: baseline` (`_page.scss`); self-closing tags normalised in the XSLT/ARC2 templates (`<… />` → `<…/>`); and the baseline-grid **debug overlay is left ON** (`$debug: 1` in `_bg.scss`) for ongoing layout work — set it back to `0` before any real deploy. Lagging current-version badges (why-rdf / security / linked-data) realigned to the release.

## [0.8.24-alpha] - 2026-06-21
- **Triplestore — make `rdf_resync_all()` runnable as `make resync-triplestore`, and realign the example census to that canonical rebuild.** Several docs (linked-data / why-rdf / roadmap) referenced `lunaModel::rdf_resync_all()` as the way to "rebuild the store from MySQL", but it had **no caller anywhere** and only *upserted* (it left graph-only orphans behind). Now: a new [`bin/resync-triplestore.php`](bin/resync-triplestore.php) CLI entry does a minimal non-web bootstrap and calls the model; a new `make resync-triplestore` target wraps `docker-compose exec -T app php bin/resync-triplestore.php`. The rebuild is a **true reset**: `rdf_resync_all($prune = true)` first clears the whole graph via a new **`rdf_clear()`** (`DELETE WHERE { ?s ?p ?o }`), then re-projects every node — so orphans are dropped, not just upserted (the default `$prune = false` keeps the old non-destructive reconcile behaviour). The model constructor gained a one-line **`LUNA_MAINTENANCE`** guard so a CLI task can obtain a bare model without the session-bound web read-model build. Verified: clear + re-project of all 35 seed nodes, app still renders from the rebuilt store (`/`, `/about`, `/login` → 200), `WebPage` parity with MySQL (14), and `make test` green. **Census realignment:** this pure-PHP projection is *fuller* than the Ontop materialise — it types **every** text `schema:Article` (not just the page-linked one) and projects the level/group/mod nodes too — so the default Oxigraph store it builds holds ~152 triples and shows **WebPage 14 / Article 2 / Person 2**. The illustrative counts in [`examples/queries.sparql`](examples/queries.sparql) and [`docs/try-it.md`](docs/try-it.md) are set back to those rebuild numbers (where they were before 0.8.23 aligned them to the thinner materialise), with the materialise's 104 / Article 1 noted as the Ontop-direct variant; [`docs/linked-data.md`](docs/linked-data.md)'s materialise step keeps its 104-triple count. try-it.md §2 now seeds the graph (`make resync-triplestore`) before the census, since the SQL-loaded seed leaves Oxigraph empty.

## [0.8.23-alpha] - 2026-06-21
- **Test — stop the delegated-admin test from orphaning triples in Oxigraph; correct stale example-census counts.** [`test/delegated_admin.sh`](test/delegated_admin.sh) creates its throwaway user through the admin UI (`POST /admin/admin_users` `mode=add`), which mirrors the new node into Oxigraph via the model's write-through — but `teardown()` deleted that user with raw `DELETE FROM luna_nodes/luna_users/luna_nodes_map` SQL, **bypassing the model**, so the node's mirrored triples (`a foaf:Person`, …) were never removed and accrued across every `make test` run (observed: the store drifting to 3 `foaf:Person` URIs vs MySQL's 2). `teardown()` now also evicts the user's triples from the triplestore through the authenticating `sparql-proxy` — `DELETE WHERE { <…/id/delegated%40test.local> ?p ?o } ; DELETE WHERE { ?s ?p <…> }`, mirroring `lunaModel::rdf_delete_node()` — via a new best-effort `rdf_purge()` helper (plus an `APP`/`APP_CONTAINER` var, now also used for the existing cache-clear). Verified: repeated runs leave the store **bounded** (no `delegated@test.local` triples remain; `foaf:Person` back to 2). Separately, the illustrative SPARQL-census counts were stale — a fresh Ontop materialise of the seed emits **104 triples** with **WebPage 14 / Person 2 / Article 1** (only one of the two seed texts is page-linked, so only one projects as a `schema:Article`), not the documented “97 triples” / “Article 2”: corrected in [`examples/queries.sparql`](examples/queries.sparql), [`docs/try-it.md`](docs/try-it.md), and [`docs/linked-data.md`](docs/linked-data.md). Query 3’s “6 siblings” re-verified correct (unchanged). No PHP/runtime change.

## [0.8.22-alpha] - 2026-06-21
- **Docs — comprehensive consistency pass: align every doc/readme with 0.8.21 and fix accumulated staleness.** A multi-agent audit swept all 26 markdown docs (plus `examples/queries.sparql`) against the code. Brought up to date: the **authenticated SPARQL proxy** (`sparql-proxy`, HTTP basic auth, internal-only Oxigraph) and **`SPARQL_AUTH_USER`/`SPARQL_AUTH_PASS`** now appear across installation / configuration / architecture / linked-data / try-it / going-public; the **per-target admin authz** completion is reflected wherever the security posture is summarized — including flipping the stale ⬜-open CSRF / session-fixation / submit-handler-privesc rows in `docs/security.md`'s status table to ✅ (all were fixed in 0.7.x–0.8.20); project-structure trees gained `semantic/sparql-proxy/`. Removed-host-port references (Oxigraph `7879`, Ontop `8081`) and direct `oxigraph:7878` query examples were rewritten to go through the proxy with credentials, including `examples/queries.sparql`. Also fixed pre-existing inaccuracies the audit surfaced: stale **PHP 5.3–5.6** runtime claims (PHP 8.3 since 0.5.0) in CONTRIBUTING and the issue/PR templates; a `varchar(32)` “unsalted MD5” `password` column (it is `varchar(255)` bcrypt); “serialised” / PEAR-Log log payloads (now JSON via a direct PDO INSERT); an “enable `session.use_trans_sid`” line (sessions are cookie-only); and broken `../docs/` links in an issue template. No code change; version badges bumped to 0.8.22-alpha (the 0.6.9–0.8.21 hardening-pass ranges are unchanged).

## [0.8.21-alpha] - 2026-06-21
- **Security — authenticate the SPARQL write endpoint behind a reverse proxy (closes the last residual).** Oxigraph has no native auth and its `/update` + `/store` accept unauthenticated writes. A new `sparql-proxy` (Caddy) now fronts it and demands **HTTP basic auth on every request** — reads and writes alike — before forwarding; Oxigraph is moved onto an **internal-only** compose network whose only other member is the proxy, so the triplestore is unreachable except through the authenticated proxy (the app can no longer reach `oxigraph:7878` directly). The app presents credentials via a new `sparql_auth_header()` on both the read (`sparql_select`) and write (`sparql_update`) paths, from `SPARQL_AUTH_USER`/`SPARQL_AUTH_PASS` (`luna.php`, via env). The proxy's bcrypt hash is generated at container start from the plaintext password, so **no password hash is committed**; the plaintext is a demo default (`.env.example`; gitignored `.env` for overrides). Verified end-to-end: an unauthenticated read or write to the proxy returns **401**, the app's authenticated reads/writes return 200/204, the app cannot reach Oxigraph directly (network-isolated), content still renders from the triplestore, and the regression + delegated-admin suites are green. Builds on 0.8.17 (no host port). Docs (README, security.md, linked-data.md) and the Oxigraph load step updated.

## [0.8.20-alpha] - 2026-06-21
- **Security — close the last two admin per-target authz gaps + add a delegated-admin escalation test (finishes B1).** Two submit handlers still skipped the per-target check: `admin_levels` *add* (the group-admission loop, which force-admits `group_admin`) now refuses — via `user_can_access_group` — to admit a group whose levels the actor does not hold; `admin_mods` *delete* now refuses — via `user_can_access_level` on the module's bound level (`get_level_node`) — to delete a module above the actor's access. All five admin modules now enforce per-target authz on every mutating path. New [`test/delegated_admin.sh`](test/delegated_admin.sh) (wired into `make test` / `make test-authz`) proves the deny path end-to-end: it manufactures a `level_edition`-only admin (re-binds the `admin` → `admin_groups` page + mod down to edition, creates a user in `group_edition`), then — as that user — attempts to grant `level_admin` to a group and asserts the guard **denies** it and writes no link, before tearing the scenario down. No-op for the shipped single-admin tier (regression green). Docs refreshed: the per-target gap is recorded as **resolved** in [docs/security.md](docs/security.md), and the stale “no per-target admin authz” / “CSP still allows `'unsafe-inline'`” / removed-host-port notes in the README are corrected.

## [0.8.19-alpha] - 2026-06-20
- **Data outputs carry only the content graph.** The machine-readable outputs (`?output=xml`/`json`/`n3`/`jsonld`) are now emitted **before** the HTML-only chrome is merged into the model, so they contain just the RDF content (pages, texts, access levels) — no session/site chrome. Gone from the data views: the site config + sort cookies + admin email (`<luna:data>`), the i18n labels (`<luna:vocabulary>`), the request params (`<luna:request>`), the current user (`<foaf:Person>`), and the language/format switchers. This supersedes the 0.8.18 csrf-token-only exclusion (reverted as redundant). HTML pages are unchanged (forms, nav, vocabulary intact); verified all four data formats are valid and chrome-free, the browser still renders the XML tree, and the regression suite is green.

## [0.8.18-alpha] - 2026-06-20
- **Fix — `?output=xml` rendered as a raw data dump (A3 regression) + CSRF-token leak in the data outputs.** The strict CSP from 0.8.16 (`style-src 'self'`) blocked Chrome's built-in XML/JSON viewer inline stylesheet, so the RDF/XML data outputs rendered as unstyled run-on text (all element text concatenated, including a query-looking `journal_sort {…}`) before the document tree. The non-HTML data responses (xml/json/n3/jsonld, emitted by `lunaModel::dump()`) now send a relaxed, data-appropriate CSP — `default-src 'none'; style-src 'unsafe-inline'; img-src data:` — so the browser viewer renders the tree, while **HTML pages keep the strict `script-src 'self'; style-src 'self'`**. Separately, those data outputs were serializing the whole `self::$data`, including the session **CSRF token** — now excluded from the machine-readable outputs (HTML forms still carry it). Verified in headless Chrome: zero CSP violations, the tree renders, and `csrf_token` no longer appears in `?output=xml`.

## [0.8.17-alpha] - 2026-06-20
- **Security — gate the unauthenticated SPARQL endpoint (B2).** Oxigraph and Ontop no longer publish a host port; they are reachable only on the internal compose network (by `app`), never from the host or a browser. This closes the **CSRF-to-localhost write vector** against Oxigraph's unauthenticated `/update` and `/store` (a malicious page could otherwise POST a SPARQL UPDATE to `localhost:7879` and corrupt the triplestore). The app is unaffected — it already used the internal `oxigraph:7878` / `ontop:8080` service names. Verified: the host ports are now refused while the app still reads from the triplestore (`ASK{}` → true, content renders) and the regression suite is green. Manual dev queries now go through the app container (`docker-compose exec app curl http://oxigraph:7878/query …`); docs updated.

## [0.8.16-alpha] - 2026-06-20
- **Security — drop CSP `'unsafe-inline'` (A3).** The admin UI's inline `onclick`/`onchange` handlers were externalized to **data-attributes** driven by event delegation in [js/luna.js](js/luna.js) — `[data-href]` navigable rows, `select[data-navigate]`, `select[data-submit-on-change]`, and `[data-confirm]` confirmations (16 handler sites across 9 XSL templates; the dead `confirmSubmit()` helper removed). With no inline handlers and no inline styles anywhere, the CSP tightens to **`script-src 'self'; style-src 'self'`** (no `'unsafe-inline'`). Verified with headless Chrome: **zero CSP violations** across all pages, row-click navigation / pager selects / delete confirmations all work, and the regression suite is green.

## [0.8.15-alpha] - 2026-06-20
- **Security — per-target authz in the remaining admin modules (finishes B1).** Extended the B1 pattern to the object-IDOR vectors: `admin_pages` (add/modify/delete), `admin_levels` (modify/delete) and `admin_mods` (add/modify) now refuse, via `lunaTools::user_can_access_level`, to bind a page/mod to — or modify/delete an object at — an access level the actor does not hold. Verified no-ops for the shipped admin (a page modify still succeeds; regression suite green). Latent in the single-admin config like the rest of the cluster.

## [0.8.14-alpha] - 2026-06-20
- **Security / UX — JSON log payloads + fix blank journal messages (B3).** `lunaLog::log()` now stores payloads as **JSON** instead of `serialize()`, so the journal read path never `unserialize()`s stored data — the object-injection sink is gone for all new logs. It also persists only a trimmed identity (`session_summary()` — firstname/lastname/email) instead of the whole session object (csrf token, levels, internals). The reader gained `decode_message()`: JSON first, with a guarded `unserialize(allowed_classes => ['lunaException','stdClass'])` fallback for pre-0.8.14 rows (transitional). This also **fixes the blank-message bug**: the earlier `allowed_classes => ['lunaException']` guard turned string-log `stdClass` payloads into `__PHP_Incomplete_Class`, so `->message` read blank — the journal list and detail now render the real messages (verified: 20 non-empty entries where there were 0, new JSON and legacy rows alike).

## [0.8.13-alpha] - 2026-06-20
- **Security — per-target authorization in the admin modules (B1).** `admin_users` and `admin_groups` linked arbitrary groups/levels to a user/group with no check the actor could access them — a privilege-escalation-via-assignment gap if admin is ever delegated to a lower tier (the two CRITICAL findings from the review's IDOR cluster; latent in the shipped single-admin config, now closed):
  - `admin_groups` (add + modify) denies granting any level the actor does not hold (`lunaTools::user_can_access_level`).
  - `admin_users` (add + modify) denies assigning any group that grants a level the actor does not hold, via a new `lunaTools::user_can_access_group()` helper (true iff the actor holds every level the group grants).
  - Verified no-ops for the shipped admin (holds all three levels): regression suite green, and an admin add-user-with-group still succeeds. `admin_pages`/`admin_levels`/`admin_mods` follow the same pattern at lower severity (object IDOR rather than privesc) — noted as remaining.

## [0.8.12-alpha] - 2026-06-20
- **CI — make the regression job deterministic.** The new `regression` job now boots only `app`+`db` with `SPARQL_READS=0` (SQL read path) instead of the full stack, so it doesn't depend on a populated Oxigraph triplestore. Verified the SQL path renders identically via the per-request `?sparql=0` toggle.

## [0.8.11-alpha] - 2026-06-20
- **Tooling — smoke + security-regression suite (C1).** Added `test/regression.sh` (run via `make test`) that exercises the 2026 hardening against a running stack: public-page smoke, case-insensitive source/secret denials (`.git`, docker files, `semantic/`, `db.ini`), security headers (CSP / X-Frame-Options / nosniff / no `X-Powered-By`), authentication (valid login, wrong-password rejection, **tokenless-login CSRF-gate rejection**), and the `load_texts` SQLi clamp (timed stacked `SLEEP`). Wired into CI as a new `regression` job that boots the stack and runs the suite. Also fixed the CI `php-lint` job to use `php:8.3-cli` (was `php:5.6-cli`, which fails on the codebase's PHP 7+ syntax).

## [0.8.10-alpha] - 2026-06-20
- **Accessibility pass (A2).** Audited every page with axe-core (WCAG 2 A/AA + best-practice) driving headless Chrome — the tree is now clean. Fixes:
  - Pager `<select>`s (page / per-page) get `aria-label`s (was a *critical* "select must have an accessible name").
  - In-text links are underlined (`p` / `dd` / `#Bottom`) so they're distinguishable without relying on colour.
  - Journal log-entry links get an `aria-label` (the message preview can render blank, which left an empty link with no name).
  - Colour contrast brought to AA: table `<th>` darkened (white text was ~1.3:1 on the `#e2e2e2` page), the pager strip text (`#999 → #555`), the brand link colour (`#cc007a → #be006f`, imperceptible) so links pass on striped rows, and inactive/deleted-row text (`#999 → #6a6a6a`).

## [0.8.9-alpha] - 2026-06-20
- **Layout — responsive pass across all pages (A1).** Verified every page type at 390px (mobile) and 1280px (desktop) with headless Chrome:
  - **Turned off the baseline-grid debug overlay** (`_bg.scss` `$debug: 1 → 0`) — it was shipping a visible grid overlay baked into `css/luna.css`.
  - **Fixed horizontal overflow on every authenticated page.** Wide admin data tables widened the whole grid on phones (admin reached 785px in a 390px viewport). Root-cause chain: grid items (`#Top`/`#Content`) defaulted to `min-width:auto` and fieldsets to `min-inline-size:min-content`, so a wide table's min-content propagated up to the `minmax(auto,1fr)` track. Now grid items + fieldsets use `min-width:0` and `.boxtable` is `overflow-x:auto`, so wide tables scroll inside their box instead of widening the page — all pages now fit 390px.
  - **Capped form fields/columns** (`input/textarea/select/.col/span.label { max-width:100%; box-sizing:border-box }`) so the fixed `bg.h()` widths never overflow; made `img` responsive.
  - **Footer toolbar fits phones** — the format/language switches were clipping the `jsonld` button under 480px; tightened gaps/padding.
  - Wrapped the `admin_groups` users table in `.boxtable` for consistency with the other admin tables.

## [0.8.8-alpha] - 2026-06-20
- **Removed the dead `en_EN` gettext locale.** The app standardised on `en-US` everywhere in 0.7.x — `lunaTools::format_language()` maps `en`→`US`, so `site_langs` (built from the DB `langs='en, fr'` config) resolves to `['en-US','fr-FR']`, and both the nav language switcher ([luna.php:560](luna/luna.php)) and `set_language()` only ever use those. Nothing produced, offered, or loaded `en_EN`/`en-EN`; 0.8.7 only marked it legacy in the docs. Now actually deleted:
  - `git rm -r luna/luna.locale/en_EN` (the whole `LC_MESSAGES/{luna,local}.{po,mo}` set; the git-ignored `local.*` files were removed from disk too).
  - Dropped the now-orphan `msgid "en-EN"` (→ `"English"`) from the remaining `en_US` and `fr_FR` `luna.po` catalogs — no source string ever looked it up (`_($lang)` is only called with full `en-US`/`fr-FR` tags) — and recompiled both `luna.mo`.
  - `Dockerfile` — removed the `localedef … en_EN.UTF-8` alias (and its comment) that existed only to satisfy the old `en`→`en_EN` mapping; the image now generates just `en_US.UTF-8` + `fr_FR.UTF-8`.
  - `README.md` — the locale tree line drops the "`en_EN` is a legacy locale" note.
  - Verified live on the Docker stack: the switcher still offers only English (`?lang=en-US`) and Français (`?lang=fr-FR`); `en-US` and `fr-FR` pages render with the correct `<html lang>` and gettext; a stale `?lang=en-EN` request still falls back cleanly (HTTP 200, no 404). No config or schema change.

## [0.8.7-alpha] - 2026-06-20
- **Docs — consistency pass (all 24 doc files + codebase audited against the 0.8.x state).** A multi-agent audit (8 areas; each finding verified against the code to separate genuine staleness from correct historical references) surfaced 11 real inconsistencies, now fixed:
  - `README.md` — the warning box and "Known issues" table no longer claim unsalted-MD5 / no-CSRF / `use_trans_sid=1` / missing `session_regenerate_id` (all fixed in 0.7.x); they now list the real residuals (unauthenticated SPARQL endpoint, no per-target admin authz, per-IP-only throttle). The directory tree drops the "jQuery from cdnjs" note and marks `en_EN` as a legacy locale.
  - `SECURITY.md` — reframed from "hardened in 0.2.14-alpha" to the 0.6.9–0.8.6 pass with residuals.
  - `CONTRIBUTING.md` + `docs/installation.md` — corrected PHP 5.6 / `php:5.6-apache` / `php:5.6-cli` → PHP 8.3 / `php:8.3-apache` / `php:8.3-cli` (matching the actual Dockerfile and lint).
  - `docs/going-public.md` — no longer lists CSRF / bcrypt / session-rotation as "deliberately not done" (they were done).
  - `docs/roadmap.md` + `docs/linked-data.md` — current-version bumped 0.5.0-alpha → 0.8.6.
  - `THIRD-PARTY-NOTICES.md` — records jQuery as removed (0.6.8-alpha); the front-end is now dependency-free vanilla JS.
  - `scss/README.md` — the partials table matches the actual tree (drops deleted `_clearfix`/`_tinymce`, adds `_init`/`_scales`/`_mixins`, de-jQuery's `_treeview`).

## [0.8.6-alpha] - 2026-06-20
- **Docs — second adversarial review results.** `docs/security.md` now records the post-0.8.4 multi-agent review (8 dimensions, two skeptics per finding): verdict **ship-with-low-risk**, the two real defects fixed in 0.8.5, and the documented residuals — chiefly that the admin modules perform no per-target authorization (safe as shipped because every admin page/mod is bound to the sole `level_admin` tier, but a latent privilege-escalation gap if admin is ever delegated to a lower level), plus the per-IP throttle's IP-rotation limitation and the unchanged UA-binding / `'unsafe-inline'` CSP.

## [0.8.5-alpha] - 2026-06-20
- **Security — second-review follow-ups (throttle race + journal deserialization).** A fresh 8-dimension adversarial review (each finding double-checked by an exploitability verifier and a refuter) graded the post-0.8.4 tree **ship-with-low-risk**. Two real defects fixed:
  - **Login-throttle TOCTOU (the one live finding).** The per-IP back-off read the attempt count, slept, *then* incremented — so N parallel requests from one IP read the same stale count and all slept the same short time, collapsing the escalation. The attempt is now counted **first** with an atomic `INSERT … ON DUPLICATE KEY UPDATE` (the MyISAM table lock serialises concurrent requests), and the sleep is based on the post-increment count (`attempts-1`, so a clean first login stays instant). Verified live: 5 parallel wrong-password logins reach counter=5 and take ~4.2s (escalating) instead of collapsing to ~0.2s; sequential attempts escalate 0→1→2s; the counter still resets on success.
  - **Unguarded `unserialize()` in the journal list path.** `mod_journal` line 169 (the list-all loop) used a bare `unserialize()` while the single-entry path was already guarded in 0.8.1; both now pass `['allowed_classes' => ['lunaException']]`. Latent (only `lunaLog` writes the column) but now consistent.

## [0.8.4-alpha] - 2026-06-20
- **Security — `use_strict_mode` is now actually enforced (review MEDIUM).** The session save-handler was registered via the legacy 6-callback `session_set_save_handler()` form, which cannot expose `validateId()` — so `session.use_strict_mode=1` was inert and `sessionRead()` would `INSERT` (adopt) any client-supplied session id. `lunaSession` now implements `SessionHandlerInterface` + `SessionUpdateTimestampHandlerInterface` and is registered as the object handler; the new `validateId()` accepts only a session id that already exists server-side, so PHP discards a forged id and mints a fresh server-side one. Verified live: a forged cookie `PHPSESSID=abcdef…` is rejected and reissued (no DB row created for it), while anon sessions persist across requests, login still regenerates the id, and logout still destroys the row.

## [0.8.3-alpha] - 2026-06-20
- **Security — logout is now a POST form, not a token-in-URL GET (review MEDIUM).** The nav rendered logout as `GET /logout?csrf_token=<token>`, leaking the per-session CSRF token into browser history and the server access log (and, on some setups, the Referer). The nav now renders a POST `<form>` with the token in a hidden field, and `logout()` requires `POST` + a matching `$_POST` token. Verified live: a forged `GET /logout?csrf_token=<valid>` no longer logs the user out (wrong method), a tokenless cross-site POST is rejected, and the legitimate POST form logs out cleanly; no `?csrf_token=` appears in any rendered URL.

## [0.8.2-alpha] - 2026-06-20
- **Security — login throttle is now per-IP, closing an account-enumeration channel (review HIGH).** The previous back-off slept only for *existing* accounts with prior failures, so an attacker could tell a real account (induces a delay) from an unknown one (no delay). The throttle is now keyed on the client IP in a new `luna_login_throttle` table: every failed login — unknown account, deactivated, or wrong password — counts against the IP and incurs the same escalating sleep (capped 5s, 15-minute window); a successful login clears the IP's counter. Verified live: a 4th failed login on an existing account (~3s) and a 5th on an unknown account (~4s) both sleep (escalation only, existence no longer observable); a correct password resets the counter to zero.

## [0.8.1-alpha] - 2026-06-20
- **Security — review follow-ups (timing, deserialization, sanitizer, authz, purge).** From the adversarial re-review: (1) the legacy-MD5 login branch now also runs a throwaway bcrypt `password_verify` so legacy accounts aren't ~3x faster than the unknown-user path (closes a timing-enumeration channel); (2) `lunaTools::user_can_act_on_text()` now fails **closed** — a text linked to a page with no resolvable level is denied (was fail-open), via a `total == accessible` page-count comparison; (3) HTML_Safe now protocol-filters `formaction`/`poster`/`ping`/`srcset`/`xlink:href` (a `javascript:` formaction/poster survived before); (4) the journal log-message `unserialize()` is guarded with `['allowed_classes' => ['lunaException']]`; (5) the request-driven `?purge` cache flush is removed (it ran pre-session so could never carry a CSRF token, and every mutating mod already purges internally). Verified live.

## [0.8.0-alpha] - 2026-06-20
- **Security — post-review release-blockers (two criticals + audit-log CSRF).** An adversarial re-review found three issues the first pass missed:
  - **Critical: a second blind/stacked SQLi** in `load_texts()` (the twin of the `load_users` fix) — `start`/`limit` reached the `LIMIT` clause raw and were exploitable by any logged-in editor (`limit=20;SELECT SLEEP(5)`). Now `intval`-clamped like `load_users` ([luna.model.class.php](luna/luna.classes/luna.model.class.php)).
  - **Critical: `.htaccess` deny rules were case-sensitive** while the filesystem is case-insensitive, so `/.GIT/HEAD`, `/DOCKERFILE`, `/DOCKER-COMPOSE.YML`, `/DOCS/…` served the full repo + creds. Added `[NC]` (case-insensitive) to every deny rule — uppercase variants now 403.
  - **High: `purgelogs` could wipe the entire audit log via a tokenless cross-site POST** (`mod_journal::load()` runs outside the central CSRF gate). It now requires POST + a valid CSRF token, and the journal page renders a token-bearing purge form. Verified: forged POST leaves the log intact; a tokened request purges.

## [0.7.9-alpha] - 2026-06-20
- **Security — logout is no longer a GET CSRF.** `logout()` now requires a valid CSRF token (read from GET or POST via `request()`), and the nav logout link carries the per-session token in its href. A forged `GET /logout` (e.g. `<img src=/logout>`) without the token no longer destroys the victim's session; the real nav logout still works. Verified live: forged GET leaves the user logged in (200), the tokened nav link logs out (404). Closes the logout residual from 0.7.4.

## [0.7.8-alpha] - 2026-06-20
- **Security — completed the edit_texts IDOR fix: content modify/delete is now gated on the text's pages.** Added `lunaTools::user_can_act_on_text()` (one query resolving the level of every page a text is linked to; the user must hold **all** of them, fail-closed) and call it at the top of `submit_modify` and `submit_delete`. A logged-in editor can no longer modify or delete a text that lives on a page above their level. Verified live with a real `group_edition` editor: **denied** on a text bound to an admin-level page (content left intact, delete refused), **allowed** on a public text (no over-restriction); admin unaffected. This closes the residual flagged in 0.7.6.

## [0.7.7-alpha] - 2026-06-20
- **Docs: recorded the 0.6.9–0.7.6 security hardening pass in [docs/security.md](docs/security.md)** — a status table of the nine areas closed (SQLi, disclosure, headers, session/cookie, passwords, session fixation, CSRF, stored XSS, IDOR) and the remaining partial items (content-level authz, logout GET, UA-bound guard).

## [0.7.6-alpha] - 2026-06-20
- **Security — per-page authorization in edit_texts (IDOR hardening).** Two fixes to `mod_edit_texts`: (1) `submit_modify` validated `add_text_pages` (empty on a modify) while actually linking `modify_text_pages`, so an editor could link a text to **any** page id with no check — it now validates the array it links. (2) Added `lunaTools::user_can_access_page()` (resolves the page's level via `get_level_node` and checks `user_can_access_level`, fail-closed) and applies it to every page a text is linked to in both `submit_add` and `submit_modify` — content can no longer be attached to a page above the user's level. Verified live: admin linking still works; a non-existent/forbidden page id is rejected. Residual (flagged): modify/delete of the *content* of a text already living on a higher-level page is not yet gated on the text's existing pages — a follow-up needing a non-admin editor account to test.

## [0.7.5-alpha] - 2026-06-20
- **Security — closed a stored-XSS bypass + hardened cache deserialization.** Stored content is sanitized through HTML_Safe on save (`sanitize_inputs()` runs `sanitize()` over all of `$_POST`), but the 2009-era blocklist let an SVG SMIL `<animate>` payload through. Added `svg`, `math`, `animate`, `animatetransform`, `animatemotion`, `set`, `foreignobject` to HTML_Safe's `deleteTagsContent` (tag **and** content removed) and the SMIL driver attributes (`attributename`, `from`, `to`, `values`, `begin`, `dur`, `by`) to its dangerous-attribute list. Verified live: an `<svg><animate …>` / `<svg onload>` / `<script>` / `onerror` / `javascript:` payload is fully stripped on save while `<p>`/`<b>`/`<img>` survive. Also passed `['allowed_classes' => false]` to the four server-side cache `unserialize()` sinks (defense-in-depth against PHP object injection).

## [0.7.4-alpha] - 2026-06-20
- **Security — CSRF protection (synchronizer token) + POST-only state changes.** Closes a *confirmed full-takeover* hole: a forged cross-site POST could mint a new administrator account. Every session now carries a 256-bit CSRF token (`luna_sessions.csrf_token`, minted on first read in `sessionRead`, rotated on login), exposed to the templates via `luna::$data['csrf_token']` and emitted as a hidden field in every state-changing form by a shared `csrf-input` XSL template (injected into all 16 admin / login / edit / search forms — as the last form child, after the `xsl:attribute` action). The bootstrap dispatch now rejects any `submit`/`batch_submit` request that isn't a `POST` carrying a token matching the session (`hash_equals`), logs the failure, and skips every `submit_*` handler. `?purge` cache-flush is now POST-only (kills the zero-click `<img>` drive-by). Verified live: the forged admin-creation is blocked ("Security check failed", no row created) while login and every legitimate admin action work with their token. (Logout remains a low-severity GET — flagged for follow-up.)

## [0.7.3-alpha] - 2026-06-20
- **Security — session-ID regeneration on login (session-fixation defence).** `login()` now rotates the session id at the privilege boundary: `session_regenerate_id(false)` (the DB-keyed handler only `UPDATE`s, so the old row is re-keyed to the new id by hand — `session_id` is the PK), then the logged-in `UPDATE`s target the new id. Combined with 0.7.1's `use_strict_mode`, a pre-seeded or pre-auth session id can no longer survive authentication. Verified live: the SID changes at login, the new session stays authenticated, and the old pre-login SID is no longer a logged-in session.

## [0.7.2-alpha] - 2026-06-20
- **Security — passwords migrated from unsalted MD5 to bcrypt, with transparent upgrade-on-login.** Login verifies via `lunaTools::verify_password()`: it accepts a legacy 32-hex MD5 hash (constant-time `hash_equals`) or a modern hash (`password_verify`), and on a successful login with a legacy/outdated hash it silently rehashes the stored password to bcrypt (`PASSWORD_DEFAULT`) — zero downtime, no forced reset. New/changed passwords (admin create + modify) are hashed with bcrypt; `luna_users.password` was widened to `VARCHAR(255)` (live + seed). Login failures now return one generic message ("Invalid email or password.") instead of distinct unknown-user / deactivated / wrong-password branches (the specific reason stays only in the server log), and the unknown/inactive paths run a dummy `password_verify` to flatten timing — closing the account-enumeration channel. Verified live: an MD5 admin logs in and is upgraded to `$2y$` in place, the next login uses the bcrypt path, wrong-password and unknown-user return identical messages.

## [0.7.1-alpha] - 2026-06-20
- **Security — session + cookie hardening.** The session cookie and every `set_cookie()` cookie (session, `lang`, sort prefs) now carry **HttpOnly + SameSite=Lax** — and `Secure` automatically under HTTPS — via the PHP 7.3+ array signatures. Sessions are now **cookie-only**: `session.use_trans_sid=0`, `use_only_cookies=1`, `use_strict_mode=1`, so a SID is never propagated through the URL (closing the `trans_sid` leak / fixation enabler) and an attacker-supplied session id is rejected instead of adopted. Removed the now-dead PHPSESSID-in-URL append in `go()`. Verified live: `Set-Cookie` shows `HttpOnly; SameSite=Lax`, a forged SID is not adopted, and valid logged-in sessions persist.

## [0.7.0-alpha] - 2026-06-20
- **Security — HTTP security headers + closed source/secret disclosure.** Added `lunaTools::send_security_headers()` (called early in the bootstrap): a Content-Security-Policy (`default-src 'self'`, `object-src`/`frame-ancestors 'none'`, `base-uri`/`form-action 'self'`), `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Cross-Origin-Opener-Policy`, and `header_remove('X-Powered-By')`. (CSP keeps `'unsafe-inline'` for scripts/styles for now because the admin UI still has inline `onclick`/`onchange` handlers — to be tightened with the XSS batch.) Hardened the root `.htaccess` to return 403 for direct access to `/.git`, `*.ini`/`*.sql`/`*.dist`/`*.example`/`*.log`, `docker-compose.yml`/`Dockerfile`/`Makefile`, and `docs/`/`scss/` — the `.git` repo, `db.ini`, demo creds, and the vuln-map doc were all served at web root (confirmed 200 → now 403). Defaulted the committed `luna.ini` to `DEBUG=0` (no `display_errors` leak) and removed the dead `lunaTools::debug()` die-dump.

## [0.6.9-alpha] - 2026-06-20
- **Security — critical blind SQL injection in the admin users list (FIXED).** `load_users()` ([luna.model.class.php](luna/luna.classes/luna.model.class.php)) interpolated the request-supplied `order_by` straight into the `ORDER BY` SQL identifier through the `switch` default branch — `order_by=last_time,(SELECT(SLEEP(3)))` was confirmed exploitable. Replaced the switch with a whitelist map (key → fixed column) that also normalizes the reflected/persisted value to a safe key; `start`/`limit` are now `intval()`-cast before the `LIMIT` clause; the `levels` `IN()` list is `intval`-mapped with an empty-guard (defense-in-depth). Verified live: the `SLEEP(3)` payload runs in ~1s (== baseline), the list still renders/sorts, a bad `limit` returns 200, and raw `order_by` is no longer reflected. First of a multi-batch hardening pass (audit confirmed CSRF/IDOR/stored-XSS/MD5/session issues — batches to follow).

## [0.6.8-alpha] - 2026-06-20
- **Removed jQuery and all JS animations.** The front-end no longer loads jQuery (3.7.1 from CDN). `js/luna.js` is now ~25 lines of dependency-free vanilla JS with no animation: `confirmSubmit()` (delete confirmations), zebra striping + row hover (`.even`/`.odd`/`.hover` classes), and the collapsible `.box-handle` toggle (the bottom-bar hamburger sitemap), which now flips classes and the adjacent `.box-content` display **instantly** instead of `show/hide('slow')`. Also dropped the dead inline-config `<script>` (`basehref`/`pagenid`/`ckeditorconfig` — CKEditor/TinyMCE were already removed) and the long-broken jQuery TreeView code (its `li[ul]` selector matched nothing, so the sitemap tree was already a static list). Show/hide is now instant — add a CSS transition on `.box-content` if you want movement. Verified in a real JS engine (jsdom): no errors, `jQuery`/`$` undefined, hamburger toggle + zebra + `confirmSubmit` all work on the public and admin pages.

## [0.6.7-alpha] - 2026-06-20
- **English locale is now `en-US` everywhere (was the bogus `en-EN`).** `format_language()` derived a region by upper-casing the language code (`en`→`EN`) — fine for `fr`→`FR`, wrong for English. Added a canonical-region map (`en`→`US`); because the region is always re-derived (texts store only a 2-char lang), this single point flows `en-US` through `site_langs`, the nav language switcher, the default page language, the page `<html lang>`, and every text's `xml:lang`. The English switcher button is now `?lang=en-US` and is **selected on the default page load**. Also hardened `set_language()`: the `session_lang` fallback now validates against `site_langs` (the one branch that didn't), so a stale `en-EN` cookie/session falls back cleanly to the default. No config or schema change. Verified live and adversarially (default / en-US / fr-FR / stale en-EN, switcher selection, text `xml:lang`, gettext, JSON-LD + RDF graph, edit_texts, admin): all `en-US`, zero `en-EN`, French untouched.

## [0.6.6-alpha] - 2026-06-19
- **Page texts: skip empty titles, render only the current language.** The `page` template (in [luna.default.html.xsl](luna/luna.xsl/luna.html.xsl/luna.default.html.xsl) and the homepage [luna.root.html.xsl](luna/luna.xsl/luna.html.xsl/luna.root.html.xsl)) rendered every attached `luna:text` regardless of language and printed the model's no-title sentinel `"0"` as an `<h2>`. Now: (1) the `<h2>` is wrapped in `<xsl:if>` that skips a blank or `"0"` title (genuine titles kept; the body still renders); (2) the `for-each` filters texts by the current page language with a **primary-subtag** match (`substring-before(@xml:lang,'-') = substring-before($lang,'-')`) — so `/about` in English shows only the English text, in French only the French. Primary-subtag (not full-tag) match is deliberate: it stays robust to the default page language resolving to `en-US` while texts are tagged `en-EN` (a separate latent bug at `luna.php:106`), so the default page never blanks. HTML-only change — the RDF/XML graph and in-head JSON-LD still carry every language. Applied byte-identically to both page templates; verified live and adversarially reviewed.

## [0.6.5-alpha] - 2026-06-19
- **Fixed: the edit_texts language field could only be set to the default (`en-EN`).** In the shared `forminput` template's `data`-mode `<option>`, the *translated language name* (`luna:value`, e.g. "Français") was put in `@value` — the value the browser submits — while the language *code* was shown as the label. `mod_edit_texts` validates the submission against `site_langs` (the codes) with `in_array()`, so any non-default choice failed the check and silently fell back to the first language. Swapped them in [common.html.xsl](luna/luna.xsl/luna.html.xsl/luna.common.html.xsl): `@value` is now the code (`luna:lid`, e.g. `fr-FR`), the visible label is the readable name, and the selected-state tests compare the code. Verified on both the add and modify forms (modify correctly pre-selects the text's own language). The `char(2)` `lang` column still stores `fr`; `format_language()` rebuilds `fr-FR` on render — unchanged.

## [0.6.4-alpha] - 2026-06-19
- **Site page labels moved to a separate, git-ignored gettext domain** — so a site's page-label translations no longer pollute the engine's catalog (reverts the `about` entry added to `luna.po` in 0.6.3). The engine `luna` catalog stays purely the CMS's UI vocabulary; per-site labels live in a `local` domain (`<lang>/LC_MESSAGES/local.po` / `local.mo`), git-ignored. New helper `lunaTools::label($lid)` resolves a label by trying `local` → engine `luna` → the raw lid; the model's three `rdfs:label` assignments now route through it, and the `local` domain is bound next to `luna`. Workflow to add a label: edit the git-ignored `local.po`, `msgfmt`, restart — nothing site-specific reaches the repo. See [luna/luna.locale/README.md](luna/luna.locale/README.md).

## [0.6.3-alpha] - 2026-06-19
- **Localised the `/about` page label.** Page labels are produced by `_($lid)` (gettext) → the node's `rdfs:label` ([luna.model.class.php:1970](luna/luna.classes/luna.model.class.php)), so a lid with no catalog entry renders verbatim. Added `msgid "about"` → `"About"` (`en_EN`) / `"À propos"` (`fr_FR`) to the gettext catalogs and recompiled the `.mo`s, so the page name now localises in the header, sitemap and breadcrumb. (`en_US` is unused — not in the language switcher — so left as-is.)

## [0.6.2-alpha] - 2026-06-19
- **Source maps now on every dev build** (`make css` and `make css-watch`), not just the watch loop — the earlier split meant a plain `make css` produced a map-less `css/luna.css`, so dev tools still showed `luna.css` line numbers. `make css-min` remains the clean, minified production build with no map. The `.map` stays gitignored.

## [0.6.1-alpha] - 2026-06-19
- **SCSS source maps in the dev build.** `make css-watch` now compiles with source maps (`--embed-sources`), so browser dev tools point compiled rules back to the `_*.scss` file and line instead of `luna.css`. The map (`css/luna.css.map`) is gitignored; `make css` stays clean (no source map) for the committed `css/luna.css`. Shared Sass flags were factored into a `SASS_FLAGS` variable in the `Makefile`.

## [0.6.0-alpha] - 2026-06-19
- **Adopted a baseline grid for typography** — [baselinegrid.scss](https://github.com/jeromev/baselinegrid.scss) (v3.0.1, MIT © Jérôme Vogel), vendored under `scss/vendor/`:
  - `scss/_bg.scss` configures it (`@forward 'baselinegrid'`); `_base.scss` calls `@include bg.begin()`, sets `html { @include bg.root(); font-family: Verdana, sans-serif; }` (a responsive base font + the `--base-unit` rhythm), reconciles form-control fonts (`font: inherit`), and aligns body text via `p { @include bg.set(); }`.
  - The `Makefile` build passes `--load-path=scss/vendor --quiet-deps`, so the vendored lib stays pristine while its compile-time deprecation notices are silenced (luna's own warnings still show).
  - Verified across content and admin (forms, tables, the bottom navbar) at the responsive scale: body text sits on a consistent baseline; the chrome is intact; clean compile; zero runtime warnings/fatals.
  - Foundation only — content headings (via `bg.scale('font-size', …)`, scoped because `h1`/`h2` are chrome here) and tuning the type `$scale` are the obvious next steps.

## [0.5.9-alpha] - 2026-06-19
- **Fixed: saving a text (or any logged admin action) showed a `DEBUGGING` dump instead of the page.** `lunaLog::log()` called `lunaTools::debug()` — which prints globals / included-files / DB-queries and `die()`s — on *every* log call whenever `DEBUG` is on and the user is an admin. Saving a text logs an INFO message ("…has been modified."), so the response terminated with the debug screen instead of returning to the editor. Removed that auto-dump; `log()` now just writes the row to `luna_logs`. Logs are inspected on the admin **journal** page, uncaught errors keep their own error page, and an explicit `lunaTools::debug()` call is still there for ad-hoc dev use. Saving now returns to the normal HTML editor with its success message, and the action is still logged.

## [0.5.8-alpha] - 2026-06-19
- **Fixed: edited text lost its markup on render.** Text saved through the `edit_texts` module came back as plain text (`<p>`, `<strong>`, links… stripped) when displayed.
  - **Root cause:** the triplestore (the default read path) stored text content only as `schema:articleBody`, which the write-through deliberately runs through `strip_tags()` (correct — `articleBody` is plain text for schema.org). The read path (`load_texts_sparql`) then rebuilt the rendered `content_html` *from* that stripped `articleBody`. Un-edited texts looked fine because the R2RML mapping materialised `articleBody` un-stripped — markup was lost only once a text was **edited** (when the PHP write-through overwrote it with the stripped version), exactly matching the report.
  - **Fix:** the write-through ([rdf_sync_node](luna/luna.classes/luna.model.class.php)) now also stores the full markup as **`luna:content`** (alongside the plain `schema:articleBody`), and the read path reads `luna:content` for the rendered content (falling back to `articleBody` for texts not yet re-synced). The R2RML mapping ([semantic/ontop/mapping.ttl](semantic/ontop/mapping.ttl)) maps `content_html` → `luna:content` too, so the materialise / Ontop paths stay consistent.
  - Verified on the live stack: edited text renders its full markup through **both** the graph and SQL read paths; rich content (quotes, `&`, newlines, `<a href>`) round-trips intact; the JSON-LD `schema:articleBody` stays correctly plain-text; zero warnings/fatals.

## [0.5.7-alpha] - 2026-06-19
- **Un-bundled jQuery — load it from a CDN.** Removed the vendored `js/jquery/jquery.js` (jQuery 1.4.1, 2010) and now load **jQuery 3.7.1 from cdnjs** with an SRI `integrity` hash + `crossorigin` / `referrerpolicy` ([luna.header.html.xsl](luna/luna.xsl/luna.html.xsl/luna.header.html.xsl)).
  - **Updated `js/luna.js` for jQuery 3.x:** the two-callback `.toggle(fn, fn)` (removed in jQuery 1.9) is gone — the `.box-handle` collapse that drives the bottom-bar hamburger is now a single class-driven click handler, and the tree-view caret uses a delegated click. The rest (zebra striping, row hover) was already 3.x-compatible.
  - Verified in a real browser (headless Chrome): `jQuery.fn.jquery` reports `3.7.1`, the SRI passes, the hamburger toggles the sitemap (`display: none → block`), and there are no console errors beyond the pre-existing `favicon.ico` 404.
  - Updated [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md) (jQuery is no longer bundled). **Note:** the page now needs internet access to fetch jQuery from the CDN.

## [0.5.6-alpha] - 2026-06-19
- **New fixed bottom toolbar.** All site tools now live in one bar pinned to the bottom of every page:
  - a **hamburger** (`☰ Site map`) on the left that pops the sitemap tree up as a panel above the bar — it reuses the existing `box-handle` jQuery toggle, and the sitemap now defaults to **collapsed**;
  - the **language** switches and **output-format** switches (html / xml / json / n3 / jsonld) on the right.
  - The left nav column is gone, so page content is now **full-width**; the top header (logo + page title) stays. `div#Page` is a single-column grid with bottom padding so content clears the fixed bar.
  - XSL: the sitemap `box-handle` now defaults to `collapsed` ([luna.common.html.xsl](luna/luna.xsl/luna.html.xsl/luna.common.html.xsl)); no JS change — the existing toggle drives the hamburger.
  - Verified with headless-Chrome screenshots (home, login, authenticated admin) and a live click-test (the tree's `display` flips `none` → `block` on click); zero warnings/fatals.

## [0.5.5-alpha] - 2026-06-19
- **Replaced every CSS float with Grid / Flexbox** (`grep float css/luna.css` → 0):
  - **Page macro-layout → CSS Grid.** `body div#Page` now uses `grid-template-areas` ("top" spanning the full width, then `nav` + `content`), placing the header, left nav and right content by area regardless of DOM order — replacing the old `div#Content { float: right }` + full-width-`div#Nav` float trick.
  - **Flexbox** for the `div#Top` header (its two boxes now sit side-by-side as intended, instead of wrapping from the old width+margin overflow), the admin form columns (`div.fields`), the `div#Options` language/output switcher, and `div#Nav`.
  - Removed the float **clearfix** (`_clearfix.scss` is now empty) and the obsolete float-clearing `clear: both` — grid/flex contain their own children.
  - Verified with before/after headless-Chrome screenshots (home, login, authenticated admin incl. an expanded multi-column form): the two-column layout, forms, nav tree, zebra tables and language switcher all render correctly with zero warnings/fatals. The only intentional visual change is the header becoming a proper side-by-side row.

## [0.5.4-alpha] - 2026-06-19
- **Refactored the SCSS partials into nested rules.** The flat descendant selectors (which repeated their full ancestor chain on every line) are now idiomatic nested SCSS — the `form` → `fieldset` → fields block, the whole `table.zebra` block (`_base.scss`), the deep `body div#Page …` layout tree (`_page.scss`), and the `ul.tv …` tree (`_treeview.scss`); pseudo-classes / compound selectors use `&` (`&:hover`, `&.even`, `&.submit`). The nesting is order-preserving, so the **compiled `css/luna.css` is byte-identical** (normalised) to before — verified against a pre-refactor snapshot of the exact same flat source. Carries forward the in-progress responsive width tweaks (rem → `%`) already in the working tree.

## [0.5.3-alpha] - 2026-06-19
- **Added an SCSS build apparatus for the stylesheet.** `css/luna.css` is now a *generated* file compiled from a new `scss/` source tree with [Dart Sass](https://sass-lang.com/dart-sass):
  - `scss/luna.scss` is the entry point; the previous single stylesheet is split into partials along its existing sections — `_tokens.scss` (the `:root` palette), `_base.scss`, `_classes.scss`, `_page.scss`, `_clearfix.scss`, `_tinymce.scss`, `_treeview.scss` — wired with `@use`.
  - A root `Makefile` drives it: `make css` (one-off), `make css-watch` (live rebuild on save), `make css-min` (minified). `sass` must be on `PATH`.
  - `.gitignore` now ignores Sass artifacts (`.sass-cache/`, `*.css.map`); `scss/README.md` documents the workflow.
  - The regenerated `css/luna.css` is CSS-equivalent to the previous hand-written file — verified by normalised comparison; the only differences are whitespace (the generated file is 2-space indented) and Sass number-normalisation (e.g. `.90` → `0.9`). The palette stays as CSS custom properties (runtime-themeable), not Sass variables.
  - **Workflow going forward:** edit the partials under `scss/`, run `make css`, and commit both the partial(s) and the regenerated `css/luna.css`. Do not hand-edit `css/luna.css`.

## [0.5.2-alpha] - 2026-06-19
- **Vendor-code audit + minimal cleanup.** A multi-agent audit inventoried every piece of third-party code still in the tree (each tagged used/dead and cross-checked against the notices). Acted on the safe findings:
  - **Corrected [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md)**, which the recent migrations had made inaccurate: dropped the **PEAR MDB2** row (removed in 0.5.0-alpha), replaced the wrong **XML_Util** row with the **XML_HTMLSax3** that is actually bundled (HTML_Safe's SAX parser), refreshed the stale "targets PHP 5.3–5.6" line and a few license/version details (ARC2 → semsol/arc2 3.1.0 GPL-2/W3C; jQuery 1.4.1 dual MIT/GPL), and added an **"Inlined third-party functions"** section documenting the small credited snippets copied into app code (phpBB `encode_ip`/`decode_ip`, WordPress `remove_accents`, Drupal `conf_path`/`conf_init`, the lost-in-code `array_to_object`).
  - **Removed dead code:** `lunaTools::parse_bbcode()` — a BBCode→HTML routine verified completely unreferenced across the tree.
  - **Removed orphaned assets:** the six duplicate `images/treeView/*.gif` icons (the `.png` variants + `tviload.gif` are the ones actually referenced by `css/luna.css`) and the unreferenced `images/body.png`.
  - No behavioural change: every public page still renders with zero warnings/fatals; the audit confirmed CKEditor is fully gone and that the kept libraries (ARC2 serializers, HTML_Safe + XML_HTMLSax3, Cache_Lite, PEAR Log constants, jQuery) are all genuinely used.

## [0.5.1-alpha] - 2026-06-19
- **Modernised `css/luna.css` (syntax cleanup, no intended visual change).** The 15-year-old admin/theme stylesheet brought up to date while keeping a pixel-identical result:
  - **Lowercased element selectors** (`HTML`/`BODY`/`TABLE`/… → `html`/`body`/`table`/…). IDs and classes keep their original casing because the XSL markup is case-sensitive (`#Page`, `#Content`, `.zebra`, …).
  - **Dropped all `-moz-`/`-webkit-` prefixes** for standard `border-radius` / `box-shadow` / `border-*-radius`. This also *restores* rounded corners that modern browsers no longer render, since the obsolete `-moz-border-radius` family was the only declaration in several rules.
  - **Replaced the `images/background/{black,white}NN.png` overlay tiles with `rgba()` fills.** Verified each PNG is a uniform 2×2 solid black/white image whose alpha byte equals its name (e.g. `black10.png` → α 26 ≈ 10% → `rgba(0,0,0,.10)`; `white60.png` → α 153 ≈ 60% → `rgba(255,255,255,.60)`), so the swap is pixel-exact. Real image assets (treeView icons, menu collapse/expand arrows) stay as `url()`.
  - **Added a `:root` custom-property palette** for the recurring colours (`--ink`, `--chrome`, `--link`, `--accent`, `--ok`, `--warn`, …) and the base `--font`.
  - **Removed dead cruft** (visual no-ops on modern browsers): IE6 underscore hacks in the tree view (`_background`/`_filter`/`_left`), the old "easyclearing" float hack (now minimal `content:""; clear:both`), a commented-out `white70` background, an `overflow:auto;;` double semicolon, and the `-webkit-border-top-right-radius: 4px` typo on `div.fields` (normalised to the intended 5px).
  - Flattened the original cascading indentation to a consistent modern style.
- **PHP 8 review pass — fixed latent issues a multi-agent review surfaced** (follow-up to the 0.5.0 upgrade), each grounded in the running 8.3 stack:
  - **Fatal:** `mod_journal` calls `Log::priorityToString()` / `stringToPriority()` statically, but the kept `Log.php` declared them non-static — a hard PHP-8 error the moment an admin opens the journal with log rows. Both are now `static`.
  - **Latent fatal:** `lunaTools::array_to_object()` assigned a property on `false` (`$data = false; $data->{$k} = …`) — now seeds `new stdClass()`.
  - **Warnings (admin pages — now zero):** the user-list sort switched on an undefined `$order_by` (→ switch on the resolved `$cookie['order_by']`); `$_GET['path']`, `$user['session_ip']` and `$row->page_nid` were read without guards (→ `isset` / `??`).
  - **Deprecations:** `strftime()` (removed in PHP 9) → `date()` in `format_date()`; `lunaException`'s dynamic `$session` / `$server` properties are now declared (PHP 8.2).
  - **Data-loss bug:** saving a text's content without re-submitting its page set unlinked it from its page (orphaning it → "Unknown text #N" on the next edit). `mod_edit_texts` now only unlinks when a page set is actually submitted. Separately, a `lunaException` logged with no code was recorded as `EMERG`; it now defaults to `ERR`.
  - **Schema:** `luna_logs.logtime` had no default, so a fresh MySQL-8 install wrote `0000-00-00`; added `DEFAULT CURRENT_TIMESTAMP` to the seed.
  - Verified authenticated on the live 8.3 stack: every public + admin page renders with **zero warnings/fatals**; the journal renders its priority labels; a content-only text edit keeps its page link and mirrors through to Oxigraph.
- **Docs: PHP-version accuracy sweep.** Updated the README and `docs/` (installation, overview, security, architecture, database-schema, configuration, why-rdf, docs/README) to state **PHP 8.3 / MySQL 8.0 + PDO** instead of the pre-upgrade "PHP 5.3–5.6 only / PEAR MDB2 / `mysql_*`" facts that the 0.5.0 migration made false.

## [0.5.0-alpha] - 2026-06-19
- **Runtime upgrade: PHP 5.6 → PHP 8.3, MySQL 5.7 → MySQL 8.0.** The app now runs on a current stack. Verified end-to-end on Docker: HTML, RDF/XML (`?output=xml`) and JSON-LD output are **byte-identical** to the PHP-5.6 baseline; the XSS sanitiser output is byte-identical; login, the admin CRUD path, logging, and the RDF read/write loop all work; every page renders with **zero PHP warnings**; all 100 PHP files lint clean on 8.3.
  - **Database: dropped PEAR MDB2 + `ext/mysql` (removed in PHP 7) for PDO (`pdo_mysql`).** `luna.db.class.php` rewritten to keep the exact `lunaDB::query/quote/get/nextID/optimise` surface plus a small `lunaResult` wrapper (`->fetchRow()` via `PDO::FETCH_OBJ`, `->free()`). `quote()` reproduces MDB2's auto-typed quoting exactly (verified against the live MDB2 stack): `null`/`''`→`NULL`, `bool`→`0`/`1`, `int`→bare, float/string→escaped. `nextID()` reproduces MDB2's `luna_nodes_seq` sequence emulation (and the seq is re-seated to `MAX(nid)+1`).
  - **RDF: replaced the 2011 vendored ARC2 with the maintained `semsol/arc2` 3.1.0** (vendored, same global `ARC2::` API, no Composer). Patched its N-Triples serializer for two PHP-8 strictnesses (array term without `type`; a UTF-8 `escape()` step that mangled multibyte to `?`) so output matches/`improves on` the old serializer.
  - **Logging: replaced the MDB2-bound PEAR Log handler with a direct PDO `INSERT`** in `lunaLog::log()`; kept `Log.php` for the `PEAR_LOG_*` constants and `Log::priorityToString()`.
  - **Removed now-dead vendored code:** the entire MDB2 tree, the PEAR Log driver dir, the PEAR installer tree, and `XML/Util.php`.
  - **Patched the kept vendored libs for PHP 8** (behaviour-preserving): the sanitiser chain (`HTML/Safe`, `XML/HTMLSax3`, `Decorators`) and `Cache/Lite` — PHP-4 constructors → `__construct` (+ `parent::__construct()` wiring), `=& new` → `= new`, curly `$x{}` offsets → `$x[]`, `/e` `preg_replace` → `preg_replace_callback`. A `magic_quotes_runtime` no-op polyfill lives in `luna.php`.
  - **App-code PHP-8 fixes:** `=& new` sweep (8 sites), `ereg_replace` → `preg_replace`, `utf8_encode` → `mb_convert_encoding`; static-call fixes (the `lunaDB`/`lunaLog` methods are now `static`; `get_user_data()` is called on the session instance); the DB session handler now returns the correct types (`read()`→string, `write()`→bool, the PHP-8 "failed to read session data" + TypeError fixes); and `isset`/`??`/`empty()` guards for the array-key/variable accesses PHP 8 reclassified from notice to warning.
  - **Docker:** `Dockerfile` → `php:8.3-apache` (`pdo_mysql`, `xsl`, `gettext`, `mbstring`; dropped the Debian-archive apt pin and `ext/mysql`); `docker-compose.yml` → `mysql:8.0` (utf8mb4, native arm64 — dropped the `platform: linux/amd64` emulation line). Ports stay loopback-bound.

## [0.4.1-alpha] - 2026-06-19
- Public-readiness, part 2 — make it a real teaching unit + open-source hygiene (docs/config only).
  - **Hands-on lab:** [docs/try-it.md](docs/try-it.md) — a ~10-minute guided tour (data views → SPARQL → edit-and-read-back → swap the engine → federation), and [examples/queries.sparql](examples/queries.sparql) — copy-paste queries (census, graph traversal, `isPartOf+`, `ASK`, `CONSTRUCT`, `DESCRIBE`, cross-store `SERVICE` federation), all verified against the running stack.
  - **OSS hygiene:** [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md) (bundled libs + licenses), [CONTRIBUTING.md](CONTRIBUTING.md), [SECURITY.md](SECURITY.md), [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md), and `.github/` (issue + PR templates; a CI workflow that runs `php -l` on the project code, validates `docker compose config`, and asserts every published port stays bound to `127.0.0.1`).
  - **Checklist:** [docs/going-public.md](docs/going-public.md) records the five-dimension readiness audit, what's done, and the owner-only steps that remain (rotate the old DB credential; flip the repo to public; set description/topics).
  - Wired the lab + checklist into the docs index and both READMEs.

## [0.4.0-alpha] - 2026-06-19
- Public-readiness, part 1 — safety + trim (toward making the repo a public, minimal, safe semantic-web testing unit; the remaining git history was audited and confirmed clean — no secret was ever committed).
  - **Safety (blocker):** `docker-compose.yml` now binds **every** host port to `127.0.0.1` (loopback) — app `8080`, MySQL `3307`, Ontop `8081`, Oxigraph `7879`. Previously they published to `0.0.0.0`, exposing an **unauthenticated Oxigraph `/update`** (open graph-write) and the MySQL port to the whole network on a bare `docker-compose up`.
  - **Safety:** added a loud "study/experiment artifact — run on localhost only" banner at the top of the README (before the quick start), a demo-credentials note next to the login line, and a concrete loopback-binding mitigation in `docs/security.md`.
  - **Trim (~40% smaller: 15 MB → 8.6 MB):** removed **CKEditor** (`js/ckeditor`, ~6 MB) — the admin text editor is now a plain `<textarea>` (you see the exact HTML going into the graph); neutralised its wiring in `luna.header.html.xsl` and `js/luna.js`. Removed the **`lunarsystem.org` production domain** (theme + the real-looking on-disk `db.ini`); the demo uses `luna.default`.
  - Docs: fixed every reference to the removed CKEditor / production domain across README, configuration, templating, modules, security, roadmap; resolved the "real DB credentials in the working tree" security finding (the file is gone).
  - Verified on Docker: site renders, content still served from the triplestore, no errors.

## [0.3.6-alpha] - 2026-06-19
- Docs: added **[docs/why-rdf.md](docs/why-rdf.md)** — a plain-English "so what?" doc explaining what the RDF-native app can do that a vanilla PHP/MySQL app can't (ad-hoc SPARQL across all content types, graph traversal, the app's own routing/ACL answered by SPARQL; schema.org JSON-LD + standards-based interop; storage as a swappable component), each capability tagged **live** / **one step away** / **roadmap** and grounded in queries run against the live stack. Wired into the docs index and READMEs. The doc's claims were adversarially fact-checked against the running Oxigraph/Ontop stack (zero inaccuracies found). No code change.

## [0.3.5-alpha] - 2026-06-19
- Docs: full accuracy audit of every doc/readme against the current RDF-native code (multi-agent pass, each finding adversarially verified). No code change.
  - Fixed ~20 stale `luna.php`/`*.class.php` line-number anchors across `architecture.md`, `templating.md`, `configuration.md`, `modules.md`, and `security.md` that had drifted after the 0.3.3/0.3.4 code (the model gained ~280 lines, `luna.php` ~20). Constructor 199, `set_requested_path` 316, `set_site_path` 327, `load_mods` 394, `transform` 518, `load_ini` 641, the OPTIMIZE/dispatch/JSON-LD-head anchors, etc.
  - `docs/README.md`: dropped "client-side rendering" from the roadmap summary (P5 was dropped).
  - `docs/modules.md` + `docs/rdf-model.md`: the CRUD methods now mirror writes into the triplestore via `rdf_sync_node()`; scoped the "RDF is not a storage engine" claim to the archival model.
  - `docs/templating.md`: corrected `forminput` attribution (it lives in `luna.common.html.xsl`, not `_admin`); added `jsonld` to the output-format list in `architecture.md`.
  - `docs/database-schema.md`: the `langs` string is split by `lunaTools::load_config()`, not `set_language()`.
  - `docs/linked-data.md`: clarified that level/group/mod nodes are projected untyped (no `rdf:type`), and that edges are `/id/{lid}` URIs while scalars carry `xsd:integer`.
  - `docs/security.md`: fixed remediation anchors and added a **"Triplestore / SPARQL surface"** section (unauthenticated Oxigraph endpoints must stay internal; hand-rolled SPARQL string assembly; best-effort write-through).

## [0.3.4-alpha] - 2026-06-19
- Semantic web (P2, first step — URI-identity policy). Resolved roadmap decision #1 as **"forbid slug edits"** and enforced it: `lunaModel::update()` now refuses any change to a node's `lid`, since `<base/id/{lid}>` *is* the resource's identity and the cardinal rule is "freeze the URIs." A rename is create-new + delete-old. This applies to every node type (page slugs, user emails, …), as all share the `/id/{lid}` scheme. Normal edits (same lid) are unaffected; a true rename returns `false` and logs a warning. Verified on Docker. The rest of P2 — retiring the MySQL *content write* itself — remains a larger, deliberate migration (see [docs/roadmap.md](docs/roadmap.md)).

## [0.3.3-alpha] - 2026-06-19
- Semantic web (Phase C complete — the triplestore is now authoritative for the read/write loop; MySQL stays the system of record). The RDF track is being finished; the client-side-XSLT idea (roadmap P5) has been **dropped**. See [docs/linked-data.md](docs/linked-data.md) and [docs/roadmap.md](docs/roadmap.md).
  - **P0 — generic write-through.** Replaced the per-mod `rdf_put_article` hook with a generic projection wired into the model's CRUD, so *every* content write mirrors to the graph by construction:
    - `lunaModel::rdf_sync_node($nid)` re-projects a node's whole description (`DELETE { <uri> ?p ?o } INSERT { … }`) to match the R2RML mapping — typing the resource (`page`→`schema:WebPage`, `text`→`schema:Article`, `user`→`foaf:Person`, level/group/mod→`luna:`) and its edges (`schema:isPartOf`/`hasPart`, `luna:level`), with numeric values typed `xsd:integer`. Called from `insert`/`update`/`link`/`unlink` (and by `mod_edit_texts` after a text-body write).
    - `lunaModel::rdf_delete_node($nid)` removes every triple mentioning a resource (subject *and* object), inside `delete()`.
    - `lunaModel::rdf_resync_all()` re-projects every node from MySQL — a pure-PHP bootstrap/repair of the store that replaces the Ontop "materialise" step. Used to close a real drift gap (a page that predated the dual-write) to exact count parity.
  - **P1 — reads default to the graph.** The read path (routing, ACL, texts) is now served from the triplestore by default: `SPARQL_ENDPOINT` defaults to **Oxigraph** (Ontop becomes an opt-in override), gated by `lunaModel::sparql_reads()` (`SPARQL_READS`, default on). Both the routing and text loaders keep an automatic **SQL fallback**; `?sparql=0` forces the SQL path. (`sanitize_inputs()` turns `'0'`→`false`, so the opt-out is read from `$_GET` with a `(bool)` cast, not via `lunaTools::request()`.)
  - Verified end-to-end on Docker: insert→link→delete of a throwaway page produced then fully removed its projection in Oxigraph; an Oxigraph-only sentinel rendered by default and vanished under `?sparql=0`; guest/admin routing + level-based ACL resolve from the graph.
  - Still SQL-only (by design, for now): the mod list (`load_mods`) and the `luna_actions` audit trail. Retiring the MySQL content write (single source of truth) is **P2**, blocked on the rename/URI-identity decision.

## [0.3.2-alpha] - 2026-06-18
- Semantic web (Phase C, in progress — make the triplestore authoritative): content **writes** now flow into the graph via SPARQL `UPDATE`, the write counterpart to the read-through-SPARQL path.
  - `lunaModel::sparql_update()` POSTs a SPARQL `UPDATE` to a new `SPARQL_UPDATE_ENDPOINT` (Oxigraph; best-effort, so a failed mirror never breaks a save), with `sparql_literal()` for safe string escaping.
  - `lunaModel::rdf_put_article()` mirrors a text block as a `schema:Article` (headline/articleBody/inLanguage) for `<base/id/{lid}>`; `mod_edit_texts` calls it on text **create and modify**.
  - Editing page content in the admin UI now **dual-writes** — the existing SQL write to MySQL *plus* a SPARQL `DELETE`/`INSERT` to the graph. Verified end-to-end on Docker: an admin text edit lands in both MySQL and Oxigraph, and the app reads it back from the triplestore. See [docs/linked-data.md](docs/linked-data.md) ("Writing through SPARQL").

## [0.3.1-alpha] - 2026-06-18
- Fixed: **localisation did nothing.** The Docker image installed the `gettext` extension but never generated the OS locales, so `setlocale()` failed and every translation silently fell back to the source string. The image now generates `en_US.UTF-8`, `fr_FR.UTF-8` and an `en_EN.UTF-8` alias (the locales `lunaTools::format_language()` maps to). French (and any other catalog) now translates.
- Fixed: the English message catalogs (`en_US`, `en_EN`) contained two stray **French** translations (`Email`→"Courriel", `Groups`→"Groupes"), so once the catalogs loaded the English UI showed French. Corrected to "Email"/"Groups" and recompiled the `.mo` files. (This was misdiagnosed at first as a gettext per-worker cache leak; it was purely bad catalog data.)
- Fixed: **`?output=xml` downloaded a `download.rdf` file** instead of displaying. It was served as `application/rdf+xml`, which browsers have no inline viewer for. It now uses `application/xml; charset=utf-8`, so the RDF/XML renders inline; the body is unchanged and still valid RDF/XML. (`json`/`n3`/`jsonld` keep their semantic content-types.)

## [0.3.0-alpha] - 2026-06-18
- Turn the archival CMS into a real Semantic Web CMS in phases, on top of the **unchanged** MySQL schema. This is now the `main` line; the original archival CMS is preserved on the **`legacy`** branch (tag **0.2.14-alpha**). See [docs/linked-data.md](docs/linked-data.md).
  - **Phase 0 — Linked Data foundations + JSON-LD.** Froze a URI policy (resource `/id/{slug}` distinct from its document) and a vocabulary mapping from the custom `luna:`/`owl:` terms to schema.org / Dublin Core / SIOC / FOAF / PROV-O — notably replacing the invalid `owl:isChildOf` with `schema:isPartOf`. Added a JSON-LD projection: `lunaModel::to_jsonld()`, an `?output=jsonld` format, and a `<script type="application/ld+json">` block embedded in every HTML `<head>`.
  - **Phase A — virtual SPARQL over MySQL (Ontop + R2RML).** Added `semantic/ontop/` (Dockerfile, `mapping.ttl`, `ontop.properties`) exposing the unchanged MySQL as a virtual SPARQL endpoint (Ontop, host port 8081). Under `?sparql=1` the app's READ path now flows through SPARQL: `lunaModel::load_texts_sparql()` (page content) and `lunaModel::load_nodes_sparql()` (routing + level-based ACL), both via `lunaModel::sparql_select()`. The endpoint is selected by a `SPARQL_ENDPOINT` constant / env var (default `http://ontop:8080/sparql`).
  - **Phase B — materialise to Oxigraph, swap the engine.** Materialised the SAME `mapping.ttl` into a real triplestore (Oxigraph, host port 7879) via `semantic/ontop/dump.nt`, then flipped the app's `SPARQL_ENDPOINT` to point at it — an engine swap with NO application code change (verified by stopping Ontop and confirming `?sparql=1` still serves routing/ACL/content from the triplestore).
  - **Docker:** `docker-compose.yml` now defines four services — `app`, `db` (`mysql:5.7`), `ontop`, `oxigraph`.

## [0.2.14-alpha] - 2026-06-18
- Security: hardened the self-contained issues from the 0.2.12 code-review pass (verified against the Docker stack). See [docs/security.md](docs/security.md).
  - SQL injection in `mod_journal`: `start` is now `intval()`'d before the `LIMIT` clause and `order_by` is whitelisted before being interpolated as a SQL identifier into `COUNT()`/`ORDER BY` (this also fixes the dead `switch($order_by)` on an undefined variable).
  - Reflected XSS: `lunaTools::raise_error_page()` now `htmlspecialchars()`-escapes the requested path before it reaches the response.
  - PHP object injection: cookies now use `json_encode`/`json_decode` instead of `serialize`/`unserialize`, and `lunaModel::load_request()` guards `unserialize()` against `O:`/`C:` object payloads.
  - `purgelogs` now requires `$_POST` (was triggerable by any GET, e.g. a forged `<img>`), so it can no longer wipe `luna_logs` via a forged link.
  - Log hygiene: `lunaLog::log()` stores only a small `$_SERVER` whitelist (remote addr, method, URI, host, UA, referer) instead of the whole array (which carried the cookie header and session id).
  - Session IP guard: `lunaTools::encode_ip()` no longer trusts the client-supplied `X-Forwarded-For`; it uses `REMOTE_ADDR`, closing the IP-spoof bypass of the session hijack check.
  - Login throttling: `mod_log::login()` now reads `login_attempts` and applies a capped per-account back-off (`sleep(min(attempts, 5))`); a correct password still resets the counter, so accounts are never permanently locked.
  - Deferred (more invasive, see security.md): CSRF tokens on every form, per-action authorisation re-checks, and session-ID rotation on login.

## [0.2.13-alpha] - 2026-06-18
- Fixed: `lunaModel::singleton()` guarded on `$this->$instance` (a variable-variable on an undefined name) in a method that is called statically, so the guard never saw the cached instance. Made it `static` and switched to `self::$instance`, matching `luna::singleton()` and `lunaSession::singleton()`.
- Fixed: `lunaModel::load_node()` array-relationship branch read `is_active` from the literal key `$node['is_activex']` instead of the computed key `$node[$is_active.($i+1)]` (`$is_activex`), so secondary related nodes never got their real `is_active` value.
- Fixed: `lunaTools::go()` redirect built its URL from an undefined `$alias` in the default branch, so the unauthorised-user redirect (`go('login')`) resolved to an empty alias instead of the login page. It now resolves `$where`, and `exit`s after sending the `Location` header so execution actually stops at the auth gate.
- Fixed: seed `luna_nodes_map` had a broken guest→group edge `(2, 3), (2, 3)` (forward duplicated, reverse missing); corrected to the symmetric pair `(2, 3), (3, 2)` used by every other edge. Affects fresh database imports only.

## [0.2.12-alpha] - 2026-06-18
- Docs: refreshed the `docs/` set after a full code read-through.
  - Fixed stale `luna.php` line-number anchors across `architecture.md`, `configuration.md`, `templating.md`, and `security.md` (they had drifted ~+8 to +11 lines after the timezone commit): `__construct` 190, `set_site_path` 314, `set_requested_path` 303, `load_mods` 381, `transform` 506, `load_ini` 625, `use_trans_sid` 33, stylesheet cascade 575-607.
  - Corrected behavioural descriptions: bootstrap's early steps `die()`/`trigger_error()` directly rather than throwing; `lunaLog::log()` screen-dumps **or** DB-writes (mutually exclusive); `OPTIMIZE TABLE` runs after *any* non-AJAX submit (not only successful ones) and `submit()` always runs before the mode switch; the AJAX short-circuit lives in `transform()`; `mod_journal` reads `luna_logs` (not `luna_actions`); `mod_admin::load()` only merges config; the model mutator list now includes `delete()`/`exists()`; documented the built-in (`luna.`-prefixed) vs domain-override (un-prefixed) stylesheet naming; noted `langs` is a single comma-separated string and `luna_logs.id` is the lone non-`unsigned` PK.
  - Security: corrected the `db.ini` guidance (it is gitignored and was never committed — rotate the on-disk credentials, nothing to untrack) and removed the inaccurate "cache files tracked" note. Added a verified "Additional findings (2026 code-review pass)" section (no CSRF, `mod_journal` `LIMIT` SQLi, session fixation, missing per-action authz, no login throttling, `unserialize()` object injection, reflected XSS in the error page, unescaped stored content, GET-triggered `purgelogs`, `$_SERVER` written to `luna_logs`, bypassable session guard).

## [0.2.11-alpha] - 2026-06-18
- Fixed: `date()` warning "It is not safe to rely on the system's timezone settings" on PHP 5.3+ when `date.timezone` is unset. The bootstrap now calls `date_default_timezone_set()` early, honouring an existing `php.ini` value and falling back to UTC.

## [0.2.10-alpha] - 2026-06-18
- Fixed: fatal `Call to undefined function: MDB2_Driver_mysql::raiseError()` on any database error path. The PHP image ships a newer system PEAR under `/usr/local/lib/php`, and `luna.lib` was *appended* to `include_path`, so MDB2 loaded the system `PEAR.php` (whose `raiseError` the bundled MDB2 cannot reach) instead of the bundled one. Now `luna.lib` is *prepended* so the bundled PEAR/MDB2/Log set always wins. This previously broke every insert/update (MDB2's id-sequence logic deliberately triggers and catches errors).

## [0.2.9-alpha] - 2026-06-18
- Fixed: schema failed to import on modern MySQL — `TYPE=MyISAM` → `ENGINE=MyISAM` (removed in MySQL 5.5)
- Fixed: fatal parse error on PHP 5.6 — duplicate `static` modifier on `lunaTools::parse_bbcode()`
- Changed: mask `E_DEPRECATED`/`E_STRICT` so the PHP 5.2/5.3-era idioms don't spam output on PHP 5.6
- Docker: repoint apt at `archive.debian.org` (Debian 9 "stretch" is off the main mirrors) and pin the `mysql:5.7` service to `linux/amd64`; drop the obsolete Compose `version` key
- Verified the full Docker stack boots, renders pages, serves the `xml`/`json`/`n3` RDF outputs, and authenticates the seed admin

## [0.2.8-alpha] - 2026-06-18
- Added a full technical documentation set under `docs/` (overview, architecture, RDF model, database schema, modules, templating, configuration, installation, security)
- Linked the documentation from `README.md`

## [0.2.7-alpha] - 2010-03-30
- Updated installation instructions

## [0.2.6-alpha] - 2010-03-16
- Added a xsl dir in default domain

## [0.2.5-alpha] - 2010-03-11
- Clean release

## [0.2.4-alpha] - 2010-03-05
- Fixed: language negotiation not working

## [0.2.3-alpha] - 2010-03-05
- Added: session id must be 32 characters long
- Added: language must be set in the form `xx-xx`

## [0.2.2-alpha] - 2010-03-03
- Added: `mod_node` now prints the requested node as RDF/XML

## [0.2.1-alpha] - 2010-03-03
- Fixed: URLs not correctly formatted when `CLEAN_URLS = 0`
- Fixed: `$_POST['level_nid']` not correctly interpreted
- Added: CSS cosmetics

## [0.2.0-alpha] - 2010-03-03
- 2nd release on SourceForge

## [0.1.66-alpha] - 2010-03-03
- Texts are now loaded along with their associated page

## [0.1.65-alpha] - 2010-03-03
- Corrected the default value in table `luna_nodes_seq`

## [0.1.64-alpha] - 2010-03-02
- Fixed the `edit_texts` mod

## [0.1.63-alpha] - 2010-03-02
- Added CKEditor to the package

## [0.1.62-alpha] - 2010-03-01
- Fixed `mod_online_users`

## [0.1.61-alpha] - 2010-03-01
- Fixed `mod_admin_users`

## [0.1.60-alpha] - 2010-03-01
- Included all dependencies (PEAR and ARC2) in `luna.lib`
- Updated `INSTALL.txt` accordingly

## [0.1.59-alpha] - 2010-03-01
- Fixed the admin page

## [0.1.58-alpha] - 2010-03-01
- Corrected `luna` class so the ini file is loaded first

## [0.1.57-alpha] - 2010-03-01
- Fixed `mod_admin_mods`

## [0.1.56-alpha] - 2010-02-28
- Fixed `mod_admin_pages`

## [0.1.55-alpha] - 2010-02-28
- Fixed `mod_admin_levels`

## [0.1.54-alpha] - 2010-02-27
- Fixed `mod_admin_groups`

## [0.1.53-alpha] - 2010-02-27
- Renamed exception class to `lunaLog`

## [0.1.52-alpha] - 2010-02-26
- Replaced RAP RDF library with ARC2

## [0.1.51-alpha] - 2010-02-24
- Moved all `lunaNode` methods into the model class
- Renamed the RDF class to `lunaModel`

## [0.1.50-alpha] - 2010-02-23
- Added the ability to navigate without clean URLs
- `mod_journal` mostly working

## [0.1.49-alpha] - 2010-02-23
- Added `luna.mod_example.php`

## [0.1.48-alpha] - 2010-02-23
- Deleted unnecessary mod class
- Set singleton pattern for module loading

## [0.1.47-alpha] - 2010-02-23
- Fixed a few deprecated pass-by-reference calls

## [0.1.46-alpha] - 2010-02-22
- Finished integrating the RDF RAP-API into luna core

## [0.1.45-alpha] - 2010-02-21
- Added classes: `lunaDB`, `lunaSession`, `lunaTools`

## [0.1.44-alpha] - 2010-02-21
- Started integrating the RDF RAP API

## [0.1.43-alpha] - 2010-02-20
- Updated CSS and JS files

## [0.1.41-alpha] - 2008-07-08
- Fixed a bug in the login page

## [0.1.40-alpha] - 2008-07-08
- Disabled cache for admin users

## [0.1.39-alpha] - 2008-07-08
- Fixed a bug in the `forminput` XSL template

## [0.1.38-alpha] - 2008-07-08
- Replaced `type` with `class` throughout the codebase
- Fixed the multiple selection bug

## [0.1.37-alpha] - 2008-07-07
- Updated the log system to correctly handle exception objects
- Fixed a bug in `luna.node.class`
- Working on `mod_edit_texts`

## [0.1.36-alpha] - 2008-07-07
- Only pages accessible to the current user are now loaded in the nav tree
- Nodes are now objects rather than arrays
- Working on `mod_edit_texts`

## [0.1.35-alpha] - 2008-07-06
- Updated copyright date
- Working on `mod_edit_texts`

## [0.1.34-alpha] - 2008-07-06
- `mod_journal` page now working; not yet fully RDF-compliant when a `log_id` is requested

## [0.1.33-alpha] - 2008-07-06
- Fixed empty variable problem in `luna.common.html.xsl`

## [0.1.32-alpha] - 2007-04-17
- Working on `mod_journal`

## [0.1.31-alpha] - 2007-04-08
- Fixed `mod_admin_users`

## [0.1.30-alpha] - 2007-04-05
- Renamed the mod class to `lunaMod`
- Fixed `luna::get_time_since()`

## [0.1.29-alpha] - 2007-04-02
- Added `luna::list_children()` public method (needs work)

## [0.1.28-alpha] - 2007-04-01
- Switched to PEAR MDB2 database abstraction layer
- Introduced try/catch exception system

## [0.1.27-alpha] - 2007-03-20
- Finished `mod_admin_mods`
- Started `mod_admin_users`

## [0.1.26-alpha] - 2007-03-19
- Added `lunaModel::get_nid_from_lid()`
- Finished `mod_admin` and `mod_admin_pages`

## [0.1.25-alpha] - 2007-03-18
- Added optional `DEBUG` constant in `luna.ini`
- Integrated the Singleton design pattern
- Finished `mod_online_users`

## [0.1.24-alpha] - 2007-03-12
- Restructured directories to a Drupal-like multi-site structure (`luna/luna.domains/`)

## [0.1.23-alpha] - 2007-03-11
- Dropped PHP 4 support
- Cleaned `lunaModel` class
- Output is now returned rather than printed
- Conformed main `luna` class to PHP 5
- Set `error_reporting(E_ALL ^ E_NOTICE)`

## [0.1.22-alpha] - 2007-03-11
- Fixed a bug in the luna constructor
- Replaced `rdf:Seq` with `rdf:Bag`
- Added `luna:current` and `luna:guest` attributes to the `foaf:Person` node

## [0.1.21-alpha] - 2007-03-10
- Restructured LunarSystem files; updated `INSTALL.txt`

## [0.1.20-alpha] - 2007-03-10
- RDF integration: renamed `printxml()` to `printRDF()`
- Added GPL licence header

## [0.1.19-alpha] - 2007-03-10
- Started RDF integration; `mod_admin_groups` done

## [0.1.18-alpha] - 2006-12-17
- Finished the `edit_text` module

## [0.1.17-alpha] - 2006-12-17
- Forced modules to be linked to at least one page

## [0.1.16-alpha] - 2006-12-12
- Added `AJAX` constant (boolean) in the luna constructor
- Added simple search module `luna.mod_search`
- Integrated `form.js` for jQuery AJAX

## [0.1.15-alpha] - 2006-12-12
- Mods can now also be stored in their own subfolder (`luna.MyMods/mymod/mymod.php`)
- Added `lunaModel::lid_is_taken()` to check for duplicate `lid` values

## [0.1.14-alpha] - 2006-12-11
- Added `ini_set('arg_separator.output', '&amp;')` for strict XHTML compliance

## [0.1.13-alpha] - 2006-12-03
- Fixed infinite loop bug in `load_users` SQL count query
- Added `luna::dbQuery()` to count SQL queries
- Added `queryID()` method and `USEXSLID` constant to the XML class

## [0.1.12-alpha] - 2006-12-01
- Renamed mods to ensure unique `lid` values

## [0.1.11-alpha] - 2006-11-30
- `luna::set_language()` now returns the language string

## [0.1.10-alpha] - 2006-11-01
- Set up English translation

## [0.1.9-alpha] - 2006-10-31
- Finished `mod_journal`; updated jQuery; updated default MySQL schema and data

## [0.1.8-alpha] - 2006-10-29
- Finished mods: `admin`, `admin_groups`, `admin_levels`, `admin_mods`, `admin_pages`, `admin_users`

## [0.1.7-alpha] - 2006-10-26
- Added `Node` class
- Added `lunaModel::insert()`, `link()`, `update()`, `unlink()`
- Finished `mod_admin_groups`

## [0.1.6-alpha] - 2006-10-24
- Added `luna::insert_action($nid)`

## [0.1.5-alpha] - 2006-10-21
- Updated scripts for the new database schema

## [0.1.4-alpha] - 2006-10-13
- Database schema updated

## [0.1.3-alpha] - 2006-10-08
- Database restructuration in progress
- Added `removeChild()` to the XML class
- Added `submit_all()` to the Mod class

## [0.1.2-alpha] - 2006-10-05
- Database restructuration in progress
- Replaced `code` with `lid` (literal identifier) throughout

## [0.1.1-alpha] - 2006-10-01
- Restructured the graphic interface

## [0.1.0-alpha] - 2006-10-01
- Optimized admin modules to be composable on a single page

## [0.0.18-alpha] - 2006-09-30
- Added `static_text` column to `luna_pages` table
- Finished `mod_edit_staticpages`
- Added `luna::purge_cache()`
- Optimized `LogViewer` module

## [0.0.17-alpha] - 2006-09-29
- Moved `admin_texts` mod under the `edition` node as `edit_texts`
- Added `mod_edit_staticpages`
- Renamed `admin_structure` to `admin_pages`
- Added the ability to associate a text block to a mod

## [0.0.16-alpha] - 2006-09-28
- Added `lunaTools::unrequest()` to destroy a user variable
- `mod_admin_texts` finished

## [0.0.15-alpha] - 2006-09-27
- Optimized list sorting (previous/next/first/last navigation)
- Added the ability to create new text entries
- Added `luna::remove_accents()` and `luna::prepare_lid()`
- Replaced DBX with jQuery

## [0.0.14-alpha] - 2006-09-26
- Fixed session ID propagation (onchange/onclick); site is now navigable without cookies

## [0.0.13-alpha] - 2006-09-24
- Started TinyMCE integration
- `lunaTools::sanitize()` now uses PEAR `HTML/Safe`

## [0.0.12-alpha] - 2006-08-24
- Added `output()` and `createEscapedElement()` methods to the XML class
- PHP 5: XML constructor now reports and locates malformed XML errors

## [0.0.11-alpha] - 2006-08-22
- Completed DBX (docking boxes) integration
- Optimized list output (sorting, order by)
- Cleaned demo `index.php` and corrected CSS image paths

## [0.0.10-alpha] - 2006-08-01
- Removed `PHPSESSID` from XML output to improve cacheability
- Restructured changelog

## [0.0.9-alpha] - 2006-07-16
- Changed default cache timeout to 7 days (was 2 hours)
- Added cache layer in `output()`: XSLT is skipped if XML is unchanged
- Added `md5()` method to the XML class
- Added cache layer in `loadPage()`
- Renamed `load_data()` to `loadPage()`
- Set default XML string in the XML class constructor

## [0.0.8-alpha] - 2006-07-13
- Fixed a typo in the levels mod; fixed 2 small bugs in the Log mod
- Added `/luna.Docs/sql` folder for base DB schemas
- The `luna` object is now passed by reference to mod classes

## [0.0.7-alpha] - 2006-07-11
- Site nav tree is now stored as an XML file, one per language; purgeable like cache
- Added `save()` method to the XML class
- Added `luna.XML` folder for XML trees; added `RDF_PATH` constant to `luna.ini`

## [0.0.6-alpha] - 2006-07-10
- Added `xsl_path` variable to page XML metadata
- Added `getLength()` method to the XML class

## [0.0.5-alpha] - 2006-07-09
- Added `getItem()` method to the XML class

## [0.0.4-alpha] - 2006-07-08
- Updated the default MySQL dump
- Added `PEAR.php` include to `luna.php`
- Moved `Log.php` include to top of `luna.php`

## [0.0.3-alpha] - 2006-07-07
- Added `die()` after database query errors
- Added `convert_to_unicode()` to the `luna` class
- Added `initialize()` call in the `Mod` class constructor
- Set `MYXSL_PATH` and added `luna.MyXSL/HTML.XSL` folder
- Set default `luna` gettext `bindtextdomain`
- CSS optimisation; 2 new images

## [0.0.2-alpha] - 2006-07-06
- Mods must now be placed in the `luna.myMods` folder
- Sanitized ini vars in `luna::load_ini()` and `luna::dbConnect()`

## [0.0.1-alpha] - 2006-07-04
- First release on SourceForge
