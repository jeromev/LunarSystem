# LunarSystem Documentation

Developer and operator documentation for **LunarSystem**, a PHP/MySQL CMS
(v0.2.9-alpha, circa 2006–2010) that models all content as **RDF triples** and
renders pages through **XSLT transformations**.

This `docs/` folder is the canonical technical reference. The top-level
[README.md](../README.md) is the quick-start; everything deeper lives here.

## Contents

| Document | What it covers |
|---|---|
| [overview.md](overview.md) | What the project is, its core ideas, and a glossary |
| [architecture.md](architecture.md) | The request lifecycle, core classes, and how they fit together |
| [rdf-model.md](rdf-model.md) | The in-memory triple store, namespaces, and SQL→RDF projection |
| [database-schema.md](database-schema.md) | Every table, column, and the seed data |
| [modules.md](modules.md) | The mod system and a mod-authoring guide |
| [templating.md](templating.md) | XSLT rendering, stylesheet lookup, and output formats |
| [configuration.md](configuration.md) | Domains, `luna.ini`, `db.ini`, and constants |
| [installation.md](installation.md) | Docker and manual setup, requirements |
| [security.md](security.md) | Known issues and hardening notes |

## Reading order

If you are new to the codebase, read in this order:

1. **[overview.md](overview.md)** — the big idea and vocabulary.
2. **[architecture.md](architecture.md)** — trace one request end-to-end.
3. **[rdf-model.md](rdf-model.md)** + **[database-schema.md](database-schema.md)** — how data is stored and shaped.
4. **[modules.md](modules.md)** + **[templating.md](templating.md)** — how a page is assembled and rendered.
5. **[configuration.md](configuration.md)** + **[installation.md](installation.md)** — how to run it.

## Status

This is a historical / archival codebase. It runs only on **PHP 5.3–5.6** and is
not maintained. See [security.md](security.md) before exposing it publicly.
