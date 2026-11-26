# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Card` | A card: its identifier, 1 to 16 faces and a back |
| `CardId`, `PlayerId`, `GameId` | Opaque identifiers of a card, a player and a game |
| `Face` | What one side of a card shows, as a key |
| `Deck` | 1 to 1 024 cards in their declared order, written to an array and read back |
| `StandardDeck` | The 52 cards of the French-suited deck and its jokers |
| `ZoneId`, `ZoneKind` | A zone — draw pile, discard pile, table, hand, named pile — and its kind |
| `Gesture\Gesture` | A gesture of the journal, written to an array |
| `Gesture\Shuffle`, `Draw`, `Deal`, `Discard`, `Flip`, `TurnFace`, `Move`, `Collect` | The gestures |
| `Gesture\Pick`, `Gesture\End` | Where a card is drawn from, and where it lands |
| `Gesture\GestureCodec` | Reads back the gestures written to arrays |
| `DeckState` | The whole state of a game: start, apply, replay, fingerprint, and the zones as a player sees them |
| `Shuffler` | The Fisher-Yates shuffle on a randomizer |
| `View\PlayerView`, `View\ZoneView`, `View\SeenCard` | What a player may see of a game, zone by zone, card by card |
| `CardTable` | Opens, starts, plays, shows and finishes the games of a store |
| `GameRecord` | What a store keeps of a game, with its version |
| `Reveal` | A finished game, published for anyone to verify |
| `Contract\GameStore` | The store port |
| `Store\InMemoryGameStore` | The store in memory, for tests |
| `Exception\CardDeckException` and its four exceptions | [Exceptions](exceptions.md) |
