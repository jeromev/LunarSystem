# Locale catalogs

These catalogs belong to **this domain** (`luna.domains/luna.default/`). At request time the
active site uses `luna.domains/<domain>/locale/` if it ships one, otherwise it falls back to
the default domain's catalogs here (the `LOCALE_PATH` ini default). Two gettext domains live
here, per language under `<lang>/LC_MESSAGES/`:

| Domain  | Files                | Tracked?       | What                                            |
|---------|----------------------|----------------|-------------------------------------------------|
| `luna`  | `luna.po` / `luna.mo`  | **committed**  | the CMS engine's UI vocabulary (`_()`)          |
| `local` | `local.po` / `local.mo`| **git-ignored**| this site's page labels, layered over `luna`    |

`lunaTools::label($lid)` resolves a page label by its lid (slug): it tries the `local`
domain first, falls back to the engine `luna` catalog, then to the raw lid.
So site-specific page-label translations never enter the engine repo.

## Add / localise a page label

Edit the (git-ignored) `local.po` for each language, then recompile to `.mo`:

```
# luna/luna.domains/luna.default/locale/fr_FR/LC_MESSAGES/local.po
msgid "about"
msgstr "À propos"
```

```
msgfmt luna/luna.domains/luna.default/locale/fr_FR/LC_MESSAGES/local.po -o luna/luna.domains/luna.default/locale/fr_FR/LC_MESSAGES/local.mo
```

Then restart Apache / the container — gettext caches `.mo` files per process.
