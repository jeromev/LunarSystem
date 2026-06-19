# Contributing to LunarSystem

Thanks for your interest! LunarSystem is a **small, deliberately-minimal teaching
artifact** — a 2006–2010 PHP/MySQL CMS revived as a hands-on way to explore the
Semantic Web (RDF, SPARQL, JSON-LD, virtual vs. materialised triplestores). It is
**not** production software (see [docs/security.md](docs/security.md)).

Contributions are welcome in that spirit: things that make it clearer, smaller,
safer to run locally, or better as a learning tool.

## Good contributions

- **Docs & learning material** — improvements to the [lab](docs/try-it.md),
  more worked [example queries](examples/queries.sparql), clearer explanations.
- **Correctness fixes** that keep the code faithful to its era but less buggy.
- **Safety for local use** — anything that keeps the "localhost-only" posture solid.
- **The roadmap** — see [docs/roadmap.md](docs/roadmap.md): P2 (retire the MySQL
  content write), P3 (inference / SHACL / named graphs), P4 (data-first server).

## Please avoid

- Turning it into production software, adding heavy frameworks, or large new deps.
  The value is that it's tiny and readable. New bundled libraries are unlikely to
  be accepted.
- "Modernising" to PHP 7+/8 — it intentionally targets PHP 5.3–5.6 (the original
  `mysql_*` / PEAR MDB2 stack). A port would be a *different* project.
- Reintroducing bulk (e.g. a WYSIWYG editor) that was removed to keep it minimal.

## Development setup

```bash
docker-compose up --build -d      # app :8080, MySQL :3307, Ontop :8081, Oxigraph :7879 (all loopback)
```

See the top-level [README](README.md) and [docs/](docs/) — start with
[docs/try-it.md](docs/try-it.md), then [docs/architecture.md](docs/architecture.md).

## Before you open a PR

- **Lint the PHP** (the project runs on PHP 5.6):
  ```bash
  docker run --rm -v "$PWD":/app -w /app php:5.6-cli sh -c \
    'find index.php luna/luna.php luna/luna.classes luna/luna.mods -name "*.php" -print0 | xargs -0 -n1 php -l'
  ```
  (CI runs the same check plus `docker compose config`.)
- **Smoke-test on Docker** — the site should render and `?output=jsonld` should
  return valid JSON-LD.
- **Update the docs** you touch, **bump the version** (`luna/luna.php`
  `$lunaVersion`, the version strings in the READMEs, and add a
  [CHANGELOG](CHANGELOG.md) entry), and keep changes small and focused.
- **Never commit secrets.** Real `db.ini` files are gitignored; keep it that way.

## License

By contributing, you agree your contributions are licensed under the project's
**GPL v2** (see [LICENSE](LICENSE)).
