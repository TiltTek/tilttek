# Exceptions

Every exception of the library implements `Exception\BingoException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidBingo` | `InvalidArgumentException` | A value is out of its bounds: an identifier, a label, a pool, a grid, a cell, a pattern, a serial, a check code, the size of a printed batch, a secret too short, a topic prefix |
| `BingoRefused` | `DomainException` | The rules refuse a move: a closed game, a call in a game ticked by activity, a claim on another player's card, a card that does not match its seed or a label that is not its own, printing in a game ticked by activity, an unknown game or card, no card unlike the others |
| `BingoConflict` | `RuntimeException` | A game or a card changed in the store since it was read, or its identifier, serial or player is taken |
