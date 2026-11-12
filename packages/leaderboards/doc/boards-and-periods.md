# Boards and periods

## A board

A `Board` has a name — 1 to 64 lower-case letters, digits, `_`, `.` or `-` —, the `Measure` it ranks members on, the
`Schedule` of its periods, the verbs and context conditions of the activities it reads, how it orders a tie, how many
members a snapshot keeps (1 to 1000, 100 by default), and its options: a league, a sliding window, a ranking by object,
a title and the luck of teams.

| Measure | What it ranks | Read from |
|---|---|---|
| `count` | The number of gestures | The journal |
| `total` | The sum of their quantities — points earned, pages read, an order's amount | The journal |
| `best` | The best quantity of a single gesture — a high score | The journal |
| `days` | The number of distinct days with a gesture, in the board's timezone — the regularity of a member | The journal |
| `latest` | The last value the application reported — a level, a balance | The application |
| `highest` | The highest value the application reported — the longest streak | The application |

A board read from the journal names at least one verb, as patterns such as `game.played` or `game.*`, and may ask the
activity's context to hold facts, such as `ranked: true`; a board whose measure is reported names neither.

## Periods

| Period | Code | Starts |
|---|---|---|
| `day` | `2026-11-07` | At midnight in the board's timezone |
| `week` | `2026-w45` | On Monday at midnight, ISO weeks |
| `month` | `2026-11` | On the first at midnight |
| `quarter` | `2026-q4` | On the first of January, April, July or October |
| `year` | `2026` | On the first of January |
| `season` | The season's code | At the start the application gives |
| `ever` | `ever` | Never ends |
| `window` | `window` | Never ends: the gestures older than the window leave it |

Calendar periods follow each other without a gap, read in the board's timezone: a game played at 03:00 UTC on the
first of November counts for October in Toronto. Seasons are named and dated by the application, never overlap, and may
leave time between them; an activity between two seasons counts for none, and a board between two seasons has no
period under way.

`Schedule::at()` says which period a moment falls in, `named()` which one a code names — `2026-13` or `2026-w54` name
none —, and `previous()` which one ended last, for the snapshots.

## A sliding window

A board ranked over a `window` counts the gestures of its last days, today included — 1 to 366, `window: 7` for the
last seven days —, from midnight in the board's timezone: `Board::windowStart()` gives the first moment that still
counts. It has one period, `window`, and takes no snapshot. The gestures leave the window as days go, when
`LeaderboardProjection::slide()` runs — a scheduler calls it soon after midnight —; see
[Scores and the projection](scores-and-projection.md#sliding-windows).

*Active five days of the last seven*: `['measure' => 'days', 'verbs' => ['lesson.done'], 'period' => 'window', 'window'
=> 7]`.

## Ties

Members with the same score are ordered, in turn:

1. by the board's tie-breaks, up to two, each in its direction;
2. by `Ties::FirstReached` (`first`, the default): the member who reached the score first ranks first, and each member
   holds a rank of their own — members who reached it at the same moment are ordered by their reference; or by
   `Ties::Shared` (`shared`): members equal on the score and every tie-break share a rank, and the next one skips as
   many — 1, 2, 2, 4.

A `TieBreak` gathers an `Aggregate` of the member's gestures in the period — their `count`, the `total`, the `highest`
or the `lowest` of their quantities, or of an integer of their context it names —, and orders it by a `Direction`:
`desc`, the highest first (the default), or `asc`, the lowest first. On a board whose measure is reported, every
tie-break is `reported`: the application gives its values with the score, `Leaderboards::report()` taking one for each.

| Tie-break | Reads |
|---|---|
| `['measure' => 'count', 'order' => 'asc']` | The fewest attempts first, on a board of the best score |
| `['measure' => 'lowest', 'order' => 'asc', 'context' => 'seconds']` | The shortest time first, read from the context integer `seconds` |
| `['measure' => 'highest']` | The best single gesture first, on a board of the total |
| `['measure' => 'reported', 'order' => 'asc']` | A value the application reports, the lowest first |

A gesture without the integer a tie-break reads — 0 to 10^15 under its context name — does not count on the board.
`Ranking` holds the order of a board: `Board::$ranking`, which the stores read.

The moment a score is reached is the latest gesture that counted for a `count` or a `total`, the earliest gesture that
reached the best for a `best`, the first gesture of the latest day for `days`, and the moment of the report for a
reported measure.

## A ranking by object

A board with `perObject` (`per_object: true`) ranks members for each object of the activities — each game, each book,
each level —: an activity without an object does not count on it, and the standings, ranks, snapshots and teams of the
board name the object they are for. The scores are kept under the board's name followed by `@` and a digest of the
object (`Board::key()`), so the stores never hold the object's reference. A board ranked by object has no league, reads
the journal, and its snapshots are the application's to take, object by object; see [Standings](standings.md).

## Titles and luck

A `Title` — a name and a threshold of 1 to 10^15 — is held by a member while their score on the board in the period
under way reaches the threshold, and nobody ranks before them for a title of the `leader`: `regular` from five days of
the last seven, the local legend of a game. A `Chance` — 1 to 10 dice of 2 to 100
faces — adds luck to the score of each team on the board. See [Teams and titles](teams-and-titles.md).

## The boards of an application

`Boards::fromArray()` reads them as a configuration gives them, with the timezone of the boards that name none:

```php
Boards::fromArray([
    'games-month' => ['measure' => 'count', 'verbs' => ['game.played'], 'context' => ['ranked' => true], 'period' => 'month'],
    'high-score' => ['measure' => 'best', 'verbs' => ['arcade.scored'], 'period' => 'week', 'ties' => 'shared', 'snapshot_size' => 10,
        'tie_breaks' => [['measure' => 'count', 'order' => 'asc']]],
    'summer' => ['measure' => 'total', 'verbs' => ['quest.*'], 'period' => 'season', 'timezone' => 'Europe/Paris',
        'seasons' => [['code' => 'summer-2026', 'start' => '2026-06-21', 'end' => '2026-09-22']]],
    'level' => ['measure' => 'latest'],
    'regular' => ['measure' => 'days', 'verbs' => ['lesson.done'], 'period' => 'window', 'window' => 7,
        'title' => ['name' => 'regular', 'threshold' => 5]],
    'game-records' => ['measure' => 'best', 'verbs' => ['game.scored'], 'per_object' => true],
    'house-cup' => ['measure' => 'total', 'verbs' => ['quest.done'], 'period' => 'month', 'chance' => ['dice' => 2, 'faces' => 6]],
], 'America/Toronto');
```

A board ranks since ever unless it names its period. Two boards by one name, an unknown measure, period, tie,
tie-break or order, a timezone or a date that is none, more than two tie-breaks, a window on a board that is not ranked
over one — or none on one that is —, and a reported tie-break on a board read from the journal are refused with an
`InvalidLeaderboard`.
