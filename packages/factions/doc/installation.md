# Installation

```sh
composer require tilttek/factions
```

The package needs PHP 8.5 or later, with `ext-mbstring`, which counts the characters of the texts. It requires
`psr/clock`, through which the service reads the time of each change.

An application gives `Factions`:

- a `Contract\RosterStore` that keeps the rosters and their history in its database;
- a `Psr\Clock\ClockInterface`: the clock of `symfony/clock`, or any other;
- a `Contract\RosterObserver`, to send its messages, or nothing.

The service keeps no state between calls, so one instance serves every request of a long-running worker.
`Store\InMemoryRosterStore` keeps the rosters in memory, for tests.
