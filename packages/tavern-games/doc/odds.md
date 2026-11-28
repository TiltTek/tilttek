# Odds

## The estimator

`Odds\Estimator::estimate($simulation, $samples, $randomizer)` plays a position to its end `$samples` times — 1 to
100 000 — and counts how each ending went for the seat it is told to.

```php
$estimate = Estimator::estimate($game->chase($state), 10_000, new Randomizer(new Secure()));

$estimate->toArray(); // ['wins' => 6120, 'ties' => 830, 'losses' => 3050, 'samples' => 10000]
```

An `Odds\Estimate` keeps the wins, the ties and the losses apart, with `samples()` the number of endings they were
counted on, and their shares from 0 to 1: `winRate()`, `tieRate()`, `lossRate()`. A tie is a shared win, which neither
a win nor a loss describes: show the three shares and the number of endings before any average drawn from them.

A position implements `Contract\Simulation`: `trial($randomizer)` plays it to its end once and returns a `Trial` —
`Win`, `Tie` or `Loss`. An estimate is chance, not the game's: it never changes a table, and it draws from the
randomizer it is given, not from the table's seed.

## The chase of a cargo

`ShipCaptainCrew::chase($state)` gives the turn of the seat playing as an `Odds\CargoChase`: how many of ship, captain
and crew are aboard, the dice rolled last, the rolls left, and the target — the best cargo of the seats before it, 0
when none carries any. Its trial rolls while rolls remain and the cargo does not beat the target, and stops as soon as
it does: a cargo above the target wins, the same cargo ties, less loses; with no cargo before it, carrying none ties.
Its dice are drawn straight from the randomizer, for speed: they are the simulation's, never the table's.
