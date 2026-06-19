# Architecture

This document traces a single HTTP request end-to-end and describes the core
classes that handle it.

## The entry point

All requests are rewritten to [`index.php`](../index.php) by the root
[`.htaccess`](../.htaccess) (clean-URL rewriting). The entry point is tiny:

```php
require_once './luna/luna.php';
if ($luna = luna::singleton()) {
    if ($luna->load_mods()) {
        die($luna->transform());
    }
}
```

Three phases, in order:

1. **`luna::singleton()`** — bootstrap (constructor).
2. **`load_mods()`** — find, instantiate, and run the page's modules.
3. **`transform()`** — serialise the model to RDF/XML and apply XSLT.

The whole system is built from **singletons**: `luna`, `lunaModel`,
`lunaSession`, and every mod. Cloning is explicitly forbidden (`__clone()`
triggers a fatal error).

## Core classes

| Class | File | Responsibility |
|---|---|---|
| `luna` | [luna/luna.php](../luna/luna.php) | Bootstrap, routing, mod loading, render orchestration |
| `lunaModel` | [luna/luna.classes/luna.model.class.php](../luna/luna.classes/luna.model.class.php) | The in-memory RDF triple store; SQL→RDF; XSLT transform |
| `lunaDB` | [luna/luna.classes/luna.db.class.php](../luna/luna.classes/luna.db.class.php) | PEAR MDB2 database wrapper |
| `lunaSession` | [luna/luna.classes/luna.session.class.php](../luna/luna.classes/luna.session.class.php) | DB-backed session handler; builds the `$user` object |
| `lunaTools` | [luna/luna.classes/luna.tools.class.php](../luna/luna.classes/luna.tools.class.php) | Sanitisation, caching, i18n, URL building, ACL, error pages |
| `lunaLog` / `lunaException` | [luna/luna.classes/luna.log.class.php](../luna/luna.classes/luna.log.class.php) | Exception type and DB error logging (PEAR Log) |

## Phase 1 — Bootstrap

