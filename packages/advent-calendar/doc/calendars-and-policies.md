# Calendars and policies

## A calendar

`Calendar::of()` takes an identifier, a timezone, the date of box 1 and one surprise per box, in the order of the days:

```php
$calendar = Calendar::of('advent-2026', 'Europe/Paris', '2026-12-01', $surprises);

$calendar->days();          // 24
$calendar->dateOf(24);      // '2026-12-24'
$calendar->surprise(3);     // the surprise of box 3
$calendar->dayAt($moment);  // the box whose day the moment falls on, or null
```

| Argument | Accepted |
|---|---|
| `$id` | 1 to 64 lower-case letters, digits, `_`, `.` or `-`, starting with a letter or a digit |
| `$timezone` | Any timezone PHP knows, `America/Toronto` |
| `$firstDate` | A date written `YYYY-MM-DD` |
| `$surprises` | A list of 1 to 92 `Surprise`s, one per day |
| `$policy` | An `OpeningPolicy`; `OpeningPolicy::untilClosing()` when null |
| `$closingDate` | The last date a box may be opened, written `YYYY-MM-DD`: the date of the last box when null, or up to 366 days after it |

A box is numbered from 1, the first date. A day starts at midnight in the calendar's timezone. On the day the clocks go
back, a box lasts 25 hours; on the day they go forward, 23; where a change of time skips midnight itself, the day starts
at its first moment, 01:00. The calendar closes at the start of the day after its closing date.

## Surprises

A surprise is a reference, never the reward itself:

| Factory | Kind | Reference | Quantity |
|---|---|---|---|
| `Surprise::points(50)` | `points` | The ledger, `points` unless another is given | The amount |
| `Surprise::badge('early-bird')` | `badge` | The badge | 1 |
| `Surprise::content(ResourceReference::of('page', '42'))` | `content` | The resource, `page:42` | 1 |
| `Surprise::code('winter-discount')` | `code` | The pool the application draws a code from | 1 |
| `Surprise::of($kind, $reference, $quantity)` | Any | 1 to 255 printable ASCII characters | 1 to 1 000 000 |

A code stays in the application's pool until a box is opened: the calendar holds the pool's name, so a page that shows
the calendar can never leak a code. `toArray()` gives the kind, the reference and the quantity, for a listener or a log.

## Opening policies

Each calendar chooses how long a box stays openable once its day has come. No policy opens a box before its day.

| Policy | A box is openable | Missed |
|---|---|---|
| `OpeningPolicy::untilClosing()`, the default | From its day until the calendar closes | Only once the calendar is closed |
| `OpeningPolicy::sameDay()` | On its day only | From midnight after its day |
| `OpeningPolicy::catchUp(3)` | On its day and the 3 days after, 1 to 31 | From midnight after the last day of catch-up, or at the closing if it comes first |

`$calendar->window($day)` gives the box's `OpeningWindow`: `from`, included, and `until`, excluded, with
`hasStarted()`, `hasEnded()` and `contains()` for a moment.

The default keeps a missed day from being a loss: a member who is away for a week finds the week's boxes waiting. The
two other policies exist for the site that wants a box tied to its day; the calendar's page then says so, and a later
closing date leaves room for the last boxes.
