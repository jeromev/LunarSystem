# Security & Known Issues

LunarSystem is **alpha-grade software from 2006–2010** and is no longer
maintained. It reflects the security practices of its era. Treat it as a
historical artifact: safe to study and run locally, **not** safe to expose on the
public internet without significant hardening.

## Hard compatibility limits

| Issue | Impact | Detail |
|---|---|---|
| **PHP 5.3–5.6 only** | Blocking | Uses the `mysql_*` extension (removed in PHP 7) via PEAR MDB2, which has no PDO path here. Will not run on PHP 7+. |
| **MyISAM storage engine** | Compatibility | The schema now uses `ENGINE=MyISAM` (the original `TYPE=MyISAM` syntax was removed back in MySQL 5.5). MySQL 5.7 also needs `sql_mode=""` for the legacy column defaults — the Docker stack sets this. |
| **PEAR dependencies** | Maintenance | Relies on vendored PEAR MDB2 / Log / Cache_Lite and ARC2 — all unmaintained. |

## Security weaknesses

| Issue | Severity | Detail | Mitigation |
|---|---|---|---|
| **Unsalted MD5 passwords** | High | `luna_users.password` is a bare `md5()` hash. Trivially crackable; vulnerable to rainbow tables. | Do not reuse real passwords. Don't expose the site publicly. |
| **Session ID in URL** | High | `session.use_trans_sid = 1` ([luna.php:33](../luna/luna.php#L33)) propagates the session ID through URLs, which leak via referrers, logs, and shared links — enabling session fixation/hijacking. | Disable trans_sid; require cookies. |
| **Real DB credentials in the working tree** | High | `luna/luna.domains/lunarsystem.org/ini/db.ini` still contains real-looking production credentials **on disk**. It is **gitignored and was never committed** (`git log --all --full-history -- '*db.ini'` is empty), so the repository is not leaking it — but anyone with a working copy has the password. | Rotate the credentials regardless (they may have been exposed before this archival re-init). No `git rm --cached` is needed — `db.ini` was never tracked; `.gitignore` already covers `luna/luna.domains/*/ini/db.ini`. |
| **Weak default admin** | Medium | Seed admin is `admin@lunarsystem.local` / `luna`. | Change immediately after install. |
| **Old sanitisation stack** | Medium | Input cleaning leans on PEAR HTML_Safe and hand-rolled filters of its era. SQL is escaped via MDB2 `quote()`, but coverage should not be assumed complete against modern XSS/SQLi techniques. | Audit before any untrusted exposure. |
| **`register_globals`-era assumptions** | Low | Code predates modern superglobal handling; it explicitly disables `register_globals` and guards `magic_quotes`, but the design assumptions are dated. | — |

## Additional findings (2026 code-review pass)

A full read of the code after the initial assessment surfaced the issues below,
each cited to a specific line. They are era-typical for 2006–2010 PHP and
reinforce the "study/run locally, do not expose publicly" guidance.

| Issue | Severity | Detail | Location |
|---|---|---|---|
| **No CSRF protection anywhere** | High | No anti-forgery token is generated or verified. Every state-changing action (create/modify/delete users, groups, levels, pages, mods; the site `disable` kill-switch) fires on the mere presence of `submit`/`batch_submit` in the request. | dispatch [luna.php:458-476](../luna/luna.php#L458) |
| **SQL injection in `mod_journal`** | High | `$_GET['start']` flows unsanitised into a `LIMIT` clause by string concatenation (never `intval()`'d); HTML_Safe does not neutralise SQL metacharacters in a numeric context. | [luna.mod_journal.php:142](../luna/luna.mods/luna.mod_journal.php#L142) → [:168](../luna/luna.mods/luna.mod_journal.php#L168) |
| **Session fixation (no ID regeneration)** | High | `login()` only `UPDATE`s `session_logged_in` on the *existing* session id; `session_regenerate_id()` is never called anywhere. With `use_trans_sid=1` the id can arrive from the URL, so a pre-seeded id survives authentication. | [luna.mod_log.php:164](../luna/luna.mods/luna.mod_log.php#L164) |
| **Submit handlers never re-check privileges** | High | `check_privileges()` runs once in the constructor against the *requested page's* level only. No `submit_add/modify/delete` re-validates rights on the specific target node (whose id comes from request input). | [luna.tools.class.php:717](../luna/luna.classes/luna.tools.class.php#L717), called at [luna.php:255](../luna/luna.php#L255) |
| **No login throttling** | Medium | `login_attempts` is incremented on failure and reset on success but **never read** — no lockout or back-off. Combined with unsalted MD5, brute-force is unbounded. | [luna.mod_log.php:156](../luna/luna.mods/luna.mod_log.php#L156), [:184](../luna/luna.mods/luna.mod_log.php#L184) |
| **PHP object injection via `unserialize()`** | Medium | Attacker-controlled sort-cookie data and request input are passed to `unserialize()` before any type check, enabling property-oriented gadget attacks if a usable magic method is autoloadable. | [luna.tools.class.php:804](../luna/luna.classes/luna.tools.class.php#L804); [luna.model.class.php:1310](../luna/luna.classes/luna.model.class.php#L1310) |
| **Reflected XSS in the error page** | Medium | `raise_error_page()` interpolates the user-controlled requested path into the HTML response with no escaping before `die()`. | [luna.tools.class.php:296](../luna/luna.classes/luna.tools.class.php#L296) |
| **Stored content rendered unescaped** | Medium | `luna:content` (WYSIWYG HTML from `luna_texts`) is emitted with `disable-output-escaping="yes"` in the default/root templates, so stored HTML is injected verbatim. By design (rich text) — safety rests entirely on the era-2009 HTML_Safe filter applied on save. | [luna.default.html.xsl:28](../luna/luna.xsl/luna.html.xsl/luna.default.html.xsl#L28) (+ `luna.root`, and the lunarsystem.org theme) |
| **`purgelogs` wipes the audit log via GET** | Medium | Any request containing `purgelogs` triggers an unconditional `DELETE FROM luna_logs` in `mod_journal::load()` — no token, no confirmation. A single forged `<img>` clears the log. | [luna.mod_journal.php:79](../luna/luna.mods/luna.mod_journal.php#L79) |
| **Sensitive data written to `luna_logs`** | Low | On every logged error `lunaLog::log()` serialises the full `$_SERVER` (client IP, URI/query, cookies header, UA, any auth headers) and the session object into the log row. | [luna.log.class.php:38](../luna/luna.classes/luna.log.class.php#L38) |
| **Weak/bypassable session hijack guard** | Low | The session is bound to UA + IP, but UA is fully client-controlled and `encode_ip()` trusts `X-Forwarded-For`, so the guard is spoofable — and it breaks legitimate users behind rotating IPs. | [luna.session.class.php:322](../luna/luna.classes/luna.session.class.php#L322); [luna.tools.class.php:656](../luna/luna.classes/luna.tools.class.php#L656) |

> These are a code-reading review, not a penetration test, and not exhaustive.
> `mod_node`, by contrast, **does** enforce per-node level access before dumping
> a node ([luna.mod_node.php:71-74](../luna/luna.mods/luna.mod_node.php#L71)).

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
6. Keep `DEBUG = 0` in production `luna.ini` (it is already `0` for
   `lunarsystem.org`) so errors aren't displayed.
7. Firewall the database; never expose port 3306/3307 publicly.

## Scope of this assessment

These notes are a code-reading review, not a penetration test. The list captures
the obvious, era-typical weaknesses; it is not exhaustive. Do not treat absence
from this list as evidence of safety.
