# Exceptions

Every exception of the library implements `Tilttek\FantasyCalendar\Exception\FantasyCalendarException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidCalendar` | A calendar, a date or a value is refused: an inconsistent definition, a date that does not exist, an array that does not read — then with the place of the value | `months.2.days: is a whole number.` |
| `OutOfRange` | A computation would leave the bounds: a year beyond a million, a range of days too long, a registry full | `A year lies from -1000000 to 1000000; it is 1000001.` |
| `CalendarMismatch` | Two days of different calendars are compared or combined without converting one first | `A day of the calendar "elves" is not a day of the calendar "empire": convert it first.` |
| `UnknownCalendar` | A calendar the registry does not hold, or two calendars no chain of links joins | `No calendar "moon" is registered.` |
