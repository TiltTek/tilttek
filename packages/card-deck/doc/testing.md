# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network: names and identifiers, cards, decks and the standard deck,
zones, every gesture and its refusals, the codec, the shuffle pinned to a seed, the state, its replay and its
fingerprint, the views, the table with its versions, the store in memory, and the published game. Property tests play
random games from fixed seeds and check, after every gesture, that no card is lost or duplicated, that a refused
gesture changes nothing, that no view of any player holds a hidden card, and that the journal replays to the same
state. In the monorepo, Infection holds the library at 100 % of covered mutants.

`tests/Fuzz/gesture.php` is the fuzz target of the gesture codec: any JSON is refused with the library's exceptions,
or gives a gesture that writes itself back to the same array. `composer fuzz` runs it.

## Benchmarks

`tests/Benchmark/ReplayBench.php` replays a journal on a deck of 54 cards and builds one player's view, as each request
of a game does. On PHP 8.6 without an opcode cache: 1 000 gestures in about 4 ms, 10 000 gestures — the bound of a
game — in about 30 ms.

## Testing code that plays cards

Give `CardTable` the store in memory and a fixed seed: the shuffles are then the same at every run.

```php
$table = new CardTable(new InMemoryGameStore());
$table->open($game, StandardDeck::create(), Seed::fromHex(str_repeat('ab', 32)));
$table->start($game);
```
