# Database

| Table | Key | Holds |
|---|---|---|
| `tilttek_leaderboards_contribution` | board, activity | What each activity brought to each board and to its tie-breaks, in which period, for which member — the key is what makes an activity count once |
| `tilttek_leaderboards_score` | board, period, member | Each member's score and tie-breaks on each board and period, and when it was reached; indexed on board, period, score, tie-breaks and moment, the rank order |
| `tilttek_leaderboards_snapshot` | board, period | The boards frozen at the end of a period: when, and how many members were ranked |
| `tilttek_leaderboards_placing` | board, period, member | The members' lines in each snapshot: rank, score, moment |
| `tilttek_leaderboards_appearance` | member | How each member who chose to appear does: `named` or `pseudonym`, with the pseudonym |
| `tilttek_leaderboards_league_tier` | board, member | The tier each member reached on each board with a league |
| `tilttek_leaderboards_league_membership` | board, week, member | Each member's tier, division and cohort in each week they took part in, when they joined, and where they went at its end; indexed on board, week, tier and division — the key places a member once a week |
| `tilttek_leaderboards_league_week` | board, week | The weeks settled, and when — the key settles a week once |
| `tilttek_leaderboards_lead` | board, period, member | The leads taken as scores changed, with `celebrate_on: score_change`, and when; indexed on member — the key tells a member once per board and period that they took the lead |

A member's reference is 320 bytes at most — a kind of 64, a colon and an identifier of 255 —, a period's code 64, and
the key of a board 96 in the tables of contributions, scores, snapshots, placings and leads: its name of 64, or — on a board
ranked by object — its name, `@` and a digest of 24. The tie-breaks are two columns, `tie_break_1` and `tie_break_2`,
and `tie_breaks` says how many a row holds. The moments — `occurred_at`, `reached_at`, `taken_at`, `joined_at`,
`settled_at` — are columns of the type `datetime_utc_immutable`: written in UTC, to the second, and read back in UTC,
whatever the zone of the moment given and the default zone of PHP. The column `placing` is quoted in every statement, a
reserved word of PostgreSQL.

With `valkey` enabled, the contributions and the scores are kept in Valkey instead of the first two tables, in the
sorted sets the library describes; the other tables stay. The commands sent are common ones — `EVAL` of short scripts
that call `HSET`, `HSETNX`, `HGET`, `HDEL`, `SADD`, `SREM`, `ZADD` and `ZREM`, and `HGET`, `HMGET`, `SMEMBERS`,
`ZRANGE`, `ZLEXCOUNT` and `ZCARD` —, through the client's `rawCommand()` or Predis's `executeRaw()`, so that the redis
extension, Relay, Predis and `ValkeyGlide` of valkey-glide-php serve them alike. Nothing asks the server to persist:
no `SAVE`, `BGSAVE` or `CONFIG SET` is sent, and the server's own persistence (RDB snapshots, AOF) applies as
configured, or not at all on a managed server that forbids it or in tests. A server that loses its data loses the
scores, never the snapshots, choices, leagues and leads kept in `store`; `tilttek:activity:rebuild leaderboards` builds again
those the journal measures, while the values an application reports are reported again by it.

A contribution, a snapshot, a membership and a settled week are inserted by a statement that writes nothing when the row
is there — `ON CONFLICT DO NOTHING` on PostgreSQL, `INSERT IGNORE` on MySQL and MariaDB, `INSERT OR IGNORE` on SQLite —,
so that two processes handing the same activity count it once without aborting their transactions. A score is replaced —
deleted and inserted — in one transaction; a reported score is inserted the same way when the member holds none, or
else written by an `UPDATE` whose condition is the comparison — `reached_at <= ?` and other values on `latest`, a better
value or tie-break on `highest` —, so that of two reports at the same moment an older or lower one never replaces the
other; moments are kept to the second, and two `latest` reports within one second count as reached together; a snapshot's lines are inserted in the transaction of the snapshot, and a
member's choice is replaced the same way. A week is settled in one transaction that begins by inserting its row: a
second settlement inserts nothing and writes nothing else. Two members joining a division at the same moment may share
its last place, which then holds one more: a division holds *about* its size.

These statements run on SQLite, MySQL 8.4 and PostgreSQL 17 in the bundle's tests; MariaDB shares MySQL's path.
