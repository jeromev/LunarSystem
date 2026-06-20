# Security Policy

## This is a study artifact, not production software

LunarSystem is **alpha-grade software from 2006–2010**, revived as an educational
exploration of the Semantic Web. It reflects the security practices of its era and
underwent a 2026 hardening pass (0.6.9–0.8.6-alpha) that closed the major weaknesses —
bcrypt passwords with upgrade-on-login, CSRF tokens, session-fixation defence, SQLi
and header fixes, a per-IP login throttle — but **residual issues remain** (the admin
modules perform no per-target authorization, and the **SPARQL write endpoint is
unauthenticated**). It is meant to be **studied and run on `localhost`**, not deployed.

- The Docker stack binds every port to `127.0.0.1` (loopback). **Do not** change
  that or otherwise expose `8080` / `7879` / `8081` / `3307` to a public or
  untrusted network.
- The full, honest list of issues — and the 2026 hardening pass (0.6.9–0.8.6-alpha) —
  lives in **[docs/security.md](docs/security.md)**. It is intentionally public:
  the weaknesses are part of what makes this a useful teaching artifact.

## Reporting

Because this is an unmaintained educational project, there is no private security
support or embargo process. If you find an issue **not already documented** in
[docs/security.md](docs/security.md), please open a normal GitHub issue describing
it — that improves the documented record for other learners. Do **not** expect a
patched, deployable release.

If you intend to run any of this code anywhere reachable from a network, treat it
as inherently unsafe and harden it yourself first.
