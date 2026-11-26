# Changelog

All notable changes to `tilttek/rpg-calendar-events` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-26

### Added

- `Entry` and `Status`: an event of the roleplaying calendar with a stable identifier, names in English, French and
  up to 32 locales, a rule of `phil/almanac`, an https source read by `phil/url` and kept in its canonical form, a
  community, the day it was last checked, up to 16 tags, 1 to 366 days, whether it is verified or still to verify,
  and whether it is a themed period of 2 days or more with a prompt a day; its name in the closest locale and its
  almanac definition.
- `EntryReader`: events read from arrays, the form of the shipped data and of an application's own events, any
  unknown field refused.
- `Catalog`, `Filter` and `Occurrence`: the events in one time zone, the dates of a year, of a period or of a day,
  filtered by tag, community or verification, each with its entry and source; an application's own events added
  beside the shipped ones, an identifier twice refused; the years 1000 to 3000.
- `PromptDay` and `Catalog::promptDays()`: the day of a themed period of daily prompts, with its index from 1 in
  calendar days and its length, for the application's own prompt of that index.
- `ShippedData`, `data/events.php` and `data/VERSION`: GM's Day, RPGaDAY and National Game Design Month, each checked
  against its source; no convention and no list of prompts.
- `DataCheck`: every problem of a set of rows, a rule that does not read, a missing source or date of verification,
  an identifier twice.
- Exceptions under one `RpgCalendarEventsException` interface: `InvalidEntry` and `InvalidQuery`.
- Benchmarks of building the shipped catalog and of the dates of a day and of a year.
- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
