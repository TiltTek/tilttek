# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network or a database: grants, spendings and transfers checked
against the rules — the cap, a balance under zero, who may —; operations sent again and identifiers reused; sessions
with their carry-over and refresh, kind by kind; another writer reaching the ledger first, once, twice and three times;
rules and ledger entries refused field by field and read back as they were written; a member erased under a pseudonym,
in every table, the balances and positions unchanged, once. In the monorepo, Infection holds
the library at 100 % of covered mutants.

`tests/Fuzz/rules.php` is the fuzz target of the rules of a table: any text gives rules whose refreshes stay between 0
and the cap and that write back as they were read, or is refused with `InvalidTokenRule`, never another exception.
`composer fuzz` runs it.

`tests/Benchmark/LuckTokensBench.php` measures a token spent and granted back after 10 000 entries, and the start of a
session for twelve players.

## Testing an application

Give `LuckTokens` the memory stores and a clock that does not move:

```php
$tokens = new LuckTokens($rules, new InMemoryTokenLedger(), $dispatcher, clock: new MockClock('2026-11-13 20:00 UTC'));
```

A test of a store of its own runs the same operations on it and on `InMemoryTokenLedger`, and compares the ledgers
with `LedgerEntry::toArray()`.
