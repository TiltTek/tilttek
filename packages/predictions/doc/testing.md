# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network, on the ledger in memory of
`tilttek/points`, the store in memory and the `MockClock` of `symfony/clock`: questions asked, asked again and asked
differently; picks made and changed; the deadline, a second before and at it; a resolution that slips in before a
prediction is written; the Brier score on known values and its rounding; the settlement paid once when run again or by
two processes at once, read in pages, and unshifted by a member erased meanwhile; cancelling; reading; and erasing. In
the monorepo, Infection holds the library at 100 % of covered mutants.

## Testing code that asks questions

Build the points on a ledger in memory, the store in memory, and a clock the test moves past the deadline:

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\Points;
use Tilttek\Predictions\Predictions;
use Tilttek\Predictions\Store\InMemoryPredictionStore;

$clock = new MockClock('2026-11-20 12:00:00 UTC');
$points = new Points(new InMemoryLedger(), $clock, new Currencies(new Currency('points')));
$predictions = new Predictions(new InMemoryPredictionStore(), $points, $clock);
```
