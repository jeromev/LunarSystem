# Security & Known Issues

LunarSystem is **alpha-grade software from 2006–2010** and is no longer
maintained. It reflects the security practices of its era. Treat it as a
historical artifact: safe to study and run locally, **not** safe to expose on the
public internet without significant hardening.

## Hard compatibility limits

| Issue | Impact | Detail |
|---|---|---|
| **PHP 5.3–5.6 only** | Blocking | Uses the `mysql_*` extension (removed in PHP 7) via PEAR MDB2, which has no PDO path here. Will not run on PHP 7+. |
| **MyISAM `TYPE=` syntax** | Blocking on MySQL 8 | The schema uses `TYPE=MyISAM`; MySQL 8 requires `ENGINE=MyISAM`. MySQL 5.7 works with `sql_mode=""`. |
| **PEAR dependencies** | Maintenance | Relies on vendored PEAR MDB2 / Log / Cache_Lite and ARC2 — all unmaintained. |

## Security weaknesses

| Issue | Severity | Detail | Mitigation |
|---|---|---|---|
| **Unsalted MD5 passwords** | High | `luna_users.password` is a bare `md5()` hash. Trivially crackable; vulnerable to rainbow tables. | Do not reuse real passwords. Don't expose the site publicly. |
| **Session ID in URL** | High | `session.use_trans_sid = 1` ([luna.php:27](../luna/luna.php#L27)) propagates the session ID through URLs, which leak via referrers, logs, and shared links — enabling session fixation/hijacking. | Disable trans_sid; require cookies. |
| **Credentials committed to the repo** | High | `luna/luna.domains/lunarsystem.org/ini/db.ini` historically held real production DB credentials. | `git rm --cached luna/luna.domains/lunarsystem.org/ini/db.ini`, rotate the password, and add it to `.gitignore`. |
| **Weak default admin** | Medium | Seed admin is `admin@lunarsystem.local` / `luna`. | Change immediately after install. |
| **Old sanitisation stack** | Medium | Input cleaning leans on PEAR HTML_Safe and hand-rolled filters of its era. SQL is escaped via MDB2 `quote()`, but coverage should not be assumed complete against modern XSS/SQLi techniques. | Audit before any untrusted exposure. |
| **`register_globals`-era assumptions** | Low | Code predates modern superglobal handling; it explicitly disables `register_globals` and guards `magic_quotes`, but the design assumptions are dated. | — |

## Repository hygiene

| Item | Status / note |
|---|---|
| `db.ini` with real credentials | Should be untracked and rotated (see above). |
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
