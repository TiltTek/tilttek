# Plans and quotas

## Today's quota

The quotas share what remained at the start of the day among the writing days from today to the deadline, in
proportion to their weights. A goal of 1 000 words over ten days, on a linear curve, asks for 100 a day:

| Situation | Today's quota | The days after |
|---|---|---|
| First day, nothing written | 100 | 100 each |
| Third day, nothing written | 125 | 125 each: the two days missed moved to the eight left |
| Second day, 280 written the first day | 80 | 80 each: the day ahead lowered them |

What is written today counts toward today's quota — `leftToday()` goes down —, and reshapes the days after from
tomorrow on: today's quota does not move while the author writes.

The quotas are whole numbers whose sum is exactly what remains: 100 words over three days are 33, 33 and 34. A curve
keeps its shape when it reshapes: on a front-loaded plan, a missed start still asks for more early than late.

## The plan

`Planner::plan($goal, $progress, $today)`, or `WritingGoals::plan($id)` on the author's today, answers a `Plan`:

| Member | Holds |
|---|---|
| `written`, `writtenToday` | What was written in all, and today |
| `todayQuota`, `leftToday()` | Today's quota, 0 on a day that is not a writing day; what is left of it |
| `quotas` | The `DailyQuota` of each writing day from today to the deadline |
| `remaining()`, `percent()` | What is left of the target; the share written, 0 to 100 |
| `isReached()` | Whether the target is reached |
| `isOverdue()` | Whether the deadline has passed with the target not reached |

Before the first day, the quotas cover the whole plan and today's quota is 0. Once the target is reached, every quota
left is 0. Past the deadline, there are no quotas left and `remaining()` says what is missing: the author saves the goal
with a later deadline, or a lower target, and the plan picks up from there. No streak is counted, nothing is lost, and
a missed day is never shown as a failure.
