# Exact arithmetic

## Ratio

`Ratio` is an exact fraction of two integers, in lowest terms with a positive denominator. Sizes and positions on an
image are ratios: a scanned map of 4 213 pixels for 30 columns has cells of 4213/30 pixels, and the cell under a pointer
never depends on the rounding of a floating-point number.

```php
$width = Ratio::of(4213, 30);
$width->plus(Ratio::of(1, 2));        // also minus(), times(), dividedBy(), negated(), abs()
$width->compare(Ratio::of(140));      // -1, 0 or 1
$width->floor();                      // 140
$width->round();                      // 140
$width->toDecimal(2);                 // '140.43', for display only
(string) $width;                      // '4213/30'
```

Every operation checks that its intermediate products and sums stay within PHP's integers, and refuses with
`OutOfBounds` rather than lose precision.

## Rounding

`Ratio::round()`, `Ratio::sqrtRounded()`, `Point::rounded()`, `DiagonalRule::cells()` and `Gridless` take PHP's
`\RoundingMode`, `HalfAwayFromZero` by default:

| Mode | 2.5 | -2.5 | 2.4 |
|---|---|---|---|
| `HalfAwayFromZero` | 3 | -3 | 2 |
| `HalfTowardsZero` | 2 | -2 | 2 |
| `HalfEven` | 2 | -2 | 2 |
| `HalfOdd` | 3 | -3 | 2 |
| `TowardsZero` | 2 | -2 | 2 |
| `AwayFromZero` | 3 | -3 | 3 |
| `NegativeInfinity` | 2 | -3 | 2 |
| `PositiveInfinity` | 3 | -2 | 3 |

`sqrtRounded()` rounds the square root of a ratio by comparing squares of integers: the Euclidean distance of 3 cells
by 4 is 5, and that of 1 by 1 is 1 — or 2 with `PositiveInfinity` —, the same on every machine.

## Bounds

| Bound | Constant | Value |
|---|---|---|
| A coordinate of a cell | `Limits::COORDINATE` | -1 000 000 to 1 000 000 |
| The steps of a line | `Limits::LINE` | 10 000 |
| The radius of an area or a ring | `Limits::RADIUS` | 100 |
| The radius of a field of view | `Limits::SIGHT` | 30 |

These bounds keep the time of every call short, whatever the input: an area of radius 100 has 30 301 hexagons, and a
field of view of radius 30 traces 2 790 lines.
