# Installation

## Requirements

- PHP 8.5 or later.
- `phil/resource`, whose references name the members.
- A `psr/clock` clock, such as `symfony/clock`.

## Installing

```sh
composer require tilttek/celebrations
```

## Wiring the library

`Celebrations` takes the application's store and clock. Everything else has a default:

```php
use Tilttek\Celebrations\Celebrations;
use Tilttek\Celebrations\Limits;

$limits = new Limits(pendingPerMember: 200, entriesPerMember: 500, shownPerGroup: 10);

$celebrations = new Celebrations(
    $store,                                // Contract\CelebrationStore
    $clock,                                // Psr\Clock\ClockInterface
    $limits,
    [$observer],                           // Contract\CelebrationObserver, told of each change
);
```

`Store\InMemoryCelebrationStore` serves tests and applications that rebuild their inboxes at each start; an
application that keeps them implements `Contract\CelebrationStore` on its storage, as
[extension points](extension-points.md) describe.

`Celebrations` keeps no state between two calls: one instance serves every request, in a worker as in a request. The
store and observers it is given keep that promise too, beside their storage.

The achievements come from the application's own listeners, one per rewards package it uses:
[celebrating](celebrating.md) shows one.
