# Changelog

## [0.2.8-alpha] - 2026-06-18
- Added a full technical documentation set under `docs/` (overview, architecture, RDF model, database schema, modules, templating, configuration, installation, security)
- Linked the documentation from `README.md`

## [0.2.7-alpha] - 2010-03-30
- Updated installation instructions

## [0.2.6-alpha] - 2010-03-16
- Added a xsl dir in default domain

## [0.2.5-alpha] - 2010-03-11
- Clean release

## [0.2.4-alpha] - 2010-03-05
- Fixed: language negotiation not working

## [0.2.3-alpha] - 2010-03-05
- Added: session id must be 32 characters long
- Added: language must be set in the form `xx-xx`

## [0.2.2-alpha] - 2010-03-03
- Added: `mod_node` now prints the requested node as RDF/XML

## [0.2.1-alpha] - 2010-03-03
- Fixed: URLs not correctly formatted when `CLEAN_URLS = 0`
- Fixed: `$_POST['level_nid']` not correctly interpreted
- Added: CSS cosmetics

## [0.2.0-alpha] - 2010-03-03
- 2nd release on SourceForge

## [0.1.66-alpha] - 2010-03-03
- Texts are now loaded along with their associated page

## [0.1.65-alpha] - 2010-03-03
- Corrected the default value in table `luna_nodes_seq`

## [0.1.64-alpha] - 2010-03-02
- Fixed the `edit_texts` mod

## [0.1.63-alpha] - 2010-03-02
- Added CKEditor to the package

## [0.1.62-alpha] - 2010-03-01
- Fixed `mod_online_users`

## [0.1.61-alpha] - 2010-03-01
- Fixed `mod_admin_users`

## [0.1.60-alpha] - 2010-03-01
- Included all dependencies (PEAR and ARC2) in `luna.lib`
- Updated `INSTALL.txt` accordingly

## [0.1.59-alpha] - 2010-03-01
- Fixed the admin page

## [0.1.58-alpha] - 2010-03-01
- Corrected `luna` class so the ini file is loaded first

## [0.1.57-alpha] - 2010-03-01
- Fixed `mod_admin_mods`

## [0.1.56-alpha] - 2010-02-28
- Fixed `mod_admin_pages`

## [0.1.55-alpha] - 2010-02-28
- Fixed `mod_admin_levels`

## [0.1.54-alpha] - 2010-02-27
- Fixed `mod_admin_groups`

## [0.1.53-alpha] - 2010-02-27
- Renamed exception class to `lunaLog`

## [0.1.52-alpha] - 2010-02-26
- Replaced RAP RDF library with ARC2

## [0.1.51-alpha] - 2010-02-24
- Moved all `lunaNode` methods into the model class
- Renamed the RDF class to `lunaModel`

## [0.1.50-alpha] - 2010-02-23
- Added the ability to navigate without clean URLs
- `mod_journal` mostly working

## [0.1.49-alpha] - 2010-02-23
- Added `luna.mod_example.php`

## [0.1.48-alpha] - 2010-02-23
- Deleted unnecessary mod class
- Set singleton pattern for module loading

## [0.1.47-alpha] - 2010-02-23
- Fixed a few deprecated pass-by-reference calls

## [0.1.46-alpha] - 2010-02-22
- Finished integrating the RDF RAP-API into luna core

## [0.1.45-alpha] - 2010-02-21
- Added classes: `lunaDB`, `lunaSession`, `lunaTools`

## [0.1.44-alpha] - 2010-02-21
- Started integrating the RDF RAP API

## [0.1.43-alpha] - 2010-02-20
- Updated CSS and JS files

## [0.1.41-alpha] - 2008-07-08
- Fixed a bug in the login page

## [0.1.40-alpha] - 2008-07-08
- Disabled cache for admin users

## [0.1.39-alpha] - 2008-07-08
- Fixed a bug in the `forminput` XSL template

## [0.1.38-alpha] - 2008-07-08
- Replaced `type` with `class` throughout the codebase
- Fixed the multiple selection bug

## [0.1.37-alpha] - 2008-07-07
- Updated the log system to correctly handle exception objects
- Fixed a bug in `luna.node.class`
- Working on `mod_edit_texts`

## [0.1.36-alpha] - 2008-07-07
- Only pages accessible to the current user are now loaded in the nav tree
- Nodes are now objects rather than arrays
- Working on `mod_edit_texts`

