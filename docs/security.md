# Security & Known Issues

LunarSystem is **alpha-grade software from 2006–2010** and is no longer
maintained. It reflects the security practices of its era. Treat it as a
historical artifact: safe to study and run locally, **not** safe to expose on the
public internet without significant hardening.

## 2026 hardening pass (0.6.9–0.8.5-alpha)

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
| **IDOR** — edit_texts text→page linking + content modify/delete | per-target `user_can_access_page()` on links; `user_can_act_on_text()` now fails **closed** (a text on a level-less or higher-level page is denied) | ✅ | 0.7.6 / 0.8.1 |
| **2nd blind/stacked SQLi** — `load_texts` start/limit | `intval`-clamped like `load_users` (was editor-exploitable: `limit=20;SELECT SLEEP(5)`) | ✅ | 0.8.0 |
| **Source/secret disclosure — case bypass** | `.htaccess` deny rules made case-insensitive (`[NC]`); `/.GIT/HEAD`, `/DOCKERFILE`, `/DOCKER-COMPOSE.YML`, `/DOCS/` no longer served (had leaked DB creds) | ✅ | 0.8.0 |
| **Audit-log CSRF** — `purgelogs` | log wipe in `mod_journal` now requires POST + a valid CSRF token; token-bearing purge form added | ✅ | 0.8.0 |
| **Login timing enumeration** — legacy MD5 | throwaway bcrypt verify on the MD5 branch so legacy accounts are not ~3× faster than unknown ones | ✅ | 0.8.1 |
| **Sanitizer / deserialization gaps** | `formaction`/`poster`/`ping`/`srcset`/`xlink:href` protocol-filtered; journal log `unserialize(…, ['allowed_classes'=>['lunaException']])`; request-driven `?purge` removed | ✅ | 0.8.1 |
| **Account enumeration** — login throttle | back-off moved to a per-IP table (`luna_login_throttle`); fires for unknown and known accounts alike (existence no longer observable from timing) | ✅ | 0.8.2 |
| **CSRF token in URL** — logout | logout converted to a POST `<form>`; `logout()` requires POST + a matching `$_POST` token (token no longer in history / access log) | ✅ | 0.8.3 |
| **`use_strict_mode` inert** | session handler switched to the object form implementing `validateId()`; a forged client session id is rejected and reissued, not adopted | ✅ | 0.8.4 |
| **Login-throttle TOCTOU** | back-off was read→sleep→increment, so N parallel requests from one IP read the same stale count and skipped the escalation; the attempt is now counted **first** with an atomic `INSERT … ON DUPLICATE KEY UPDATE` (MyISAM table lock serialises), then slept on the post-increment count | ✅ | 0.8.5 |
| **Journal deserialization** — list path | the list-all loop used a bare `unserialize()` (the single-entry path was guarded in 0.8.1); both now pass `['allowed_classes'=>['lunaException']]` | ✅ | 0.8.5 |

## Second adversarial review (post-0.8.4)

A fresh multi-agent review audited the post-0.8.4 tree across 8 dimensions (SQLi, authn/timing, session, authz/IDOR, CSRF, XSS/sanitizer, disclosure/deploy, fix-regression). Each finding was independently voted on by two skeptics — one proving exploitability, one trying to refute — then synthesised.

**Verdict: ship-with-low-risk.** No remotely-exploitable critical/high in the stock configuration. 29 raw findings → 2 real defects fixed (both in 0.8.5, above) + 1 latent gap, since closed (below). The rest were dismissed with reasons: the 8-finding admin "IDOR/privilege-escalation" cluster is not reachable as shipped (single admin tier, below); the SameSite=Lax/`validateId`-rebind/regenerate-race session findings are defended in depth by the **mandatory UA+IP binding** in `get_user_data()` (a mismatch yields zero rows → demotion to anonymous); the legacy-MD5 timing leak is mitigated by the dummy bcrypt verify and is below network noise; the `Cache_Lite`/`ARC2_Store` deserialization sinks are dead/unreachable vendor code; and all host ports bind `127.0.0.1` (Oxigraph's `0.0.0.0:7878` is container-internal only).

**Resolved since the review:**
- **Admin modules now enforce per-target authorization in their submit handlers** (0.8.13–0.8.20). `mod_admin_users/groups/pages/levels/mods` re-check the actor against the *specific* target — `user_can_access_level()` / `user_can_access_page()` / `user_can_access_group()` on the node being modified/deleted **and** on every level/group/page being assigned — mirroring the `edit_texts`/`mod_node` per-target pattern. This is a no-op in the shipped single-admin tier (the admin holds every level, so each check passes), but it closes the privilege-escalation/IDOR vector if an operator **delegates admin by re-binding an admin page/mod to a lower level**: a lower-tier admin can no longer grant a level/group above their own, nor act on a target above their access. Validated by [`test/delegated_admin.sh`](../test/delegated_admin.sh), which manufactures a `level_edition`-only admin and asserts it cannot grant `level_admin` to a group (the attempt is denied and no link is written).

**Residual (documented, not exploitable as shipped):**
- The per-IP login throttle resists parallel/sequential brute force from one IP but is still bypassable by **IP rotation** (one free first guess per fresh IP), and there is no per-account lockout — a deliberate trade-off, since a per-account counter would re-introduce the account-enumeration timing leak fixed in 0.8.2.
- The session guard bound to the client `User-Agent` is a fixed, weak binding. (The CSP was tightened to strict `'self'` for scripts and styles — no `'unsafe-inline'` — in 0.8.16, with all inline handlers delegated via data-attributes.)

This remains an archival app on PHP 8.3 with a flat group→level authz model. Keep it behind web-server auth / a VPN and **off the public internet**.

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
  not be public. **Mitigation in place (0.8.17):** the SPARQL services have **no host port** at all
  — Oxigraph and Ontop are reachable only on the internal compose network (by `app`),
  never from the host or a browser, which closes the CSRF-to-localhost write vector.
  The remaining published ports (app `8080`, MySQL `3307`) are bound to `127.0.0.1`.
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
