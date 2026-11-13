# Changelog

All notable changes to `tilttek/seasonal-events` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `SeasonSchedule`: a season that comes once or each year by an RFC 5545 yearly rule, in its timezone, up to 183 days,
  with its times found at a moment, after a moment and between two dates.
- `Season`, `SeasonalEvent`, `Content`, `ContentKind` and `Multiplier`: the quests, items and decorations a season and
  its events bring, and the multiplier of a ledger, only on an event announced at least a day ahead.
- `SeasonalEvents`, `Snapshot`, `ActiveSeason`, `ActiveEvent` and `Period`: what is on at a moment, the events
  announced, and the next time of a season, each time coded by the date of its first day.
- `ActiveContent`: the port the other packages read what is on through.
- `SeasonCatalogue`, `InMemorySeasonCatalogue` and `SeasonReader`: seasons from code or plain arrays, read and written
  back, at most 100 in a list; sample seasons in `resources/sample-seasons.php`.
- `Bridge\Quests\SeasonQuestSchedule` and `Bridge\Collections\SeasonalItems`: the optional bridges to `tilttek/quests`
  and `tilttek/collections`.
- `SeasonalEventsException` and `InvalidSeason`.
- A fuzz target of the definitions and benchmarks of the questions of a moment.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
