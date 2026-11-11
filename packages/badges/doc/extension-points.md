# Extension points

| Point | What an application gives | Where |
|---|---|---|
| `Store\AwardStore`, `Store\ProgressStore`, `Store\ProfileStore`, `Store\GrantStore` | The storage of awards, progress, profiles and consents, in place of the stores in memory | [Writing a store](writing-a-store.md) |
| `Measures` | The values the `Measure` criteria read — a balance of points, a level — from the application's other packages | [Badges and criteria](criteria.md#criteria) |
| `Criterion\Criterion`, `Criterion\Leaf` | A criterion of its own, built in code beside the built-in ones | [Badges and criteria](criteria.md#criteria) |
| `AwardObserver` | What is done when a member earns or loses a badge: a notification, a reward | [Awarding](awarding.md) |
| `ShareChannel` | Where a member shares an achievement, when they ask for it | [What a member shows](showing.md) |
| `Psr\Clock\ClockInterface` | The clock the services read: the system's in production, a fixed one in tests | [Testing](testing.md) |

Every point is passed to the constructor of the service that uses it; the services keep no state of their own between
two calls.
