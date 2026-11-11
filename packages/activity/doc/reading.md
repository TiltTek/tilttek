# Reading and counting

## Positions

Every entry has a position, starting at 1, that only grows: an entry written later has a greater position, and a
position is never given twice, even when an erasure removed the last entries. `ActivityLog::lastPosition()` gives the
position of the last entry kept, 0 for an empty journal.

## Filters

A `Filter` selects activities; each method returns a new filter.

```php
use Tilttek\Activity\Filter;

$filter = Filter::all()
    ->member($member)                       // one member
    ->verbs('game.*', 'news.commented')     // any of these verbs
    ->object($game)                         // one object
    ->since($monday)                        // from this moment, included
    ->until($nextMonday);                   // to this moment, excluded
```

The period is read on the moment of the activity, `occurredAt`, not on the moment it was recorded. A verb pattern
names one verb (`game.played`), every verb under a prefix (`game.*` matches `game.played` and `game.won.flawless`,
not `game` nor `gamebook.read`), or every verb (`*`).

## Reading in order

```php
$entries = $journal->read(after: 0, limit: 500, filter: $filter);
```

`read()` returns the entries after a position whose activity matches the filter, in the order of their positions, at
most `limit` of them; read the next page after the last position returned. A cancellation matches when the activity
it cancels matches. The position is 0 or more and the limit at least 1, or `InvalidActivity` is raised.

## Totals

```php
$totals = $journal->totals(Filter::all()->member($member)->verbs('game.played')->since($monday));
$totals->count;     // how many activities
$totals->quantity;  // the sum of their quantities
```

Totals leave the cancelled activities out. They read the whole journal through the filter: a package that counts
often keeps its own totals in a projection instead.
