# Changelog

All notable changes to `tilttek/advent-calendar` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.0-alpha - 2026-11-14

### Changed

- `Contract\OpeningStore` keeps whether each opening was delivered: `undelivered($member)`, the member's openings not
  yet marked delivered, by calendar then day, and `markDelivered($opening)`. An opening is added undelivered. A store
  written for 0.1 implements the two methods; `doc/writing-a-store.md` gives the column.
- `BoxOpened` is dispatched at least once: an opening is marked delivered once its event was dispatched without an
  exception. A listener that throws leaves it undelivered without stopping the others, and the first exception is
  thrown again at the end; the next `open()` of the calendar by the member tells it again. Listeners must be
  idempotent, by `idempotencyKey()`.

### Added

- `AdventCalendar::deliver()`: the member's undelivered openings of a calendar told again, to resume after a listener
  failed; an opening of a day the calendar no longer holds is let go.

### Fixed

- A listener of `BoxOpened` that failed no longer loses the surprise of an opening already recorded.

## 0.1.0-alpha - 2026-11-12

### Added

- `Calendar`: one box per day from a first date, 1 to 92 boxes, counted in the calendar's timezone across changes of
  time, closing on the date of the last box or up to 366 days after it.
- `Surprise` and `SurpriseKind`: the reward of a box as a reference — points to a ledger, a badge, content, a code
  pool —, granted by the application.
- `OpeningPolicy`, `PolicyKind` and `OpeningWindow`: a past box openable until the calendar closes by default, on its
  day only, or for a catch-up of 1 to 31 days; never before its day.
- `AdventCalendar`: idempotent openings recorded once, even when two requests race; the boxes as a member sees them —
  locked, openable, opened, missed — without revealing an unopened surprise; the erasure of a member.
- `Event\BoxOpened`: dispatched once per first opening, with an idempotency key, for the application to grant the
  surprise and start a celebration.
- `Contract\OpeningStore` and `Store\InMemoryOpeningStore`, with `OpeningStoreTestCase`, the contract any store passes.
- Exceptions under `Exception\AdventCalendarException`: `InvalidCalendar` and `BoxNotOpenable`.
- `tests/Benchmark/BoxesBench.php`: the boxes a page reads, on calendars of 24 and 92 boxes.
- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
