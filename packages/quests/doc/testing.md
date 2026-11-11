# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The suite covers the values and their refusals, the reader and every wrong definition, the catalog and its chains, the
progress of ordered and free steps, the four stores in memory through `ContributionStoreTestCase`,
`EnrolmentStoreTestCase`, `CompletionStoreTestCase` and `DuelStoreTestCase` — the contracts any store passes —, the
projection with the journal of `tilttek/activity`: personal, dated, accepted, abandoned, chained, community, team and
duel quests, cancellations and rebuilds, and `Quests` and `Duels`. In the monorepo, Infection holds the library at
100 % of covered mutants.

## Testing code that reads the quests

Build the stores in memory, a `Journal` over an `InMemoryActivityLog` with `LiveProjections` around a
`QuestProjection`, and a clock you control; record activities, then read:

```php
$journal->record(Activity::of('game:1:member:1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-07 20:00')));

self::assertSame(QuestState::Completed, $quests->status($member, 'first-game')->state);
```

An observer that writes down what it is told checks the quests accomplished, their rewards and the duels won.
