# tilttek/grid

**Measure, snap and align game maps exactly: square, hex and isometric grids with coordinates, distances, lines of
sight and areas of effect, without floats.**

tilttek/grid holds the arithmetic of the grids drawn on game maps — square, hexagonal with points or flat sides up,
isometric, or none. It gives hexagons their three coordinate systems — offset, as a printed map numbers them, axial
and cube — and converts between them; it measures distances, finds neighbours, draws lines, rings and areas of effect
described by their distance in hexagons, computes what a token can see past walls, and turns a map piece around its
reference cell. On a square grid, the diagonal is a rule of the game: 5-5-5, 5-10-5, Euclidean, or no diagonal.

On the image of a map, it finds the cell under a pointer, the centre of a cell, and snaps a token to a centre or a
corner. A scanned map is aligned from two cells clicked on it; the pixels per cell and the size of the map are read
from its file name when the map maker wrote them there. Every position is an exact fraction and every result a whole
number: no floating-point number decides which cell a point falls in or how far a token moves.

It is for map tools of games played online or at the table — a hex crawl, a map revealed as it is explored, a
generated dungeon, a token maker —, never a virtual tabletop. It is a plain PHP library without dependencies.

## Status

| Part | State |
|---|---|
| `Hex\Axial`, `Hex\Cube`, `Hex\Offset`, `Hex\OffsetLayout`: the three coordinate systems of hexagons and their conversions | Implemented and tested |
| Distances, neighbours, lines, rings, areas and fields of view on hexagons and squares | Implemented and tested |
| `Square\DiagonalRule`, `Square\Movement`: 5-5-5, 5-10-5, Euclidean and orthogonal moves | Implemented and tested |
| `Square\SquareGrid`, `Hex\HexGrid`, `Isometric\IsometricGrid`, `Gridless`: cells on the image of a map, snapping and alignment | Implemented and tested |
| `MapHints`: the pixels per cell, columns and rows read from a file name | Implemented and tested |
| `Ratio`, `Point`: exact positions and their rounding | Implemented and tested |
| A Symfony bundle, form types and Twig helpers | Not started: a future bundle |
| Finding the grid or the rooms of an image by itself | Not planned here: a later package |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Coordinates](doc/coordinates.md) | Square cells, axial, cube and offset coordinates of hexagons, and their conversions. |
| [Measuring](doc/measuring.md) | Distances, diagonal rules, neighbours, lines, rings, areas, fields of view and turns. |
| [Grids on images](doc/images.md) | Cells under a pointer, centres, snapping, aligning a scanned map, maps without a grid and file names. |
| [Exact arithmetic](doc/exact-arithmetic.md) | `Ratio`, the rounding modes and the bounds of every computation. |
| [Extension points](doc/extension-points.md) | What an application gives the library. |
| [Exceptions](doc/exceptions.md) | `GridException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, its fuzz target and its benchmarks. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```php
use Tilttek\Grid\Hex\Axial;
use Tilttek\Grid\Hex\HexGrid;
use Tilttek\Grid\Hex\Offset;
use Tilttek\Grid\Hex\OffsetLayout;
use Tilttek\Grid\Hex\Orientation;
use Tilttek\Grid\MapHints;
use Tilttek\Grid\Point;

// The hex 0304 of a printed map, odd rows pushed right.
$camp = new Offset(3, 4)->toAxial(OffsetLayout::OddRows);
$ruins = new Axial(7, 1);

$camp->distanceTo($ruins);                 // 6
$camp->lineTo($ruins);                     // the 7 hexagons from the camp to the ruins
$camp->area(2);                            // the 19 hexagons of a fireball of radius 2
$camp->fieldOfView(6, fn (Axial $hex): bool => $walls->has($hex)); // what a sentry sees

// Align a scanned map from two hexagons clicked on it, then find the hexagon under the pointer.
$grid = HexGrid::aligned(Orientation::Pointy, Point::of(212, 180), new Axial(1, 1), Point::of(1460, 905), new Axial(12, 6));
$grid->cellAt(Point::of(640, 512));

MapHints::fromFileName('forest_30x20_140ppi.jpg')->pixelsPerCell; // 140
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
