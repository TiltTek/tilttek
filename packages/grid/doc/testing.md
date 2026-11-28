# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network: exact ratios and every rounding mode, bounds, square cells,
diagonal rules, lines, areas, rings and fields of view on squares and hexagons, the conversions of the coordinates,
the hexagon nearest a fractional position, grids on images and their alignment, maps without a grid, and file names.
Property tests draw coordinates from fixed seeds and check that conversions go there and back, that the centre of a
cell lies in that cell on every kind of grid, that lines are chains of neighbours as long as the distance, that turns
keep distances, and that areas and rings have the size their radius gives. In the monorepo, Infection holds the
library at 100 % of covered mutants.

`tests/Fuzz/file-name.php` is the fuzz target of `MapHints`: any name gives hints within their bounds, the same every
time, never an error. `composer fuzz` runs it.

## Benchmarks

`tests/Benchmark/GridBench.php` finds the hexagon under a pointer on a scanned map a thousand times, draws a line of
100 steps, and computes a field of view at the largest radius with walls. On PHP 8.5: about 25 ms for the thousand
pointers, under 0.1 ms for the line, about 80 ms for the field of view of radius 30.

## Testing code that uses grids

The types are values: build them in a test as in production. A field of view takes a closure, so a test gives it the
walls as a set of keys:

```php
$walls = ['1,0' => true, '2,-1' => true];
$seen = new Axial(0, 0)->fieldOfView(3, fn (Axial $hex): bool => isset($walls[$hex->key()]));
```
