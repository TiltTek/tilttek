# Installation

## Requirements

- PHP 8.5 or later.
- `tilttek/activity`, the journal the thanks are recorded in.
- `phil/resource`, whose references name the members and the objects.
- A `psr/clock` clock, such as `symfony/clock`.

## Installing

```sh
composer require tilttek/kudos
```

## Wiring the library

`Kudos` takes the application's journal and clock; everything else has a default:

```php
use Tilttek\Kudos\Kudos;
use Tilttek\Kudos\Limits;
use Tilttek\Kudos\Messages;
use Tilttek\Kudos\TimeZone\SiteTimeZone;

$kudos = new Kudos(
    $journal,                              // Tilttek\Activity\Journal
    $clock,                                // Psr\Clock\ClockInterface
    Messages::defaults(),                  // the closed list of messages
    new Limits(daily: 5, perReceiver: 3),  // five a day, three to one member in seven days
    $preferences,                          // Contract\KudosPreferences; Preferences\AcceptAll by default
    new SiteTimeZone(new DateTimeZone('America/Toronto')), // Contract\MemberTimeZones; UTC by default
    [$observer],                           // Contract\KudosObserver, told of thanks given and cancelled
);
```

When the journal declares its verbs, the declaration includes `kudos.given` (`Kudos::VERB`), or `kudos.*`.

`Kudos` keeps no state between two calls: one instance serves every request, in a worker as in a request. The
journal and the ports it is given keep that promise too.
