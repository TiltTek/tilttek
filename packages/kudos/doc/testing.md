# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network, on the journal in memory of
`tilttek/activity` and the `MockClock` of `symfony/clock`: the activity thanks record and the events they tell; keys,
built or given, and thanks given again under them; refusals of oneself, of a declining receiver, of the daily cap — at
midnight in the member's time zone, a second before and at the turn — and of the limit per receiver, at the edge of its
period; withdrawn thanks counted towards both; messages and their list; limits and their bounds; withdrawing and
cancelling; pages of thanks received and given; the absence of any reading of what another member received; the count
of thanks given, by period; and erasing a member through the journal. In the monorepo, Infection holds the library at
100 % of covered mutants.

## Testing code that gives thanks

Build a journal in memory and a clock the test moves:

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\Activity\Journal;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Kudos\Kudos;
use Tilttek\Kudos\Limits;

$clock = new MockClock('2026-11-20 12:00:00', 'UTC');
$kudos = new Kudos(new Journal(new InMemoryActivityLog(), $clock), $clock, limits: new Limits(daily: 1));

$kudos->give($giver, $receiver, 'help');
$clock->modify('+1 day'); // the cap is counted again
```
