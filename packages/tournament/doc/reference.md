# Reference

Every public type of the package, with its role. The types marked internal may change in any version.

## Tournaments

| Type | Role |
|---|---|
| `Tournaments` | The service: starts tournaments, prepares and publishes rounds, takes, settles and corrects results, takes withdrawals and reschedules rounds; gives the standings, the bracket, a participant's schedule and the explanation of a pairing. |
| `Tournament` | A tournament as it is declared: participants, stages, scoring, tie-breakers and the published seed. |
| `TournamentState` | A tournament as the store keeps it: its declaration, the first order, the rounds, the results, the conflicts, the withdrawals, the podium and the version. |
| `Stage` | A stage: Swiss, round robin or single elimination, with its rounds, pools, legs and qualifiers. |
| `StageFormat` | The format of a stage. |
| `Scoring` | The points of a win, a draw, a loss, a bye and the forfeits, in whole numbers. |
| `Round` | A round of a stage: its number, its tables and the date it starts. |
| `Pairing` | A table: the participant who plays first, the one who plays second or none for a bye or a rest, the pool and the reasons. |
| `PairingReason` | One reason of a pairing: its kind, the participant, the other participant and the round it refers to. |
| `ReasonKind` | The kinds of reasons, as stable codes. |
| `ScheduledGame` | A round in a participant's schedule. |
| `Explanation` | Why two participants meet in a round, or do not. |
| `ExplanationKind` | The kinds of explanations, as stable codes. |
| `SeededDraw` | The order the published seed gives participants. |
| `Identifier` | Internal: the shape of every identifier. |
| `Progress` | Internal: what a tournament's state gives, for one call. |

## Results

| Type | Role |
|---|---|
| `Result` | The result of a match: its outcome and mission points. |
| `Outcome` | How a match ended, from the side of the first player. |
| `Submission` | A result as one person entered it. |
| `Recording` | What a result entered, settled or corrected, or a withdrawal, did: its status, the changes of the standings, the events. |
| `RecordingStatus` | Recorded, unchanged or in conflict. |
| `Records` | The records of the participants of a stage. |
| `Record` | What one participant did in a stage. |
| `Game` | One match of a participant, as the standings read it. |

## Pairings

| Type | Role |
|---|---|
| `Swiss\SwissPairer` | Pairs a Swiss round by score groups. |
| `Contract\Matcher` | Internal: a matching of the lowest cost found. |
| `Swiss\ExactMatcher` | Internal: the matching of the lowest cost, for small groups. |
| `Swiss\BoundedMatcher` | Internal: a matching of a low cost, in bounded time, for larger groups. |
| `RoundRobin\BergerTable` | The rounds of a pool by the Berger tables. |
| `RoundRobin\Pools` | Participants spread over pools in a snake. |
| `Elimination\Bracket` | A single-elimination bracket by seeds. |
| `Elimination\BracketMatch` | A match of a bracket. |
| `Elimination\BracketState` | A bracket filled with what is known: champion, podium, the tables of a round, the round each participant reached. |
| `Elimination\MatchState` | Where a match of a bracket stands. |
| `Elimination\SeedOrder` | The places of the seeds in a bracket. |
| `Elimination\Slot` | A place of a bracket: a participant, a qualifier, the winner of a match, or a bye. |
| `Elimination\SlotKind` | What fills a place. |

## Standings and tie-breakers

| Type | Role |
|---|---|
| `Standings\Standings` | The standings of a stage or a pool, and what moved since others. |
| `Standings\Standing` | A participant's place: rank, points, tie-breaker scores, the draw, the withdrawal. |
| `Standings\StandingChange` | How a participant's rank and points moved. |
| `Standings\StandingsCalculator` | Ranks by points, then by the tie-breakers in order, then by the draw. |
| `Contract\Tiebreaker` | A tie-breaker. |
| `Tiebreak\Tiebreakers` | The tie-breakers a tournament may declare, by code. |
| `Tiebreak\Buchholz` | The Buchholz score, cut or not. |
| `Tiebreak\SonnebornBerger` | The Sonneborn-Berger score. |
| `Tiebreak\HeadToHead` | The points scored between the participants still tied. |
| `Tiebreak\Wins` | The matches won over the board. |
| `Tiebreak\MissionPoints` | The mission points scored. |

## Seeding, storage and events

| Type | Role |
|---|---|
| `Contract\SeedingSource` | Where the strengths of participants come from. |
| `Seeding\FixedSeeding` | The strengths given, or a list in order. |
| `Seeding\RatingSeeding` | The strengths of the ratings of `tilttek/rating` for one game. |
| `Seeding\InitialOrder` | The first order: by strength, then by the seed. |
| `Contract\TournamentStore` | Where tournaments are kept, at a version. |
| `Store\InMemoryTournamentStore` | The tournaments in memory, for tests. |
| `Event\TournamentFinished` | The tournament has its winner and its podium. |

## Exceptions

| Type | Role |
|---|---|
| `Exception\TournamentException` | Every exception of the library. |
| `Exception\InvalidTournament` | A value out of its range or shape, or unknown. |
| `Exception\InvalidResult` | A result that does not fit its table. |
| `Exception\RoundRefused` | A round asked for too early or too late. |
| `Exception\TournamentConflict` | The tournament changed since it was read. |
| `Exception\TournamentNotFound` | No tournament has the identifier. |
