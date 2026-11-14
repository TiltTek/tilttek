# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network or a database: Elo and OpenSkill against values computed by
a separate implementation in Python from the published formulas — duels, games of several players with ties, team
games, draws, upsets far in the tail, the K-factor step by step, the scale, the floor —, the duel of two new players
that the publication's method gives, the normal distribution against reference values, results and stored ratings
written and read back with everything else refused, private ratings and the standings, conflicts, the events and the
games before a celebration, and replays that give back exactly the ratings recorded, for members named by numbers too.
In the monorepo, Infection holds the library at 100 % of covered mutants.

`tests/Fuzz/rating.php` is the fuzz target of the computation of a rating: any text, read as a result in JSON, is
refused with `InvalidRating` or rated by each system from ratings taken from the same text, every new rating reading
back the same. `composer fuzz` runs it.

`tests/Benchmark/RatingBench.php` measures a replay of 10 000 results among 500 members — duels, games of four and games
of two teams of two — by Elo, OpenSkill Plackett-Luce and OpenSkill Thurstone-Mosteller.

## Testing an application

Give `Ratings` the in-memory store, a source of results of your own and a dispatcher that records the events:

```php
$ratings = new Ratings(new InMemoryRatingStore(), new EloSystem(), $recordingDispatcher, $source);
$ratings->record(GameResult::duel('r1', 'chess', new DateTimeImmutable('2026-11-13 20:00 UTC'), 'ann', 'bob'));
self::assertSame(151_600, $ratings->rating('chess', 'ann')->score);
```

The values are whole numbers, so a test compares them exactly.
