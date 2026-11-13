# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network or a database: the shapes of codes, members and holders,
holders referenced by an e-mail address refused, every bound of a definition, tiers out of order, definitions written
and read back with everything else refused, evaluations at several levels, versions defined again, rewritten or older,
completions recorded and told once, kept when a new version grows the set, per holder, erased, and the stores. In the
monorepo, Infection holds the library at 100 % of covered mutants.

`tests/Fuzz/definition.php` is the fuzz target of a definition: any text reads as a definition that writes back to
itself, or is refused with `InvalidSet`, never another exception. `composer fuzz` runs it.

`tests/Benchmark/SetsBench.php` measures the largest set — 1 000 members and 20 tiers, every tier reached — against
holdings of 5 000 members, and its definition read back from JSON.

## Testing an application

Give `Sets` the in-memory stores, a clock of your own and a dispatcher that keeps the events, then check what the
application grants when it hears them:

```php
$sets = new Sets(new InMemoryDefinitionStore(), new InMemoryCompletionStore(), $frozenClock, $recordingDispatcher);
```
