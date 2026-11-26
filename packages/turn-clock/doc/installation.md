# Installation

```sh
composer require tilttek/turn-clock
```

The package needs PHP 8.5 or later and `psr/clock`, whose clock gives the service the time.

An application gives `TurnClock`:

- a `Contract\ClockStore` that keeps the clocks in its database;
- a PSR-20 `ClockInterface`, such as the `NativeClock` of `symfony/clock`;
- a `Contract\ClockObserver`, to send its messages, or nothing.

The service keeps no state between calls, so one instance serves every request of a long-running worker.

`Store\InMemoryClockStore` keeps the clocks in memory, for tests.
