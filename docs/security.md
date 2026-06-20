# Security & Known Issues

LunarSystem is **alpha-grade software from 2006–2010** and is no longer
maintained. It reflects the security practices of its era. Treat it as a
historical artifact: safe to study and run locally, **not** safe to expose on the
public internet without significant hardening.

## 2026 hardening pass (0.6.9–0.7.6-alpha)

A focused, verified hardening pass closed the major issues catalogued below. Each
fix was confirmed against the running Docker stack. The tables further down record
the **original** findings; several are now fixed or partially fixed as noted here.

| Area | Fix | Status | Version |
|---|---|---|---|
| **Blind SQLi** — admin users `order_by` | whitelist map for the sort column; `intval` on LIMIT/IN | ✅ | 0.6.9 |
| **Source / secret disclosure** | `.htaccess` 403s `/.git`, `*.ini`/`*.sql`, docker files, `docs/`, `scss/`; `DEBUG=0` default; dead `debug()` dump removed | ✅ | 0.7.0 |
| **No security headers** | CSP, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, COOP; `X-Powered-By` removed | ✅ | 0.7.0 |
| **Session / cookie hardening** | HttpOnly + SameSite=Lax (+ Secure on HTTPS); cookie-only sessions (`use_trans_sid=0`, `use_only_cookies=1`, `use_strict_mode=1`) | ✅ | 0.7.1 |
| **Unsalted MD5 passwords** | bcrypt (`password_hash`) with transparent upgrade-on-login; generic login errors + dummy verify (no account enumeration) | ✅ | 0.7.2 |
| **Session fixation** | `session_regenerate_id` on login (DB-handler row re-keyed) | ✅ | 0.7.3 |
| **No CSRF protection** | per-session synchronizer token in every state-changing form + central POST-only `hash_equals` verify in the dispatch; `?purge` POST-only | ✅ | 0.7.4 |
| **Stored XSS** (SVG SMIL `<animate>` bypass) | HTML_Safe strips `svg`/`math`/`animate`/… + SMIL attrs; cache `unserialize(..., ['allowed_classes'=>false])` | ✅ | 0.7.5 |
| **IDOR** — edit_texts text→page linking | `user_can_access_page()` per-target check on links; fixed `submit_modify` validating the wrong array | ◐ | 0.7.6 |

**Still open / partial:** per-target authorization for *content* modify/delete of a text already on a higher-level page (edit_texts) is not yet gated; **logout** is still a low-severity GET CSRF; the session guard bound to the client User-Agent is unchanged. This remains an archival app — keep it behind web-server auth / a VPN and **off the public internet**.

## Hard compatibility limits

| Issue | Impact | Detail |
|---|---|---|
| **Runtime** | — | Runs on **PHP 8.3 / MySQL 8.0** via PDO (`pdo_mysql`); the 0.5.0-alpha migration removed the PHP-7-blocking `mysql_*` extension and PEAR MDB2. |
| **MyISAM storage engine** | Compatibility | The schema now uses `ENGINE=MyISAM` (the original `TYPE=MyISAM` syntax was removed back in MySQL 5.5). MySQL 8.0 also needs `sql_mode=""` for the legacy column defaults — the Docker stack sets this. |
| **Vendored libs** | Maintenance | Bundles Cache_Lite, HTML_Safe and semsol/arc2 3.1.0; the PEAR Log base class is kept only for its `PEAR_LOG_*` constants. |

## Security weaknesses

