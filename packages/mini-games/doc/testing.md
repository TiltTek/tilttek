# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network: score orders and records, controls, days and their
numbers, the daily seed, grids and symbols, endings, histories, the registry, the daily games at tables of the engine
— the same game for every player of a day, the score sent once, the seed shown once the day is over —, and both
example games, their moves, refusals, outcomes, views, shares and corrupted data. In the monorepo, Infection holds the
library at 100 % of covered mutants.

`tests/Fuzz/code.php` is the fuzz target of the guesses of Bulls and Cows: any text is refused with
`InvalidMiniGame`, or read as a code of different digits that is the text itself and scores all bulls against itself.
`composer fuzz` runs it.

## Benchmark

`tests/Benchmark/DailyGameBench.php` plays a whole daily game of Bulls and Cows, ten guesses each followed by its
view, then writes its share, with the log store in memory: about 0.5 ms on PHP 8.5 without an opcode cache.

## Testing a mini-game

A mini-game's methods are pure: build a `GameState` by hand and call them, or play it at `Tables` with the log store in
memory and a fixed seed, which gives the same setup at every run.

```php
$tables = new Tables(new MiniGames($game)->games(), new InMemoryLogStore());
$state = $tables->open('my-game', 't-1', ['u-1'], seed: str_repeat('ab', 32));
```
