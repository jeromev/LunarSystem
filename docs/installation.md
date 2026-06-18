# Installation

> LunarSystem runs **only on PHP 5.3–5.6**. It uses the `mysql_*` extension
> (removed in PHP 7) and the PEAR MDB2 library. The Docker setup below pins a
> compatible stack; running on a modern PHP directly will not work. See
> [security.md](security.md).

## Requirements

| Component | Version / notes |
|---|---|
| PHP | **5.3–5.6** |
| PHP extensions | `mysql` (or `mysqli`), `xsl`, `mbstring`, `gettext` |
| Web server | Apache 2 with `mod_rewrite` and `AllowOverride All` |
| Database | MySQL 5.x (5.7 works with `sql_mode=""`; MySQL 8 needs schema edits) |
| Bundled libs | PEAR MDB2, PEAR Log, Cache_Lite, ARC2 — all vendored in `luna/luna.lib/`, no Composer/PEAR install needed |

## Option A — Docker (recommended)

The repo ships a working Docker stack: PHP 5.6 + Apache, and MySQL 5.7.

```bash
docker-compose up --build -d
```

Then wait ~15 seconds for MySQL to initialise and open
**http://localhost:8080**.

Log in as **`admin@lunarsystem.local`** / password **`luna`**.

What the stack does (see [docker-compose.yml](../docker-compose.yml) and
[Dockerfile](../Dockerfile)):

- **app** — builds `php:5.6-apache`, installs the `mysql`, `mysqli`, `xsl`, and
  `gettext` extensions, enables `mod_rewrite`, and sets `AllowOverride All`.
  Mounts the repo at `/var/www/html` and publishes port **8080 → 80**.
- **db** — `mysql:5.7` started with `--sql_mode=""` (so the legacy
  `TYPE=MyISAM` schema imports cleanly), seeded automatically by mounting
  [luna.mysql.sql](../luna/luna.sql/luna.mysql.sql) into
  `/docker-entrypoint-initdb.d/`. Database `lunadb`, user/pass `luna`/`luna`.
  Published on host port **3307** (to avoid clashing with a local MySQL on 3306).

The default domain's `luna.default/ini/db.ini` already points at the Docker
`db` host, so no config editing is required.

### Reset the database

The seed import only runs on first init (empty data volume). To re-seed:

```bash
docker-compose down -v   # drops the db_data volume
docker-compose up --build -d
```

## Option B — Manual setup

1. Install Apache 2 + PHP 5.3–5.6 with the `mysql`, `xsl`, `mbstring`, and
   `gettext` extensions; enable `mod_rewrite` and set `AllowOverride All` for the
   document root.
2. Create a MySQL database and import the schema + seed data:
   ```bash
   mysql -u <user> -p <database> < luna/luna.sql/luna.mysql.sql
   ```
   On MySQL 8, first rewrite `TYPE=MyISAM` → `ENGINE=MyISAM` in the dump.
3. Configure the database connection: copy
   `luna/luna.domains/luna.default/ini/db.example.ini` to `db.ini` and fill in
   `driver`, `username`, `password`, `host`, `database`. See
   [configuration.md](configuration.md).
4. Make `luna/luna.domains/<your-domain>/cache/` writable by the web server.
5. Point a vhost at the repo root. The root [`.htaccess`](../.htaccess) routes
   every non-file request through `index.php?path=<alias>` — confirm
   `AllowOverride All` so it takes effect.
6. Open the site. It resolves the active domain from `HTTP_HOST`; if no matching
   `luna.domains/<host>/` exists it uses `luna.default/`.

## Adding a site-specific domain

To serve a real hostname with its own settings, create:

```
luna/luna.domains/<hostname>/ini/luna.ini
luna/luna.domains/<hostname>/ini/db.ini
luna/luna.domains/<hostname>/cache/     (writable)
luna/luna.domains/<hostname>/xsl/        (optional theme override)
```

Domain resolution is automatic — the presence of `ini/luna.ini` under a
directory matching the host is what selects it. See
[configuration.md](configuration.md#domain-resolution).

## Output formats

Any page URL also serves the raw model: append `?output=xml`, `?output=json`, or
`?output=n3` to receive RDF/XML, RDF/JSON, or N-Triples instead of HTML. See
[templating.md](templating.md#non-html-output).

## First steps after install

1. Log in as the seeded admin (`admin@lunarsystem.local` / `luna`) and **change
   the password** immediately.
2. Edit site settings under **Admin** (`mod_admin`): site name, description,
   languages, timezone.
3. Create pages and content under **Admin → Pages** and **Edit Texts**.
