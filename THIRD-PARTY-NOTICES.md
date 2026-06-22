# Third-party notices

LunarSystem itself is licensed under the **GNU GPL v2** (see [LICENSE](LICENSE)).
It bundles third-party code two ways: legacy components vendored in-tree under
`luna/luna.lib/` (committed, no installer — from the 2006–2010 PHP 5.x era), and, as
of 0.8.28-alpha, one **Composer** dependency under `vendor/` (committed too, so the app
stays clone-and-run). Each retains its own license and copyright; the per-file headers
are authoritative. The app now runs on PHP 8.3 / MySQL 8.0: the DB layer moved from PEAR
MDB2 to PDO in 0.5.0-alpha (MDB2 dropped); PEAR **Log** was removed in 0.8.27-alpha (its
`PEAR_LOG_*` constants inlined in `luna.classes/luna.log.class.php`); and PEAR
**HTML_Safe** + **XML_HTMLSax3** were replaced by **HTMLPurifier** (Composer) in
0.8.28-alpha; and PEAR **Cache_Lite** — the last consumer of PEAR base — was replaced by
a small native file cache (`luna.classes/luna.cache.class.php`) in 0.8.29-alpha, removing
both. **ARC2 is now the only vendored in-tree library.**

| Component | Location | Upstream | License (per file headers / upstream) |
|---|---|---|---|
| **ARC2** (RDF/SPARQL library) | `luna/luna.lib/arc/` | github.com/semsol/arc2 | semsol/arc2 3.1.0 — GPL-2.0-or-later / W3C Software License |
| **HTMLPurifier** (input sanitiser) | `vendor/ezyang/htmlpurifier/` (Composer) | github.com/ezyang/htmlpurifier | ezyang/htmlpurifier 4.19.0 — LGPL-2.1-or-later (© Edward Z. Yang) |

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
