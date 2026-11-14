# Maps

A generator with a `map` returns cells or nodes that the application draws; each is filled by the same `cell` — a
template, or `{generate: name}` — which sees its place and the generator's variables.

## Square and hex maps

```yaml
generators:
  village:
    map: { layout: square, width: 4, height: 3, cell: "{{ pick('village.place') }}" }
  region:
    map: { layout: hex, width: 8, height: 6, cell: { generate: region.terrain } }
```

A square map gives each cell `x` and `y`; a hex map, in rows where every odd row is pushed half a cell to the right,
gives each cell its offset coordinates `col` and `row` and its axial coordinates `q` (`col - floor(row / 2)`) and `r`
(`row`). The cell template sees these variables.

```php
[
    'layout' => 'hex', 'width' => 8, 'height' => 6,
    'cells' => [['col' => 0, 'row' => 0, 'q' => 0, 'r' => 0, 'value' => 'forest'], …],
]
```

## Graphs

```yaml
generators:
  dungeon:
    map: { layout: graph, nodes: '1d4+3', extra_edges: 2, cell: { generate: dungeon.room } }
```

A graph has a number of nodes — a number, or a dice expression rolled for each map, at least one — each filled by the
cell, which sees `index` and `count`. Each node after the first is joined to an earlier one chosen at random, so that
every node can be reached; then `extra_edges` more passages join two nodes not yet joined, making loops, as many as the
graph can still take. Edges are pairs of node identifiers, the smaller first, in order.

```php
[
    'layout' => 'graph',
    'nodes' => [['id' => 0, 'value' => ['kind' => 'hall', 'feature' => 'a locked chest']], …],
    'edges' => [[0, 1], [0, 3], [1, 2], …],
]
```

A map has at most `Limits::$cells` cells or nodes. Placing the nodes in space, the terrain that follows its neighbours
and the drawing are left to the application or to more specific packages.

## Exporting a map

`MapSchema` writes a map as JSON for another program — a map editor, a virtual tabletop, a printer — with the
identifier of the schema it follows and its version first:

```php
use Tilttek\Generator\MapSchema;

$json = MapSchema::json($generator->draw('dungeon')->first(), JSON_PRETTY_PRINT);
// {"$schema": "urn:tilttek:generator:map:1", "version": 1, "layout": "graph", "nodes": […], "edges": […]}
```

`MapSchema::export()` gives the same as an array, and refuses with `InvalidDefinition` an array that is not a map a
generator made. `MapSchema::schema()` gives the JSON Schema (draft 2020-12) of an exported map, which the package also
ships as `schema/map.v1.json`: a square grid with `x` and `y`, a hex grid with `col`, `row`, `q` and `r`, or a graph of
nodes with their `id` and of edges as pairs of identifiers; the `value` of a cell or a node is a text or the fields of
a structured result. `MapSchema::VERSION` is raised, and `MapSchema::ID` with it, whenever an exported map changes in a
way an older reader would misread; a reader checks `$schema` before it reads the rest.
