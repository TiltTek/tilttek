# Measuring

## Hexagons

On hexagons, every step to a neighbour counts one.

| Method of `Hex\Axial` | Gives |
|---|---|
| `distanceTo($other)` | The number of steps between two hexagons |
| `neighbor($direction)` | The neighbour in a direction, 0 to 5; any other number counts round the six |
| `neighbors()` | The six neighbours, in the order of the directions |
| `diagonals()` | The six hexagons two steps away across a corner |
| `lineTo($other)` | The hexagons a straight line between the two centres crosses, both ends included |
| `area($radius)` | Every hexagon at most a radius away: the area of an effect described by its distance in hexagons |
| `ring($radius)` | Every hexagon exactly a radius away |
| `fieldOfView($radius, $blocksSight)` | The hexagons of an area a viewer here can see |
| `rotateAround($pivot, $sixthTurns)` | This hexagon turned around a pivot, clockwise as the map is seen |
| `plus()`, `minus()`, `scale()` | Hexagons as vectors |

The directions count counterclockwise: for pointy hexagons, 0 east, 1 north-east, 2 north-west, 3 west, 4 south-west
and 5 south-east; for flat ones, 0 south-east, 1 north-east, 2 north, 3 north-west, 4 south-west and 5 south.

A line between two hexagons passes exactly between two others when they lie on a straight line across a corner; it
then takes the same side every time, so a line, a field of view and their tests are reproducible.

## Squares and diagonal rules

On a square grid, how a diagonal step counts is a rule of the game, `Square\DiagonalRule`:

| Rule | Value | A move of 3 columns and 2 rows costs |
|---|---|---|
| `Orthogonal` | `orthogonal` | 5 cells: no diagonal step |
| `Uniform` | `5-5-5` | 3 cells: a diagonal step costs one |
| `Alternating` | `5-10-5` | 4 cells: diagonal steps cost one, then two, in turn |
| `Euclidean` | `euclidean` | 4 cells: the straight distance, 3.6, rounded |

`DiagonalRule::cells($dx, $dy)` gives the cost of a move; the Euclidean rule rounds by the `\RoundingMode` given,
`HalfAwayFromZero` by default, computed exactly. Multiply by the size of a cell — 5 feet, 1.5 metres — for the distance
the game speaks of.

`Square\Movement` applies a rule to cells:

```php
$movement = new Movement(DiagonalRule::Alternating);
$movement->distance(new Cell(0, 0), new Cell(3, 2));   // 4
$movement->line($from, $to);                           // each cell touches the one before by a side or a corner
$movement->area($center, 6);                           // every cell at most 6 away under the rule
$movement->ring($center, 6);                           // every cell exactly 6 away
$movement->fieldOfView($viewer, 12, fn (Cell $cell): bool => $walls->has($cell));
```

`Square\Cell` has `neighbors()` — the four that share a side, then the four that share a corner unless
`neighbors(false)` — and `rotateAround($pivot, $quarterTurns)`.

## Fields of view

A field of view is every cell of an area that a viewer at its centre can see: a cell is seen when no cell strictly
between it and the viewer, on the line between their centres, blocks the sight. A cell that blocks is seen itself: a
wall is seen, not what lies behind it. The closure given says whether a cell blocks; it is called once per cell,
however many lines cross it.

The viewer comes first in the result, then the cells in the order of the area.

## Turning a map piece

Turning every cell of a map piece — a room of a generated dungeon, a tile of a map — around its reference cell turns the
piece: a quarter turn for squares, a sixth for hexagons, clockwise as the map is seen, counterclockwise for a negative
number. Turns keep every distance to the pivot.

```php
$turned = array_map(fn (Axial $hex): Axial => $hex->rotateAround($reference, 2), $room);
```

## Bounds

| Computation | Bound |
|---|---|
| A line | 10 000 steps |
| An area or a ring | A radius of 100 |
| A field of view | A radius of 30 |

A request beyond is refused with `OutOfBounds`; see [Exact arithmetic](exact-arithmetic.md#bounds).
