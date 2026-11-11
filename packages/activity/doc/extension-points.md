# Extension points

| Point | What an application gives | Where |
|---|---|---|
| `Log\ActivityLog` | The storage of the journal, in place of `InMemoryActivityLog` | [Writing a journal](writing-a-journal.md) |
| `Projection\Projection` | A derived state of its own, kept up to date from the journal and rebuilt when a rule changes | [Projections](projections.md#writing-one) |
| `Projection\Checkpoints` | Where each projection stopped, in place of `InMemoryCheckpoints` | [Projections](projections.md#the-projector) |
| `JournalObserver` | What is done right after an entry is written, in the same process | [Recording activity](recording.md#observers) |
| `Psr\Clock\ClockInterface` | The clock that stamps each entry: the system's in production, a fixed one in tests | [Testing](testing.md) |

Every point is passed to the constructor of the service that uses it; the services keep no state of their own between
two calls.
