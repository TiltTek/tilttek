# Installation

```sh
composer require tilttek/game-shelf
```

The package needs PHP 8.5 or later, `ext-mbstring` and `tilttek/game-catalog`, whose `Catalog` it checks the games
against.

An application gives the service:

- a `Contract\ShelfStore` that keeps the shelves in its database;
- the `Catalog` of `tilttek/game-catalog`;
- a `Contract\ShelfObserver`, to send its messages, or nothing.

`Store\InMemoryShelfStore` keeps the shelves in memory, for tests.
