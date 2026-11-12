# Changelog

All notable changes to `tilttek/writing-goals` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles. Until the package is launched
publicly, split to its public repository, this file keeps one "Added" section that describes the current state.

## 0.1.0-alpha - 2026-11-12

### Added

- `Goal`, `Day` and `Unit`: a target in words or pages from a first day to a deadline, both included, up to 3 660 days,
  in the author's timezone.
- `Curve` and `CurveShape`: linear, front-loaded, back-loaded, or 1 to 366 weights the author draws, stretched over the
  writing days.
- `WritingDays`: the days of the week the author writes, less up to 366 dates set aside.
- `Planner`, `Plan` and `DailyQuota`: the daily quota recomputed from what remains, in proportion to each day's weight
  in the whole plan, in whole numbers adding up to it; a missed day only reshapes the days after, and no streak is
  counted.
- `Progress`: the counts of each day.
- `WritingGoals`: goals kept and changed, counts logged, taken back and corrected on the author's today or an earlier
  day, plans, and the erasure of an author.
- `Event\GoalReached`: dispatched when a count brings a goal to its target, for a badge or a celebration.
- `Contract\GoalStore`, `Contract\LogStore`, `Store\InMemoryGoalStore` and `Store\InMemoryLogStore`, with
  `GoalStoreTestCase` and `LogStoreTestCase`, the contracts any store passes.
- Exceptions under `Exception\WritingGoalsException`: `InvalidGoal` and `UnknownGoal`.
- `tests/Benchmark/PlannerBench.php`: a plan of a month and one of ten years.
- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