## [0.1.35-alpha] - 2008-07-06
- Updated copyright date
- Working on `mod_edit_texts`

## [0.1.34-alpha] - 2008-07-06
- `mod_journal` page now working; not yet fully RDF-compliant when a `log_id` is requested

## [0.1.33-alpha] - 2008-07-06
- Fixed empty variable problem in `luna.common.html.xsl`

## [0.1.32-alpha] - 2007-04-17
- Working on `mod_journal`

## [0.1.31-alpha] - 2007-04-08
- Fixed `mod_admin_users`

## [0.1.30-alpha] - 2007-04-05
- Renamed the mod class to `lunaMod`
- Fixed `luna::get_time_since()`

## [0.1.29-alpha] - 2007-04-02
- Added `luna::list_children()` public method (needs work)

## [0.1.28-alpha] - 2007-04-01
- Switched to PEAR MDB2 database abstraction layer
- Introduced try/catch exception system

## [0.1.27-alpha] - 2007-03-20
- Finished `mod_admin_mods`
- Started `mod_admin_users`

## [0.1.26-alpha] - 2007-03-19
- Added `lunaModel::get_nid_from_lid()`
- Finished `mod_admin` and `mod_admin_pages`

## [0.1.25-alpha] - 2007-03-18
- Added optional `DEBUG` constant in `luna.ini`
- Integrated the Singleton design pattern
- Finished `mod_online_users`

## [0.1.24-alpha] - 2007-03-12
- Restructured directories to a Drupal-like multi-site structure (`luna/luna.domains/`)

## [0.1.23-alpha] - 2007-03-11
- Dropped PHP 4 support
- Cleaned `lunaModel` class
- Output is now returned rather than printed
- Conformed main `luna` class to PHP 5
- Set `error_reporting(E_ALL ^ E_NOTICE)`

## [0.1.22-alpha] - 2007-03-11
- Fixed a bug in the luna constructor
- Replaced `rdf:Seq` with `rdf:Bag`
- Added `luna:current` and `luna:guest` attributes to the `foaf:Person` node

## [0.1.21-alpha] - 2007-03-10
- Restructured LunarSystem files; updated `INSTALL.txt`

## [0.1.20-alpha] - 2007-03-10
- RDF integration: renamed `printxml()` to `printRDF()`
- Added GPL licence header

## [0.1.19-alpha] - 2007-03-10
- Started RDF integration; `mod_admin_groups` done

## [0.1.18-alpha] - 2006-12-17
- Finished the `edit_text` module

## [0.1.17-alpha] - 2006-12-17
- Forced modules to be linked to at least one page

## [0.1.16-alpha] - 2006-12-12
- Added `AJAX` constant (boolean) in the luna constructor
- Added simple search module `luna.mod_search`
- Integrated `form.js` for jQuery AJAX

## [0.1.15-alpha] - 2006-12-12
- Mods can now also be stored in their own subfolder (`luna.MyMods/mymod/mymod.php`)
- Added `lunaModel::lid_is_taken()` to check for duplicate `lid` values

## [0.1.14-alpha] - 2006-12-11
- Added `ini_set('arg_separator.output', '&amp;')` for strict XHTML compliance

## [0.1.13-alpha] - 2006-12-03
- Fixed infinite loop bug in `load_users` SQL count query
- Added `luna::dbQuery()` to count SQL queries
- Added `queryID()` method and `USEXSLID` constant to the XML class

## [0.1.12-alpha] - 2006-12-01
- Renamed mods to ensure unique `lid` values

## [0.1.11-alpha] - 2006-11-30
- `luna::set_language()` now returns the language string

## [0.1.10-alpha] - 2006-11-01
- Set up English translation

## [0.1.9-alpha] - 2006-10-31
- Finished `mod_journal`; updated jQuery; updated default MySQL schema and data

## [0.1.8-alpha] - 2006-10-29
- Finished mods: `admin`, `admin_groups`, `admin_levels`, `admin_mods`, `admin_pages`, `admin_users`

## [0.1.7-alpha] - 2006-10-26
- Added `Node` class
- Added `lunaModel::insert()`, `link()`, `update()`, `unlink()`
- Finished `mod_admin_groups`

## [0.1.6-alpha] - 2006-10-24
- Added `luna::insert_action($nid)`

