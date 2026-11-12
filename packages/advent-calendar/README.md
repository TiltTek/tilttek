# tilttek/advent-calendar

**An advent calendar: one box per day, one surprise per box, and a missed day that is not lost.**

tilttek/advent-calendar keeps advent calendars for a site's members. A calendar has a box for each day from its first
date — twenty-four in December, twelve, or any count up to ninety-two — and one surprise per box. A surprise is a
reference to a reward the application grants: an amount of points, a badge, a piece of content, a code drawn from a
pool. The days are counted in the calendar's timezone, across changes of time. Each calendar declares how long a box
stays openable once its day has come: by default until the calendar closes, so a member who missed a day opens its box
later without losing it; on its day only; or for a few days of catch-up. No box opens before its day. Opening is
idempotent: the first opening is recorded and dispatched as an event the application grants the surprise from, and
opening the box again answers the same opening and grants nothing twice, even when two requests race. Each member sees
the boxes in their state — locked, openable, opened, missed — and an unopened box never reveals its surprise. Erasing a
member removes their openings.

It is for a community or a shop that wants a seasonal ritual without pressure: there is nothing to buy, no box that
vanishes by default, no ranking of who opened first. The rewards stay in the application, which already knows how to
credit points or award a badge; the calendar only says when.

It is a plain PHP library whose dependencies are `phil/resource`, the opaque reference of a member, `psr/clock`, the
clock the days are read from, and `psr/event-dispatcher`, which carries the openings to the application. Members are
references the application gives, `member:44`: the library never reads a name, an e-mail or an account.

The design is small: a `Calendar` holds its boxes' `Surprise`s, its timezone, its `OpeningPolicy` and its closing, and
gives each box its `OpeningWindow`; `AdventCalendar` opens a box for a member, records the `Opening` in an
`OpeningStore`, dispatches `BoxOpened` once, and shows the boxes as `BoxView`s.

## Status

| Part | State |
|---|---|
| `Calendar`: boxes by day from a first date, in a timezone, closing on the last box or later | Implemented and tested |
| `Surprise`, `SurpriseKind`: points, a badge, content or a code, as references | Implemented and tested |
| `OpeningPolicy`, `PolicyKind`, `OpeningWindow`: until closing by default, the same day, or a catch-up of 1 to 31 days | Implemented and tested |
| `AdventCalendar`: idempotent openings, boxes as a member sees them, erasure | Implemented and tested |
| `BoxOpened`: the first opening told once, with a key for an idempotent grant | Implemented and tested |
| `OpeningStore`, `InMemoryOpeningStore`: the openings, with the contract any store passes | Implemented and tested |
| Exceptions under one `AdventCalendarException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring the library. |
| [Calendars and policies](doc/calendars-and-policies.md) | Boxes, surprises, timezones, changes of time, opening policies and the closing. |
| [Opening boxes](doc/opening-boxes.md) | Opening once, granting the surprise, the boxes a member sees, celebrating and erasure. |
| [Writing a store](doc/writing-a-store.md) | The contract an application's storage of openings implements. |
| [Extension points](doc/extension-points.md) | What an application gives the library: the store, the clock, the dispatcher. |
| [Exceptions](doc/exceptions.md) | `AdventCalendarException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that opens boxes. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require tilttek/advent-calendar
```

```php
use Phil\Resource\ResourceReference;
use Symfony\Component\Clock\Clock;
use Tilttek\AdventCalendar\{AdventCalendar, Calendar, Surprise};
use Tilttek\AdventCalendar\Store\InMemoryOpeningStore;

$surprises = [];

for ($day = 1; $day <= 24; ++$day) {
    $surprises[] = 0 === $day % 6 ? Surprise::badge('advent-week') : Surprise::points(10);
}

$calendar = Calendar::of('advent-2026', 'America/Toronto', '2026-12-01', $surprises);
$advent = new AdventCalendar(new InMemoryOpeningStore(), new Clock(), $dispatcher);

$opened = $advent->open($calendar, ResourceReference::of('member', '44'), 3);
$opened->first;        // true the first time: BoxOpened was dispatched
$opened->surprise;     // Surprise::points(10)
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
