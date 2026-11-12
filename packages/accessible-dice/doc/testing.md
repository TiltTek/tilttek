# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests/Unit tests/Functional
node --test tests/js
```

The unit suite, under `tests/Unit`, runs without a kernel or a network: the dice offered and the expressions of the
choices, the descriptions of the faces, the announcements in English and French, the view of the form and the
configuration. The functional test boots a small application — TilttekDiceBundle with a coin, its labels in French,
and Twig — and draws the form with a roll. The scripts of the controller are tested with Node's test runner. In the
monorepo, Infection holds the package at 100 % of covered mutants.

## The browser suite

The suite under `tests/Browser` drives a headless Chrome with Panther: the expression follows the choice, the buttons
step how many dice, a roll of a dice tower is announced in the polite region, the targets are at least 44 pixels,
nothing is read aloud by default, and the roll does not move under `--force-prefers-reduced-motion`. It runs apart from
the other suites, with a chromedriver on the path and Stimulus among the browser dependencies of the PHIL UX kit:

```sh
vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Browser
```

It is skipped when either is missing.
