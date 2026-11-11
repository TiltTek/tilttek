# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The suite covers every value and its refusals, the ledger in memory through `LedgerTestCase` — the contract any ledger
passes —, `Points`, the lots and the expiry, the rules and their periods across timezones, and the projection with the
journal of `tilttek/activity`: caps, cancellations and rebuilds. In the monorepo, Infection holds the library at 100 %
of covered mutants.

## Testing code that moves points

Build `Points` over an `InMemoryLedger` with a clock you control, call the code under test, then read the balance:

```php
$points = new Points(new InMemoryLedger(), new MockClock('2026-11-07 12:00:00'), new Currencies(new Currency('points')));

$checkout = new RedeemAtCheckout($points);
$checkout($order);
$checkout($order); // a handler run twice

self::assertSame(20, $points->balance($member));
```

Earning rules are tested through the journal: record activities in a `Journal` over an `InMemoryActivityLog` with
`LiveProjections` around a `PointsProjection`, then read the balances.
