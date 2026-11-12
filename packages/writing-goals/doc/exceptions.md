# Exceptions

Every exception of the library implements `Tilttek\WritingGoals\Exception\WritingGoalsException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidGoal` | An identifier, a target, a span of days, a timezone, a date, a curve, a day of the week, a count or a day without writing is refused, or a count is logged on a day that has not come | `A goal lasts 1 to 3660 days, its first and last included; from 2026-11-01 to 2026-10-31 is not one.` |
| `UnknownGoal` | A goal is asked for by an identifier the store does not know | `No goal has the identifier "novel-2026".` |
