# tilttek/writing-goals

**An author's writing goals: a plan that bends to the days they actually write, and no streak to break.**

tilttek/writing-goals keeps an author's writing goals and tracks them. A goal is a target in words or pages, from a
first day to a deadline, both included, over the days of the week the author chooses to write, less the dates they set
aside. Its plan follows a curve: the same share each day, more at the start, more at the end, or weights the author
draws. Each writing day's quota is what remains shared among the writing days left, in proportion to their weights in
the whole plan: a day without writing is not a failure, its share moves to the days after, which keep the curve's
shape; a day ahead lowers them. The author logs what they write each day, takes back a count logged too high, or
corrects a day. A plan answers what was written, what remains, today's quota and what is left of it, the quota of each
writing day to the deadline, the share of the target written, and whether the goal is reached — the application is told
once — or past its deadline, which the author extends without losing anything. No streak is counted. Erasing an author
removes their goals and their counts.

It is for a writing platform or a community that wants to help authors finish what they start — a novel in a month, a
game book over a season — without the pressure of a chain that breaks at the first missed day.

It is a plain PHP library whose dependencies are `phil/resource`, the opaque reference of an author, `psr/clock`, the
clock the author's today is read from, and `psr/event-dispatcher`, which tells the application of a goal reached.
Authors are references the application gives, `member:44`: the library never reads a name, an e-mail or a text; the
application counts the words and logs the count.

The design is small: a `Goal` holds its target, its `Day`s, its `Unit`, its `Curve` and its `WritingDays`; a `Planner`
turns a goal and its `Progress` into a `Plan` of `DailyQuota`s; `WritingGoals` keeps the goals in a `GoalStore`, logs
the counts in a `LogStore`, and dispatches `GoalReached`.

## Status

| Part | State |
|---|---|
| `Goal`, `Day`, `Unit`: a target in words or pages from a first day to a deadline, in the author's timezone | Implemented and tested |
| `Curve`, `CurveShape`: linear, front-loaded, back-loaded or custom weights | Implemented and tested |
| `WritingDays`: the days of the week the author writes, less the dates set aside | Implemented and tested |
| `Planner`, `Plan`, `DailyQuota`: the daily quota recomputed from what remains, in whole numbers adding up to it | Implemented and tested |
| `Progress`: the counts of each day | Implemented and tested |
| `WritingGoals`: goals kept, counts logged, taken back and corrected, plans on the author's today, erasure | Implemented and tested |
| `GoalReached`: the target reached, told to the application | Implemented and tested |
| `GoalStore`, `LogStore` and their in-memory implementations, with the contracts any store passes | Implemented and tested |
| Exceptions under one `WritingGoalsException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring the library. |
| [Goals and curves](doc/goals-and-curves.md) | Targets, days, timezones, writing days and the four curves. |
| [Plans and quotas](doc/plans-and-quotas.md) | How the daily quota is computed, missed days, days ahead, reaching the goal and the deadline. |
| [Logging](doc/logging.md) | Logging, taking back and correcting counts, the goal reached, celebrating and erasure. |
| [Writing a store](doc/writing-a-store.md) | The two contracts an application's storage implements. |
| [Extension points](doc/extension-points.md) | What an application gives the library: the stores, the clock, the dispatcher. |
| [Exceptions](doc/exceptions.md) | `WritingGoalsException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that logs counts. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require tilttek/writing-goals
```

```php
use Phil\Resource\ResourceReference;
use Symfony\Component\Clock\Clock;
use Tilttek\WritingGoals\{Curve, Day, Goal, WritingDays, WritingGoals};
use Tilttek\WritingGoals\Store\{InMemoryGoalStore, InMemoryLogStore};

$goals = new WritingGoals(new InMemoryGoalStore(), new InMemoryLogStore(), new Clock(), $dispatcher);

$goals->save(new Goal(
    'novel-2026',
    ResourceReference::of('member', '44'),
    50_000,
    Day::fromString('2026-11-01'),
    Day::fromString('2026-11-30'),
    curve: Curve::frontLoaded(),
    writingDays: WritingDays::on(1, 2, 3, 4, 5, 6),
    timezone: 'America/Montreal',
));

$plan = $goals->log('novel-2026', 1_200);
$plan->todayQuota;   // what today asks for
$plan->leftToday();  // what is left of it
$plan->remaining();  // what is left of the target
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
