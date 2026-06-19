# Changelog

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
