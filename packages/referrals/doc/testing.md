# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network, on the ledger in memory of
`tilttek/points`, the store in memory and the `MockClock` of `symfony/clock`: codes drawn, kept and drawn again when
taken; referrals and each guard, at the edge of each date; the reward refused without a first action, or with one made
before the referral; both sides paid, in the terms' currency and from their source; the reward asked again, and by two
processes at once, paid once; a payment cut short completed; the cap, including two newcomers acting at once; a
referral of one person found later; reading; and erasing either side. Two stores in the tests run what another process
does between a read and the write that follows. In the monorepo, Infection holds the library at 100 % of covered
mutants.

## Testing code that refers members

Build the points on a ledger in memory, the store in memory, and first actions the test sets:

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\Points;
use Tilttek\Referrals\Referrals;
use Tilttek\Referrals\Store\InMemoryReferralStore;

$clock = new MockClock('2026-11-20 12:00:00 UTC');
$points = new Points(new InMemoryLedger(), $clock, new Currencies(new Currency('points')));
$referrals = new Referrals(new InMemoryReferralStore(), $points, $firstActions, $clock);
```

A store of the application's own is tested against the contract of [Extension points](extension-points.md): the
unique constraints, and a settlement that holds the cap when two processes settle at once.
