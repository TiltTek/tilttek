# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/grid` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Ratio` and `Point`: exact fractions in lowest terms, their arithmetic checked against overflow, their rounding by
  every `\RoundingMode` and their square root rounded without a floating-point step; positions on an image.
- `Limits`: coordinates from -1 000 000 to 1 000 000, lines of at most 10 000 steps, areas and rings of radius 100 at
  most, fields of view of radius 30 at most.
- `Square\Cell`: cells of square and isometric grids, written as `x` and `y`, their four or eight neighbours and their
  quarter turns around a pivot.
- `Square\DiagonalRule` and `Square\Movement`: orthogonal, 5-5-5, 5-10-5 and Euclidean moves, and distances, lines,
  areas, rings and fields of view under a rule.
- `Hex\Axial`, `Hex\Cube`, `Hex\Offset`, `Hex\OffsetLayout` and `Hex\Orientation`: the axial, cube and offset
  coordinates of hexagons, their conversions under the four offset layouts, written with the keys of the hex maps of
  `tilttek/generator`; distances, neighbours, diagonals, lines, areas, rings, fields of view and sixth turns around a
  pivot, computed with integers.
- `Square\SquareGrid`, `Hex\HexGrid` and `Isometric\IsometricGrid`: cells on the image of a map, the cell under a
  position, the centre of a cell, snapping to a centre or a corner, and the alignment of a scanned map from two cells
  clicked on it.
- `Gridless` and `GridKind`: distances in the units of a map without a grid, and the kinds of grid.
- `MapHints`: the pixels per cell, columns and rows read from the file name of a map image.
- Exceptions under one `GridException` interface: `InvalidGrid` and `OutOfBounds`.
- Property tests over random coordinates, a fuzz target of the file names and a benchmark of the hot paths.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