| Issue | Severity | Detail | Mitigation |
|---|---|---|---|
| **Unsalted MD5 passwords** | High | `luna_users.password` is a bare `md5()` hash. Trivially crackable; vulnerable to rainbow tables. | Do not reuse real passwords. Don't expose the site publicly. |
| **Session ID in URL** | High | `session.use_trans_sid = 1` ([luna.php:33](../luna/luna.php#L33)) propagates the session ID through URLs, which leak via referrers, logs, and shared links — enabling session fixation/hijacking. | Disable trans_sid; require cookies. |
| **Weak default admin** | Medium | Seed admin is `admin@lunarsystem.local` / `luna`. | Change immediately after install. |
| **Old sanitisation stack** | Medium | Input cleaning leans on PEAR HTML_Safe and hand-rolled filters of its era. SQL is escaped via PDO `quote()` (`lunaDB::quote`), but coverage should not be assumed complete against modern XSS/SQLi techniques. | Audit before any untrusted exposure. |
| **`register_globals`-era assumptions** | Low | Code predates modern superglobal handling; it explicitly disables `register_globals` and guards `magic_quotes`, but the design assumptions are dated. | — |

## Additional findings (2026 code-review pass)

A full read of the code after the initial assessment surfaced the issues below,
each cited to a specific line. They are era-typical for 2006–2010 PHP and
reinforce the "study/run locally, do not expose publicly" guidance.

**Status** reflects the **0.2.14-alpha** hardening pass (✅ fixed, ◐ partially
fixed, ⬜ open). The remaining ⬜ items are the more invasive changes (CSRF
tokens across every form, per-action authorisation, session-ID rotation) and the
by-design WYSIWYG output; they were deliberately deferred. Every ✅ was verified
against the running Docker stack.

| Issue | Severity | Status | Detail | Location |
|---|---|---|---|---|
| **No CSRF protection anywhere** | High | ⬜ open | No anti-forgery token is generated or verified. Every state-changing action (create/modify/delete users, groups, levels, pages, mods) fires on the mere presence of `submit`/`batch_submit` in the request. | dispatch [luna.php:470-487](../luna/luna.php#L470) |
| **SQL injection in `mod_journal`** | High | ✅ fixed | `start` is now `intval()`'d before the `LIMIT` clause and `order_by` is whitelisted before it is interpolated as a SQL identifier into `COUNT()`/`ORDER BY`. | [luna.mod_journal.php](../luna/luna.mods/luna.mod_journal.php) |
| **Session fixation (no ID regeneration)** | High | ⬜ open | `login()` only `UPDATE`s `session_logged_in` on the *existing* session id; `session_regenerate_id()` is never called. With `use_trans_sid=1` the id can arrive from the URL, so a pre-seeded id survives authentication. (Deferred: the DB-keyed session handler needs careful manual rotation.) | [luna.mod_log.php:167-176](../luna/luna.mods/luna.mod_log.php#L167) |
| **Submit handlers never re-check privileges** | High | ⬜ open | `check_privileges()` runs once in the constructor against the *requested page's* level only. No `submit_add/modify/delete` re-validates rights on the specific target node (whose id comes from request input). | [luna.tools.class.php:710](../luna/luna.classes/luna.tools.class.php#L710), called at [luna.php:264](../luna/luna.php#L264) |
| **No login throttling** | Medium | ✅ fixed | `login()` now reads `login_attempts` and applies a capped per-account back-off (`sleep(min(attempts, 5))`); a correct password still resets the counter, so accounts are never permanently locked. | [luna.mod_log.php](../luna/luna.mods/luna.mod_log.php) |
| **PHP object injection via `unserialize()`** | Medium | ✅ fixed | Sort cookies now use `json_encode`/`json_decode` (cannot instantiate objects); the `load_request()` path guards `unserialize()` against `O:`/`C:` object payloads. | [luna.tools.class.php](../luna/luna.classes/luna.tools.class.php); [luna.model.class.php](../luna/luna.classes/luna.model.class.php) |
| **Reflected XSS in the error page** | Medium | ✅ fixed | `raise_error_page()` now `htmlspecialchars()`-escapes the requested path before it reaches the HTML response. | [luna.tools.class.php:295](../luna/luna.classes/luna.tools.class.php#L295) |
| **Stored content rendered unescaped** | Medium | ⬜ open | `luna:content` (WYSIWYG HTML from `luna_texts`) is emitted with `disable-output-escaping="yes"`, so stored HTML is injected verbatim. By design (rich text) — safety rests on the era-2009 HTML_Safe filter applied on save. | [luna.default.html.xsl:28](../luna/luna.xsl/luna.html.xsl/luna.default.html.xsl#L28) (+ `luna.root`) |
| **`purgelogs` wipes the audit log via GET** | Medium | ✅ fixed | The `DELETE FROM luna_logs` now requires `$_POST['purgelogs']`, so a forged link/`<img>` (GET) can no longer trigger it. | [luna.mod_journal.php:81](../luna/luna.mods/luna.mod_journal.php#L81) |
| **Sensitive data written to `luna_logs`** | Low | ✅ fixed | `lunaLog::log()` now stores only a small `$_SERVER` whitelist (remote addr, method, URI, host, UA, referer) instead of the whole array (which carried the cookie header / session id). | [luna.log.class.php](../luna/luna.classes/luna.log.class.php) |
| **Weak/bypassable session hijack guard** | Low | ◐ partial | `encode_ip()` no longer trusts `X-Forwarded-For` (it uses `REMOTE_ADDR`), closing the IP-spoof bypass. The guard is still bound to the client-controlled User-Agent, and still breaks users behind rotating IPs. | [luna.session.class.php:322](../luna/luna.classes/luna.session.class.php#L322); [luna.tools.class.php](../luna/luna.classes/luna.tools.class.php) |

> These are a code-reading review, not a penetration test, and not exhaustive.
> `mod_node`, by contrast, **does** enforce per-node level access before dumping
> a node ([luna.mod_node.php:71-74](../luna/luna.mods/luna.mod_node.php#L71)).

## Triplestore / SPARQL surface (0.3.x)

The RDF-native read/write loop (see [linked-data.md](linked-data.md)) adds a
network surface the original CMS did not have. Status: ⬜ open — new, not yet
hardened.

- **Unauthenticated endpoints — keep them internal.** The app talks to Oxigraph
  over plain HTTP (`SPARQL_ENDPOINT`, `SPARQL_UPDATE_ENDPOINT` in `luna.php`) with
  no authentication. Oxigraph's `/update` accepts arbitrary graph mutations, so it
  must stay on the internal Docker network and never be exposed publicly — the same
  posture as the MySQL port. Ontop (virtual SPARQL) is read-only but equally must
  not be public. **Mitigation in place:** `docker-compose.yml` publishes every host
  port on `127.0.0.1` only (loopback) — app `8080`, Oxigraph `7879`, Ontop `8081`,
  MySQL `3307` — so a default `docker-compose up` is unreachable from other machines.
  Keep it that way: do not change the host bindings to `0.0.0.0`, and never put
  Oxigraph's `/update` behind a public proxy.
- **Hand-rolled SPARQL string assembly.** The write-through (`rdf_sync_node` /
  `rdf_delete_node`) builds updates by interpolation, escaping string literals via
  `sparql_literal()` and IRIs via `rdf_uri()` (rawurlencode'd lid); the read
  builders (`load_nodes_sparql` / `load_texts_sparql`) interpolate the page slug and
  the user's level ids. Present but bespoke — treat as SPARQL-injection surface and
  audit before trusting (same caveat as the PDO `quote()` note). User content
  reaches RDF only through these escapers.
- **Best-effort by design.** The write-through never blocks a save on a SPARQL
  failure, so MySQL stays the source of truth; a failed mirror means the graph can
  lag — reconcile with `rdf_resync_all()`.

## Repository hygiene

| Item | Status / note |
|---|---|
| `db.ini` with real credentials | **Gitignored and never committed** — only the working-tree copy holds the credentials. Rotate them; there is nothing to untrack. |
| `.svn/` directories | Present on disk from the original Subversion checkout. **Not tracked by git** — they are working-copy cruft and can be deleted (`find . -name .svn -type d -prune -exec rm -rf {} +`). |
| `.DS_Store` files | Present on disk, **not tracked**. Already ignored. |
| Cache directories | `luna.domains/*/cache/` should be writable but its *contents* should not be committed. Only `.gitkeep` is tracked. |

## If you must deploy it

This is strongly discouraged, but if a live instance is unavoidable:

1. Put it behind authentication at the web-server level (HTTP basic auth / VPN).
2. Serve over HTTPS only.
3. Disable `session.use_trans_sid` and force cookie-based sessions.
4. Change the admin password and rotate all DB credentials.
5. Restrict the MySQL user to the minimum required grants.
6. Keep `DEBUG = 0` in any production `luna.ini` so errors aren't displayed.
7. Firewall the database; never expose port 3306/3307 publicly.

## Scope of this assessment

These notes are a code-reading review, not a penetration test. The list captures
the obvious, era-typical weaknesses; it is not exhaustive. Do not treat absence
from this list as evidence of safety.
