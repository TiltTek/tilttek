# Events

The bundle dispatches two events through the application's dispatcher:

| Event | When | Carries |
|---|---|---|
| `Tilttek\CollectionsBundle\Infrastructure\Event\CopyGranted` | A member was given a copy — the item of the day, a pack, a reward, a purchase | `copy` |
| `Tilttek\Collections\SetCompleted` | A member completed a set for the first time, just after the copy that completed it | `set`, with its `rewards`, and `completion` |

```php
#[AsEventListener]
public function __invoke(SetCompleted $event): void
{
    foreach ($event->set->rewards as $reward) {
        // give $reward->amount of $reward->code to $event->completion->member
    }
}
```

A move between members dispatches no `CopyGranted`; it dispatches `SetCompleted` when it completes a set.
