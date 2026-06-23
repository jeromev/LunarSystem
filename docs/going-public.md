# Going public — safety & scope

LunarSystem is published as a **minimal, focused, safe, well-documented** unit for
exploring the Semantic Web. This note records what makes it safe to publish and what is
deliberately out of scope.

## Safe to publish

- **No secrets in the repo or its history.** `db.ini` is gitignored and was never
  committed; under Docker the app runs from the `DB_*` environment defaults with no
  `db.ini` at all.
- **Loopback-only.** `docker-compose.yml` publishes every host port on `127.0.0.1` only
  (app `8080`, MySQL `3307`); the SPARQL services (Ontop, Oxigraph, and the `sparql-proxy`
  that fronts Oxigraph) have **no host port** — internal compose network only. CI enforces
  the loopback binding on every push.
- The security posture and known limitations are documented in [security.md](security.md).

## Deliberately out of scope (and why)

- **Production-grade hardening.** The app is hardened for *local study* — CSRF tokens,
  bcrypt passwords, session rotation, per-target admin authorization, admin-lockout
  guardrails, a per-IP login throttle, and an authenticated SPARQL proxy (see
  [security.md](security.md)) — but it stays a localhost teaching artifact. The fix for
  safety is "run it on localhost," not "make it production-grade."
- **Authenticating Ontop's virtual endpoint.** Oxigraph's read+write surface is
  authenticated through `sparql-proxy` (Caddy basic auth); Ontop's endpoint stays
  unauthenticated, but it is read-only and internal-only (no host port). Real auth there
  belongs to later roadmap work if the store is ever exposed — see [roadmap.md](roadmap.md).
- **The dual Ontop + Oxigraph stack is kept on purpose** — it is the core lesson (virtual
  vs. materialised SPARQL), not bloat.
