# Installation

> LunarSystem runs on **PHP 8.3 / MySQL 8.0** (0.5.0-alpha migrated PEAR MDB2 and
> the removed `mysql_*` extension to PDO / `pdo_mysql`). The Docker stack below
> builds it; a modern PHP install also works directly. See
> [security.md](security.md).

## Requirements

| Component | Version / notes |
|---|---|
| PHP | **8.3** |
| PHP extensions | `pdo_mysql`, `xsl`, `mbstring`, `gettext` |
| Web server | Apache 2 with `mod_rewrite` and `AllowOverride All` |
| Database | MySQL 8.0 (run with `sql_mode=""` for the legacy MyISAM column defaults; the Docker stack sets this) |
| Bundled libs | Cache_Lite, HTML_Safe, semsol/arc2 3.1.0 — vendored in `luna/luna.lib/`, no Composer needed |

## Option A — Docker (recommended)

The repo ships a working Docker stack: PHP 8.3 + Apache, and MySQL 8.0.

```bash
docker-compose up --build -d
```

Then wait ~15 seconds for MySQL to initialise and open
**http://localhost:8080**.

Log in as **`admin@lunarsystem.local`** / password **`luna`**.

What the stack does (see [docker-compose.yml](../docker-compose.yml) and
[Dockerfile](../Dockerfile)):

- **app** — builds `php:8.3-apache`, installs the `pdo_mysql`, `xsl`, `gettext`, and
  `mbstring` extensions, enables `mod_rewrite`, and sets `AllowOverride All`.
  Mounts the repo at `/var/www/html` and publishes port **8080 → 80** (bound to
  `127.0.0.1` only).
- **db** — `mysql:8.0` started with `--sql_mode=""` (so the legacy column
  defaults import cleanly), seeded automatically by mounting
  [luna.mysql.sql](../luna/luna.sql/luna.mysql.sql) into
  `/docker-entrypoint-initdb.d/`. Database `lunadb`, user/pass `luna`/`luna`.
  Published on host port **3307** (to avoid clashing with a local MySQL on 3306).

> **Semantic-web services (optional).** Beyond the CMS itself, `docker-compose.yml`
> defines two more services — **ontop** (a virtual SPARQL endpoint over the
> unchanged MySQL, host port **8081**) and **oxigraph** (a triplestore, host port
> **7879**) — and the app gains a `SPARQL_ENDPOINT` env var. They are **not needed**
> to run the CMS itself: `docker-compose up -d` will start them, but the site runs
> entirely on **app + db**. To run only the core stack, use `docker-compose up -d
> app db`. See [linked-data.md](linked-data.md) for what the extra services do and
> how to use them.

The default domain's `luna.default/ini/db.ini` already points at the Docker
`db` host, so no config editing is required.

### Reset the database

The seed import only runs on first init (empty data volume). To re-seed:

```bash
docker-compose down -v   # drops the db_data volume
docker-compose up --build -d
```

## Option B — Manual setup

1. Install Apache 2 + PHP 8.3 with the `pdo_mysql`, `xsl`, `mbstring`, and
   `gettext` extensions; enable `mod_rewrite` and set `AllowOverride All` for the
   document root.
2. Create a MySQL database and import the schema + seed data:
   ```bash
   mysql -u <user> -p <database> < luna/luna.sql/luna.mysql.sql
   ```
   On MySQL 5.7/8, run the server with `sql_mode=""` so the legacy zero-date /
   column defaults in the dump import without strict-mode errors.
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
