# Awarding from the activity

The bundle registers `Tilttek\Badges\BadgeProjection` with the tag `tilttek_activity.projection`:
`tilttek/activity-bundle` hands it each entry of the journal, live when its live projections are on, or by its
commands.

```php
$journal->record(Activity::of('comment:981', 'member:44', 'news.commented', $now, 'comment:981'));
// the projection brings it to the badges whose criteria read news.commented, and awards those reached
```

## Events

When the activity earns a badge, the event dispatcher receives `BadgeAwarded`; when a cancellation withdraws a
revocable badge, `BadgeRevoked`. Both carry the `Award` and the `Badge`.

```php
#[AsEventListener]
final readonly class CongratulateMember
{
    public function __invoke(BadgeAwarded $event): void
    {
        // notify $event->award->member of $event->badge
    }
}
```

An award made by hand dispatches nothing: the application that makes it knows.

## Rebuilds

When a badge's criterion changes, rebuild the projection with `tilttek/activity-bundle`'s command over the period that
changed: the contributions and the awards derived from it are forgotten and the journal replayed. Awards earned again
dispatch no event; awards made by hand are kept.

## Measures

A `measure` criterion reads a value the application keeps — a balance of points — through the service
`Tilttek\Badges\Measures`, which the application defines; without it, a catalog with a measure fails when the
evaluator is built.
