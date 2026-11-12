# Extension points

| Point | What an application gives | Where |
|---|---|---|
| `Contract\GoalStore`, `Contract\LogStore` | The storage of goals and counts, in place of the stores in memory | [Writing a store](writing-a-store.md) |
| `Psr\EventDispatcher\EventDispatcherInterface` | The dispatcher `GoalReached` goes through, to a listener that awards a badge or starts a celebration | [Logging](logging.md#the-goal-reached) |
| `Psr\Clock\ClockInterface` | The clock the author's today is read from: the system's in production, a fixed one in tests | [Testing](testing.md) |

Every point is passed to the constructor of `WritingGoals`, which keeps no state of its own between two calls.
`Planner` needs none of them.

The public types and the store contracts are stable within a minor version of `0.x`; a change to them comes with a new
minor version and a line in the changelog.
