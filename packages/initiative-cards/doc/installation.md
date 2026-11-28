# Installation

```sh
composer require tilttek/initiative-cards
```

The package needs PHP 8.5 or later and `tilttek/card-deck`, whose decks, cards and shuffle it uses.

An application gives `InitiativeTracker`:

- a `Contract\EncounterStore` that keeps the encounters in its database;
- a `Contract\CardRanking`, or nothing for `StandardRanking`, the 52-card deck and its jokers;
- a `Contract\InitiativeObserver`, to send its messages, or nothing;
- a `Random\Randomizer`, or nothing for one on the `Secure` engine.

The service keeps no state between calls, so one instance serves every request of a long-running worker. A randomizer
on a seeded engine moves on at each shuffle: it is for tests, and the `Secure` engine is the one for production.

`Store\InMemoryEncounterStore` keeps the encounters in memory, for tests.
