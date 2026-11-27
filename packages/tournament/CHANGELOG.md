# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/tournament` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-26

### Added

- `Tournament`, `Stage` and `Scoring`: a tournament of 2 to 512 participants known by opaque identifiers, 1 to 4 stages
  — Swiss rounds, pools in one or two legs, a single-elimination bracket last —, a scoring in whole numbers, up to 8
  tie-breakers in order, and a published seed.
- `Tournaments`: the service that starts a tournament with its first order, prepares each round with its reasons
  before it is published, publishes it as prepared or seated otherwise, reschedules a round without changing its
  number, gives the standings of a stage or a pool, the bracket, a participant's schedule with rests and foreseen
  rounds, and why two participants meet in a round or do not.
- Results entered once by table: the same result again changes nothing, a contrary one waits for an organiser's
  `resolve()`; `correct()` changes a past result and returns how the standings moved; mission points beside the win.
- `withdraw()`: a participant who withdraws is not paired again, their tables left without a result are forfeits, and
  they never qualify; in a bracket, a walkover.
- `Swiss\SwissPairer`: Swiss rounds by score groups after the FIDE rules taken as a written reference — no rematch and
  no second bye while another pairing avoids them, higher half against lower half, sides alternated, rematches with
  the sides swapped —, exact up to 16 participants, a bounded search above, deterministic.
- `RoundRobin\BergerTable` and `RoundRobin\Pools`: pools by the Berger tables, a rest in each round of an odd pool shown
  in the schedule, a return leg with the sides swapped, and pools filled in a snake by seed.
- `Elimination\Bracket` and `Elimination\BracketState`: a single-elimination bracket by seeds, byes in the first round,
  prepared before the qualifiers of the stage before are known.
- `Tiebreak\Tiebreakers`: `buchholz`, `buchholz-cut-1`, `buchholz-cut-2`, `sonneborn-berger`, `head-to-head`, `wins`,
  `mission-points`, and the tie-breakers an application adds; the ties left broken by the draw of the published seed.
- `Contract\SeedingSource`, `Seeding\FixedSeeding` and `Seeding\RatingSeeding`, which reads the ratings of
  `tilttek/rating`, suggested.
- `Event\TournamentFinished`: the winner and the podium, returned when the tournament ends and again when a correction
  changes the podium.
- `Contract\TournamentStore`, which keeps each tournament at a version, and `Store\InMemoryTournamentStore` for tests.
- The documentation in `doc/`, one file per topic, indexed by the README; property tests and benchmarks of the
  pairings.
- `composer.json`, `phil-dev.php`, `composer-dependency-analyser.php`, `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.
