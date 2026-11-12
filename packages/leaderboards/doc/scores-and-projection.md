# Scores and the projection

## Kept up to date, not counted at each display

`LeaderboardProjection` is a projection of `tilttek/activity`: the journal hands it each recorded activity whose verb a
board reads. For each board the activity matches — its verb, its context conditions — and whose schedule has a period
at the activity's moment, it writes a `Contribution` — one for a `count` or `days`, the quantity for a `total` or a
`best`, and what the activity brings to each tie-break — and updates the member's `Score` in that period. A gesture
recorded late, on an earlier day than the score's on a board that counts days, has the score counted again from every
contribution. A standing then reads the scores in rank order from the store, without
reading the journal.

An activity contributes once per board: the store refuses a second contribution of the same activity to the same board,
so an entry handed twice after a crash counts once.

The contribution is added in one step, but the score is then read and written back: two activities of the same member
on the same board, projected at the same moment by two processes, can leave the score without one of them, and a value
reported by `report()` can be overwritten by an older one. Project from a single consumer — the projection run by
Messenger or by a command rather than live in each request — or hold a lock (`symfony/lock`) per member, board and
period around the projection; a rebuild counts every score again from the contributions.

The projection is named `leaderboards` unless the application names it otherwise — two sets of boards need two names.

## Sliding windows

Given a clock, the projection leaves a gesture older than a window out of it. `slide()` takes out of each board ranked
over a window the contributions older than its first day at the moment given, counts again the scores they had made —
a score left without a contribution is deleted —, and returns how many contributions left:

```php
$projection->slide($clock->now()); // soon after midnight, from a scheduler
```

## Cancellations

A cancelled activity — an order refunded, a game annulled — takes back every contribution it made, and the scores they
made are counted again from the contributions left: a total goes down, a best falls back to the next best, and a score
left without a contribution is deleted.

## Rebuilds

When a board changes, `Projector::rebuild($projection, $since)` forgets the contributions of the activities from
`since` on — all of them without a date —, counts again the scores they touched, and replays the journal under the new
boards. A board no longer declared loses the scores the rebuild took back. The scores of a reported measure are not
touched: they were never read from the journal.

## Values the application reports

A level, a points balance or the longest streak lives in another package or in the application. A board whose measure
is `latest` or `highest` takes them by `Leaderboards::report()`, at the moment they hold — now by default — and in the
period that moment falls in:

```php
$leaderboards->report('level', $member, 12);
```

A `latest` board keeps the last value reported, ignoring a report older than the one it holds or the same; a `highest`
board keeps the highest, ignoring a value that ranks no better, its tie-breaks included. `report()` says whether the
score changed. A listener on the event of the package that keeps the balance reports it: tilttek/leaderboards depends
on no other gamification package. A reported value is not replayed by a rebuild; the application reports it again when
it must.

A board with reported tie-breaks takes their values with the score, one for each:

```php
$leaderboards->report('level', $member, 12, tieBreaks: [340]);
```
