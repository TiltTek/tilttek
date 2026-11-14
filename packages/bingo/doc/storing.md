# Storing

`Contract\BingoStore` keeps the games and their cards; `Store\InMemoryBingoStore` keeps them in memory, for tests and
scripts.

| Method | Does |
|---|---|
| `game()`, `addGame()`, `saveGame()` | Reads, adds at version 1 and saves the next version of a game |
| `card()`, `cardOf()` | Reads a card by serial, printed cards included, or the card a player holds in a game |
| `addCard($card, $fingerprint)` | Adds a card at version 1; returns false, keeping nothing, when another card of the game has the fingerprint |
| `saveCard()` | Saves the next version of a card |
| `openCardsOf($player, $limit)` | The player's cards in the games that are open, at most `$limit`; never a printed card |

## Versions

A game and a card count their changes: each gives them their next version, starting at 1. `save…()` refuses with
`BingoConflict` when the version stored is not the one before it; `addGame()` refuses an identifier taken, and
`addCard()` a serial taken or a second card for a player; a printed card has no player, and a game holds as many as it
prints. `Bingo` reads the game or the card again and makes the change
again on a conflict, up to `Bingo::ATTEMPTS` times: two gestures recorded at once both tick, one after the other. A
store backed by a database writes `save…()` as an update conditioned on the version, and keeps the fingerprints of a
game under a unique index.

## What a store keeps

A game keeps its pool, grid, patterns and mode, the number of cards dealt, the squares called and the serials that won.
A card keeps its game, serial, player reference — none for a printed card —, layout, the idempotency keys of the
gestures that ticked each cell, the cells pinned by more gestures than they remember, the pattern and moment of its
win, and the events of its ticks and its win not delivered yet. A game also keeps the squares called whose event is not
delivered yet. No label a player wrote and no name is kept:
players are the references the application gives.

## Cards and games stored before

The cells pinned and the events not delivered are properties a card or a game stored before them does not have: read
again, the card has no cell pinned and the game and the card have no event to deliver, which is what they had. A store
that serializes the objects with PHP reads them as they are; a store that maps their properties to columns adds the
new ones, empty by default. Nothing is migrated.
