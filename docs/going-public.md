# Going public — readiness checklist

The plan for turning this repo into a public, **minimal, focused, safe, well
documented** testing unit for anyone interested in the Semantic Web — and a record
of what's done. Driven by a five-dimension audit (git history, app/deploy safety,
minimalism, docs/onboarding, OSS hygiene), with the safety findings verified
against the running stack.

**Headline:** the irreversible risk is clear — the git history was audited and
holds **no secrets** (25 commits; no `db.ini` ever committed; the production
credential never appears). The source is safe to publish; the only true gate was
operational (open ports), now fixed.

## Done

### Safety (Tier 0)
- [x] **Ports loopback-bound.** `docker-compose.yml` publishes every host port on
      `127.0.0.1` only (app `8080`, MySQL `3307`, Ontop `8081`, Oxigraph `7879`).
      Previously `0.0.0.0` — which exposed an unauthenticated Oxigraph `/update`
      (open graph-write) and MySQL on a bare `docker-compose up`. *(0.4.0-alpha)*
- [x] **Loud local-only banner** at the top of the README, before the quick start.
- [x] **Demo-credentials note** next to the login instructions.
- [x] **Mitigation documented** in [security.md](security.md); the new
      "Triplestore / SPARQL surface" section explains the posture.
- [x] **Git history verified clean** — no secret ever committed; the production
      `db.ini` is gitignored and was never tracked.

### Minimal & focused (Tier 2)
- [x] **Removed CKEditor** (`js/ckeditor`, ~6 MB) — admin editor is a plain
      `<textarea>`. Repo dropped from ~15 MB to ~8.6 MB. *(0.4.0-alpha)*
- [x] **Removed the `lunarsystem.org` production domain** (theme + the real-looking
      on-disk `db.ini`); the demo uses `luna.default`.

### A real testing unit (Tier 1)
- [x] **[try-it.md](try-it.md)** — a ~10-minute hands-on lab (data views → SPARQL →
      edit-and-read-back → swap the engine → federation).
- [x] **[../examples/queries.sparql](../examples/queries.sparql)** — copy-paste
      queries, all verified against the running stack.
- [x] README + docs index point at [why-rdf.md](why-rdf.md) and the lab.

### Open-source hygiene (Tier 3)
- [x] **[../THIRD-PARTY-NOTICES.md](../THIRD-PARTY-NOTICES.md)** — bundled libs +
      their licenses (the project is GPL v2; original author *Odradek* is credited
      in every file header).
- [x] **[../CONTRIBUTING.md](../CONTRIBUTING.md)**, **[../SECURITY.md](../SECURITY.md)**,
      **[../CODE_OF_CONDUCT.md](../CODE_OF_CONDUCT.md)**.
- [x] **`.github/`** — issue + PR templates and a **CI** workflow (`php -l` on
      project code, `docker compose config`, and a guard that every published port
      stays `127.0.0.1`-bound).

## Remaining — owner-only (can't be done from inside the repo)

- [x] **Rotate the `dbuser@mysql.jeromev.net` database password.** It lived in a
      working tree for years (never in git, but presume it's exposed). The file is
      gone from the repo now; rotate the credential at the DB server regardless.
- [ ] **On GitHub:** flip the repo to **public**; set a description and topics
      (`semantic-web`, `sparql`, `rdf`, `linked-data`, `json-ld`, `teaching`);
      confirm the default branch is `main` (with `legacy` preserved); optionally
      enable branch protection on `main`.
- [ ] **After the first push:** confirm the CI workflow runs green.

## Deliberately *not* done (and why)

- **Production-grade hardening beyond the 2026 pass.** The 0.6.9–0.8.6 pass added
  CSRF tokens, bcrypt passwords, session rotation, SQLi/header fixes and a per-IP login
  throttle (see [security.md](security.md)) — but this stays a localhost teaching
  artifact: the admin modules still lack per-target authorization and the SPARQL
  endpoint is unauthenticated. The fix for safety is "run it on localhost," not
  "make it production-grade." 
- **Authenticating the SPARQL endpoints.** Mitigated by loopback binding; real auth
  belongs to the P2/P4 work if the store is ever exposed. See [roadmap.md](roadmap.md).
- **Removing the dual Ontop + Oxigraph stack.** It's the core lesson (virtual vs
  materialised SPARQL), not bloat — kept on purpose.
