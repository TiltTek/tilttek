# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Square\Cell` | A cell of a square or isometric grid, its neighbours and its turns |
| `Square\DiagonalRule` | How a diagonal step counts: orthogonal, 5-5-5, 5-10-5, Euclidean |
| `Square\Movement` | Distances, lines, areas, rings and fields of view on squares, under a rule |
| `Square\SquareGrid` | Square cells on an image: cell under a position, centre, snapping, alignment |
| `Isometric\IsometricGrid` | Diamond cells on an image: cell under a position, centre, snapping |
| `Hex\Axial` | A hexagon in axial coordinates: distances, neighbours, lines, areas, rings, fields of view, turns |
| `Hex\Cube` | A hexagon in cube coordinates, and the hexagon nearest a fractional position |
| `Hex\Offset` | A hexagon in offset coordinates, column and row |
| `Hex\OffsetLayout` | Which rows or columns are pushed: `odd-r`, `even-r`, `odd-q`, `even-q` |
| `Hex\Orientation` | Hexagons with a point or a flat side up |
| `Hex\HexGrid` | Hexagons on an image: hexagon under a position, centre, snapping, alignment |
| `Gridless` | Distances on a map without a grid, in its own units |
| `GridKind` | The grid of a map, as stored beside it |
| `MapHints` | The pixels per cell, columns and rows read from a file name |
| `Point` | A position on an image, exact |
| `Ratio` | An exact fraction, its arithmetic and its rounding |
| `Limits` | The bounds of every computation |
| `Exception\GridException` and its two exceptions | [Exceptions](exceptions.md) |
