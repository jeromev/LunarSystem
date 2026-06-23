# Try it — the Semantic Web in ~10 minutes

A guided tour of what makes LunarSystem unusual: the same content served as a
web page, as JSON-LD, and as a **queryable RDF graph** — with the storage engine
swappable underneath. No prior RDF/SPARQL experience needed.

> Everything here runs on `localhost` only (the stack binds its ports to
> `127.0.0.1`). It's a study artifact — see [security.md](security.md).

## 0. Start the stack (~1 min + ~15 s for MySQL)

```bash
docker-compose up --build -d
```

This starts five services: the **app** (`8080`), **MySQL** (`3307`), **Ontop**
(virtual SPARQL over MySQL), **Oxigraph** (the triplestore) and **sparql-proxy**
(a Caddy reverse proxy that adds the HTTP basic auth Oxigraph lacks). The
semantic-web services have no host port — Oxigraph sits on an internal-only
network reachable solely through `sparql-proxy`, which the app talks to with
credentials (`SPARQL_AUTH_USER` / `SPARQL_AUTH_PASS`, demo defaults
`luna` / `luna-sparql-dev`). Query them via `docker-compose exec -T app`.

Open **http://localhost:8080** — the home page. Log in (top of the site) as
`admin@lunarsystem.local` / `luna` to see the admin pages too.

## 1. The same page, three ways

A normal CMS gives you HTML. Ask LunarSystem for the *data* instead:

```bash
# Human view:
open http://localhost:8080/                      # (or just visit it)

# The schema.org JSON-LD a search engine would read (also embedded in every <head>):
curl -s 'http://localhost:8080/?output=jsonld'

# The same model as RDF/XML and as N-Triples:
curl -s 'http://localhost:8080/?output=xml'
curl -s 'http://localhost:8080/?output=n3'
```

One model, many standard representations — the content describes itself with
schema.org / FOAF terms, so any RDF-aware tool understands it with no bespoke API.

## 2. Query the content as a graph

The whole site is in a triplestore you can ask arbitrary questions of. The seed
content loads via SQL, so the graph starts empty — populate it from MySQL first
(once):

```bash
make resync-triplestore        # → bin/resync-triplestore.php; clears + re-projects every node
```

Then try the census — every content type counted in one query:

```bash
docker-compose exec -T app sh -c "curl -s -u \"\$SPARQL_AUTH_USER:\$SPARQL_AUTH_PASS\" \
http://sparql-proxy:7878/query -H 'Accept: text/csv' --data-urlencode \
'query=PREFIX schema: <https://schema.org/>
SELECT ?type (COUNT(?s) AS ?n) WHERE { ?s a ?type } GROUP BY ?type'"
```

→ `WebPage 13`, `Article 1`, `Person 2`. Now something a SQL app would hand-write
a self-join for — "which pages share `admin`'s access level?":

```bash
docker-compose exec -T app sh -c "curl -s -u \"\$SPARQL_AUTH_USER:\$SPARQL_AUTH_PASS\" \
http://sparql-proxy:7878/query -H 'Accept: text/csv' --data-urlencode \
'query=PREFIX schema: <https://schema.org/>
PREFIX luna: <https://jeromev.github.io/LunarSystem/ontology#>
SELECT ?sibling WHERE {
  ?a schema:name \"admin\" ; luna:level ?l .
  ?s luna:level ?l ; schema:name ?sibling . FILTER (?s != ?a) }'"
```

More to paste in [`../examples/queries.sparql`](../examples/queries.sparql):
transitive ancestry (`schema:isPartOf+`), `ASK`, `CONSTRUCT`, `DESCRIBE`, and
cross-store federation.

## 3. The app runs *on* the graph — prove it

Routing and access control are answered by SPARQL by default, not SQL. Edit a text
in the admin UI (Edition → edit a text block, e.g. the "welcome" text on the home
page) and save. Then read it back **straight from the triplestore**:

```bash
docker-compose exec -T app sh -c "curl -s -u \"\$SPARQL_AUTH_USER:\$SPARQL_AUTH_PASS\" \
http://sparql-proxy:7878/query -H 'Accept: text/csv' --data-urlencode \
'query=PREFIX schema: <https://schema.org/>
SELECT ?body WHERE { <http://localhost:8080/id/welcome> schema:articleBody ?body }'"
```

Your edit is there — the write went to MySQL **and** mirrored into the graph, and
the home page is rendered from the graph.

## 4. Swap the engine — no code change

The app talks only to a SPARQL endpoint, so you can change what's behind it. Point
the read path at **Ontop** (virtual SPARQL compiled to SQL over the *unchanged*
MySQL) instead of Oxigraph:

```bash
SPARQL_ENDPOINT=http://ontop:8080/sparql docker-compose up -d app
```

Reload http://localhost:8080 — identical site, now served live from MySQL through
SPARQL. Switch back by re-running `docker-compose up -d app` with no override.

You can also bypass SPARQL entirely for one request and read from the SQL joins:
`http://localhost:8080/?sparql=0`.

## 5. Federation — join the triplestore against live MySQL in one query

```bash
docker-compose exec -T app sh -c "curl -s -u \"\$SPARQL_AUTH_USER:\$SPARQL_AUTH_PASS\" \
http://sparql-proxy:7878/query -H 'Accept: text/csv' --data-urlencode \
'query=PREFIX schema: <https://schema.org/>
SELECT ?name ?nid WHERE {
  ?p a schema:WebPage ; schema:name ?name .
  SERVICE <http://ontop:8080/sparql> { ?p schema:identifier ?nid } }'"
```

Oxigraph supplies the names; Ontop supplies the live row ids from MySQL; they join
on the shared `/id/{slug}` identity — no ETL, no shared schema.

## Where to go next

- **[why-rdf.md](why-rdf.md)** — what all this *unlocks* vs a plain PHP/MySQL app.
- **[linked-data.md](linked-data.md)** — the design: URI policy, vocabulary, the
  read/write loop, the Ontop→Oxigraph swap.
- **[architecture.md](architecture.md)** — how a request flows end to end.

## Tear down

```bash
docker-compose down          # stop; keep data
docker-compose down -v       # stop and wipe the MySQL + Oxigraph volumes
```
