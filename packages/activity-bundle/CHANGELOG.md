# Changelog

All notable changes to `tilttek/activity-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `DbalActivityLog`: the journal on Doctrine DBAL, each entry written once by `ON CONFLICT DO NOTHING`, `INSERT IGNORE`
  or `INSERT OR IGNORE` and read back, filters in SQL with verb prefixes compared by `SUBSTRING`, totals without the
  cancelled activities, erasure in one transaction, dates in UTC, rows that do not read refused.
- `DbalCheckpoints`: the checkpoint of each projection, updated or inserted, a lost insert race updated.
- `ActivityTables`: the tables `tilttek_activity_entry` — an auto-increment position, a unique index on the
  idempotency key and the kind, indexes on the member, the object and the verb with the moment — and
  `tilttek_activity_checkpoint`; `ActivitySchemaListener` adds them to the schema the ORM generates for the journal's
  connection.
- `TilttekActivityBundle`, alias `tilttek_activity`: the store (`doctrine` or `memory`), the DBAL connection, the
  declared verbs, checked when the container is compiled, the batch size, how the projections follow the journal —
  `live` in the request by default, `messenger` or `manual` —, the bus of their messages, the window a gesture's
  moment must fall in (`dates.past`, `dates.ahead`) and the kind of a member reference. The journal and the checkpoints in memory are tagged `kernel.reset`.
- Services: `Journal` with the application's clock, the declared verbs and the observers tagged
  `tilttek_activity.observer`; `Projections` from the services tagged `tilttek_activity.projection`; `Projector`;
  `LiveProjections`; projections and observers autoconfigured by their interfaces.
- `MessengerProjections`, `CatchUpProjection` and `CatchUpProjectionHandler`: the projections kept up to date out of
  the request, a message per projection and entry carrying the projection's name and the entry's position only, the
  journal as outbox and the checkpoints as inbox, so that a message lost is made up and a message handled twice applies
  nothing.
- Commands `tilttek:activity:catch-up`, `tilttek:activity:rebuild` (with `--since`) and `tilttek:activity:status`, when
  the Console is installed.
- `ActivityPrivacy`: the export and the erasure of a member's activity for `phil/privacy-bundle`, when it is installed.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
