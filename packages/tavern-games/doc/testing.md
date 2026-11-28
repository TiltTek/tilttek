# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network: the turns and their data, the endings, the registry, the
tables with the turn timer and late timeouts, the dice cup, the estimator and the chase of a cargo with dice set by
hand, and the three games — every move, refusal, timeout, outcome, view and corrupted data. A whole game of Crazy
Eights is played at a table, then replayed from its log card for card. In the monorepo, Infection holds the library at
100 % of covered mutants.

`tests/Fuzz/crazy-eights-move.php` is the fuzz target of the moves of Crazy Eights: any JSON payload of a play, a draw,
a pass or a timeout is refused with an exception of the engine, or played, after which no card is lost and the view
holds no card of another hand. `composer fuzz` runs it.

## Benchmarks

`tests/Benchmark/CrazyEightsBench.php` builds the view of a seat at a table of four, which replays the gestures of the
cards, as each move and each view does. On PHP 8.5 without an opcode cache, with shuffles, the heaviest gesture: about
2.5 ms after 200 gestures — a long game — and about 12 ms at the bound of 1 000.

## Testing a tavern game

A game's methods are pure: build a `GameState` by hand and call them, or play it at `Tables` with the log store in
memory and a fixed seed, which gives the same dice and cards at every run. A test lays out the cards of Crazy Eights by
writing the gestures of its data — a card moved into a hand, onto the discard pile.

```php
$tavern = new TavernTables(new Tables($games->games(), new InMemoryLogStore()), $games, new InMemoryTurnTimer());
$tavern->open('pig', 't-1', ['ann', 'bob'], seed: str_repeat('ab', 32));
```
