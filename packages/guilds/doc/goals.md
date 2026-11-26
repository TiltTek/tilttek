# The group track

A guild works towards common goals on its group track: a goal is a target of points its members' contributions add up
to, before a deadline or not. `Goals` runs them on a `Contract\GoalStore`, and asks `Guilds` who belongs to the guild.

Points are the application's counters — games played, pages read, hours painted —, never money.

## Opening a goal

```php
$goals->open(
    'lanterns-december',                         // the key the application gives
    $guild,
    'Play 200 games in December',
    200,
    $founder,                                    // the founder or an administrator
    new DateTimeImmutable('2027-01-01 00:00:00', new DateTimeZone('America/Toronto')), // or none
);
```

| Value | Bounds |
|---|---|
| Key | 1 to 100 ASCII letters, digits, `_`, `.`, `:` or `-`, unique across guilds |
| Name | 1 to 100 characters of UTF-8, no control character, no space around |
| Target | 1 to 1,000,000,000 points |
| Deadline | after the opening, or none |

A key already used is refused with `Refusal::GoalExists`; a guild holding as many open goals as its limit allows —
ten by default — refuses another with `Refusal::TooManyGoals`. A goal reached, closed or past its deadline no longer
counts towards that limit.

## Contributing

```php
$progress = $goals->contribute('lanterns-december', $member, 3, 'game:9812');
```

Only a member of the guild contributes, `Refusal::NotMember` otherwise; a contribution brings 1 to 1,000,000,000
points. Under a key — 1 to 100 ASCII letters, digits, `_`, `.`, `:` or `-` — a contribution made again counts once and
returns the goal's progress, even once the goal is over: a game recorded twice brings its points once. Without a key,
each call counts.

The contribution that meets or passes the target reaches the goal: its `reachedAt` is set, the observers are told a
`GoalReached` — the goal, the guild, the points gathered, the moment —, and the goal takes no more contributions. A
goal also stops taking them at its deadline, to the second, or once its founder or an administrator calls
`close($goal, $by)`. A contribution to a goal that is over is refused with `Refusal::GoalOver`.

A member who leaves the guild keeps their points in its total; they no longer contribute.

## Progress

`progress($goal)` returns a `GoalProgress`:

| Member | Holds |
|---|---|
| `goal` | The `Goal`, with its `reachedAt` and `closedAt` |
| `points` | The points gathered, which may pass the target |
| `contributors` | How many members brought points, erased members counted as one together |
| `percent()` | The share of the target, 0 to 100, rounded down |
| `remaining()` | The points still missing, 0 once the target is met |
| `isReached()` | Whether the target was met |

`contributed($goal, $member)` is the member's own share, for them to read. The track ranks nobody inside the guild:
there is no reading of who brought the most.

## Group track or quest

The group track is the guild's own goal, fed by points the application gives. A goal read on the members' recorded
activity — *play three games*, with steps, chains and rewards — is a team quest of `tilttek/quests`, which a guild joins
as a team: see [quests and leaderboards](quests-and-leaderboards.md).

## Erasure

`erase($member)` forgets the member on every contribution they made; the points stay with the guild, so a goal reached
stays reached.
