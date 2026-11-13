# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network or a database: the shapes of codes, identifiers and names,
every bound of a card, a format, a taboo list and a deck, taboo lists in order and on the day's own time zone, the rules
of a day, formats and decks written and read back with everything else refused, every violation and its message,
collections, versions saved, kept, in conflict and copied, and differences. In the monorepo, Infection holds the library
at 100 % of covered mutants. The cards and formats of the tests are invented.

`tests/Fuzz/deck-format.php` and `tests/Fuzz/deck.php` are the fuzz targets of a format and a deck: any text reads as a
format or a deck that writes back to itself, or is refused with `InvalidDeck`, never another exception. `composer fuzz`
runs them.

`tests/Benchmark/DeckBuilderBench.php` measures the largest deck — 1 000 distinct cards — validated against a format of
10 000 banned and 10 000 restricted cards and 100 taboo lists with a collection, its differences with another version,
and its reading from JSON.

## Testing an application

Give the validator in-memory catalogs of invented cards, and `Decks` the in-memory store:

```php
$validator = new DeckValidator(new InMemoryCardCatalog(new Card('fox', 'Ember Fox')), new InMemoryFormatCatalog($format));
$decks = new Decks(new InMemoryDeckStore());
```
