# Third-party notices

LunarSystem itself is licensed under the **GNU GPL v2** (see [LICENSE](LICENSE)).
It bundles the following third-party libraries under `luna/luna.lib/`.
Each retains its own license and copyright; the per-file headers are the
authoritative statement. They are vendored (committed in-tree, no Composer/PEAR
install) because the app originated in the 2006–2010 PHP 5.x era; it now runs on
PHP 8.3 / MySQL 8.0 (the database layer was migrated from PEAR MDB2 to PDO in
0.5.0-alpha, so MDB2 is no longer bundled).

| Component | Location | Upstream | License (per file headers / upstream) |
|---|---|---|---|
| PEAR Base (`PEAR.php`, `PEAR5.php`) | `luna/luna.lib/` | pear.php.net | New BSD License (© 1997–2009 The Authors / PHP Group) |
| PEAR **Log** (base class) | `luna/luna.lib/Log.php` | pear.php.net/package/Log | PHP / BSD-style (© Chuck Hagenbuch, Jon Parise) |
| PEAR **Cache_Lite** | `luna/luna.lib/Cache/` | pear.php.net/package/Cache_Lite | LGPL (© Fabien Marty) |
| **XML_HTMLSax3** (HTML SAX parser; HTML_Safe's dependency) | `luna/luna.lib/XML/` | pear.php.net/package/XML_HTMLSax3 | PHP License v3.0 (© 1997–2002 The PHP Group; A. Zhukov, H. Fuecks) |
| **HTML_Safe** (input sanitiser) | `luna/luna.lib/HTML/Safe.php` | pear.php.net/package/HTML_Safe | BSD License (3-Clause, © Roman Ivanov) |
| **ARC2** (RDF/SPARQL library) | `luna/luna.lib/arc/` | github.com/semsol/arc2 | semsol/arc2 3.1.0 — GPL-2.0-or-later / W3C Software License |

**jQuery has been removed** (0.6.8-alpha). The front-end is now dependency-free
vanilla JavaScript ([js/luna.js](js/luna.js)) — no CDN dependency, no animation. For
the record: releases 0.5.7–0.6.7 loaded jQuery 3.7.1 from cdnjs (pinned with an SRI
`integrity` hash); releases before 0.5.7-alpha vendored jQuery 1.4.1 under `js/jquery/`.

The **jQuery TreeView** navigation icons under `images/treeView/` (© 2006 Myles
Angell, <http://be.twixt.us/jquery/>) are still bundled third-party assets; no
explicit license was stated by the author.

**Build-time (SCSS):** `scss/vendor/_baselinegrid.scss` is the
[baselinegrid.scss](https://github.com/jeromev/baselinegrid.scss) toolkit (v3.0.1,
MIT © Jérôme Vogel), vendored to compile the baseline-grid typography into
`css/luna.css`. Build-time only — not shipped to the browser.

## Inlined third-party functions

A few small utility functions were copied — with attribution in their
docblocks — directly into the app code rather than vendored as packages. All are
GPL-compatible with LunarSystem's own GPL v2:

| Function(s) | File | Origin | License |
|---|---|---|---|
| `encode_ip()` / `decode_ip()` | [luna.tools.class.php](luna/luna.classes/luna.tools.class.php) | phpBB 2.0.5 | GPL v2 (© 2001 The phpBB Group) |
| `remove_accents()` | [luna.tools.class.php](luna/luna.classes/luna.tools.class.php) | WordPress 2.0.4 | GPL v2 (© 2006 WordPress) |
| `set_site_path()` (`conf_path`/`conf_init`) | [luna.php](luna/luna.php) | Drupal 5.1 `bootstrap.inc` | GPL v2 |
| `array_to_object()` | [luna.tools.class.php](luna/luna.classes/luna.tools.class.php) | lost-in-code.com (2008 blog post) | no license stated by the original author |

## Notes

- The project license (GPL v2) governs LunarSystem's own code. The bundled
  components above are independent works, aggregated for convenience; their
  permissive/LGPL/BSD/MIT terms are compatible with redistribution here. If you
  repackage or relicense, consult each component's own license text and headers.
- Where a license column cites the upstream PEAR package rather than an inline
  header, the vendored copy did not carry a license header inline; verify against
  the upstream project if it matters to you.
- The **CKEditor** rich-text editor that previously lived under `js/ckeditor/`
  (LGPL/MPL/GPL tri-license) was **removed** in 0.4.0-alpha to keep this build
  minimal; the admin editor is now a plain `<textarea>`.
