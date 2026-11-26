# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network: declarations read from JSON and their refusals, each
operation and its codec, entries and teams, the scoreboard with its explanations, ranks, tie-breakers and teams, the
life of a match sheet with its versions and revisions, the result and its message, and the blank sheet. Property
tests enter random values from fixed seeds and check, after each entry, that the total is the sum of the categories
that count, that the ranks follow the totals, and that a team holds the sum of its members.

`tests/Fuzz/sheet.php` is the fuzz target of the declarations: any text is refused with the library's exceptions, or
gives a declaration that writes itself back to the same one, a blank sheet and a scoreboard. `composer fuzz` runs it.

## Testing code that keeps scores

Give `ScoreKeeper` the store in memory and a sheet declared in the test:

```php
$keeper = new ScoreKeeper(new InMemoryMatchSheetStore());
$keeper->prepare($match, SheetDefinition::fromJson($json), new Entries([$ada, $bo]));
```