The `luna::__construct()` method ([luna.php:192](../luna/luna.php#L192)) runs a
fixed sequence wrapped in a `try`/`catch (lunaException)`. Most steps throw a
`lunaException` on failure (caught, logged, then `die()`); the earliest steps
(site path, ini load, core-class includes) `die()` or `trigger_error()` directly
with a message instead ([luna.php:205-215](../luna/luna.php#L205)):

1. **Environment setup** ([luna.php:20-40](../luna/luna.php#L20)) — `define('NOW', time())`,
   set a default timezone (honour an existing `php.ini` `date.timezone`, else UTC),
   disable `register_globals`/`display_errors`, enable `session.use_trans_sid`, set
   `error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT)`, and a
   `magic_quotes` compatibility guard. (`display_errors` is turned back **on** for
   admins later when `DEBUG` is set — [luna.php:219](../luna/luna.php#L219).)
2. **Resolve the site path** (`set_site_path()`, [luna.php:320](../luna/luna.php#L320)) —
   walks `HTTP_HOST` segment by segment looking for a matching directory under
   `luna/luna.domains/`, falling back to `luna.default/`. This logic is adapted
   verbatim from **Drupal 5.1's `conf_path()`**. Defines the `SITEPATH` constant
   and sets the `site_uri` / `site_relative_url` static properties.
   (`set_requested_path()` at [luna.php:309](../luna/luna.php#L309) is a *different*
   method — it normalises the requested URL path; see step 9.)
3. **Load the ini** (`load_ini()`, [luna.php:635](../luna/luna.php#L635)) —
   parses `<domain>/ini/luna.ini` and turns `[Paths]` and `[Constantes]` entries
   into PHP constants. See [configuration.md](configuration.md).
4. **Require the core classes** — log, tools, db, session, model.
5. **Sanitise inputs** — `lunaTools::sanitize_inputs()` cleans the superglobals.
6. **Cache + AJAX detection** — `lunaTools::check_cache()`; define `AJAX` from
   the `X-Requested-With` header.
7. **Database** — `lunaDB::prepare()` then `lunaDB::connect()` (PEAR MDB2).
8. **Config** — `lunaTools::load_config()` reads the `luna_config` table.
9. **Routing** — `set_requested_path()` normalises the requested URL path.
10. **Session** — `lunaSession::singleton()->start()` (DB-backed; builds `$user`).
11. **Language** — `lunaTools::set_language()` negotiates locale (gettext).
12. **Output format** — `lunaTools::set_output_format()` (`html`/`xml`/`json`/`n3`).
13. **Model** — `lunaModel::singleton()` builds the triple store.
14. **Admin cache bypass** — if the user holds `level_admin`, caching is disabled
    and `IS_ADMIN` is defined true.
15. **Resolve the page** — `get_page_node_from_alias($path)` maps the URL to a
    page node; a miss raises a **404**. Defines `PAGENID` and `PAGELID`.
16. **Authorise** — `check_privileges()`; unauthorised users are redirected to
    `login`.
17. **Load page texts** — `load_texts(0, PAGENID)` merges the page's content
    blocks into the model. (Under `?sparql=1` this instead calls
    `load_texts_sparql(PAGENID)`, fetching the same content over SPARQL —
    [luna.php:260-263](../luna/luna.php#L260); see [linked-data.md](linked-data.md).)
18. **Collect page metadata** — site name, description, version, author, language,
    etc. into `luna::$data`.

After the constructor, the model holds the page node, its texts, the current
user, and site metadata.

## Phase 2 — Load mods

`load_mods()` ([luna.php:387](../luna/luna.php#L387)) is where a page gets its
content. A single SQL query joins the triple tables to find every mod that is
**(a)** linked to the current page **and (b)** linked to an access level the
current user holds:

```
pm.nid1 = PAGENID AND pm.nid2 = m.nid     -- page → mod
AND ml.nid1 = m.nid AND ml.nid2 = l.nid   -- mod → level
AND tm.lid = 'mod'  AND m.tid = tm.id
AND tl.lid = 'level' AND l.tid = tl.id
AND m.is_active = 1 AND l.is_active = 1
```

For each matching mod ([luna.php:427-494](../luna/luna.php#L427)):

1. Skip it unless the user actually holds the mod's level
   (`$session->user->levels[$level_nid]`).
2. Locate the mod's PHP file via a cascading lookup (domain `mods/` dir first,
   then the built-in `luna.mods/`).
3. `require_once` it and call its `singleton()`.
4. On form submission (`submit`/`batch_submit` present), dispatch to the mod's
   `submit()` and — based on `$_POST['mode']` — `submit_add()`,
   `submit_modify()`, or `submit_delete()`.
5. Call the mod's `load()` to inject its data into the model.
6. Mark the mod node `is_loaded` in the model.

Finally it merges any flash messages into the model. The mod system is detailed
in [modules.md](modules.md).

> **AJAX short-circuit:** at the start of `transform()`
> ([luna.php:574](../luna/luna.php#L574)), if `AJAX` is true the request `die()`s
> — XSLT is skipped and only the model-population side effects of `load_mods()`
> persist. `load_mods()` itself only special-cases AJAX to skip the post-submit
> `OPTIMIZE TABLE` ([luna.php:483](../luna/luna.php#L483)).

## Phase 3 — Transform

`transform()` ([luna.php:512](../luna/luna.php#L512)) assembles the remaining
view data and renders:

1. Merge in the current user, available languages, available output formats, the
   `luna::$data` metadata, the i18n vocabulary, and the raw `$_REQUEST`.
2. If `output_format != html`, call `lunaModel::dump($format)` which serialises
   the model with **ARC2** and exits (RDF/XML, JSON, or N-Triples).
3. Otherwise pick an XSLT stylesheet by a **cascading file lookup** keyed on the
   page `lid` and output format ([luna.php:580-613](../luna/luna.php#L580)):
   domain override → built-in, page-specific → `default`.
4. Call `lunaModel::transform($XSLfile)`, which serialises the model to RDF/XML
   via ARC2 and runs PHP's `XSLTProcessor`. The result is cached (Cache_Lite)
   keyed on a hash of the model.

The returned string is the HTTP response. See [templating.md](templating.md) for
the stylesheet lookup order and the RDF/XML the templates expect.

> **Semantic-web extension (experiment branch):** on this branch the model
> additionally exposes a JSON-LD projection — `?output=jsonld` routes through
> `lunaModel::to_jsonld()`, and the HTML `<head>` carries an embedded
> `<script type="application/ld+json">` block. The read path can also flow
> through a SPARQL endpoint under `?sparql=1` (routing, level-based ACL, and
> page content), with no change to the archival path described above. See
> [linked-data.md](linked-data.md).

## Request lifecycle diagram

```
HTTP request
   │  (.htaccess rewrite → index.php?path=…)
   ▼
luna::singleton()  ── bootstrap ──────────────────────────────┐
   │  • pick domain (HTTP_HOST → luna.domains/…)               │
   │  • load luna.ini → constants                              │
   │  • connect MySQL (PEAR MDB2)                              │
   │  • start DB-backed session, build $user (groups/levels)   │
   │  • negotiate language + output format                     │
   │  • build lunaModel (triple store)                         │
   │  • resolve URL → page node (404 if none)                  │
   │  • check_privileges (→ login if unauthorised)             │
   │  • load page texts                                        │
   ▼                                                           │
load_mods()                                                    │
   │  • SQL: mods linked to page ∩ user's levels               │
   │  • for each mod: require, singleton, submit*?, load()     │
   ▼                                                           │
transform()                                                    │
   │  • merge user / langs / formats / data / request          │
   │  • output=xml|json|n3 → ARC2 dump() & exit                │
   │  • else: pick XSLT stylesheet (cascading lookup)          │
   │  • lunaModel: model → RDF/XML → XSLTProcessor → HTML      │
   ▼                                                           │
HTTP response  ◄───────────────────────────────────────────────┘
```

## Error handling

Errors are exceptions of type `lunaException`. They are caught at the top of
each phase and passed to `lunaLog::log()`
([luna.log.class.php:31](../luna/luna.classes/luna.log.class.php#L31)). The two
output paths are **mutually exclusive**: when `DEBUG` *and* `IS_ADMIN` are set,
`log()` dumps the exception to screen via `lunaTools::debug()` (which `die()`s),
so the DB write is skipped; otherwise it writes a serialised record (including
`$_SERVER` and the session — see [security.md](security.md)) to the `luna_logs`
table via PEAR Log's `mdb2` handler. HTTP error pages (404, etc.) are produced
by `lunaTools::raise_error_page()`.
