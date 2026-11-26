# Games and stores

## The table

`CardTable` runs the games of a store and keeps nothing between two calls:

| Method | What it does |
|---|---|
| `open(GameId, Deck, Seed): Commitment` | Stores a new game and returns the commitment to publish |
| `start(GameId, string $clientSeed): DeckState` | Starts the game once: the draw pile shuffled |
| `play(GameId, int $version, Gesture): DeckState` | Applies a gesture when the game is at that version, records it |
| `view(GameId, ?PlayerId): PlayerView` | What a player — or a spectator — may see |
| `finish(GameId): Reveal` | Finishes the game and reveals what anyone needs to verify it |

A game is played once started and until finished; `IllegalGesture` says otherwise. A journal holds at most 10 000
gestures, so a replay stays bounded.

## Versions

A `GameRecord` has a version, one more at every change: 0 when opened, 1 when started, then one per gesture and one
when finished. A view carries the version it was built from; the player's gesture is played against it, and
`ConcurrentChange` refuses it when another gesture was recorded first — two players who draw the last card at once get
one card and one refusal. The application reads the game again and lets the player decide anew.

## The store port

`Contract\GameStore` has two methods: `find(GameId): ?GameRecord`, and `save(GameRecord)`, which writes the record only
over the version before it — none for version 0 — and throws `ConcurrentChange` otherwise.

The record holds the deck, the seed, the client seed, the journal, whether the game is finished and its version. The
state is never stored: it is replayed from the journal.

## A store of your own

A store on a database keeps one row per game, with `Deck::toArray()` and each `Gesture::toArray()` as JSON, the seed
as `Seed::hex()`, and the version in a column that the update checks:

```sql
UPDATE card_game SET record = :record, version = :version WHERE id = :id AND version = :version - 1
```

No row updated means `ConcurrentChange`. It reads the record back with `Deck::fromArray()` and
`GestureCodec::fromArray()`. It encrypts the seed at rest, and never logs it.

`Store\InMemoryGameStore` is the store for tests.
