# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The Unit suite checks the seeds and their source, the drawers, the commitments and the listener of the order workflow,
the reveal once, the texts, the physical dice, the results and their replay, what is printed, on orders of the
commerce core in memory; the stores against one contract — Doctrine on SQLite in memory —, the form of the dice, the
buyer's component, the replay command, the production sheet, and the services the configuration registers. The
Functional suite boots an application with the commerce core, Twig and TwigComponent in French, and the recipes of
the PHIL UX kit copied where `ux:install` puts them: it places an order through the workflow, which commits its draws,
pays it, lets the core draw it, verifies and rolls again every draw; it renders the buyer's draws before and after the
payment, rolls a draw again with the command, enters dice through the form and its component, and prints the
production sheet. It is skipped when the event dispatcher, a development dependency, or the PHIL UX kit is missing.

## Testing an application that uses it

Set `store: memory`. The seeds come from the cryptographic generator in tests too, so a test asserts what holds of
every draw rather than given faces: `LootResults::verify()` checks a draw end to end, `LootResults::replay()` gives the
faces again, and physical dice entered with `PhysicalDice::enter()` give chosen faces.
