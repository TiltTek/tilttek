# Snapshots

## Freezing a period

```php
$snapshot = $leaderboards->snapshot('games-month', '2026-10');
```

`snapshot()` freezes a board at the end of a period: its first members — as many as the board keeps —, their ranks,
scores and the moments they reached them, how many members were ranked, and when it was taken. A period that has not
ended raises `PeriodNotOver`, and so does `ever`, which never ends. A snapshot is taken once per board and period:
asked again, the one taken is returned, and the observer is not told again.

`snapshotDue()` takes, for each board, the snapshot of the period it ended last — yesterday, last week, last month, the
last season over — when it has none yet, and returns those it took. A scheduler runs it soon after midnight; an activity
recorded late for a period already frozen changes its scores, not its snapshot.

## Rewarding the first

The `LeaderboardObserver` is told `snapshotTaken()` once per snapshot. A listener rewards the first members through the
packages the application has — points, a badge, a title —:

```php
final class RewardTheFirst implements LeaderboardObserver
{
    public function snapshotTaken(Snapshot $snapshot): void
    {
        foreach (array_slice($snapshot->placings, 0, 3) as $placing) {
            // grant something to $placing->member for $placing->rank
        }
    }
}
```

Rewarding a hidden member is the application's call: the member's choice is about being shown, not about being ranked.

## Showing the final standing

`frozen()` returns the snapshot as a `Standing` seen by a viewer, by each member's choice now: its first `limit` lines
(1 to 1000, 10 by default), the viewer's own line when they are in it, how many were ranked, and when it was frozen
(`Standing::frozenAt`). It returns null when no snapshot was taken.

Erasing a member removes their lines from every snapshot; the others keep their ranks.
