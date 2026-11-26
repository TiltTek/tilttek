# Exceptions

Every exception of the library implements `Tilttek\CardDeck\Exception\CardDeckException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidDefinition` | An identifier, a card, a deck, a gesture or a published game is refused when it is made or read: a name out of its pattern, a deck out of its bounds, data that does not decode, a client seed too long | `A deck holds 1 to 1024 cards.` |
| `IllegalGesture` | The state of the game refuses a gesture: a zone empty or not open, a card the gesture cannot reach, a game not started or finished, too many zones or gestures; the state is left as it was | `The zone "draw" is empty.` |
| `UnknownGame` | The store holds no game of that identifier | `The store holds no game "g-7f3a".` |
| `ConcurrentChange` | The game changed since the version the change was made on | `The game "g-7f3a" is at version 9, not 8.` |
