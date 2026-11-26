# Installation

```sh
composer require tilttek/session-scheduler
```

The package needs PHP 8.5 or later, `phil/recurrence`, which reads the rules and lists their occurrences, and
`psr/clock`, whose clock gives the scheduler the time.

An application gives `SessionScheduler`:

- a `Contract\SessionStore` that keeps the series and sessions in its database;
- a PSR-20 `ClockInterface`, such as the `NativeClock` of `symfony/clock`;
- a `Contract\SessionObserver`, to send its messages, or nothing;
- a `Contract\DateProposer`, to put dates to the members, or nothing;
- the most upcoming sessions a member may say they are coming to, 1 to 100, or nothing for no cap.

The service keeps no state between calls, so one instance serves every request of a long-running worker.
`Store\InMemorySessionStore` keeps the series and sessions in memory, for tests.
