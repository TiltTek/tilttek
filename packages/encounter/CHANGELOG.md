# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/encounter` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Participant` and `Kind`: one line of an encounter — a character, or 1 to 50 copies of an adversary or an ally of one
  model — with its rating as the game system writes it, up to 16 characters, the member who controls it, whether the
  players see it, an initiative bonus from -99 to 99 and the identifier of the application's record; each copy a
  combatant of its own, `wolves.1` to `wolves.n`.
- `Encounter`: its game master, game system, a title of one line up to 200 characters, up to 100 participants and 100
  combatants in the order the game master set, each combatant identifier once, whether it was launched, its revision.
- `EncounterPlanner::prepare()`, `retitle()`, `add()`, `change()`, `move()` and `remove()`: an encounter opened for a
  game system with rules, and changed by its game master alone; a participant placed last, or right before or after
  another one; a change that changes nothing keeping nothing.
- `Contract\DifficultyRules` and `Difficulty\Assessment`, `Difficulty\Band`: the cost of the adversaries, up to 12 bands
  with their thresholds in order, the band reached, and what remains before each band.
- `Difficulty\BudgetRules` and `Difficulty\Multiplier`: amounts by character level, costs by adversary challenge,
  multiplied by the number of adversaries; up to 1,000 ratings per table and 20 multipliers.
- `Difficulty\LevelRules`: budgets for a party of a standard size, adjusted by its size, and costs by how many levels an
  adversary stands above the party, from -50 to 50.
- `Difficulty\Declaration`: either kind of rules read from plain data, every key, type and bound checked, fuzzed; and
  from the difficulty numbers `encounter` of a `tilttek/rpg-systems` system by `fromSystem()` — method `budget` with
  `budgets`, `costs` and optional `multipliers`, method `level` with `party-size`, `budgets`, `adjustments` and
  `costs` —, an unknown or missing table refused.
- `Rulebooks` and `EncounterPlanner::assess()`: the rules of up to 1,000 game systems, found by key or by the
  `GameSystem` of `tilttek/rpg-systems`, read by `Rulebooks::fromSystems()` from the difficulty numbers of each system
  with the rules the application brings in their place, and the difficulty of an encounter by the rules of its
  system.
- `EncounterPlanner::launch()`, `roster()`, `Initiative\InitiativeRoster`, `Initiative\RosterEntry` and
  `Contract\InitiativeHandOff`: the encounter kept launched, no longer changing, and its combatants handed in order to
  any initiative tracker, without depending on one.
- `EncounterPlanner::forget()`: an encounter removed from the store.
- The events `EncounterPrepared`, `EncounterRetitled`, `ParticipantAdded`, `ParticipantChanged`, `ParticipantMoved`,
  `ParticipantRemoved`, `EncounterLaunched` and `EncounterForgotten`, by identifiers only, told once to a
  `Contract\EncounterObserver`.
- `Contract\EncounterStore`, keeping each encounter at a revision, and `Store\InMemoryEncounterStore` for tests.
- The exceptions `InvalidEncounter`, `EncounterRefused`, `EncounterNotFound`, `EncounterConflict` and `UnknownSystem`,
  under `EncounterException`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
