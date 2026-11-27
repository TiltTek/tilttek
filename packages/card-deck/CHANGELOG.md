# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/card-deck` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-26

### Added

- `CardId`, `PlayerId`, `GameId` and `Face`: opaque names of 1 to 64 ASCII letters, digits, dots, underscores and
  hyphens.
- `Card`, `Deck` and `StandardDeck`: cards with an identity separate from their 1 to 16 faces and their back, decks of
  1 to 1 024 cards in their declared order, written to arrays and read back, and the 52-card French-suited deck with
  as many jokers as asked.
- `ZoneId` and `ZoneKind`: the draw pile, the discard pile, the table, players' hands and named piles, written as text
  and read back; at most 256 zones in a game.
- The gestures `Shuffle`, `Draw` — from the top, the bottom or at random —, `Deal` face down or face up, `Discard`,
  `Flip`, `TurnFace`, `Move` and `Collect`, written to arrays, and `GestureCodec`, which reads them back.
- `DeckState`: the state of a game, every card in one zone; a start that shuffles the deck from the number 0 of the
  seed, distinct from a `Shuffle` during the game; gestures that leave the state as it was when refused; an empty zone
  distinct from a zone of jokers; the replay of a journal and the fingerprint of a state.
- `Shuffler`: the Fisher-Yates shuffle on the randomizer of a seed of `tilttek/dice`; random draws from the numbers
  that follow.
- `View\PlayerView`, `View\ZoneView` and `View\SeenCard`: what a player or a spectator may see — their hand, the cards
  face up and the number of cards of every zone, never the order of the draw pile nor a hidden card.
- `CardTable`, `GameRecord` and `Reveal`: open a game and publish the commitment to its seed, start it with the
  players' seed, play gestures against a version, view it, finish it and publish what anyone needs to verify it, with
  at most 10 000 gestures a game.
- `Contract\GameStore` and `Store\InMemoryGameStore`: the store port, written over the previous version only, and the
  store in memory for tests.
- Exceptions under one `CardDeckException` interface: `InvalidDefinition`, `IllegalGesture`, `UnknownGame` and
  `ConcurrentChange`.
- Property tests over random games, a fuzz target of the gesture codec and a benchmark of the replay.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
