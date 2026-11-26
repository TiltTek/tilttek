# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network, on the in-memory stores and the
`MockClock` of `symfony/clock`: founding, joining open guilds and asking to join guilds on request; approving,
declining and withdrawing requests; capacity, at its edge, and the limits of guilds per member and waiting requests;
roles — who may remove, promote, demote, transfer, update and dissolve —; the order members and guilds are read in;
erasing a member, a founder handing over to the earliest administrator or member, a guild dissolved with its only
member; goals opened, contributed to under keys and without, reached at their target, refused at their deadline to the
second or once closed, bounded per guild; points kept after a member leaves or is erased; the events told; and a guild
as the team of a quest and the circle of a leaderboard. In the monorepo, Infection holds the library at 100 % of
covered mutants.

## Testing code that uses guilds

Build the services on the in-memory stores and a clock the test moves:

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\Guilds\Goals;
use Tilttek\Guilds\Guilds;
use Tilttek\Guilds\Store\InMemoryGoalStore;
use Tilttek\Guilds\Store\InMemoryGuildStore;

$clock = new MockClock('2026-12-01 10:00:00', 'UTC');
$guilds = new Guilds(new InMemoryGuildStore(), $clock);
$goals = new Goals(new InMemoryGoalStore(), $guilds, $clock);

$goals->open('december', $guild, 'Play 10 games', 10, $founder, new DateTimeImmutable('2026-12-02 10:00:00'));
$clock->modify('+1 day'); // the deadline has passed
```

A store of the application passes the same tests when the in-memory store is replaced by it in a copy of the suite.
