# Changelog

All notable changes to `tilttek/points-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.0-alpha - 2026-11-14

### Changed

- `PointsTables` adds a third table, `tilttek_points_lots`, which keeps the checkpoint of each account's lots in a
  currency, as JSON at a position; the application's next migration creates it.
- `DbalLedger` implements `Ledger::appendAfter()` under the lock row of the debited account, which payments take too,
  and `keptLots()` and `keepLots()` on `tilttek_points_lots`; forgetting the derived movements and erasing a member
  delete the checkpoints.
- The bundle requires `tilttek/points` `^0.2@dev`, whose `Ledger::appendAfter()`, `keptLots()` and `keepLots()`
  `DbalLedger` implements.

### Fixed

- An expiry no longer leaves a balance below zero when a spending of the same account slips between its read of the
  lots and its write, and no longer reads the whole history of the account at each spending.
- `PointsPrivacy` implements `Phil\PrivacyBundle\Application\Contract\PersonalDataExporter` and `PersonalDataEraser`,
  the interfaces `phil/privacy-bundle` exposes, and the bundle registers it when they exist: the export and the
  erasure of a member now reach it.

## 0.1.0-alpha - 2026-11-11

### Added

- `PointsTables` — `tilttek_points_movement`, one row per movement with its unique key, and `tilttek_points_lock`, one
  lock row per paying account and currency that counts its payments — and `PointsSchemaListener`, which adds them to
  the schema the ORM generates for the ledger's connection.
- `DbalLedger`: a movement written once by key, the insertion that writes nothing on a duplicate per platform
  (`ON CONFLICT DO NOTHING`, `INSERT IGNORE`, `INSERT OR IGNORE`), a payment that updates its account's lock row to
  hold its lock before it reads anything, at READ COMMITTED on MySQL and MariaDB and with locking reads inside a
  transaction the application opened, so that it checks the balance the last payment left, balances, nets and statements in SQL, the derived movements forgotten
  before a rebuild, the erasure of a member in one transaction, dates in UTC, and unreadable rows refused.
- `TilttekPointsBundle` and its configuration `tilttek_points`: the store (`doctrine` or `memory`), the DBAL connection,
  the currencies with their lifetimes, the default currency, the timezone of the caps, the earning rules, the
  projection's name, the batch size and the kind of a member for the privacy module; the currencies and the rules are
  checked when the container is compiled. The ledger in memory is tagged `kernel.reset`.
- `PointsServices`: the ledger, `Currencies`, `Rules`, `Points`, and `PointsProjection` tagged
  `tilttek_activity.projection` for `tilttek/activity-bundle`.
- Commands `tilttek:points:expire` and `tilttek:points:history` (`--currency`, `--after`, `--limit`).
- `PointsPrivacy`: the export and the erasure of a member's points for `phil/privacy-bundle` (module
  `tilttek_points`).
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
