# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The suite covers the periods and their codes across timezones and daylight saving, the seasons, the boards and their
refusals, the measures, the stores in memory through `ScoreStoreTestCase`, `SnapshotStoreTestCase`,
`AppearanceStoreTestCase` and `LeagueStoreTestCase` — the contracts any store passes —, the projection with the journal
of `tilttek/activity`: scores by period, contexts, seasons, an entry handed twice, cancellations and rebuilds, and
`Leaderboards`: what each viewer sees, ties, circles, reported values, snapshots, export and erasure, and the leagues
over several weeks: divisions filled in the order of arrival, promotion and relegation by tier, absent members, weeks
settled late, a division as its members see it, divisions formed by volume; the tie-breaks, the sliding windows and the
days, the rankings by object, the titles and the teams with their luck.

`ValkeyScoreStoreTest` runs the score contract against a real Valkey whose URL `PHIL_TEST_VALKEY_URL` gives, and is
skipped without it; it empties the database it is given. Beyond the contract, it checks what the scripts leave in
Valkey — every key under the prefix's hash tag, none left once the contributions and the scores are gone —, the
comparisons of `keep()` on the sort keys at their bounds — an equal score, a moment one microsecond earlier, a better
tie-break —, the bounds of `expire()` and `forget()` to the microsecond, the batches of 1 and 10 000, and the rankings
asked in other directions than the board's. `ValkeyScoreStoreRepliesTest` needs no server: it checks the commands the
store sends and how it reads values it did not write and replies of another shape than a server gives.

Any Valkey 9 does; database 15 is the tests' own:

```sh
docker run --rm -d -p 127.0.0.1:16379:6379 valkey/valkey:9-alpine
PHIL_TEST_VALKEY_URL=redis://127.0.0.1:16379/15 vendor/bin/phpunit --no-configuration tests
```

When the `phil/dev-tools` installed in the monorepo lists Valkey among its services, it starts the same server and
sets the variable:

```sh
vendor/bin/phil-dev services up
eval "$(vendor/bin/phil-dev services env)"
composer test
```

Every test empties database 15: run the suites that use it one at a time, and Infection on one thread.

`tests/Benchmark/RankingBench.php` measures the reads a page makes — the first ten lines, a member's rank, how many are
ranked, a division of thirty — on boards of 1 000 and 10 000 members, in memory and, with `PHIL_TEST_VALKEY_URL`, in
Valkey. In the monorepo, Infection holds the library at 100 % of covered mutants, `ValkeyScoreStore` included when
`PHIL_TEST_VALKEY_URL` is set.

## Testing code that reads the boards

Build the stores in memory, a `Journal` over an `InMemoryActivityLog` with `LiveProjections` around a
`LeaderboardProjection`, and a clock you control; record activities, then read:

```php
$journal->record(Activity::of('game:1:member:1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-07 20:00 UTC')));

self::assertSame(1, $leaderboards->rank('games-month', $member)?->rank);
```

An observer that writes down the snapshots and the settlements it is told checks the rewards. For a league, move the
clock past the end of a week, in the board's timezone, and call `settleDue()`; record the next week's activities, and
read each member's tier.
