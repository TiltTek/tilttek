# Exceptions

Every exception of the library implements `Tilttek\Rating\Exception\RatingException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidRating` | An identifier, a result, a setting of a system, a limit of the standings or stored values are refused, or a source gives a result of another game, out of order or twice | `A result has 2 to 64 sides.` |
| `RatingRefused` | The ratings of a game were computed by another system, a rating of another system is asked whether it is provisional, or a replay has no source of results | `The ratings of the game chess were computed by the system elo; replay the game to rate it with the system openskill-pl.` |
| `ResultAlreadyRecorded` | A result its game counts already, its events all delivered, is recorded again: a double click, or a message delivered twice | `The result play-81 of the game chess is recorded already.` |
| `RatingConflict` | A rating changed or was added since it was read: another result of the same members was recorded at once | `The rating of the member member-1042 for the game chess changed since it was read.` |

`InvalidRating` extends `InvalidArgumentException`, `RatingRefused` and `ResultAlreadyRecorded` extend
`DomainException`, and `RatingConflict` extends `RuntimeException`.
