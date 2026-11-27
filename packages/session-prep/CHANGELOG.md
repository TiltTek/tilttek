# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/session-prep` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-26

### Added

- `Preparation` and `PreparationStatus`: the game master's preparation of one session of a campaign — its title, the
  session it is for by its identifier only, up to 50 scenes, 50 secrets, 100 non-player characters, 50 places, 100
  notes and a checklist of 50 items, in draft, ready or closed — bounded and checked as a whole, each change returning a
  new value.
- `Part`, `Scene`, `SceneStatus`, `Secret`, `Clue`, `NonPlayerCharacter`, `Place`, `Note` and `ChecklistItem`: the
  parts of a preparation, each found by its key; a scene features only the characters and places its preparation holds,
  and a secret is tied to no scene.
- The texts of a preparation: valid UTF-8, trimmed, counted in characters, one line without control characters or lines
  with their ends written alike, without direction overrides.
- The lists of parts kept in the game master's order: a part put in place or after the others, removed, or moved before
  another; a character or a place removed is dropped from the scenes featuring it.
- `ChecklistTemplate` and `ChecklistTemplate::standard()`: the steps of a preparation, each named by what it is for,
  the opening, the scenes and the secrets required, added after a preparation's own items.
- `SessionPreparer`: create, change by the game master — who may hand a preparation over — with the session, the status
  and the facts of the table kept, and link to a session or to none; it keeps no state between calls.
- `SessionPreparer::markScene()`, `markSecret()`, `markClue()` and `markItem()`: a scene played, skipped or planned
  again, a secret revealed or hidden, a clue found or not, an item checked or not, each told once.
- `SessionPreparer::ready()`, `close()` and `reopen()`: a draft ready once its required items are checked, closed when
  the session is over and then changed only once reopened.
- `SessionPreparer::carryOver()`: the secrets not revealed and the scenes still planned of a preparation, with the
  characters and places those scenes feature, carried into another one of the campaign, again only what is new.
- `SessionPreparer::delete()`, `preparation()` and `preparations()`, with `PreparationQuery`: by campaign, game master,
  session and status, by identifier, up to 1000.
- The events `PreparationCreated`, `PreparationChanged`, `SessionLinked`, `SceneMarked`, `SecretMarked`, `ClueMarked`,
  `ItemMarked`, `PreparationReady`, `PreparationReopened`, `PreparationClosed`, `PreparationCarriedOver` and
  `PreparationDeleted`, by identifiers only, told once to a `Contract\PreparationObserver`.
- `Contract\PreparationStore`, kept at a version, deleted at a version and read by query, and
  `Store\InMemoryPreparationStore` for tests.
- The exceptions `InvalidPreparation`, `PreparationRefused`, `PreparationNotFound` and `PreparationConflict`, under
  `SessionPrepException`.
- Benchmarks of the largest preparation: a secret marked, a carry-over, a whole check.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
