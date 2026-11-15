# Installation

## Requirements

- PHP 8.5 or later, with `sodium`.
- `phil/resource` 0.1, whose `ResourceReference` names each member by the stable reference the site gives it, and the
  subject of each bounty.
- `tilttek/points` 0.1 or 0.2, for the escrow on its ledger and for `InsufficientPoints`.
- `psr/clock` and `symfony/clock`, for the time of each move; `psr/event-dispatcher`, for the events.

## Installing

```sh
composer require tilttek/bounties
```

## Wiring the library

`Bounties` takes two ports, and optionally an event dispatcher, the site's rules and a clock:

```php
use Symfony\Component\Clock\NativeClock;
use Tilttek\Bounties\Bounties;
use Tilttek\Bounties\BountyRules;
use Tilttek\Bounties\Escrow\PointsBountyEscrow;

$bounties = new Bounties(
    $store,                           // Contract\BountyStore: where the bounties are kept
    new PointsBountyEscrow($points),  // Contract\BountyEscrow: where the points are held, here the ledger of tilttek/points
    $dispatcher,                      // Psr\EventDispatcher\EventDispatcherInterface, or null to dispatch nothing
    new BountyRules(),                // length, grace, smallest and largest bounty
    new NativeClock(),                // Psr\Clock\ClockInterface: the time of each move, kept in UTC
);
```

`BountyRules` takes, in seconds, the shortest bounty — a day by default, an hour at least —, the longest — thirty days
by default, ninety at most —, the grace after the end during which the sponsor still awards — a day by default, a week
at most —, and the smallest and largest amount, 10 and 10 000 points by default.

`Bounties` keeps nothing between two calls, so one instance serves every request of a worker. Run `expireDue()` from a
scheduled task, every few minutes: it settles the bounties whose grace is over, 100 at a time by default and 500 at
most.
