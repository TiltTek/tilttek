# Installation

```sh
composer require tilttek/encounter
```

The package needs PHP 8.5 or later and `tilttek/rpg-systems`: the rulebooks find the rules of a game system by its key
or by its `GameSystem`.

An application gives `EncounterPlanner`:

- a `Contract\EncounterStore` that keeps each encounter in its database;
- `Rulebooks`, the rules of difficulty of each game system it offers, or nothing: without them, any system key is taken
  and `assess()` is refused;
- a `Contract\EncounterObserver`, to send its messages, or nothing;
- a `Contract\InitiativeHandOff`, the adapter of its initiative tracker, or nothing: `launch()` then only returns the
  roster.

The service, the rulebooks and the rules keep no state between calls, so one instance of each serves every request of
a long-running worker.

`Store\InMemoryEncounterStore` keeps the encounters in memory, for tests.
