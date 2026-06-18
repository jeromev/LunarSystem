# The RDF Model

The heart of LunarSystem is
[`lunaModel`](../luna/luna.classes/luna.model.class.php) — a singleton that holds
an **in-memory RDF triple store** for the duration of a request, projects
relational rows into it, and serialises it (to RDF/XML for XSLT, or to
XML/JSON/N-Triples for raw output).

> RDF here is a *representation*, not a storage engine. Nothing is persisted as
> triples; the graph is rebuilt from MySQL on every request and discarded at the
> end.

## The in-memory structure

The store is a nested PHP array, `lunaModel::$index`, shaped like:

```
$index[ subject_uri ][ predicate_uri ][ n ] = array(
    'value' => <string|uri>,
    'type'  => 'uri' | 'literal' | 'bnode',
    'lang'  => <optional language tag>,
);
```

So a single "node" is one `subject_uri` key whose value is a map of predicates,
each holding an ordered list of value objects. A second array,
`lunaModel::$aliases`, maps URL paths to node IDs for routing.

Conceptually, a page node looks like:

```
/node/9
  luna:nid          → "9"            (literal)
  luna:lid          → "root"         (literal)
  rdf:type          → luna:page      (uri)
  rdfs:label        → "Home" @en     (literal, lang-tagged)
  owl:isChildOf     → /node/9        (uri)
```

## Namespaces

Configured in the constructor. The vocabulary mixes standard ontologies with one
custom namespace:

| Prefix | URI | Used for |
|---|---|---|
| `rdf` | `http://www.w3.org/1999/02/22-rdf-syntax-ns#` | `rdf:type`, descriptions |
| `rdfs` | `http://www.w3.org/2000/01/rdf-schema#` | `rdfs:label` |
| `foaf` | `http://xmlns.com/foaf/0.1/` | users → `foaf:Person`, `foaf:name`, `foaf:mbox` |
| `owl` | `http://www.w3.org/2002/07/owl#` | `owl:isChildOf` (page hierarchy) |
| `dc` | `http://purl.org/dc/elements/1.1/` | Dublin Core (metadata) |
| `dcterms` | `http://purl.org/dc/terms/` | Dublin Core terms |
| **`luna`** | **`http://lunarsystem.org/ontology#`** | **all system predicates**: `nid`, `lid`, `is_active`, `content`, `user`, `group`, `level`, `alias`, `value`, `code`, … |

The `luna:` namespace string is exposed as `lunaModel::$lunaNameSpace` and used
throughout the loaders.

## SQL → RDF projection

The relational schema (see [database-schema.md](database-schema.md)) is a generic
graph, and the model's job is to read it back as triples. The key mapping:

| SQL | RDF |
|---|---|
| `luna_nodes` row | a subject node with `luna:nid`, `luna:lid`, `rdfs:label`, `luna:is_active`, `owl:isChildOf` (parent) |
| `luna_nodes.tid` → `luna_types.lid` | `rdf:type` (e.g. `luna:page`, `luna:text`) |
| `luna_nodes_map (nid1, nid2)` | a typed predicate edge between two nodes (e.g. mod→level, user→group) |
| `luna_texts` row | a `luna:content` property (lang-tagged) on the text node |
| `luna_users` row | a `foaf:Person` with `foaf:name`, `foaf:mbox` (`mailto:`), `luna:ip`, `luna:last-visit` |
| `luna_actions` (joined) | author/timestamp metadata on content nodes |

The trick that makes a single edge table work as RDF: `load_nodes()` reconstructs
*typed* n-ary relationships by joining `luna_nodes_map` back through
`luna_nodes`/`luna_types`, so an edge `nid1→nid2` becomes
`subject luna:<type-of-nid2> object`.

## Method groups

### Building / merging the index
- `merge_index($nodes)` — merge a set of nodes into `$index`.
- `merge_nodes($a, $b)` — deep-merge two node arrays, de-duplicating by value.
- `purge_index()` — clear and rebuild.
- `calculate_aliases($nodes, $nid)` — recursively build the path→nid alias table.

### Accessors (read the graph)
- `get_node($nid, $type, $ns)`, `get_node_from_alias($alias, …)`,
  `get_page_node_from_alias($path)` — fetch nodes.
- `get_nid($node)`, `get_lid($node)`, `get_type($node)` — extract identity.
- `get_parent_node()`, `get_children_nodes()`, `get_children_nids()` — traverse
  the page hierarchy (via `owl:isChildOf`).
- `get_level_node($node)` — the access level a node requires.
- `get_nid_from_lid($lid)` — direct DB lookup, slug → id.

### Mutators (write the graph + DB)
- `set_property($node, $lid, $value, $ns)` — add/update a property on a node.
- `insert()`, `link()`, `update()`, `unlink()` — node and edge CRUD that writes
  through to `luna_nodes` / `luna_nodes_map` (used by the admin mods).

### Loaders (project SQL/PHP data into the model)
- `load_node()` / `load_nodes()` — DB rows → typed nodes + relationships.
- `load_user()` / `load_users()` — users → `foaf:Person` + groups/levels.
- `load_text()` / `load_texts()` — content blocks (lang-tagged `luna:content`).
- `load_data()`, `load_var()`, `load_request()`, `load_vocabulary()` — flatten
  PHP arrays (config, `$_REQUEST`, i18n strings) into blank-node variables under
  the `luna:` namespace, so the view layer can reach them.
- `load_messages()`, `load_pager()` — flash messages and pagination metadata.

### Validation helpers
- `check_requested_node()`, `check_if_node_exists()`,
  `check_if_lid_is_protected()`, `check_if_lid_is_taken()` — guard rails used by
  mods before mutating the graph.

## Serialisation and rendering

Two output paths, both backed by the bundled **ARC2** RDF library
([luna/luna.lib/](../luna/luna.lib/)):

### `dump($flavor, $return, $node)` — raw output
Serialises `$index` to one of:
- **xml** → `ARC2::getRDFXMLSerializer()` (`application/rdf+xml`)
- **json** → `ARC2::getRDFJSONSerializer()` (`application/rdf+json`)
- **n3** → `ARC2::getNtriplesSerializer()`
- **turtle** → `ARC2::getTurtleSerializer()`

Triggered by `?output=xml|json|n3` on any URL. Sets the content-type header and
exits.

### `transform($xslfile)` — HTML output
1. Check the Cache_Lite cache, keyed on `md5(serialize([$conf, $index]))`.
2. On a miss: serialise `$index` to RDF/XML (ARC2), load it into a `DOMDocument`,
   load the XSLT stylesheet, and run PHP's built-in `XSLTProcessor`
   (`transformToXML`).
3. Cache and return the resulting HTML string.

See [templating.md](templating.md) for how the stylesheet is chosen and the
RDF/XML shape the templates consume.
