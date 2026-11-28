# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| What blocks the sight | A closure that says whether a cell or a hexagon blocks, read from its own map | [Measuring](measuring.md#fields-of-view) |
| The diagonal rule and the rounding | The rule of its game, and the `\RoundingMode` of its distances | [Measuring](measuring.md#squares-and-diagonal-rules) |
| The placement of a grid | The origin and sizes it stores beside a map, or the two cells a user clicked | [Grids on images](images.md) |

Every type is an immutable value; the library calls nothing but the closures it is given and keeps no state between two
calls.
