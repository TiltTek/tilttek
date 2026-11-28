# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the encounters live in
`Store\InMemoryEncounterStore`, and two invented game systems — one by budget, one by level — give the rules of
difficulty. The tests cover each bound at its limit and one past it, each placement of a participant, each refusal of
the service, and the assessments of both kinds of rules, multipliers and party sizes included. In the monorepo,
Infection holds the package at 100 % of covered mutants.

## The fuzz target

`tests/Fuzz/declaration.php` feeds any JSON to `Difficulty\Declaration::read()`, then assesses an encounter with the
rules it gives: every input is either refused with the library's exceptions or gives an assessment within its shape.
`composer fuzz -- declaration` runs it in the monorepo; its seeds are under `tests/Fuzz/corpus/declaration`.

## Testing an application's own parts

An application tests its own `EncounterStore` against the rules of [the store](extension-points.md#the-store): nothing
for an encounter never kept, a new encounter kept once, a stale one refused on save and on removal, the revision raised
by one at each write, and the participants read back in their order.

It tests its own `DifficultyRules` with a party and adversaries whose cost and thresholds it computes by hand, at each
band's threshold and one under it, and its `InitiativeHandOff` by launching an encounter and reading the tracker.
