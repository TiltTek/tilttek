# Coordinates

## Square and isometric cells

`Square\Cell` is a cell of a square grid, or of an isometric grid, which is a square grid drawn as diamonds: its column
`x`, to the right, and its row `y`, down. It is written `{"x": 3, "y": 4}`, the keys of the square maps of
`tilttek/generator`.

```php
$cell = new Cell(3, 4);
$cell->toArray();                  // ['x' => 3, 'y' => 4]
Cell::fromArray(['x' => 3, 'y' => 4]);
$cell->key();                      // '3,4', to index cells in an array
```

## Hexagons

A hexagon has three coordinate systems, each with its use.

| System | Type | Written | For |
|---|---|---|---|
| Axial | `Hex\Axial` | `{"q": 1, "r": 4}` | Every computation of the library: distances, lines, areas, turns |
| Cube | `Hex\Cube` | `q`, `r`, `s`, with `q + r + s = 0` | Seeing a rotation or a reflection as a swap of axes |
| Offset | `Hex\Offset` | `{"col": 3, "row": 4}` | Numbering the hexagons of a printed map, column and row |

Axial coordinates are cube coordinates without their third axis, `s = -q - r`; `Axial::s()` gives it, and
`Axial::toCube()` and `Cube::toAxial()` convert one into the other.

Offset coordinates depend on which rows or columns are pushed half a hexagon, the `Hex\OffsetLayout`:

| Layout | Hexagons | Pushed |
|---|---|---|
| `OddRows` (`odd-r`) | Point up, in rows | Every odd row, half a hexagon to the right |
| `EvenRows` (`even-r`) | Point up, in rows | Every even row, half a hexagon to the right |
| `OddColumns` (`odd-q`) | Flat side up, in columns | Every odd column, half a hexagon down |
| `EvenColumns` (`even-q`) | Flat side up, in columns | Every even column, half a hexagon down |

`OffsetLayout::orientation()` gives the `Hex\Orientation` of a layout, `Pointy` or `Flat`.

```php
$axial = new Offset(3, 4)->toAxial(OffsetLayout::OddRows);   // q 1, r 4
$axial->toOffset(OffsetLayout::OddRows);                     // col 3, row 4
```

The conversions hold for negative rows and columns too, and go there and back for every coordinate within the bounds.

## The hex maps of tilttek/generator

The hex maps of `tilttek/generator` are pointy hexagons in rows, every odd row pushed right: the `OddRows` layout. Each
of their cells carries `col` and `row`, and `q` and `r`, the keys `Offset` and `Axial` read and write, so a cell of a
generated map converts directly:

```php
$hex = Axial::fromArray(['q' => $cell['q'], 'r' => $cell['r']]);
$hex->toOffset(OffsetLayout::OddRows)->toArray();           // ['col' => $cell['col'], 'row' => $cell['row']]
```

## Bounds

Every coordinate — `x`, `y`, `q`, `r`, `s`, `col` and `row` — lies from -1 000 000 to 1 000 000; a coordinate beyond
is refused with `OutOfBounds`. A malformed array is refused with `InvalidGrid`.
