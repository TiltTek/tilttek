# Exceptions

Every exception of the library implements `Tilttek\Grid\Exception\GridException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidGrid` | A grid, a coordinate or an alignment is refused: a size that is not positive, cube coordinates that do not add up to zero, a malformed array, two reference cells that cannot fix a grid, a ratio with a zero denominator | `Two reference cells differ in column and in row.` |
| `OutOfBounds` | A computation would leave the bounds of the library: a coordinate too far, a line, an area or a field of view too large, a number too large for exact arithmetic | `The radius of an area lies from 0 to 100; it is 101.` |
