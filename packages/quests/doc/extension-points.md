# Extension points

| Point | What an application gives | Where |
|---|---|---|
| `Catalog`, `WritableCatalog` | The quests, from a configuration or a catalog the site edits | [Installation](installation.md) |
| `Store\ContributionStore`, `Store\CompletionStore`, `Store\EnrolmentStore`, `Store\DuelStore` | The storage of contributions, completions, enrolments and duels, in place of the stores in memory | [Writing a store](writing-a-store.md) |
| `Schedule\Schedule`, `Schedule\ScheduleReader` | When a quest is open beyond the built-in schedules, read from the keys of its definition | [Schedules and chains](schedules-and-chains.md#when-a-quest-is-open) |
| `QuestObserver` | What is done when a quest is accomplished, a duel won or a duel expired | [The projection](projection.md) |
| `Psr\Clock\ClockInterface` | The clock the services read: the system's in production, a fixed one in tests | [Testing](testing.md) |

Every point is passed to the constructor of the service that uses it; the services keep no state of their own between
two calls.
