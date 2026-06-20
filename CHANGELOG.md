# Changelog

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
