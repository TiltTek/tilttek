# Logging

## Logging what was written

```php
$plan = $goals->log('novel-2026', 1_200);                               // today, in the author's timezone
$plan = $goals->log('novel-2026', 800, Day::fromString('2026-11-02'));  // an earlier day
$plan = $goals->log('novel-2026', -200);                                // taken back: logged too high
$plan = $goals->correct('novel-2026', 950);                             // today's count replaced
```

`log()` adds to a day's count, in one step at the store, so two counts logged at once both count; a count taken back
never brings a day below 0. `correct()` replaces a day's count. Each answers the plan on the author's today. A count
logged is -1 000 000 000 to 1 000 000 000; a day counts 0 to 1 000 000 000. A day after the author's today is refused:
words are logged on a day that has come; so is a day before the goal's first day.

The application counts the words — of the author's manuscript, of a session in its editor — and logs the count; the
library never reads a text.

## The goal reached

When a count brings the goal from under its target to its target or above, `WritingGoals` dispatches
`Event\GoalReached` with the goal's identifier, its author and the day the count went to. A count logged after does not
dispatch it again; a goal brought back under its target by a correction, then reached again, dispatches it again, and two counts logged at once can both dispatch it. A listener that
grants something — a badge, points — keeps it once by the event's `idempotencyKey()`, one per goal.

## Celebrating

Reaching a goal is worth marking. The library leaves the celebration to the application: a listener of `GoalReached`,
or the page that receives a plan with `isReached()` newly true, starts the animation the site's interface kit offers —
confetti, fireworks — and respects the member's choice of reduced motion. The library adds no animation of its own.

## Erasure

`$goals->erase($author)` removes every goal of the author and the counts logged toward them, and answers how many
goals there were.
