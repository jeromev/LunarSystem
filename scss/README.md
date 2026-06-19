# Styling (SCSS)

`css/luna.css` is **generated** — do not edit it by hand. The source of truth is
this `scss/` tree, compiled with [Dart Sass](https://sass-lang.com/dart-sass).

## Workflow

| Command | What |
|---|---|
| `make css`       | one-off build → `css/luna.css` |
| `make css-watch` | live rebuild on every save while you work |
| `make css-min`   | minified build |

`sass` must be on your PATH (`brew install sass`, or `npm i -g sass`).
After editing, run `make css` and commit **both** the changed partial(s) and the
regenerated `css/luna.css`.

## Layout

| File | What |
|---|---|
| `luna.scss`      | entry point — `@use`s the partials in cascade order |
| `_tokens.scss`   | the `:root` custom-property palette + base font |
| `_base.scss`     | element/tag styles (TAGS) |
| `_classes.scss`  | utility / class styles |
| `_page.scss`     | layout: `#Page`, `#Content`, widths (PAGE) |
| `_clearfix.scss` | float clearfix |
| `_tinymce.scss`  | legacy editor styles |
| `_treeview.scss` | jQuery TreeView nav (TVlists) |

The palette is CSS custom properties (runtime-themeable), not Sass `$` variables,
so themes can override them without recompiling.
