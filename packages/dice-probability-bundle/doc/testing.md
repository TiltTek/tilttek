# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit tests check the configuration and the services, `DiceStatistics` with its locales and rounding modes, the
weight of the groups, the Twig functions and the bounds of the fractions, with and without `ext-gmp`, the names of the
kit's components, the rate limit by network, and the component's results, refusals and errors. The functional test
boots a small application — TilttekDiceBundle with a custom die, Twig, TwigComponent and LiveComponent, in French —
with the recipes of the PHIL UX kit, from `phil/ux-kit`, copied where `ux:install` puts them, at the root of the
components directory and in a subdirectory, and renders the component, the table, the chart, the errors and the
refusals, within a second for the groups that cost the most.

## Testing pages that show probabilities

The probabilities are exact, so a page can be compared with its text: `6/36`, `16.67 %`. For an estimate, set few
samples and a seed in the test configuration — `samples: 200`, `seed: 42` —: the same estimate comes back each time.

A functional test that sends the component many requests turns the rate limit off, or raises it, in its configuration:
`rate_limit: false`.
