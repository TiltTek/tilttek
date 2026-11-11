# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The suite covers every value and its refusals, the stores in memory through the contract test cases, `Redemptions`
with the ledger of `tilttek/points`, the guards across timezones, `Transfers`, `Trades` with an inventory in memory,
stores that lose a race to another process, and the erasure. In the monorepo, Infection holds the library at 100 % of
covered mutants.

## Testing code that exchanges

Build the services over the stores in memory, a ledger in memory and a clock you control, call the code under test,
then read the balances and the stores:

```php
$clock = new MockClock('2026-11-07 12:00:00');
$currencies = new Currencies(new Currency('points'));
$points = new Points(new InMemoryLedger(), $clock, $currencies);
$redemptions = new Redemptions(new InMemoryCatalog(new Reward('seat', 250, stock: 1)), new InMemoryReservationStore(), $points, $currencies, $clock);
$points->grant($member, 300, 'welcome');

$checkout = new BookSeat($redemptions);
$checkout($member, 'order:1');
$checkout($member, 'order:1'); // a handler run twice

self::assertSame(50, $points->balance($member));
self::assertSame(0, $redemptions->remaining('seat'));
```

Move the clock past the lifetime and call `expireDue()` to test what happens to an unconfirmed reservation. An
`Announcer` of your own records the events.
