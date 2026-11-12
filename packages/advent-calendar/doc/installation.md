# Installation

## Requirements

- PHP 8.5 or later.
- `phil/resource`, the opaque reference of a member.
- `psr/clock`, the clock the days are read from, and `psr/event-dispatcher`, which tells the application of each first
  opening. A Symfony application passes its `clock` and `event_dispatcher` services.

## Installing

```sh
composer require tilttek/advent-calendar
```

## Wiring the library by hand

A store, a clock and a dispatcher make `AdventCalendar`:

```php
use Symfony\Component\Clock\Clock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tilttek\AdventCalendar\AdventCalendar;
use Tilttek\AdventCalendar\Event\BoxOpened;
use Tilttek\AdventCalendar\Store\InMemoryOpeningStore;

$dispatcher = new EventDispatcher();
$dispatcher->addListener(BoxOpened::class, $grantSurprise);

$advent = new AdventCalendar(new InMemoryOpeningStore(), new Clock(), $dispatcher);
```

In a Symfony application, register `AdventCalendar` as a service with the application's own `OpeningStore`, and the
listener of `BoxOpened` with `#[AsEventListener]`. The calendars themselves are values the application builds from
its configuration or its database, at each request: `Calendar::of()` is cheap and keeps no state.

The store in memory forgets everything with the process: an application writes its own on its storage
([Writing a store](writing-a-store.md)).
