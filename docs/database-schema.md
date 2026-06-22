# Database Schema

The full schema and seed data live in
[luna/luna.sql/luna.mysql.sql](../luna/luna.sql/luna.mysql.sql). Table names are
not hard-coded — they are mapped through the `[DBtables]` section of
`luna.ini` (see [configuration.md](configuration.md)), but the defaults are the
`luna_*` names below.

> All tables use `ENGINE=MyISAM`. The schema imports cleanly on MySQL 8.0.

## The graph core

Three tables form a generic node/edge graph. Everything else hangs off it.

### `luna_nodes` — every entity is a node
The central table. One row per user, group, level, page, mod, or text.

| Column | Type | Meaning |
|---|---|---|
| `nid` | `int unsigned` PK, auto-inc | Node ID (the graph vertex identity) |
| `lid` | `varchar(255)` | *Literal identifier* — unique human-readable slug (`admin`, `root`, `login`) |
| `tid` | `int unsigned` | Type — FK into `luna_types.id` |
| `parent_nid` | `int unsigned` | Hierarchical parent (page tree); maps to `schema:isPartOf` |
| `is_active` | `tinyint(1)` | Soft on/off flag |

### `luna_nodes_map` — every relationship is an edge
A single edge table used for **all** relationships in the system.

| Column | Type | Meaning |
|---|---|---|
| `id` | `int unsigned` PK | Edge ID |
| `nid1` | `int unsigned` | Source node |
| `nid2` | `int unsigned` | Target node |

The *meaning* of an edge comes from the types of its endpoints. Examples seen in
the seed data:
- user → group (a user's group memberships)
- group → level (the levels a group grants)
- page → mod (which modules render on a page)
- mod → level (the level required to see a mod)
- page → level (the level required to view a page)

### `luna_types` — the class registry
Defines the handful of node types.

| Column | Type | Meaning |
|---|---|---|
| `id` | `int unsigned` PK | Type ID (referenced by `luna_nodes.tid`) |
| `lid` | `varchar(255)` | Type slug: `user`, `group`, `level`, `text`, `page`, `mod` |
| `page_nid` | `int unsigned` | The admin page that manages this type |

## Content

### `luna_texts` — content blocks (per language)
The actual editable content, one row per language per text node.

| Column | Type | Meaning |
|---|---|---|
| `id` | `int unsigned` PK | — |
| `nid` | `int unsigned` | FK to the text node in `luna_nodes` |
| `title` | `tinytext` | Title |
| `lang` | `char(2)` | Language code (`en`, `fr`, …) |
| `content_html` | `longtext` | HTML body (FULLTEXT-indexed for search) |

## Users, sessions, audit

### `luna_users` — user metadata
Profile/auth data for nodes of type `user`. The user's *identity* (nid, lid) is
in `luna_nodes`; this table holds the rest.

| Column | Type | Meaning |
|---|---|---|
| `id` | `int unsigned` PK | — |
| `nid` | `int unsigned` | FK to the user node |
| `firstname` / `lastname` | `varchar(255)` | Name |
| `password` | `varchar(255)` | bcrypt hash (`password_hash`); legacy seed rows are MD5 and upgrade to bcrypt on first login (see [security.md](security.md)) |
| `regis_time` / `last_time` | `int` | Registration / last-seen timestamps |
| `last_url` | `varchar(255)` | Last visited URL |
| `newpasswd` | `varchar(32)` | Password-reset token |
| `login_attempts` | `tinyint(1)` | Failed-login counter |
| `lang` | `varchar(5)` | Preferred language |

> The user's **email** is stored as the node's `lid` in `luna_nodes`, not in this
> table.

### `luna_sessions` — DB-backed PHP sessions
Written by `lunaSession` via a custom `session_set_save_handler`.

| Column | Type | Meaning |
|---|---|---|
| `session_id` | `varchar(32)` PK | PHP session ID |
| `session_user_nid` | `int` | The logged-in user node (or guest) |
| `session_start` / `session_time` | `int` | Created / last-active timestamps |
| `session_ip` | `varchar(8)` | Client IP (packed/encoded, phpBB-style) |
| `session_url` | `varchar(255)` | Current URL |
| `session_logged_in` | `tinyint(1)` | Authenticated flag |
| `session_lang` | `varchar(5)` | Session language |
| `session_useragent` | `varchar(255)` | UA string (hijack check) |

### `luna_actions` — audit trail
Who touched which node, when. Joined by the model to attach author/timestamp
metadata to content.

| Column | Type | Meaning |
|---|---|---|
| `id` | `int unsigned` PK | — |
| `nid` | `int unsigned` | The affected node |
| `unid` | `int unsigned` | The acting user node |
| `ntime` | `int unsigned` | Timestamp |

## Configuration & logging

### `luna_config` — runtime key/value config
Read at bootstrap by `lunaTools::load_config()` and editable from the admin UI.

| Column | Type | Meaning |
|---|---|---|
| `id` | `int unsigned` PK | — |
| `name` | `varchar(255)` | Key |
| `value` | `varchar(255)` | Value |

Seeded keys include: `sitename`, `site_desc`, `author`, `general_email`,
`version`, `keywords`, `timezone`, `langs` (a single comma-separated string,
seeded as `en, fr` — split on `,` by `lunaTools::load_config`), `session_length`
(`604800` = 7 days), `cache_timeout` (`3600`), `startdate`, `root_module`,
`disable` / `disable_txt`.

### `luna_logs` — error log sink
Written by `lunaLog::log()` via a direct PDO `INSERT`; the `PEAR_LOG_*` constants set the `priority` column.

| Column | Type | Meaning |
|---|---|---|
| `id` | `int(11)` PK, auto-inc | The only id PK declared **without** `unsigned` (every other table uses `int unsigned`) |
| `logtime` | timestamp | When |
| `ident` | `varchar(16)` | Source identifier |
| `priority` | `int` | PEAR_LOG_* level |
| `message` | `text` | JSON-encoded exception / message payload |

### `luna_nodes_seq` — id sequence
A single-row counter table (`sequence`) used to allocate the next `nid`. Seeded
to `33` (the seed data uses nodes 1–32).

## Seed data

The dump ships a working site:

- **Admin user** — `nid=1`, `lid="admin@lunarsystem.local"`, name "Admin Luna",
  password MD5 `ba8a48b0e34226a2992d871c65600a7c` (plaintext: **`luna`**).
- **Guest user** — `nid=2`, `lid="guest"`, `is_active=0` (the anonymous identity).
- **Three groups** — default, admin, edition.
- **Three levels** — public, admin, edition.
- **System pages** — `root`, `admin`, `admin_groups`, `admin_levels`,
  `admin_mods`, `admin_pages`, `admin_users`, `edition`, `edit_texts`, `journal`,
  `login`, `logout`, `node`.
- **Six type rows** and the `luna_nodes_map` edges wiring users→groups→levels and
  pages→mods→levels.

## Entity-relationship sketch

```
                 luna_types
                  (class)
                     ▲ tid
                     │
   parent_nid   ┌────┴─────┐        nid1 / nid2
   (self-ref) ◄─┤ luna_nodes ├──────────────────► luna_nodes_map
                └────┬─────┘  (generic edges:        (graph edges)
                     │         user→group→level,
        ┌────────────┼────────────┐  page→mod→level)
        │ nid        │ nid        │ nid
        ▼            ▼            ▼
   luna_users   luna_texts   luna_actions
   (profile)    (content)    (audit: nid + unid + ntime)

   luna_sessions ── session_user_nid ──► (user node)
   luna_config   (k/v)     luna_logs (errors)   luna_nodes_seq (counter)
```