## [0.1.5-alpha] - 2006-10-21
- Updated scripts for the new database schema

## [0.1.4-alpha] - 2006-10-13
- Database schema updated

## [0.1.3-alpha] - 2006-10-08
- Database restructuration in progress
- Added `removeChild()` to the XML class
- Added `submit_all()` to the Mod class

## [0.1.2-alpha] - 2006-10-05
- Database restructuration in progress
- Replaced `code` with `lid` (literal identifier) throughout

## [0.1.1-alpha] - 2006-10-01
- Restructured the graphic interface

## [0.1.0-alpha] - 2006-10-01
- Optimized admin modules to be composable on a single page

## [0.0.18-alpha] - 2006-09-30
- Added `static_text` column to `luna_pages` table
- Finished `mod_edit_staticpages`
- Added `luna::purge_cache()`
- Optimized `LogViewer` module

## [0.0.17-alpha] - 2006-09-29
- Moved `admin_texts` mod under the `edition` node as `edit_texts`
- Added `mod_edit_staticpages`
- Renamed `admin_structure` to `admin_pages`
- Added the ability to associate a text block to a mod

## [0.0.16-alpha] - 2006-09-28
- Added `lunaTools::unrequest()` to destroy a user variable
- `mod_admin_texts` finished

## [0.0.15-alpha] - 2006-09-27
- Optimized list sorting (previous/next/first/last navigation)
- Added the ability to create new text entries
- Added `luna::remove_accents()` and `luna::prepare_lid()`
- Replaced DBX with jQuery

## [0.0.14-alpha] - 2006-09-26
- Fixed session ID propagation (onchange/onclick); site is now navigable without cookies

## [0.0.13-alpha] - 2006-09-24
- Started TinyMCE integration
- `lunaTools::sanitize()` now uses PEAR `HTML/Safe`

## [0.0.12-alpha] - 2006-08-24
- Added `output()` and `createEscapedElement()` methods to the XML class
- PHP 5: XML constructor now reports and locates malformed XML errors

## [0.0.11-alpha] - 2006-08-22
- Completed DBX (docking boxes) integration
- Optimized list output (sorting, order by)
- Cleaned demo `index.php` and corrected CSS image paths

## [0.0.10-alpha] - 2006-08-01
- Removed `PHPSESSID` from XML output to improve cacheability
- Restructured changelog

## [0.0.9-alpha] - 2006-07-16
- Changed default cache timeout to 7 days (was 2 hours)
- Added cache layer in `output()`: XSLT is skipped if XML is unchanged
- Added `md5()` method to the XML class
- Added cache layer in `loadPage()`
- Renamed `load_data()` to `loadPage()`
- Set default XML string in the XML class constructor

## [0.0.8-alpha] - 2006-07-13
- Fixed a typo in the levels mod; fixed 2 small bugs in the Log mod
- Added `/luna.Docs/sql` folder for base DB schemas
- The `luna` object is now passed by reference to mod classes

## [0.0.7-alpha] - 2006-07-11
- Site nav tree is now stored as an XML file, one per language; purgeable like cache
- Added `save()` method to the XML class
- Added `luna.XML` folder for XML trees; added `RDF_PATH` constant to `luna.ini`

## [0.0.6-alpha] - 2006-07-10
- Added `xsl_path` variable to page XML metadata
- Added `getLength()` method to the XML class

## [0.0.5-alpha] - 2006-07-09
- Added `getItem()` method to the XML class

## [0.0.4-alpha] - 2006-07-08
- Updated the default MySQL dump
- Added `PEAR.php` include to `luna.php`
- Moved `Log.php` include to top of `luna.php`

## [0.0.3-alpha] - 2006-07-07
- Added `die()` after database query errors
- Added `convert_to_unicode()` to the `luna` class
- Added `initialize()` call in the `Mod` class constructor
- Set `MYXSL_PATH` and added `luna.MyXSL/HTML.XSL` folder
- Set default `luna` gettext `bindtextdomain`
- CSS optimisation; 2 new images

## [0.0.2-alpha] - 2006-07-06
- Mods must now be placed in the `luna.myMods` folder
- Sanitized ini vars in `luna::load_ini()` and `luna::dbConnect()`

## [0.0.1-alpha] - 2006-07-04
- First release on SourceForge
