# Configuration

LunarSystem is **multi-site**: a single codebase serves many hostnames, each with
its own configuration directory under
[luna/luna.domains/](../luna/luna.domains/). Configuration is split between two
INI files per domain (`luna.ini`, `db.ini`) and the runtime `luna_config` table.

## Domain resolution

At bootstrap, `luna::set_site_path()`
([luna.php:327](../luna/luna.php#L327)) — logic adapted from **Drupal 5.1's
`conf_path()`** — walks `$_SERVER['HTTP_HOST']` from most- to least-specific,
looking for a directory under `luna.domains/` that contains `ini/luna.ini`. The
first match wins; if none matches it falls back to
`luna.domains/luna.default/`.

For `www.example.org:8080/sub/` it tries, in order:
`8080.www.example.org.sub`, `www.example.org.sub`, `example.org.sub`,
`org.sub`, … `example.org`, `org`, then `luna.default`.

The resolved directory becomes the `SITEPATH` constant.

## Domain directory layout

```
luna.domains/<domain>/
  ini/
    luna.ini          # paths, table names, constants  (required — its presence selects the domain)
    db.ini            # database credentials
    luna.example.ini  # template (default domain only)
    db.example.ini    # template (default domain only)
  cache/              # Cache_Lite output cache (must be web-server-writable)
  xsl/                # per-domain XSLT overrides (optional; empty falls back to built-ins)
  mods/               # per-domain mods (optional)
```

One domain ships in the repo:

| Domain | Role |
|---|---|
| `luna.default/` | Fallback config, wired for local/Docker development |

## `luna.ini`

Parsed by `luna::load_ini()` ([luna.php:641](../luna/luna.php#L641)). Every
`[Paths]` value becomes a constant prefixed with the luna root path; every
`[Constantes]` value becomes a bare constant. Example
(`luna.default/ini/luna.ini`):

```ini
[Paths]
MODS_PATH    = "luna.mods/"      ; built-in mods directory
CLASSES_PATH = "luna.classes/"   ; core classes
LOCALE_PATH  = "luna.locale/"    ; gettext translations
XSL_PATH     = "luna.xsl/"       ; built-in stylesheets

[DBtables]                        ; logical name → physical table
CONFIG    = "luna_config"
LOGS      = "luna_logs"
CLASSES   = "luna_types"
NODES     = "luna_nodes"
NODES_MAP = "luna_nodes_map"
SESSIONS  = "luna_sessions"
ACTIONS   = "luna_actions"
TEXTS     = "luna_texts"
USERS     = "luna_users"

[Constantes]
ANONYMOUS  = "guest"   ; lid of the anonymous user
PERPAGE    = "20"      ; default pagination size
CACHE      = 0         ; 1 = enable Cache_Lite output cache
INCLUDEPATH = ""       ; extra PHP include path(s)
CLEAN_URLS = 1         ; 1 = /path style, 0 = ?path= query style
DEBUG      = 1         ; 1 = display errors + dump logs to admins
```

The `[DBtables]` indirection is why the code never hard-codes table names — it
calls `luna::get_ini('DBtables', 'NODES')` etc. You could repoint the CMS at
differently-named tables without touching code.

## `db.ini`

Read by `lunaDB::prepare()` ([luna.db.class.php](../luna/luna.classes/luna.db.class.php))
to build a PDO DSN. Copy `db.example.ini` to `db.ini` and fill in:

```ini
driver   = "mysql"
username = "username"
password = "password"
host     = "localhost"
database = "database"
```

The `luna.default/ini/db.ini` checked into the repo holds **Docker defaults**
(`host = db`, user/pass/db = `luna`/`luna`/`lunadb`).

## `SPARQL_ENDPOINT` (semantic-web layer, optional)

The app reads three extra settings, all defined as constants in
[luna.php](../luna/luna.php) (via `getenv()`) and wired through `docker-compose.yml`:

- **`SPARQL_ENDPOINT`** — the read endpoint, defaulting to the Oxigraph
  triplestore (`http://oxigraph:7878/query`); set it to `http://ontop:8080/sparql`
  to read live over MySQL through Ontop instead (no code change).
- **`SPARQL_UPDATE_ENDPOINT`** — where content writes mirror to
  (`http://oxigraph:7878/update`, best-effort).
- **`SPARQL_READS`** — whether the read path uses SPARQL at all (default `1`);
  `SPARQL_READS=0`, or `?sparql=0` on any URL, forces the SQL read path.

None of these are used by the archival CMS — see [linked-data.md](linked-data.md).

## `luna_config` (runtime config)

Editable site settings live in the `luna_config` table (see
[database-schema.md](database-schema.md)), loaded at bootstrap by
`lunaTools::load_config()` and editable from **Admin** (`mod_admin`). These are
*content-level* settings (site name, description, author, email, keywords,
timezone, languages, session length, cache timeout) as opposed to the
*infrastructure-level* settings in the INI files.

## Where settings live — summary

| Setting kind | Lives in | Edited by |
|---|---|---|
| Domain selection | directory name under `luna.domains/` | filesystem |
| Paths & table mapping | `luna.ini` `[Paths]` / `[DBtables]` | hand-edit |
| Behaviour flags (cache, debug, clean URLs) | `luna.ini` `[Constantes]` | hand-edit |
| DB credentials | `db.ini` | hand-edit |
| Site name, languages, timezone, etc. | `luna_config` table | admin UI |
| Theme | `luna.domains/<domain>/xsl/` or built-in `luna.xsl/` | XSLT files |
