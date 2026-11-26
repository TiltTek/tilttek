# Installation

## Requirements

- PHP 8.5 or later, with `ext-mbstring`.
- `phil/resource`, whose references name the handouts, the tables, the players and the files.
- A `psr/clock` clock, such as `symfony/clock`.

## Installing

```sh
composer require tilttek/handouts
```

## Wiring the library

`Handouts` takes the application's store and clock. Everything else has a default:

```php
use Tilttek\Handouts\Handouts;
use Tilttek\Handouts\Limits;
use Tilttek\Handouts\MediaTypes;

$limits = new Limits(handoutsPerTable: 200, attachmentsPerHandout: 10, audiencePerHandout: 50, releaseBatch: 100);

$handouts = new Handouts(
    $store,                                // Contract\HandoutStore
    $clock,                                // Psr\Clock\ClockInterface
    $limits,
    new MediaTypes(),                      // the media types attachments may have
    [$observer],                           // Contract\HandoutObserver, told of each change
);
```

`Store\InMemoryHandoutStore` serves tests and applications that rebuild their handouts at each start; an application
that keeps them implements `Contract\HandoutStore` on its storage, as [extension points](extension-points.md) describe.

`Handouts` keeps no state between two calls: one instance serves every request, in a worker as in a request. The store
and observers it is given keep that promise too, beside their storage.

## Releasing staged reveals

A reveal staged at a moment happens when the application calls `releaseDue()` after that moment: its scheduler calls
it every minute, or at the rhythm its tables need. Each call reveals at most `Limits::$releaseBatch` staged reveals,
the earliest moment first; the scheduler calls again while a call reveals as many.
