# Installation

```sh
composer require tilttek/game-night
```

The package needs PHP 8.5 or later, `psr/clock` and `tilttek/game-catalog`, whose `Catalog` it reads the games from.

An application gives the services:

- a `Contract\NightStore` that keeps the nights in its database;
- a PSR-20 clock, such as the one of `symfony/clock`;
- the `Catalog` of `tilttek/game-catalog`;
- a `Contract\NightObserver`, to send its messages and notifications;
- a `Contract\ShelfReader`, to read the games the members can bring;
- a `Contract\PlayJournal`, to receive the games played.

`Store\InMemoryNightStore` keeps the nights in memory, for tests.
