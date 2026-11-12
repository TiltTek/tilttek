# Erasure

## The export

`Leaderboards::export($member)` returns what the boards hold about a member:

```php
[
    'appearance' => ['visibility' => 'pseudonym', 'pseudonym' => 'Le Nain Gris'],
    'scores' => [['board' => 'games-month', 'period' => '2026-11', 'value' => 7, 'reached_at' => '2026-11-03T10:00:00+00:00']],
    'placings' => [['board' => 'games-month', 'period' => '2026-10', 'rank' => 2, 'value' => 7, 'reached_at' => '2026-10-02T10:00:00+00:00']],
    'leagues' => [
        'tiers' => ['weekly-xp' => 'silver'],
        'memberships' => [['board' => 'weekly-xp', 'week' => '2026-w45', 'tier' => 'bronze', 'division' => 3, 'joined_at' => '2026-11-03T10:00:00+00:00', 'movement' => 'promoted']],
    ],
]
```

## The erasure

`Leaderboards::erase($member)` removes the member's contributions and scores, their lines in every snapshot, their
choice of appearance, and their tiers and memberships in the leagues, and returns an `Erasure` counting them. The other
members keep their ranks in the snapshots and their divisions; the current standings move up, since the member no
longer holds a score.

`leagues` is empty when `Leaderboards` is given no `Leagues`.

The journal of activity is erased by `tilttek/activity`: a rebuild after an erasure finds nothing of the member.

Two things stay with the application. The snapshots and settlements already handed to an observer
(`snapshotTaken`, `leagueSettled`) keep the member's reference wherever the application stored them. And a projection
or a `settlePeriod()` running while the member is erased can write a score, a tier or a membership back: erase once the
member's activity is erased and no settlement is running, or erase again after it.
