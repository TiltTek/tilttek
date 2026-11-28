# Installation

```sh
composer require tilttek/play-log
```

The package needs PHP 8.5 or later, `ext-mbstring` and `tilttek/game-catalog`, whose `Catalog` it checks the games
against.

An application gives `PlayLog`:

- a `Contract\PlayStore` that keeps the plays in its database;
- the `Catalog` of `tilttek/game-catalog`;
- a `Contract\PlayObserver`, to send its messages, or nothing;
- a `Contract\PlayerConsent`, when members may agree beforehand to be linked to the plays of others, or nothing.

`PlayStatistics` takes the same `Contract\PlayStore`. `Store\InMemoryPlayStore` keeps the plays in memory, for tests.
