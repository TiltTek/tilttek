# Reference

| Type | Role |
|---|---|
| `WritingGoals` | Keeps goals, logs and corrects counts, answers plans on the author's today, erases an author |
| `Goal` | A target in words or pages, from a first day to a deadline, its curve, writing days and timezone |
| `Day` | A date without a time, counted in days since 1970-01-01 |
| `Unit` | Words or pages |
| `Curve`, `CurveShape` | The weight of each writing day; linear, front-loaded, back-loaded or custom |
| `WritingDays` | The days of the week the author writes, less the dates set aside |
| `Progress` | The counts of each day |
| `Planner` | Shares what remains among the writing days left |
| `Plan`, `DailyQuota` | A goal as it stands on one day; the quota of one writing day |
| `Event\GoalReached` | Dispatched when a count brings a goal to its target |
| `Contract\GoalStore`, `Store\InMemoryGoalStore` | Where the goals are kept |
| `Contract\LogStore`, `Store\InMemoryLogStore` | Where the counts are kept |
| `Exception\WritingGoalsException` and its two exceptions | [Exceptions](exceptions.md) |
