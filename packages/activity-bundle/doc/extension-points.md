# Extension points

| Interface | Tag, by autoconfiguration | What the application does with it |
|---|---|---|
| `Tilttek\Activity\Projection\Projection` | `tilttek_activity.projection` | Keeps derived state from the journal, caught up and rebuilt by the bundle |
| `Tilttek\Activity\JournalObserver` | `tilttek_activity.observer` | Is told of each new entry, in the same process |
| `Tilttek\Activity\Log\ActivityLog` | — | Replaces the journal's storage: alias `ActivityLog` to the application's service |
| `Tilttek\Activity\Projection\Checkpoints` | — | Replaces the checkpoints' storage: alias `Checkpoints` to the application's service |

The services `Journal`, `Projector`, `Projections`, `ActivityLog` and `Checkpoints` are public.
