# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The suite checks the limits of every value, the reveal against known seeds, every field of a tampered proof, the
codes of every die from the d2 to the d20, and the draw texts. In the monorepo, Infection holds the library at 100 % of
covered mutants, and two fuzz targets feed the readers of the shop codes and of the draw texts with any input: what
reads writes back to the same code or faces, everything else is refused with `InvalidCode`.

```sh
composer fuzz -- shop-code draw-text
```

The benchmarks measure the reveal and the verification of a box of ten d20 tables, and the codes written and read.

## Testing code that uses the library

A seeded engine gives the same seed every run: `PendingDraw::open(new Randomizer(new Xoshiro256StarStar(1)))`, or
`PendingDraw::restore()` with a fixed seed. The faces a seed gives never change, since the dice of `tilttek/dice` rest
on PHP's seeded engines. Results of chosen faces come from `LootResult::ofFaces()`.
