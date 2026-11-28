# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests/Unit
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network, on the in-memory inventory and the
`MockClock` of `symfony/clock`: codes, keys, quantities and their bounds; ingredients by item and by tag, tools;
recipes that combine or evolve and every recipe refused; the book read from plain values, written back, and every
value refused; protected items; previews and what is missing; items set aside by code, by choice, by tag, the scarcest
tag first; selections; crafts once per key, the key given again after the items are gone, a key of another craft, a
craft applied at the same time under its key; repetitions; what is craftable and closest; the bounds of the holdings;
and the events told. In the monorepo, Infection holds the library at 100 % of covered mutants.

The fuzz target `recipe-book`, under `tests/Fuzz`, reads any JSON as a book: it is refused with the library's own
exceptions, or it writes itself back to the same values and none of its recipes is craftable from an empty inventory.
`composer fuzz -- recipe-book` runs it from the monorepo.

The bench, under `tests/Benchmark`, fills the book to its bound — 1,000 recipes of four ingredients, two of them asked
by a tag — for a holder of 500 items, and measures what is craftable, what is closest, one preview and one craft.

## Testing code that crafts

`Store\InMemoryInventory`, given the tags of each item, and a `MockClock` give a whole crafting setup in a test:
`put()` gives a holder items, `quantity()` reads them back after a craft. An observer that records the `Crafted` it is
told checks what the application relays.
