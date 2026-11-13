# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network or a database: rolls sealed and committed from a seeded
randomizer, results rolled again from their seed, reveals in order and once, partial and total reveals at the closing,
withheld rolls revealed after the scene, every refusal, openings and entries written and read back with everything
else refused, and journals checked fault by fault — an entry moved, changed, out of its scene, a roll committed twice
or after the closing, a reveal that does not match. In the monorepo, Infection holds the library at 100 % of covered
mutants.

`tests/Fuzz/opening.php` is the fuzz target of the verification of a revealed roll: any JSON text gives true or false,
never an exception. `tests/Fuzz/journal.php` is that of a published journal: any JSON text is refused with
`InvalidSecretRoll` or gives a journal whose check lists its faults. `composer fuzz` runs both.

`tests/Benchmark/SecretRollsBench.php` measures one roll committed, one revealed roll checked, and the export of a
scene of 500 rolls, all revealed, read and checked whole.

## Testing an application

Give `SecretRolls` a seeded randomizer, so that its seeds are the same at every run:

```php
$rolls = new SecretRolls(new InMemorySecretRollStore(), secure: new Randomizer(new Xoshiro256StarStar(7)));
```

Then check that the journal the application publishes holds: `self::assertSame([], $journal->problems());`.
