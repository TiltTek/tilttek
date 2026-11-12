# Changelog

All notable changes to `tilttek/leaderboards` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `Period`, `PeriodKind` and `Schedule`: the periods of a board — a calendar day (`2026-11-07`), an ISO week from Monday
  (`2026-w45`), a calendar month (`2026-11`), quarter (`2026-q4`) or year (`2026`) in the board's timezone, named and
  dated seasons that never overlap and may leave time between them, ever, or a sliding window —; which period a moment
  falls in, which one a code names, and which one ended last.
- `Measure`, `Ties`, `Board` and `Boards`: boards that count gestures, add up their quantities, keep the best one or
  count the distinct days with a gesture from the journal, for verbs by pattern and context conditions, or keep the
  latest or the highest value the application reports; equal scores to the first who reached them or a shared rank;
  snapshots of 1 to 1000 members; a sliding window of 1 to 366 days; a ranking for each object of the activities, kept
  under a digest of the object; the boards of an application from plain values, as a configuration gives them, a
  weekly board with its league.
- `TieBreak`, `Aggregate`, `Direction` and `Ranking`: up to two tie-breaks per board — the count, the total, the
  highest or the lowest of the gestures' quantities or of a context integer, or a value reported with the score —, each
  ascending or descending, before the first to reach a score or a shared rank; the keys that sort a board's scores in
  its order.
- `Title`, `Team`, `TeamRow` and `Chance`: a title held while a score reaches its threshold, by the leader alone when
  it says so; teams the application
  gives, ranked by their members' scores added up, with 1 to 10 dice of 2 to 100 faces of luck a board enables, drawn
  from the application's secret.
- `Contribution` and `Score`: what one activity brought a member on a board in a period and to each tie-break, kept to
  be taken back, and the score it makes — 0 to 10^15 —, with its tie-breaks and the moment it was reached.
- `Visibility`, `Appearance`, `Circle`, `Row` and `Standing`: every member hidden until they choose to appear by name
  or under a pseudonym of 1 to 40 characters; a board as one viewer sees it — the first lines, the viewer's own line
  even below them, how many are ranked —, among everyone or among the 1 to 1000 members of a circle.
- `Placing`, `Snapshot` and `LeaderboardObserver`: a board frozen at the end of a period with its first members, how
  many were ranked and when, taken once and told once, the snapshots due for the period each board ended last, and a
  snapshot shown to a viewer by each member's current choice.
- `League`, `Formation`, `Movement`, `Membership`, `Division`, `Settlement`, `LeagueObserver` and `Leagues`: the league
  of a weekly board — 1 to 50 tiers, divisions of 2 to 100 members, how many go up and down, divisions formed by arrival
  or by volume —; a member entering a division of their tier the first time they score in a week, a new member at the
  lowest tier, divisions filled in the order of arrival, or among the members of their cohort by their score of the week
  before; a member's division ranked among its members only, by each one's choice of appearance, with its zones; the
  weeks settled once and told once — the first up, the last of a full division down, nobody above the highest tier nor
  below the lowest, a member absent all week left where they are —, the weeks still open settled before a member enters
  the next; tiers, export and erasure.
- `ScoreStore`, `SnapshotStore`, `AppearanceStore` and `LeagueStore`, with `InMemoryScoreStore`,
  `InMemorySnapshotStore`, `InMemoryAppearanceStore` and `InMemoryLeagueStore`: an activity contributing once per board,
  the contributions of a board older than a moment taken out, scores read in the order of a ranking among everyone or a
  circle, how many rank ahead of a score, a snapshot kept once per board
  and period, the choices of the members who appear, a member placed once a week and a week settled once. The
  implementations in memory empty themselves with `reset()`, for a long-running process.
- `ValkeyScoreStore`, `ValkeyConnection` and `RawCommandConnection`: the contributions and the scores in the sorted
  sets of Valkey, each ranking read by its index, each write a Lua script, every key under a hash tag; a connection
  through the redis extension, Relay or Predis.
- `LeaderboardProjection`: the scores kept up to date from the journal of `tilttek/activity`, a gesture older than a
  sliding window left out of it and the windows slid, an activity handed twice counted once, a cancellation that counts
  the scores it touched again, and a rebuild that forgets the contributions of a period and replays it under the new
  boards, the reported boards left as they are, and a member entering a division when they score on a board with a
  league.
- `Leaderboards`: the current or a named period of a board, standings of 1 to 100 lines, a member's rank, for an object
  on a board ranked by object, the values the application reports with their tie-breaks — the latest ignoring an older
  report, the highest ignoring one that ranks no better —, a member's division, the titles a member holds and whether
  they hold a board's, a ranking of teams, each member's choice of appearance, the export and the erasure of a member.
- `Erasure`: what erasing a member removed — contributions and scores, lines in the snapshots, choice of appearance,
  tiers and memberships in the leagues.
- `LeaderboardsException`, `InvalidLeaderboard`, `UnknownBoard`, `UnknownPeriod` and `PeriodNotOver`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
