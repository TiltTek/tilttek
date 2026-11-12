# Exceptions

Every exception of the library implements `Tilttek\Leaderboards\Exception\LeaderboardsException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidLeaderboard` | A name, a board, a period, a season, a schedule, a score, a pseudonym, a circle, a limit, a timezone, a date, a league or a division number is refused, a value is reported to a board that reads the journal, or a division is asked of a board without a league | `A snapshot keeps 1 to 1000 members; the board "games" asks for 0.` |
| `UnknownBoard` | A board is asked for by a name none has | `No board is named "games".` |
| `UnknownPeriod` | A period is asked for by a code the board's schedule does not know, or a board between two seasons has no period under way | `The board "games" has no period coded "2026-13".` |
| `PeriodNotOver` | A snapshot is asked for a period that has not ended, or for `ever`, or a week of a league is settled before it ends | `The period "2026-11" of the board "games" is not over: it cannot be frozen yet.` |

The verb patterns and the context conditions of a board are checked by `tilttek/activity`, which raises its own
`InvalidActivity`.
