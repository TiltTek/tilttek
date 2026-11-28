# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| The calendars of its world | Their definitions, built or read from arrays | [Defining a calendar](defining-a-calendar.md) |
| `Contract\ReminderSource` | The store of its reminders, on its own database | [Reminders](reminders.md#the-reminder-port) |
| `Contract\SeasonSource` | Seasons and climate zones of its own — by region, by plane —, for its weather | [Moons, eras and seasons](moons-eras-seasons.md#the-season-port) |
| The current date | The day, the moment and the pace it keeps, and the real time | [Time and ages](time-and-ages.md) |

Calendars, days and dates are immutable values; `Agenda` keeps no state between two calls.
