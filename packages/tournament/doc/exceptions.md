# Exceptions

Every exception of the library implements `Tilttek\Tournament\Exception\TournamentException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidTournament` | A value is out of its range or shape: an identifier, a seed, the participants, a stage, the scoring, the tie-breakers, a round or a table; or a stage, a pool, a round or a participant is unknown | `A Swiss stage has 1 to 64 rounds.` |
| `InvalidResult` | A result does not fit its table: a table unknown, a bye, a draw in an elimination, mission points out of range, no conflict to settle, no result to correct | `A bye has no result to enter.` |
| `RoundRefused` | A round is asked for too early or too late, or a correction would leave a bracket wrong | `The round 2 of the stage swiss has results missing or conflicts to settle.` |
| `TournamentConflict` | The tournament changed, or was added, since it was read: nothing is kept | `The tournament cup changed since it was read.` |
| `TournamentNotFound` | No tournament has the identifier in the store | `No tournament has the identifier cup.` |
