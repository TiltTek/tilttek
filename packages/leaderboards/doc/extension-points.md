# Extension points

| Point | What an application gives | Where |
|---|---|---|
| `Store\ScoreStore`, `Store\SnapshotStore`, `Store\AppearanceStore`, `Store\LeagueStore` | The storage of scores, snapshots, choices of appearance and leagues, in place of the stores in memory | [Writing a store](writing-a-store.md) |
| `Contract\ValkeyConnection` | The connection to Valkey of `ValkeyScoreStore`, when the client is none `RawCommandConnection` wraps | [Writing a store](writing-a-store.md#scores-in-valkey) |
| `LeaderboardObserver` | What is done when a snapshot is taken: rewarding the first members | [Snapshots](snapshots.md) |
| `League\LeagueObserver` | What is done when a week is settled: congratulating the members who went up | [Leagues](leagues.md) |
| `Psr\Clock\ClockInterface` | The clock the services read — the projection too, to leave out a gesture older than a window —: the system's in production, a fixed one in tests | [Testing](testing.md) |
| The secret of `Leaderboards` | The secret the luck of the teams is drawn from | [Teams and titles](teams-and-titles.md) |

The boards themselves are values: `Boards::fromArray()` reads them from a configuration, or the application builds them
in code. Every point is passed to the constructor of the service that uses it; the services keep no state of their own
between two calls.
