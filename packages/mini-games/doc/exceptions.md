# Exceptions

Every exception of the library implements `Tilttek\MiniGames\Exception\MiniGameException`. The exceptions of
`tilttek/game-engine` — `IllegalMove`, `ConcurrentMove`, `InvalidGame`, `UnknownTable`, `GameInProgress` — pass
through unchanged.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidMiniGame` | A value is refused: a day, a secret too short, a control, a grid or its symbols, a code of Bulls and Cows, an unknown game, two games of one name, a history of several players | `No mini-game is named "chess".` |
| `SeedNotRevealable` | The log of a daily game, seed included, is asked for while its day is not over | `The seed of the daily game "bulls-and-cows" of 2026-11-24 is shown once the day is over.` |

A guess an example game refuses — `{guess: "0447"}` — is an `IllegalMove` of the engine, with the message of the
refusal: `A guess is 4 different digits.`
