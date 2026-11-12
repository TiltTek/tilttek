# Reference

| Type | Role |
|---|---|
| `Leaderboards` | Standings, ranks, divisions, reported values, titles, teams, appearances, snapshots, export and erasure |
| `LeaderboardProjection` | The scores kept up to date from the journal, cancellations, rebuilds, sliding windows |
| `LeaderboardObserver` | Told of the snapshots taken |
| `Board`, `Boards` | A leaderboard; the boards of an application |
| `Measure`, `Ties` | What a board ranks on; how it orders equal scores after the tie-breaks |
| `TieBreak`, `Aggregate`, `Direction` | A measure that orders equal scores; what it gathers; which way it orders |
| `Ranking` | The order of a board's scores, their ranks, and the keys that sort in that order |
| `Title` | A title held from a threshold |
| `Team`, `TeamRow`, `Chance` | A team the application gives; its line in a ranking of teams; the luck a board adds to it |
| `Contribution`, `Score` | What an activity brought a member on a board; a member's score in a period |
| `Visibility`, `Appearance` | How a member appears to the others; their choice and pseudonym |
| `Circle` | The members a board is ranked among |
| `Row`, `Standing` | A line as one viewer sees it; a board in a period as one viewer sees it |
| `Placing`, `Snapshot` | A member's line in a snapshot; a board frozen at the end of a period |
| `Erasure` | What erasing a member removed |
| `Names` | The names of boards and periods |
| `Period\Period`, `Period\PeriodKind` | A period and its code; the kinds of periods |
| `Period\Schedule` | The periods of a board: at a moment, by code, the last one ended |
| `Store\ScoreStore`, `Store\InMemoryScoreStore` | Where the contributions and scores are kept |
| `Store\Valkey\ValkeyScoreStore` | The contributions and scores in the sorted sets of Valkey |
| `Contract\ValkeyConnection`, `Store\Valkey\RawCommandConnection` | A connection to Valkey; one through the redis extension, Relay or Predis |
| `Store\SnapshotStore`, `Store\InMemorySnapshotStore` | Where the snapshots are kept |
| `Store\AppearanceStore`, `Store\InMemoryAppearanceStore` | Where the members' choices are kept |
| `League\League`, `League\Formation` | The tiers of a weekly board, the size of a division, how many go up and down; how its divisions are formed |
| `League\Leagues` | Divisions entered, weeks settled, tiers, export and erasure |
| `League\Membership`, `League\Movement` | A member's division in a week; where they went at its end |
| `League\Division` | A member's division as they see it, with its zones |
| `League\Settlement`, `League\LeagueObserver` | A week settled; told of each week settled |
| `Store\LeagueStore`, `Store\InMemoryLeagueStore` | Where the tiers, memberships and settled weeks are kept |
| `Exception\LeaderboardsException` and its four exceptions | [Exceptions](exceptions.md) |
