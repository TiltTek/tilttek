# Goals and curves

## A goal

```php
$goal = new Goal(
    'novel-2026',                         // the goal's identifier
    ResourceReference::of('member', '44'), // its author
    50_000,                               // the target
    Day::fromString('2026-11-01'),        // the first day, included
    Day::fromString('2026-11-30'),        // the deadline, included
    Unit::Words,                          // or Unit::Pages
    Curve::linear(),                      // the default
    WritingDays::everyDay(),              // the default
    'America/Montreal',                   // the author's timezone, UTC by default
);
```

| Argument | Accepted |
|---|---|
| `$id` | 1 to 128 letters, digits, `_`, `.`, `:` or `-`, starting with a letter or a digit |
| `$target` | 1 to 1 000 000 000 |
| `$start`, `$deadline` | `Day`s from 1 to 3 660 days apart, both included, with one writing day at least between them |
| `$timezone` | Any timezone PHP knows |

`writingDaysOfPlan()` lists the goal's writing days in order. A goal is a value: to extend a deadline or change a
curve, save a new `Goal` with the same identifier; what was logged stays.

## Days

A `Day` is a date without a time: `Day::fromString('2026-11-01')`, `Day::of($moment, $timezone)` for the date a moment
falls on, `plus()`, `daysUntil()`, `isBefore()`, `isAfter()`, `equals()`, `weekday()` — 1 for Monday to 7 for Sunday —
and `toString()`. Two days compare as two integers, the days since 1970-01-01. The author's today is the date in the
goal's timezone: at 23:30 in Montreal, a count goes to that day, not to the next day in UTC.

## Writing days

```php
WritingDays::everyDay();
WritingDays::on(1, 2, 3, 4, 5);                                    // Monday to Friday
WritingDays::on(1, 3, 5)->except(Day::fromString('2026-11-11'));   // less a date set aside
```

A day that is not a writing day has no quota; an author sets aside up to 366 dates.

## Curves

A curve gives each writing day a weight by its place among all the writing days of the plan:

| Curve | Weights over 4 writing days | For |
|---|---|---|
| `Curve::linear()` | 1, 1, 1, 1 | The same share each day |
| `Curve::frontLoaded()` | 7, 6, 5, 4 | A strong start, a lighter end; the first day weighs about twice the last |
| `Curve::backLoaded()` | 4, 5, 6, 7 | A gentle start that builds up |
| `Curve::custom([1, 0, 1])` | Stretched over the days | The author's own shape: here, a pause in the middle third |

A custom curve has 1 to 366 weights of 0 to 1 000, one above 0 at least. The weights are stretched over the writing
days: with three weights and six days, each weight covers two days. A day of weight 0 asks for nothing while other days
are left; when only days of weight 0 remain, they share what remains equally, so a goal never asks for the impossible.
