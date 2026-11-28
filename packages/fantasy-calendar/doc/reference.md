# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Calendar` | A calendar: dates and days, weekdays, moon phases, years in eras, seasons, years between two days |
| `Month` | A month: its days, its leap days, and whether it counts in the week |
| `LeapRule`, `LeapCycle` | Which years are leap, by cycles and exceptions |
| `Moon`, `Phase` | A moon with its own cycle, and its eight phases |
| `Era`, `EraYear` | An era that numbers the years again, and a year as an era numbers it |
| `Season`, `SeasonAt` | A season, and the season of a day with its climate zone |
| `Day` | A day of a calendar as a whole number, compared by its methods |
| `Date` | A year, month and day of the month |
| `CalendarCodec` | Writes a calendar to an array and reads it back |
| `Template\Gregorian` | The proleptic Gregorian calendar, as a model to copy |
| `Calendars`, `Link` | The calendars of a world, their links, parents and conversions |
| `Reminder`, `Recurrence`, `Occurrence` | A reminder tying an entry to a day, how it comes back, and one of its days |
| `Agenda` | The reminders of a range of days, inherited ones included |
| `CurrentDate`, `Pace` | The current day of a world and the pace it moves at |
| `Lifespan` | The span of an entry and its age on a day |
| `Limits` | The bounds of every definition and computation |
| `Contract\SeasonSource` | The port of the seasons and climate zones, for the weather |
| `Contract\ReminderSource` | The port of the reminders |
| `Store\InMemoryReminders` | The reminders in memory, for tests |
| `Exception\FantasyCalendarException` and its four exceptions | [Exceptions](exceptions.md) |
