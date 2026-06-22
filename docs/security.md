# Security & Known Issues

LunarSystem is **alpha-grade software from 2006–2010** and is no longer
maintained. It reflects the security practices of its era. Treat it as a
historical artifact: safe to study and run locally, **not** safe to expose on the
public internet without significant hardening.

## 2026 hardening pass (0.6.9–0.8.21-alpha)

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
| **Stored XSS** (SVG SMIL `<animate>` bypass) | HTML_Safe stripped `svg`/`math`/`animate`/… + SMIL attrs (the whole sanitiser was **replaced by HTMLPurifier** in 0.8.28 — an allowlist parser that closes the class by construction); cache `unserialize(..., ['allowed_classes'=>false])` | ✅ | 0.7.5 / 0.8.28 |
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

**Verdict: ship-with-low-risk.** No remotely-exploitable critical/high in the stock configuration. 29 raw findings → 2 real defects fixed (both in 0.8.5, above) + 1 latent gap, since closed (below). The rest were dismissed with reasons: the 8-finding admin "IDOR/privilege-escalation" cluster is not reachable as shipped (single admin tier, below); the SameSite=Lax/`validateId`-rebind/regenerate-race session findings are defended in depth by the **mandatory UA+IP binding** in `get_user_data()` (a mismatch yields zero rows → demotion to anonymous); the legacy-MD5 timing leak is mitigated by the dummy bcrypt verify and is below network noise; the `ARC2_Store` deserialization sink is dead/unreachable vendor code (the `Cache_Lite` one is gone — that library was replaced by a native cache in 0.8.29); and all host ports bind `127.0.0.1` (Oxigraph's `0.0.0.0:7878` is container-internal only).

**Resolved since the review:**
- **Admin modules now enforce per-target authorization in their submit handlers** (0.8.13–0.8.20). `mod_admin_users/groups/pages/levels/mods` re-check the actor against the *specific* target — `user_can_access_level()` / `user_can_access_page()` / `user_can_access_group()` on the node being modified/deleted **and** on every level/group/page being assigned — mirroring the `edit_texts`/`mod_node` per-target pattern. This is a no-op in the shipped single-admin tier (the admin holds every level, so each check passes), but it closes the privilege-escalation/IDOR vector if an operator **delegates admin by re-binding an admin page/mod to a lower level**: a lower-tier admin can no longer grant a level/group above their own, nor act on a target above their access. Validated by [`test/delegated_admin.sh`](../test/delegated_admin.sh), which manufactures a `level_edition`-only admin and asserts it cannot grant `level_admin` to a group (the attempt is denied and no link is written).

- **Admin-lockout guardrails** (0.8.30). The admin tier reaches the admin UI through one path — a user in `group_admin`, which grants `level_admin`, which gates the admin pages/mods — so any edit that severs it can lock *every* administrator out of a running site (this actually happened: the sole admin unticked their own `group_admin` membership in the user editor). A multi-agent audit enumerated and adversarially verified the lockout surface; four reachable vectors were closed: (1) `mod_admin_users::submit_modify` now refuses to drop the actor's own admin membership or the **last active** administrator; (2) `submit_delete` won't delete the last admin; (3) `mod_admin_pages::submit_delete` and (4) `mod_admin_mods::submit_delete`/`submit_modify` gained the protected-lid guard their siblings already had. As defence-in-depth, `lunaModel::delete()` itself refuses to delete any node in the central `luna::$protected_lids` set (now extended to `group_admin`/`group_default`, `level_admin`/`level_public`, the admin pages and the admin modules) — so even a future handler that forgets the check cannot delete a structural node. Validated by [`test/admin_lockout.sh`](../test/admin_lockout.sh), which asserts all four vectors are blocked while ordinary user create/delete still works.

**Residual (documented, not exploitable as shipped):**
- The per-IP login throttle resists parallel/sequential brute force from one IP but is still bypassable by **IP rotation** (one free first guess per fresh IP), and there is no per-account lockout — a deliberate trade-off, since a per-account counter would re-introduce the account-enumeration timing leak fixed in 0.8.2.
- The session guard bound to the client `User-Agent` is a fixed, weak binding. (The CSP was tightened to strict `'self'` for scripts and styles — no `'unsafe-inline'` — in 0.8.16, with all inline handlers delegated via data-attributes.)

This remains an archival app on PHP 8.3 with a flat group→level authz model. Keep it behind web-server auth / a VPN and **off the public internet**.

## Hard compatibility limits

| Issue | Impact | Detail |
|---|---|---|
| **Runtime** | — | Runs on **PHP 8.3 / MySQL 8.0** via PDO (`pdo_mysql`); the 0.5.0-alpha migration removed the PHP-7-blocking `mysql_*` extension and PEAR MDB2. |
| **MyISAM storage engine** | Compatibility | The schema now uses `ENGINE=MyISAM` (the original `TYPE=MyISAM` syntax was removed back in MySQL 5.5). MySQL 8.0 also needs `sql_mode=""` for the legacy column defaults — the Docker stack sets this. |
| **Vendored libs** | Maintenance | The only in-tree vendored library left is **semsol/arc2 3.1.0** (RDF/SPARQL), kept vendored on purpose: it carries local PHP-8 patches the upstream lacks (stock 3.1.0 fatals on `?output=n3`), so it is not a drop-in Composer package — see [`luna.lib/arc/VENDOR.txt`](../luna/luna.lib/arc/VENDOR.txt). Removed in the libs cleanup: PEAR **Log** (0.8.27, constants inlined), **HTML_Safe** + **XML_HTMLSax3** (0.8.28 → HTMLPurifier), and **Cache_Lite** + PEAR base (0.8.29 → a native file cache, `luna.cache.class.php`). The lone Composer dependency is HTMLPurifier (committed under `vendor/`). |

## Security weaknesses

| Issue | Severity | Detail | Mitigation |
|---|---|---|---|
| **Unsalted MD5 passwords** | High | `luna_users.password` is a bare `md5()` hash. Trivially crackable; vulnerable to rainbow tables. | Do not reuse real passwords. Don't expose the site publicly. |
| **Session ID in URL** | High | `session.use_trans_sid = 1` ([luna.php:33](../luna/luna.php#L33)) propagates the session ID through URLs, which leak via referrers, logs, and shared links — enabling session fixation/hijacking. | Disable trans_sid; require cookies. |
| **Weak default admin** | Medium | Seed admin is `admin@lunarsystem.local` / `luna`. | Change immediately after install. |
| **Sanitisation stack** | Medium | Input HTML cleaning uses **HTMLPurifier** (0.8.28 — an allowlist DOM parser, replacing the era-2005 HTML_Safe denylist). SQL is escaped via PDO `quote()` (`lunaDB::quote`); SPARQL via `sparql_literal`/`rdf_uri` (audited 0.8.26). Modern, but coverage should still not be assumed complete against every technique. | Audit before any untrusted exposure. |
| **`register_globals`-era assumptions** | Low | Code predates modern superglobal handling; it explicitly disables `register_globals` and guards `magic_quotes`, but the design assumptions are dated. | — |

## Additional findings (2026 code-review pass)

A full read of the code after the initial assessment surfaced the issues below,
each cited to a specific line. They are era-typical for 2006–2010 PHP and
reinforce the "study/run locally, do not expose publicly" guidance.

**Status** is current as of **0.8.32-alpha** (✅ fixed, ◐ partially fixed, ⬜ open).
The invasive changes that were initially deferred — CSRF tokens across every form,
per-target authorisation, session-ID rotation — were completed during the
0.6.9–0.8.21 hardening pass; the only ⬜ left is the by-design WYSIWYG output. Every
✅ was verified against the running Docker stack.

| Issue | Severity | Status | Detail | Location |
|---|---|---|---|---|
| **No CSRF protection anywhere** | High | ✅ fixed | A per-session synchroniser token is embedded in every state-changing form and verified centrally (POST-only `hash_equals`) in the dispatch before any handler runs; `?purge` is POST-only too. | dispatch [luna.php:479](../luna/luna.php#L479) (0.7.4) |
| **SQL injection in `mod_journal`** | High | ✅ fixed | `start` is now `intval()`'d before the `LIMIT` clause and `order_by` is whitelisted before it is interpolated as a SQL identifier into `COUNT()`/`ORDER BY`. | [luna.mod_journal.php](../luna/luna.mods/luna.mod_journal.php) |
| **Session fixation (no ID regeneration)** | High | ✅ fixed | `login()` now calls `session_regenerate_id()` (the DB-handler row is re-keyed), so a pre-seeded id does not survive authentication; sessions are cookie-only (`use_trans_sid=0`, `use_only_cookies=1`, `use_strict_mode=1`), so an id can no longer arrive from the URL. | [luna.mod_log.php](../luna/luna.mods/luna.mod_log.php) (0.7.1 / 0.7.3) |
| **Submit handlers never re-check privileges** | High | ✅ fixed | All five admin modules (`admin_users/groups/pages/levels/mods`) now re-check the actor against the *specific* target on every `submit_add/modify/delete` — via `user_can_access_level()`/`user_can_access_page()`/`user_can_access_group()` on the target node and on every level/group/page being assigned. No-op in the single-admin tier; blocks delegated-admin escalation. Validated by [`test/delegated_admin.sh`](../test/delegated_admin.sh). | [luna.mods/](../luna/luna.mods/) (0.8.13–0.8.20) |
| **No login throttling** | Medium | ✅ fixed | `login()` now reads `login_attempts` and applies a capped per-account back-off (`sleep(min(attempts, 5))`); a correct password still resets the counter, so accounts are never permanently locked. | [luna.mod_log.php](../luna/luna.mods/luna.mod_log.php) |
| **PHP object injection via `unserialize()`** | Medium | ✅ fixed | Sort cookies now use `json_encode`/`json_decode` (cannot instantiate objects); the `load_request()` path guards `unserialize()` against `O:`/`C:` object payloads. | [luna.tools.class.php](../luna/luna.classes/luna.tools.class.php); [luna.model.class.php](../luna/luna.classes/luna.model.class.php) |
| **Reflected XSS in the error page** | Medium | ✅ fixed | `raise_error_page()` now `htmlspecialchars()`-escapes the requested path before it reaches the HTML response. | [luna.tools.class.php:295](../luna/luna.classes/luna.tools.class.php#L295) |
| **Stored content rendered unescaped** | Medium | ◐ partial | `luna:content` (WYSIWYG HTML from `luna_texts`) is emitted with `disable-output-escaping="yes"`, so stored HTML is injected verbatim. By design (rich text) — safety now rests on **HTMLPurifier** (0.8.28), an allowlist DOM sanitiser applied to every input on save: `<script>`, SVG/MathML, `on*` handlers and `javascript:` URIs are dropped *by construction*, so the whole denylist-bypass class (incl. the SVG-SMIL `<animate>` vector the old HTML_Safe had to be hand-patched against) is closed structurally rather than per-CVE. | [luna.default.html.xsl:28](../luna/luna.xsl/luna.html.xsl/luna.default.html.xsl#L28) (+ `luna.root`); [lunaTools::sanitize()](../luna/luna.classes/luna.tools.class.php) |
| **`purgelogs` wipes the audit log via GET** | Medium | ✅ fixed | The `DELETE FROM luna_logs` now requires `$_POST['purgelogs']`, so a forged link/`<img>` (GET) can no longer trigger it. | [luna.mod_journal.php:81](../luna/luna.mods/luna.mod_journal.php#L81) |
| **Sensitive data written to `luna_logs`** | Low | ✅ fixed | `lunaLog::log()` now stores only a small `$_SERVER` whitelist (remote addr, method, URI, host, UA, referer) instead of the whole array (which carried the cookie header / session id). | [luna.log.class.php](../luna/luna.classes/luna.log.class.php) |
| **Weak/bypassable session hijack guard** | Low | ◐ partial | `encode_ip()` no longer trusts `X-Forwarded-For` (it uses `REMOTE_ADDR`), closing the IP-spoof bypass. The guard is still bound to the client-controlled User-Agent, and still breaks users behind rotating IPs. | [luna.session.class.php:322](../luna/luna.classes/luna.session.class.php#L322); [luna.tools.class.php](../luna/luna.classes/luna.tools.class.php) |

> These are a code-reading review, not a penetration test, and not exhaustive.
> `mod_node`, by contrast, **does** enforce per-node level access before dumping
> a node ([luna.mod_node.php:71-74](../luna/luna.mods/luna.mod_node.php#L71)).

## Triplestore / SPARQL surface (0.3.x)

The RDF-native read/write loop (see [linked-data.md](linked-data.md)) adds a
network surface the original CMS did not have. Status: ✅ hardened — authenticating
proxy + internal-only network + no host port.

- **SPARQL endpoint — authenticated and network-isolated.** The app reaches Oxigraph
  through an authenticating reverse proxy (`sparql-proxy`, Caddy) that demands HTTP
  basic auth on **every** request — reads and writes alike — before forwarding
  (`SPARQL_ENDPOINT`, `SPARQL_UPDATE_ENDPOINT`, `SPARQL_AUTH_USER`/`SPARQL_AUTH_PASS` in
  `luna.php`; `sparql_auth_header()` in the model adds the `Authorization` header). Two
  layered mitigations:
  - *(0.8.17)* the SPARQL services have **no host port** — never reachable from the host
    or a browser, which closes the CSRF-to-localhost write vector.
  - *(0.8.21)* Oxigraph — which has no native auth and accepts unauthenticated writes on
    `/update` + `/store` — is moved onto an **internal-only** compose network whose only
    other member is the proxy, so it is unreachable except *through* the authenticated
    proxy (the app cannot even reach `oxigraph:7878` directly). An unauthenticated request
    to the proxy gets `401`; the bcrypt hash is generated at container start from the
    plaintext `SPARQL_AUTH_PASS`, so **no password hash is committed**. Ontop (virtual,
    read-only) has no host port and stays on the default compose network (reachable by
    the app, never the host); only Oxigraph sits on the internal-only `triplestore` network.

  Keep the host bindings on `127.0.0.1`, change `SPARQL_AUTH_PASS` from its demo default
  (via `.env`) for any real use, and never publish Oxigraph or the proxy.
- **Hand-rolled SPARQL string assembly — audited injection-safe (0.8.26).** The
  write-through (`rdf_sync_node` / `rdf_delete_node` / `rdf_resync_all`) and the read
  builders (`load_nodes_sparql` / `load_texts_sparql`) assemble SPARQL by string
  interpolation, but every user-controlled value passes through one of three typed
  wrappers around an otherwise **static** query: `rdf_str()` wraps `sparql_literal()`
  (escapes `\`, `"`, `\n`, `\r`, `\t` — backslash first — the only characters that can
  break a double-quoted literal) in quotes; `rdf_uri()` `rawurlencode`s the lid, so every
  IRIREF-breaking character (`<` `>` `"` `{` `}` `|` `^` backtick `\` space, control
  bytes) is percent-encoded and cannot escape `<…>`; `rdf_int()` `intval`s. The read
  builders likewise `rawurlencode` the page slug and `intval` the level ids. Audited both
  statically (every call site) and with live break-out probes against the running
  triplestore using the actual escapers — a value crafted to close the literal and
  smuggle a second `INSERT DATA`, and a lid crafted to break the IRI, were each stored as
  inert data with **no injected triple** (`ASK → false`). User content reaches RDF only
  through these escapers.
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
