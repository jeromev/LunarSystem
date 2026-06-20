# Styling (SCSS)

`css/luna.css` is **generated** — do not edit it by hand. The source of truth is
this `scss/` tree, compiled with [Dart Sass](https://sass-lang.com/dart-sass).

## Workflow

| Command | What |
|---|---|
| `make css`       | build `css/luna.css` **with a source map** — dev tools show `_*.scss:line` |
| `make css-watch` | same, live on every save while you work |
| `make css-min`   | minified production build (no source map) |

`sass` must be on your PATH (`brew install sass`, or `npm i -g sass`).
After editing, run `make css` and commit **both** the changed partial(s) and the
regenerated `css/luna.css`.

## Layout

| File | What |
|---|---|
| `luna.scss`      | entry point — `@use`s the partials in cascade order |
| `_bg.scss`       | baseline-grid config — `@forward`s the vendored toolkit |
| `_init.scss`     | global CSS reset / normalised element defaults |
| `_tokens.scss`   | the `:root` custom-property palette + base font |
| `_base.scss`     | element/tag styles (TAGS) |
| `_classes.scss`  | utility / class styles (CLASSES) |
| `_page.scss`     | layout: `#Page`, `#Content`, widths (PAGE) |
| `_treeview.scss` | TreeView nav icons (TVlists; CSS only) |
| `_mixins.scss`   | shared Sass helpers / mixins |
| `_scales.scss`   | responsive typography / layout / spacing scales (used by the partials above) |
| `vendor/baselinegrid.scss` | [baselinegrid.scss](https://github.com/jeromev/baselinegrid.scss) (vendored, MIT) — the baseline-grid engine |

The palette is CSS custom properties (runtime-themeable), not Sass `$` variables,
so themes can override them without recompiling.

The baseline grid is vendored under `vendor/` and loaded via `--load-path=scss/vendor` (already in the `Makefile`) so `--quiet-deps` can silence its compile-time deprecation notices while still surfacing luna's own.

`make css` / `make css-watch` emit `css/luna.css.map` (gitignored, with the SCSS embedded via `--embed-sources`) plus a `sourceMappingURL` comment in `css/luna.css`, so browser dev tools resolve compiled rules back to the `.scss` source and line. `make css-min` is the clean, minified production build (no map). After switching the build on, hard-reload the browser so it re-reads the CSS.
