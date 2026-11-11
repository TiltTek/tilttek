# Projections

A projection is a reader of the journal that keeps its own state — the points of a member, the progress of a badge,
the games played this season — so that the journal is read in full only when a rule changes.

## Writing one

```php
use Tilttek\Activity\Entry;
use Tilttek\Activity\Filter;
use Tilttek\Activity\Projection\Projection;

final class GamesPlayed implements Projection
{
    public function name(): string { return 'games_played'; }

    public function filter(): Filter { return Filter::all()->verbs('game.played'); }

    public function apply(Entry $entry): void
    {
        $this->store->add($entry->activity->member, $entry->isCancellation() ? -1 : 1, $entry->position);
    }

    public function reset(?DateTimeImmutable $since): void
    {
        $this->store->forgetFrom($since); // everything when $since is null
    }
}
```

- **`name()`** is the name its checkpoint is kept under: 1 to 64 lower-case letters, digits, `_`, `.` or `-`,
  starting with a letter. Two projections cannot share a name.
- **`filter()`** says which activities it reads; the others are not handed to it.
- **`apply()`** receives a recorded activity, or the cancellation of one, to undo. A crash between applying and saving
  the checkpoint hands the same entries again: keep the positions applied, or make `apply()` tolerate a repeat.
- **`reset()`** forgets what was derived from the activities from `since` on, before they are handed again.

## The projector

```php
use Tilttek\Activity\Projection\Projector;

$projector = new Projector($log, $checkpoints, batchSize: 500);
$projector->catchUp($gamesPlayed);              // the entries it has not seen
$projector->rebuild($gamesPlayed, $seasonStart); // a rule changed: replay the season
$projector->lag($gamesPlayed);                   // how many entries were written after its checkpoint
```

- **Catching up** hands the projection the entries after its checkpoint, in batches; the checkpoint moves after each
  batch, so a failure loses at most one batch, which the next run hands again. Entries the filter leaves out move the
  checkpoint too, to the end of the journal as it was when the run began.
- **Rebuilding** resets the projection from `since` on — entirely without it — and hands it the activities of that
  period again, from the start of the journal. When the projection's own filter starts later, the later start wins.
- **One run at a time.** Two runs of the same projection at the same moment would apply the same entries twice: run
  catch-ups from one worker, or under a lock.

`Checkpoints` keeps the position of each projection by its name; `InMemoryCheckpoints` is the one in memory.

## Live projections

```php
$journal = new Journal($log, $clock, observers: [new LiveProjections($projector, new Projections([$gamesPlayed]))]);
```

`LiveProjections` catches up, after each new entry, every projection whose filter reads it. If a projection fails, the
entry stays recorded and the projection's checkpoint stays before it: the next catch-up hands it again. An application
with many projections, or slow ones, keeps them out of the request and catches them up from a scheduled task instead.
