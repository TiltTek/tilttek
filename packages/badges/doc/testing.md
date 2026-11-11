# Testing

## The library's tests

The tests under `tests/Unit` cover every class: the values and their limits, each criterion and the reader, the
catalog, the projection over a real journal of `tilttek/activity` in memory — awards, repeats, withdrawals, manual
awards, measures, rebuilds —, `Badges`, and the contracts of the four stores, written as abstract test cases that every implementation extends.

```sh
vendor/bin/phpunit
```

Every mutant Infection makes of the library's code is killed by a test: the bar is 100 % of covered mutants.

## Testing code that awards badges

Build the catalog, the stores in memory and a `Journal` whose `LiveProjections` hold the `BadgeProjection`; record the
activities the code under test records, and assert on `Badges`. A clock the test sets dates the manual awards; an
`AwardObserver` of the test writes down what is told. Reuse the abstract store test cases, `ProgressStoreTestCase` and
its siblings, to check an application's own stores.
