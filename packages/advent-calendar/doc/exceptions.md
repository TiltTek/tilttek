# Exceptions

Every exception of the library implements `Tilttek\AdventCalendar\Exception\AdventCalendarException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidCalendar` | An identifier, a timezone, a date, a count of boxes, a closing date, a catch-up, a surprise's reference or quantity is refused, or a box is asked for by a day the calendar has none for | `The calendar "advent-2026" has boxes 1 to 24; 25 is not one.` |
| `BoxNotOpenable` | A box never opened is opened before its day, or after its window | `The box 4 of the calendar "advent-2026" opens at 2026-12-04T00:00:00+01:00.` |
