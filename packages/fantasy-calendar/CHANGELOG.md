# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/fantasy-calendar` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Calendar`, `Month`, `LeapRule` and `LeapCycle`: calendars of 1 to 100 months and weeks of 1 to 100 days, months
  outside the week and leap-only days, leap years by cycles and exceptions repeating within 10 000 years; dates turned
  into whole day numbers and back for any year within a million of year 0, weekdays, and whole years between two days.
- `Day` and `Date`: days of one calendar compared by their methods, refused across calendars, and dates of negative
  years.
- `Moon` and `Phase`: moons with a cycle of their own as an exact fraction of days, read from a decimal number, their
  eight phases and the next day of a phase.
- `Era` and `EraYear`: eras that number the years again, and one era that counts backward before the first.
- `Season`, `SeasonAt` and `Contract\SeasonSource`: seasons, the season of a day with its day, its length and the
  climate zone of the calendar, and the port the weather reads them through.
- `CalendarCodec`: calendars written to arrays and read back, refusing any unknown key or wrong value with its place.
- `Template\Gregorian`: the proleptic Gregorian calendar, as a definition to copy.
- `Calendars` and `Link`: linked calendars, conversions through the shortest chain of links, and parents whose
  reminders appear in their children.
- `Reminder`, `Recurrence`, `Occurrence`, `Agenda`, `Contract\ReminderSource` and `Store\InMemoryReminders`:
  reminders tying an entry to a day once, yearly or monthly, the agenda of a range of up to 3 660 days, the port of the
  reminders and the store in memory for tests.
- `CurrentDate` and `Pace`: the current day of a world, moving at a chosen pace or only when moved.
- `Lifespan`: ages from a birth or a founding, stopping at the end.
- `Limits` and the exceptions under one `FantasyCalendarException` interface: `InvalidCalendar`, `OutOfRange`,
  `CalendarMismatch` and `UnknownCalendar`.
- Tests against PHP's own Gregorian dates, property tests over a million years, a fuzz target of the codec and a
  benchmark of the hot paths.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
