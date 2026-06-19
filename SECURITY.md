# Security Policy

## This is a study artifact, not production software

LunarSystem is **alpha-grade software from 2006–2010**, revived as an educational
exploration of the Semantic Web. It reflects the security practices of its era and
has **known, unfixed weaknesses** (unsalted MD5 passwords, no CSRF tokens on admin
actions, session handling that predates modern hardening, and an **unauthenticated
SPARQL write endpoint**). It is meant to be **studied and run on `localhost`**, not
deployed.

- The Docker stack binds every port to `127.0.0.1` (loopback). **Do not** change
  that or otherwise expose `8080` / `7879` / `8081` / `3307` to a public or
  untrusted network.
- The full, honest list of known issues — and what was hardened in 0.2.14-alpha —
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
