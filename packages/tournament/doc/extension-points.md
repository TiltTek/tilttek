# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep tournaments | Implement `Contract\TournamentStore`, and pass it to `Tournaments`. |
| Order participants by strength | Implement `Contract\SeedingSource`, or use `Seeding\FixedSeeding` or `Seeding\RatingSeeding`. |
| Break ties your way | Implement `Contract\Tiebreaker`, and pass it to `Tiebreak\Tiebreakers`. |
| Pair larger groups exactly, or search longer | Pass a `Swiss\SwissPairer` with its `exactLimit` and `passes` to `Tournaments`. |
| Celebrate the podium | Dispatch the `Event\TournamentFinished` a `Recording` carries. |

## The store

`Contract\TournamentStore` keeps each tournament as a `TournamentState`, at a version: `get()` reads it, `save()` keeps
it at the next version. A state at version 0 is new, and the store has no tournament of its identifier; any other is
kept only when the store holds it at that version, and `TournamentConflict` is raised otherwise, nothing kept. Two
changes made at once never overwrite each other: the one refused is made again on the tournament read anew.

`Store\InMemoryTournamentStore` keeps the tournaments in memory, as long as the object lasts: it is for tests. An
application keeps them in its database, the state serialised as it likes; the state holds identifiers, numbers and
dates only.

## The seeding source

`Contract\SeedingSource::strengths()` gives a strength for each participant it knows, the strongest highest; a
participant without one is placed after the others, in the order of the seed. `Seeding\FixedSeeding` takes the
strengths given, or a list in order with `FixedSeeding::inOrder()`. `Seeding\RatingSeeding` reads the scores of the
ratings of one game in `tilttek/rating`, which an application installs beside this library:

```php
$tournaments = new Tournaments($store, seeding: new RatingSeeding($ratings, 'chess'));
```

The seeding shows an order, never a score.

## The event

`Tournaments` returns the events it raises in the `Recording` of the call that raised them, and dispatches nothing: an
application dispatches them on its own bus. A listener keys what it does by `TournamentFinished::key()`, so that a
podium told again does nothing more.

## Worker-safe

`Tournaments` and everything it uses keep nothing between calls: they can live as long as a worker, and serve
several tournaments at once.
