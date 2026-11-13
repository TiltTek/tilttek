# Installation

## Requirements

- PHP 8.5 or later, with `intl` for the normalization of answers and `mbstring`.
- `phil/resource` 0.1, whose `ResourceReference` names a team.
- `psr/clock` and `symfony/clock`, for the time of each move; `psr/event-dispatcher`, for the events.
- `symfony/rate-limiter`, suggested, for `Limiter\RateLimiterGuessLimiter`.

## Installing

```sh
composer require tilttek/puzzle-builder
composer require symfony/rate-limiter   # to count the guesses with a limiter of the component
```

## Wiring the library

`PuzzleEngine` takes the limiter of guesses, and optionally an event dispatcher and a clock:

```php
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Tilttek\PuzzleBuilder\Limiter\RateLimiterGuessLimiter;
use Tilttek\PuzzleBuilder\PuzzleEngine;

$guesses = new RateLimiterFactory(
    ['id' => 'puzzle_guesses', 'policy' => 'sliding_window', 'limit' => 10, 'interval' => '1 minute'],
    new CacheStorage($cachePool),   // a store every worker reads
);

$engine = new PuzzleEngine(
    new RateLimiterGuessLimiter($guesses),   // Contract\GuessLimiter: the guesses of a team on a puzzle in a period
    $dispatcher,                             // Psr\EventDispatcher\EventDispatcherInterface, or null to dispatch nothing
    new NativeClock(),                       // Psr\Clock\ClockInterface: the time of each move, written in UTC
);
```

In a Symfony application, the factory is the limiter declared under `framework.rate_limiter`, injected by its name.

`PuzzleEngine` keeps nothing between two calls, so one instance serves every request of a worker. The limiter is
required: there is no engine without a bound on guesses. `Limiter\InMemoryGuessLimiter` counts in the memory of the
process, for tests and for a game played in one process.
