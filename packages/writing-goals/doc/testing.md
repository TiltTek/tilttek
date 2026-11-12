# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The suite covers the days and their days of the week on both sides of 1970, the author's today in their timezone, the
goals and their refusals, the four curves, the writing days and the dates set aside, the plans — first day, missed
days, days ahead, today's count, rounding that adds up, days of weight 0, days without writing, before the start, a goal
reached, past the deadline —, logging, taking back and correcting counts, the goal reached told once, erasure, and the
stores in memory through `GoalStoreTestCase` and `LogStoreTestCase`, the contracts any store passes.
`tests/Benchmark/PlannerBench.php` measures a plan of a month and one of ten years. In the monorepo, Infection holds the
library at 100 % of covered mutants.

## Testing code that logs counts

Build `WritingGoals` with the stores in memory, a clock you move — `Symfony\Component\Clock\MockClock` — and a
dispatcher that writes down its events:

```php
$clock = new MockClock('2026-11-03 03:30:00', 'UTC');
$goals = new WritingGoals(new InMemoryGoalStore(), new InMemoryLogStore(), $clock, $dispatcher);
$goals->save($goal);

self::assertSame('2026-11-02', $goals->plan($goal->id)->today->toString()); // in America/Montreal
```

To test a plan alone, call `Planner::plan()` with a `Progress::fromArray()` and the day you choose.
