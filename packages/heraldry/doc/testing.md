# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network or a database: the model and its refusals, ordinary by
ordinary and count by count; the rule of tincture on every combination of field, ordinary and charges; the generator
on 3 000 seeds — every coat keeps the rule, every part of the model is reached, the shares match the documentation —,
known seeds giving known arms, and the same seed giving the same coat; over fifty blazons in English and French, each
grammatical case; and the drawing — structure, ids, accessibility, places, palette, furs — with 400 generated coats
passed through `phil/svg-sanitizer` untouched; and the arms, blazons and drawings of a thousand fixed seeds, held by
their SHA-256, so that a change to what a seed gives shows at once. In the monorepo, Infection holds the package at
100 % of covered mutants.

`tests/Fuzz/drawing.php` is the fuzz target of the generator and the drawing: any input gives arms that keep the rule
and an image the sanitizer keeps whole. `tests/Fuzz/seed.php` is that of the seeds: any text is refused or writes
back to its digits. `composer fuzz` runs both.

`tests/Benchmark/HeraldryBench.php` measures arms drawn from a seed, a French blazon and an SVG image.

## Testing an application

Fix the seed, and the arms are fixed:

```php
$coat = new CoatGenerator()->generate(Seed::fromHex(str_repeat('2a', 32)));
self::assertSame('Purpure, on a bend sinister Or a lozenge azure', new Blazonry()->write($coat));
```
