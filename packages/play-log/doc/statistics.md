# Statistics

`PlayStatistics` computes the statistics of a member or of a group when asked, from the plays the store gives, and
keeps nothing:

```php
$statistics = new PlayStatistics($store);

$member = $statistics->ofMember('member-1');
$season = $statistics->ofGroup('thursday-club', new DateTimeImmutable('2027-01-01'), new DateTimeImmutable('2027-07-01'));
```

`ofMember()` reads the plays where the member holds a confirmed seat, and `ofGroup()` the plays of the group. Both
take a period, from its start, included, to its end, excluded, either open; a period leaves out the plays whose date
is unknown.

## What a result holds

| `Statistics` | What it holds |
|---|---|
| `plays` | The plays read. |
| `wins` | The plays the member won; null for a group. |
| `unfinished` | The plays left unfinished. |
| `minutes`, `timedPlays` | The minutes of the plays whose length is known, and how many they are; `averageMinutes()` gives the average in whole minutes, or null. |
| `games` | A `GameStatistics` for every game played, the most played first, then by identifier; `game()` finds one. |
| `players` | A `PlayerStatistics` for each member played with — for a member, the others who held a confirmed seat beside them; for a group, its members — with the plays and the wins, at most 50, the most plays first, then by identifier. |
| `hIndex` | The most games played at least as many times as their number: 3 when three games were played three times or more, but not four games four times. |
| `truncated` | Whether more plays matched than were read. |

| `GameStatistics` | What it holds |
|---|---|
| `game` | The identifier of the game in the catalogue. |
| `plays` | Its plays. |
| `wins` | The plays of it the member won; null for a group. |
| `minutes` | The minutes of its plays whose length is known. |
| `lastPlayed` | The latest date of a play of it, or null when no date is known. |
| `highestScore` | The highest score of the member's seats, or, for a group, of every confirmed seat; null when none has a score. |

Only confirmed seats count: a seat waiting for its member, or anonymous, adds to the plays and the minutes of the
group, never to a member's. A win follows the [result](plays.md#seats) of the play.

## Bounds

At most 10 000 plays are read for one result, the latest first: a member or a group with more gets the statistics of
their latest 10 000, and `truncated` says so; a period reads an earlier season. The players a result names are at most
50. The work grows with the plays read, never with the plays the store holds: a store answers a query by an index on
the member, the group and the date.
