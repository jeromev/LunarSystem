# LunarSystem

A PHP/MySQL CMS (v0.3.0-alpha, circa 2006–2010) that models all content as **RDF triples** and renders pages through **XSLT transformations**. Originally developed by Odradek / lunarsystem.org.

> **Active experiment — Semantic Web CMS.** On the `experiment/semantic-web` branch (not merged to `main`) the archival CMS is being turned into a *real* Semantic Web CMS: a frozen URI policy and a vocabulary mapping onto schema.org / Dublin Core / SIOC / FOAF / PROV-O, a JSON-LD projection, and a SPARQL read path served first by **Ontop** (a virtual SPARQL endpoint over the unchanged MySQL) and then by **Oxigraph** (a materialised triplestore) with no app code change. The archival release stays `0.2.14-alpha` on `main`. See **[docs/linked-data.md](docs/linked-data.md)** for the full design and phase-by-phase status.

## Quick start (Docker)

```bash
docker-compose up --build -d
```

Wait ~15 seconds for MySQL to initialise, then open **http://localhost:8080**.

> On the `experiment/semantic-web` branch the stack also starts a **SPARQL endpoint** (Ontop on host port `8081`) and a **triplestore** (Oxigraph on host port `7879`). The app's read path can be routed through SPARQL with `?sparql=1`; see [docs/linked-data.md](docs/linked-data.md).

Log in as **`admin@lunarsystem.local`** with password **`luna`**.

> MySQL is exposed on host port `3307` to avoid conflicts with a local MySQL on `3306`.

## Manual setup

Requirements: Apache 2 + `mod_rewrite`, **PHP 5.3–5.6**, MySQL 5.x, PHP extensions: `mysql`, `xsl`, `mbstring`, `gettext`.

> PHP 7+ is **not supported** — the `mysql_*` extension was removed in PHP 7 and the bundled PEAR MDB2 library depends on it.

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

Append `?output=xml`, `?output=json`, `?output=n3`, or `?output=jsonld` to any page URL to receive the raw RDF model instead of HTML. (On the experiment branch a JSON-LD block is also embedded in every HTML page's `<head>`; see [docs/linked-data.md](docs/linked-data.md).)

## Project structure

```
index.php                      Entry point
.htaccess                      Clean URL rewrite rules
luna/
  luna.php                     Main luna class (bootstrap, routing, XSLT rendering)
  luna.classes/
    luna.model.class.php       RDF model (in-memory triple store, ARC2, XSLT)
    luna.db.class.php          Database wrapper (PEAR MDB2)
    luna.session.class.php     DB-backed session handler
    luna.tools.class.php       Utilities (sanitisation, URL building, i18n, ACL)
    luna.log.class.php         Error logging (PEAR Log)
  luna.mods/                   Pluggable page modules (admin, journal, node, …)
  luna.xsl/luna.html.xsl/      Built-in XSLT templates (HTML output)
  luna.lib/                    Vendored libraries: PEAR MDB2, PEAR Log, Cache_Lite, ARC2
  luna.domains/
    luna.default/              Fallback site configuration (used for local/Docker)
    lunarsystem.org/           Original production configuration
  luna.sql/luna.mysql.sql      Database schema + seed data
  luna.locale/                 gettext translations (en_EN, en_US, fr_FR)
css/                           Stylesheets
js/                            jQuery + CKEditor (rich-text editing)
semantic/                      Semantic-web experiment (experiment/semantic-web branch)
  ontop/                       R2RML mapping + Ontop image (virtual SPARQL over MySQL); Oxigraph dump
```

## Known issues

| Issue | Impact | Notes |
|---|---|---|
| **PHP 5.3–5.6 only** | Hard limit | `mysql_*` removed in PHP 7; PEAR MDB2 does not support PDO |
| **MD5 passwords** | Security | `luna_users.password` is unsalted MD5 — do not expose publicly |
| **DB credentials on disk** | Security | `luna/luna.domains/lunarsystem.org/ini/db.ini` holds real credentials in the working tree. It's **gitignored and was never committed** — rotate the credentials; there's nothing to untrack |
| **Session ID in URL** | Security | `session.use_trans_sid = 1` leaks session IDs into URLs; no `session_regenerate_id()` on login (fixation) |
| **No CSRF on admin actions** | Security | Admin forms still carry no anti-forgery token (the `mod_journal` SQLi, reflected XSS, login throttling, cookie object-injection and `$_SERVER` log leak were hardened in 0.2.14 — see [docs/security.md](docs/security.md)) |

The Docker stack now boots cleanly: the schema was updated from the obsolete `TYPE=MyISAM` to `ENGINE=MyISAM`, a duplicate-modifier parse error was fixed, and the build repoints apt at `archive.debian.org`. See the [changelog](CHANGELOG.md) and [docs/installation.md](docs/installation.md).

## Documentation

Full technical documentation lives in [docs/](docs/):

- [Overview](docs/overview.md) — the big idea and glossary
- [Architecture](docs/architecture.md) — request lifecycle and core classes
- [RDF model](docs/rdf-model.md) — the in-memory triple store and SQL→RDF projection
- [Database schema](docs/database-schema.md) — every table and the seed data
- [Modules](docs/modules.md) — the mod system and a mod-authoring guide
- [Templating](docs/templating.md) — XSLT rendering and output formats
- [Configuration](docs/configuration.md) — domains, `luna.ini`, `db.ini`
- [Installation](docs/installation.md) — Docker and manual setup
- [Security](docs/security.md) — known issues and hardening
- [Linked Data](docs/linked-data.md) — the Semantic Web experiment (URI policy, vocabularies, JSON-LD, SPARQL via Ontop & Oxigraph)

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

GPL v2 — see [luna/LICENSE.txt](luna/LICENSE.txt).
