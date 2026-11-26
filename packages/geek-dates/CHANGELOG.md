# Changelog

All notable changes to `tilttek/geek-dates` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-26

### Added

- `Entry` and `Status`: a date of geek culture with a stable identifier, names in English, French and up to 32
  locales, a rule of `phil/almanac`, an https source read by `phil/url` and kept in its canonical form, a community,
  the day it was last checked, up to 16 tags, 1 to 366 days, and whether it is verified or still to verify; its name
  in the closest locale and its almanac definition.
- `EntryReader`: entries read from arrays, the form of the shipped data and of an application's own entries, any
  unknown field refused.
- `Catalog`, `Filter` and `Occurrence`: the entries in one time zone, the dates of a year, of a period or of a day,
  filtered by tag, community or verification, each with its entry and source; an application's own entries added
  beside the shipped ones, an identifier twice refused; the years 1000 to 3000.
- `ShippedData`, `data/dates.php` and `data/VERSION`: 18 entries — science, mathematics, space and computing days,
  days of readers of Tolkien and Douglas Adams —, each checked against its source.
- `DataCheck`: every problem of a set of rows, a rule that does not read, a missing source or date of verification,
  an identifier twice.
- Exceptions under one `GeekDatesException` interface: `InvalidEntry` and `InvalidQuery`.
- Benchmarks of building the shipped catalog and of the dates of a day and of a year.
- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
