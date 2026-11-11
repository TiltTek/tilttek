# Extension points

| Point | What an application gives | Where |
|---|---|---|
| `Store\ExperienceStore`, `Store\MilestoneStore`, `Store\StreakStore` | The storage of experience, milestones and streaks, in place of the stores in memory | [Writing a store](writing-a-store.md) |
| `Season\SeasonSchedule` | Seasons of its own, beside `CalendarSeasons` and `NamedSeasons` | [Seasons](seasons.md) |
| `Streak\TimezoneResolver` | The timezone a member's day is read in | [Streaks](streaks.md#the-members-day) |
| `ProgressionObserver` | What is done when a level changes, a milestone is reached or a streak breaks | [The projection](projection.md) |
| `Psr\Clock\ClockInterface` | The clock the services read: the system's in production, a fixed one in tests | [Testing](testing.md) |

Every point is passed to the constructor of the service that uses it; the services keep no state of their own between
two calls.
