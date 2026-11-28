# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network, on the in-memory store and the
`MockClock` of `symfony/clock`: forming, renaming, changing system and disbanding; joining, at the edges of the limits,
roles kept once and bounded, standing, leaving with the loot going back to the stash; the purse's deposits, withdrawals
and bounds, shares among the active or the chosen members with their remainder, distribution; loot added, given, stashed
and removed; notes written, edited and removed; the order members, loot, notes and parties are read in; erasing a member
from every party and from its notes; the composition — missing, covered and crowded roles, the fair share and the
minimums, away members, unassigned members and unknown roles, catalogues from a system's terms, a game without roles
that gets no advice —; and the events told. In the monorepo, Infection holds the library at 100 % of covered mutants.

## Testing code that uses parties

Build the service on the in-memory store and a clock the test moves:

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\Party\Parties;
use Tilttek\Party\Store\InMemoryPartyStore;

$clock = new MockClock('2026-12-01 19:00:00', 'UTC');
$parties = new Parties(new InMemoryPartyStore(), $clock);

$parties->form($party, $campaign, 'The Lantern Bearers');
$clock->modify('+1 week');
$parties->join($party, $character, ['role.healer']);
```

A `Contract\PartyObserver` that keeps the events it is told lets a test read what was told. A store of the application
passes the same tests when the in-memory store is replaced by it in a copy of the suite.
