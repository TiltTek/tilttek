# Exceptions

Every exception of the library implements `Tilttek\TavernGames\Exception\TavernGamesException`. The exceptions of
`tilttek/game-engine` — `IllegalMove`, `ConcurrentMove`, `InvalidGame`, `UnknownTable` — pass through unchanged.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidTavernGame` | A value is refused: an unknown game, two games of one name, a roll of no die or of more than 10, an estimate of no sample or of more than 100 000 | `No tavern game is named "chess".` |

A move a game refuses is an `IllegalMove` of the engine, with the message of the refusal:
`An eight names the suit to follow: {"suit": "S", "H", "D" or "C"}.` Data a game cannot read back — a cache that gives
back anything else — is an `InvalidGame`: `The gestures of Crazy Eights are a list.`
