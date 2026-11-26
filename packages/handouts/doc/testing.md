# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network, on the in-memory store and the
`MockClock` of `symfony/clock`: handouts created, edited and removed at the edges of their bounds and limits; titles,
texts, attachments and media types refused; reveals to everyone and to players, again at a new revision, at the edges
of the audience limits; concealing from everyone and from players; reveals staged at a moment and on a cue, replaced,
withdrawn, released in batches the earliest first, finished by a reveal; sightings once per revision; the player's
view with its freshness and its order; the game master's views; erasing a player; the order every list is read in;
and the events told. In the monorepo, Infection holds the library at 100 % of covered mutants.

## Testing code that uses handouts

Build the service on the in-memory store and a clock the test moves:

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\Handouts\Audience;
use Tilttek\Handouts\Handouts;
use Tilttek\Handouts\Store\InMemoryHandoutStore;
use Tilttek\Handouts\Trigger;

$clock = new MockClock('2026-12-04 19:00:00', 'UTC');
$handouts = new Handouts(new InMemoryHandoutStore(), $clock);

$handouts->create($letter, $table, 'The miller\'s letter', 'Meet me at the mill.');
$handouts->stage($letter, Audience::everyone(), Trigger::at($clock->now()->modify('+1 hour')));
$clock->modify('+1 hour');
$handouts->releaseDue();
```

A `Contract\HandoutObserver` that keeps the events it is told lets a test read what was told. A store of the
application passes the same tests when the in-memory store is replaced by it in a copy of the suite.
