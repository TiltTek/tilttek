# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The suite checks every distribution of a set of dice against all of its rolls, each judged by the roller of
`tilttek/dice` as a list of values with the same modifiers; the explosions, rerolls, counts rolled first and arithmetic
against probabilities worked by hand; the events against the reference example and the counts of their rolls; the
estimates, their seeds and their roll and group work limits; and the exact numbers. `AgreementPropertyTest` generates
expressions from fixed seeds — sums and differences of sets of dice, kept, rerolled, and of numbers — and checks that
the bounds of each distribution are those the expression allows and that every total the roller gives from the parsed
expression is a value of its distribution. In the monorepo, Infection holds the library at 100 % of covered
mutants.

## Testing code that uses probabilities

The results are exact: compare fractions as text, `'16/36'`, or by value with `equals()`. An estimate depends only on
its seed and its number of samples: pass `new Settings(samples: 200, seed: 42)` and the same estimate comes back, quickly.
To force an estimate, pass a small work limit: `new Settings(workLimit: 1)`.
