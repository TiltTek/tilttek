# Exceptions

Every exception of the library implements `Tilttek\GameEngine\Exception\GameEngineException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidGame` | A definition, a phase, a transition, an option, a preference, a statistic, the options or seats of a table, the preferences of a seat, a move's type or payload, a seed, a log or a state read back is refused, a log cannot be replayed or its seed is not the one committed to, or an outcome reports a statistic the game does not declare | `The move 4 of the table "table:42" draws differently when it is replayed: the game is not pure, or its rules changed.` |
| `IllegalMove` | A move is refused in the table's current state: the game is over, the seat is not at the table or not in its turn, the game refuses it, a transition does not leave the phase, or an undo is not allowed | `It is not the turn of the seat "seat:bob".` |
| `GameOver` | A move is taken back at a table whose game is over, since its seed may have been shown | `The game at the table "table:42" is over: its seed may be shown, so no move is taken back.` |
| `GameInProgress` | The log of a table, seed included, is asked to be shown while its game goes on | `The game at the table "table:42" is not over: its log, which holds the seed, stays secret until it is.` |
| `ConcurrentMove` | A move or an undo is made on a log that changed since it was read — another number of moves, or the same number at another revision —, a log changed during each of its reads, or the log of a finished table read is not the one of its finished state | `The log of the table "table:42" holds 5 moves, not the 4 expected: another move came first.` |
| `UnknownTable` | A table or a game is asked for by an identifier or a name none has | `No table has the identifier "table:9".` |
