# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/initiative-cards` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Encounter` and `Combatant`: an encounter — its game master, a deck of `tilttek/card-deck`, its draw and discard piles,
  up to 100 combatants in turn order, its round and its version — and its combatants, each with a controller, hidden or
  not, defeated or not, drawing one to three cards, bounded and checked.
- `InitiativeTracker`: an encounter started on a shuffled deck, changed by its game master; it keeps no state between
  calls.
- `InitiativeTracker::deal()`: a round dealt, the cards held discarded, the discard pile shuffled back after a card that
  reshuffles or when the draw pile is short, the best of each combatant's cards kept, the turns ordered by the cards, the
  ties in their previous order and the defeated last.
- `InitiativeTracker::endTurn()`: the turn of the combatant holding it ended by its controller or the game master, who
  may name the next one among those who have not acted.
- `InitiativeTracker::join()` and `leave()`: a combatant joining a round with its cards drawn, waiting after the turn in
  progress by its card, or leaving with its card discarded.
- `InitiativeTracker::defeat()` and `revive()`: a defeated combatant faded and skipped, standing again in this round or
  the next by the place of its card.
- `InitiativeTracker::reveal()`: a combatant hidden from the players or revealed to them.
- `InitiativeTracker::end()`: an encounter closed.
- `View\InitiativeView` and `View\Slot`: the turn order a member may see — every combatant for the game master, the
  hidden ones left out for anyone else — as plain data.
- `StandardRanking` and `Contract\CardRanking`: the 52-card deck and its jokers, a joker first and reshuffling, an order
  of the suits; another deck ranked by the application.
- The events `EncounterStarted`, `RoundDealt`, `DeckReshuffled`, `TurnStarted`, `TurnEnded`, `RoundEnded`,
  `CombatantJoined`, `CombatantLeft`, `CombatantDefeated`, `CombatantRevived`, `CombatantRevealed`, `CombatantHidden`
  and `EncounterEnded`, by identifiers only, told once to a `Contract\InitiativeObserver`.
- `Contract\EncounterStore`, kept at a version, and `Store\InMemoryEncounterStore` for tests.
- The exceptions `InvalidEncounter`, `InitiativeRefused`, `EncounterNotFound` and `EncounterConflict`, under
  `InitiativeException`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
