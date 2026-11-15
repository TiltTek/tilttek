# Installation

## Requirements

- PHP 8.5 or later, with `mbstring` and `sodium`.
- `phil/resource` 0.1, whose `ResourceReference` names each member by the stable reference the site gives it.
- `tilttek/points` 0.1 or 0.2, for the escrow on its ledger and for `InsufficientPoints`.
- `psr/clock` and `symfony/clock`, for the time of each move; `psr/event-dispatcher`, for the events.

## Installing

```sh
composer require tilttek/friend-challenges
```

## Wiring the library

`FriendChallenges` takes four ports, and optionally an event dispatcher, the site's rules and a clock:

```php
use Symfony\Component\Clock\NativeClock;
use Tilttek\FriendChallenges\ChallengeRules;
use Tilttek\FriendChallenges\Escrow\PointsPrizeEscrow;
use Tilttek\FriendChallenges\FriendChallenges;

$challenges = new FriendChallenges(
    $store,                          // Contract\ChallengeStore: where the challenges are kept
    new PointsPrizeEscrow($points),  // Contract\PrizeEscrow: where the prizes are held, here the ledger of tilttek/points
    $friendship,                     // Contract\Friendship: who is a friend of whom
    $moderation,                     // Contract\Moderation: where reports go, and who moderates
    $dispatcher,                     // Psr\EventDispatcher\EventDispatcherInterface, or null to dispatch nothing
    new ChallengeRules(),            // how long a challenge lasts, how long its review, the largest prize
    new NativeClock(),               // Psr\Clock\ClockInterface: the time of each move, kept in UTC
);
```

`ChallengeRules` takes, in seconds, the shortest challenge — an hour by default, a minute at least —, the longest —
thirty days by default, a year at most —, the review that follows the end — three days by default, thirty at most —,
and the largest prize, 10 000 points by default.

`FriendChallenges` keeps nothing between two calls, so one instance serves every request of a worker. Run
`expireDue()` from a scheduled task, every few minutes: it ends the challenges whose review is over and gives their
prize back, 100 at a time by default and 500 at most.
