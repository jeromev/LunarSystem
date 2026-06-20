# LunarSystem

A PHP/MySQL CMS (v0.8.12-alpha, circa 2006–2010) that models all content as **RDF triples** and renders pages through **XSLT transformations**. Originally developed by Odradek / lunarsystem.org.

> ⚠️ **Study / experiment artifact — run it on `localhost` only.** This is alpha-grade
> 2006–2010 code revived for learning. A 2026 hardening pass (0.6.9–0.8.6) closed the
> major issues — bcrypt passwords, CSRF tokens, session-fixation defence, SQLi fixes,
> security headers, a per-IP login throttle — but residual gaps remain (the admin
> modules have no per-target authorization, and the **SPARQL write endpoint is
> unauthenticated**), so keep it on `localhost` and off the public internet. See
> [docs/security.md](docs/security.md).
> The Docker stack binds every port to `127.0.0.1`; **do not change that or otherwise
> expose `8080` / `7879` / `8081` / `3307` to a public or untrusted network.** It is not
> hardened for any networked or production deployment. See [docs/security.md](docs/security.md).

> **Now an RDF-native Semantic Web CMS.** The original 2006–2010 archival CMS has been extended into a *real* Semantic Web CMS: a frozen URI policy and a vocabulary mapping onto schema.org / Dublin Core / SIOC / FOAF / PROV-O, a JSON-LD projection, and — as of 0.3.3-alpha — a **triplestore-backed read/write loop**. Every content write mirrors into **Oxigraph** via SPARQL `UPDATE` (a generic write-through in the model's CRUD), and the read path (routing, access control, texts) is served **from the triplestore by default**, with MySQL as the system of record and an automatic SQL fallback (`?sparql=0` to bypass). The same SPARQL can also be served by **Ontop** (a virtual endpoint over the unchanged MySQL) with no app change. The untouched archival CMS is preserved on the **`legacy`** branch (tag `v0.2.14-alpha`). See **[docs/linked-data.md](docs/linked-data.md)** for the full design and **[docs/roadmap.md](docs/roadmap.md)** for what remains.

## Quick start (Docker)

```bash
docker-compose up --build -d
```

Wait ~15 seconds for MySQL to initialise, then open **http://localhost:8080**.

> The Docker stack also starts a **triplestore** (Oxigraph on host port `7879`) and a virtual **SPARQL endpoint** (Ontop on host port `8081`). The read path is served from the triplestore by default; append `?sparql=0` to any URL to read from MySQL instead, or set `SPARQL_ENDPOINT=http://ontop:8080/sparql` to read live through Ontop. See [docs/linked-data.md](docs/linked-data.md).

Log in as **`admin@lunarsystem.local`** with password **`luna`**. (These are demo credentials shipped in the seed data — change them before exposing the app anywhere.)

> **New here?** Follow the ~10-minute hands-on tour in **[docs/try-it.md](docs/try-it.md)**.

> MySQL is exposed on host port `3307` to avoid conflicts with a local MySQL on `3306`.

## Manual setup

Requirements: Apache 2 + `mod_rewrite`, **PHP 8.3** (the tested stack), MySQL 8.0, PHP extensions: `pdo_mysql`, `xsl`, `mbstring`, `gettext`.

> As of 0.5.0-alpha the app runs on **PHP 8.3 / MySQL 8.0** via PDO (`pdo_mysql`); the `mysql_*` + PEAR MDB2 dependency that previously blocked PHP 7+ was removed.

1. Copy `luna/luna.domains/luna.default/ini/db.example.ini` → `db.ini` and fill in your credentials.  
   *(The file at `luna/luna.domains/luna.default/ini/db.ini` already contains Docker defaults.)*
2. Import `luna/luna.sql/luna.mysql.sql` into your MySQL database.
3. Ensure the `luna/luna.domains/<your-domain>/cache/` directory is writable by the web server.
4. The root `.htaccess` handles clean URL rewriting — confirm `AllowOverride All` is set in Apache.
5. Open the site in a browser.

### Domain configuration

The CMS detects the active domain by walking `$_SERVER['HTTP_HOST']` and looking for a matching directory under `luna/luna.domains/`. If none is found it falls back to `luna/luna.domains/luna.default/`.

To add a site-specific config: create `luna/luna.domains/<hostname>/ini/luna.ini` and `db.ini`.

## Output formats

Append `?output=xml`, `?output=json`, `?output=n3`, or `?output=jsonld` to any page URL to receive the raw RDF model instead of HTML. (A JSON-LD block is also embedded in every HTML page's `<head>`; see [docs/linked-data.md](docs/linked-data.md).)

## Project structure

```
index.php                      Entry point
.htaccess                      Clean URL rewrite rules
luna/
  luna.php                     Main luna class (bootstrap, routing, XSLT rendering)
  luna.classes/
    luna.model.class.php       RDF model (in-memory triple store, ARC2, XSLT)
    luna.db.class.php          Database wrapper (PDO / pdo_mysql)
    luna.session.class.php     DB-backed session handler
    luna.tools.class.php       Utilities (sanitisation, URL building, i18n, ACL)
    luna.log.class.php         Error logging (direct PDO INSERT)
  luna.mods/                   Pluggable page modules (admin, journal, node, …)
  luna.xsl/luna.html.xsl/      Built-in XSLT templates (HTML output)
  luna.lib/                    Vendored libraries: semsol/arc2 3.1.0, Cache_Lite, HTML_Safe (PEAR Log base kept for constants)
  luna.domains/
    luna.default/              Fallback site configuration (used for local/Docker)
  luna.sql/luna.mysql.sql      Database schema + seed data
  luna.locale/                 gettext translations (en_US, fr_FR)
css/                           Stylesheets
js/                            luna.js (admin UI behaviours; dependency-free, no jQuery)
semantic/                      Semantic-web layer (SPARQL over the unchanged MySQL)
  ontop/                       R2RML mapping + Ontop image (virtual SPARQL); Oxigraph dump
```

## Known issues

A 2026 hardening pass (0.6.9–0.8.12-alpha) closed the major security issues; a second
adversarial review graded the result *ship-with-low-risk*. See [docs/security.md](docs/security.md)
for the full timeline and verdict. The residual, by-design limitations:

| Issue | Impact | Notes |
|---|---|---|
| **Unauthenticated SPARQL endpoint** | Security | Oxigraph/Ontop expose an unauthenticated SPARQL write endpoint; it is bound to `127.0.0.1` only — keep it internal |
| **No per-target admin authz** | Security | Admin modules don't re-check per-target access; safe as shipped (single `level_admin` tier) but unsafe if admin is delegated to a lower level |
| **Per-IP login throttle** | Security | Per-IP only (no per-account lockout, to avoid account enumeration); bypassable by IP rotation |
| **Legacy model / hardening residue** | Design | Unsalted MD5 hashes upgrade to bcrypt transparently on next login; flat group→level authz; the CSP still allows `'unsafe-inline'` |

The Docker stack boots cleanly on **PHP 8.3 + MySQL 8.0** (0.5.0-alpha migrated the DB layer from PEAR MDB2 to PDO; an earlier pass had fixed the schema's obsolete `TYPE=MyISAM` → `ENGINE=MyISAM`). See the [changelog](CHANGELOG.md) and [docs/installation.md](docs/installation.md).

## Documentation

Full technical documentation lives in [docs/](docs/):

- [Overview](docs/overview.md) — the big idea and glossary
- [Try it](docs/try-it.md) — a ~10-minute hands-on Semantic-Web lab
- [Architecture](docs/architecture.md) — request lifecycle and core classes
- [RDF model](docs/rdf-model.md) — the in-memory triple store and SQL→RDF projection
- [Database schema](docs/database-schema.md) — every table and the seed data
- [Modules](docs/modules.md) — the mod system and a mod-authoring guide
- [Templating](docs/templating.md) — XSLT rendering and output formats
- [Configuration](docs/configuration.md) — domains, `luna.ini`, `db.ini`
- [Installation](docs/installation.md) — Docker and manual setup
- [Security](docs/security.md) — known issues and hardening
- [Linked Data](docs/linked-data.md) — the Semantic Web layer (URI policy, vocabularies, JSON-LD, SPARQL via Ontop & Oxigraph)
- [Why RDF](docs/why-rdf.md) — what going RDF-native unlocks, in plain English (and what's still roadmap)
- [Roadmap](docs/roadmap.md) — what's next: single source of truth, semantics, a data-first server
- [Going public](docs/going-public.md) — readiness checklist for opening the repo

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

GPL v2 — see [LICENSE](LICENSE).
