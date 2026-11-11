# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The suite covers the definitions and their refusals, the reader and every wrong definition, the odds and the
distribution of draws, `Collections` — keys, conflicts, bound copies, sets given whole, base sets, moves, races,
copies taken back, the album and sets completed —, packs with fixed seeds and their verification, the calendar, the
claim and the projection with the journal of `tilttek/activity`, cancellations and rebuilds, the dice, the erasure,
and the five stores in memory through the contracts any store passes. In the monorepo, Infection holds the library at
100 % of covered mutants.

## Testing code that reads the collections

Build the stores in memory, a clock you control and a `SeedSource` that returns fixed seeds; give copies, open packs,
record activities, then read:

```php
$collections->grant($member, 'deckhand', Source::Granted, 'k1');

self::assertSame(['deckhand'], array_map(static fn (Item $item): string => $item->code, $collections->album($member)->set('crew')->owned ?? []));
```

An observer that writes down what it is told checks the copies given and the sets completed with their rewards.
