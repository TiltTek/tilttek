# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

`tests/Unit` covers the journal's row, `DiceRolls`, both journals — the Doctrine one on SQLite in memory —,
`DiceMacros` and both stores of macros, the constraint, the field, the Twig extension and the template, and the
configuration. `tests/Functional` boots a kernel
with FrameworkBundle, TwigBundle and DoctrineBundle on SQLite, and rolls, replays, renders and validates through the
container. In the monorepo, Infection holds the bundle at 100 % of covered mutants.

## Testing an application that rolls dice

Set `store: memory` in the test environment, and build `DiceRolls` with a seeded or scripted `Randomizer` where a test
needs to know the dice; `MockClock` dates the rolls.
