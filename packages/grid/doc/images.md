# Grids on images

A grid on the image of a map is placed by an origin and the size of a cell, in pixels from the top-left corner of the
image, x to the right and y down. Positions are `Point` values of exact fractions; see
[Exact arithmetic](exact-arithmetic.md).

## Square grids

`Square\SquareGrid` takes the top-left corner of the cell (0, 0) and the width and height of a cell.

```php
$grid = new SquareGrid(Point::of(12, 8), Ratio::of(140), Ratio::of(140));
$grid = SquareGrid::ofPixelsPerCell(140);    // origin at the corner of the image

$grid->cellAt(Point::of(300, 451));          // the cell under the pointer
$grid->centerOf(new Cell(2, 3));             // its centre, exact
$grid->snapToCenter($drop);                  // where a token dropped there lands
$grid->snapToCorner($drop);                  // where a token as large as four cells lands
```

A position on a side belongs to the cell to its right or below it.

## Hex grids

`Hex\HexGrid` takes the `Orientation`, the centre of the hexagon (0, 0), and the width and height of a hexagon as it is
drawn: from side to side and from point to point. A hexagon drawn stretched is measured as it is drawn, so the grid
fits it.

```php
$grid = new HexGrid(Orientation::Pointy, Point::of(70, 80), Ratio::of(121), Ratio::of(140));
$grid->cellAt(Point::of(640, 512));          // an Axial
$grid->centerOf(new Axial(3, -1));
$grid->snapToCenter($drop);
```

The hexagon under a position is found with integers, from the exact fractions of the position: a pointer always falls
in the same hexagon.

## Isometric grids

`Isometric\IsometricGrid` takes the top corner of the diamond (0, 0) and the width and height of a diamond. Its cells
are those of a square grid — `x` down to the right, `y` down to the left — and so are its distances and lines.

## Aligning a scanned map

A scanned map rarely starts at the corner of its image or has whole cells. Click the centres of two cells and give their
coordinates: the grid follows, origin and sizes, exactly.

```php
$grid = SquareGrid::aligned(Point::of(212, 180), new Cell(1, 1), Point::of(3010, 2290), new Cell(21, 16));
$grid = HexGrid::aligned(Orientation::Flat, $first, new Axial(0, 2), $second, new Axial(9, -1));
```

- For squares, the two cells differ in column and in row.
- For pointy hexagons, they lie in different rows and not straight above one another; for flat ones, in different
  columns and not straight beside one another.
- The farther apart, the more exact the grid.
- A pair that cannot fix both sizes, or gives a size that is not positive — a cell given the coordinates of another —,
  is refused with `InvalidGrid`.

The image must be straight; turn a skewed scan before aligning it.

## Maps without a grid

`Gridless` measures a map without a grid in its own units, from the pixels one unit spans:

```php
$map = new Gridless(Ratio::of(14));                        // 14 pixels to a foot
$map->distance(Point::of(0, 0), Point::of(42, 56));        // 5
```

The distance is the straight one, rounded to a whole unit by the `\RoundingMode` given, without a floating-point step.

## File names

Map makers often write the grid of a map in its file name. `MapHints::fromFileName()` reads it:

| Written | Read as |
|---|---|
| `140ppi`, `140px`, `140pxpi`, `300dpi`, `150 DPI`, `150_dpi` | 140, 140, 140, 300, 150 and 150 pixels per cell |
| `30x20`, `30 x 20`, `30×20` | 30 columns and 20 rows |

```php
$hints = MapHints::fromFileName('maps/forest_30x20_140ppi.jpg');
$hints->pixelsPerCell;                         // 140
[$hints->columns, $hints->rows];               // [30, 20]
$hints->cellWidth(4200);                       // the pixels per cell, or the image width shared by the columns
```

- A print map at 300 DPI with one-inch squares has 300 pixels per cell, so DPI reads as pixels per cell.
- The directories of a path are left out.
- Pixels per cell are read from 10 to 2 000, columns and rows from 1 to 999; a value out of bounds, a number glued to
  other digits — `1920x1080` — and a name longer than 255 bytes give no hint.
- A name that says nothing gives empty hints, never an error: `isEmpty()` tells.

## Grid kinds

`GridKind` names the grid of a map as an application stores it beside the map: `square`, `hex-pointy`, `hex-flat`,
`isometric` or `none`.
