# Third-party notices

LunarSystem itself is licensed under the **GNU GPL v2** (see [LICENSE](LICENSE)).
It bundles the following third-party libraries under `luna/luna.lib/` and
`js/`. Each retains its own license and copyright; the per-file headers are the
authoritative statement. They are vendored (committed in-tree) because the app
targets PHP 5.3–5.6 and its original 2006–2010 dependency set.

| Component | Location | Upstream | License (per file headers / upstream) |
|---|---|---|---|
| PEAR Base (`PEAR.php`, `PEAR5.php`) | `luna/luna.lib/` | pear.php.net | New BSD License (© 1997–2009 The Authors / PHP Group) |
| PEAR **MDB2** (database layer) | `luna/luna.lib/MDB2*` | pear.php.net/package/MDB2 | BSD-style license (© 1998–2007 Manuel Lemos, Tomas V.V.Cox, et al.) |
| PEAR **Log** | `luna/luna.lib/Log*` | pear.php.net/package/Log | MIT / BSD (per upstream PEAR package) |
| PEAR **Cache_Lite** | `luna/luna.lib/Cache/` | pear.php.net/package/Cache_Lite | LGPL (per upstream PEAR package) |
| PEAR **XML_Util** | `luna/luna.lib/XML/` | pear.php.net/package/XML_Util | BSD License (© 2003–2008 Stephan Schmidt) |
| **HTML_Safe** (input sanitiser) | `luna/luna.lib/HTML/Safe.php` | pear.php.net/package/HTML_Safe | BSD License (3-Clause) |
| **ARC2** (RDF/SPARQL library) | `luna/luna.lib/arc/` | github.com/semsol/arc2 | semsol ARC2 license — <http://arc.semsol.org/license> (W3C-style; also offered under the GPL) |
| **jQuery** | `js/jquery/` | jquery.com | MIT License |

Notes:

- The project license (GPL v2) governs LunarSystem's own code. The bundled
  components above are independent works, aggregated for convenience; their
  permissive/LGPL/BSD/MIT terms are compatible with redistribution here. If you
  repackage or relicense, consult each component's own license text and headers.
- Where a license column says "per upstream PEAR package," the vendored copy did
  not carry a license header inline; the stated license is the upstream package's
  published license. Verify against the upstream project if it matters to you.
- The **CKEditor** rich-text editor that previously lived under `js/ckeditor/`
  (LGPL/MPL/GPL tri-license) was **removed** in 0.4.0-alpha to keep this build
  minimal; the admin editor is now a plain `<textarea>`.
