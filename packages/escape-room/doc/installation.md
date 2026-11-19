# Installation

## Requirements

- PHP 8.5 or later, with `mbstring`.
- `tilttek/puzzle-builder` 0.1, the puzzle engine of the rooms, with its own requirements.
- `phil/resource` 0.1, whose `ResourceReference` names a team, a party and a host.
- `psr/clock` and `symfony/clock`, for the countdown; `psr/event-dispatcher`, for the events.
- `phil/reservation` 0.1, suggested, for `Reservation\BookableSessionReservations`: the sessions booked as intervals of a
  bookable resource, with its opening hours, notice and booking window.

## Installing

```sh
composer require tilttek/escape-room
```

## Wiring the library

`EscapeRooms` takes a `PuzzleEngine`, and optionally an event dispatcher and a clock; `Booking\SessionBooker` takes the
application's reservations, and optionally the same dispatcher and clock:

```php
use Symfony\Component\Clock\NativeClock;
use Tilttek\EscapeRoom\Booking\SessionBooker;
use Tilttek\EscapeRoom\EscapeRooms;
use Tilttek\PuzzleBuilder\Limiter\RateLimiterGuessLimiter;
use Tilttek\PuzzleBuilder\PuzzleEngine;

$clock = new NativeClock();
$puzzles = new PuzzleEngine(new RateLimiterGuessLimiter($guessLimiterFactory), $dispatcher, $clock);

$rooms = new EscapeRooms(
    $puzzles,      // Tilttek\PuzzleBuilder\PuzzleEngine: the guesses, hints and puzzles of each game
    $dispatcher,   // Psr\EventDispatcher\EventDispatcherInterface, or null to dispatch nothing
    $clock,        // Psr\Clock\ClockInterface: the countdown, the same clock as the engine's
);

$booker = new SessionBooker(
    $reservations, // Contract\SessionReservations: the application's reservations of the sessions
    $dispatcher,
    $clock,        // when a session has started and is no longer booked
);
```

Both services keep nothing between two calls, so one instance serves every request of a worker.
