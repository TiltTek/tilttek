# Changelog

All notable changes to `tilttek/exchange-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-20

### Added

- `ExchangeTables` and `ExchangeSchemaListener`: the catalogue, the reservations with a lock row per reward, the journal
  of transfers, the offers with their items and the holds of the offered items, added to the schema the ORM generates
  for the exchanges' connection.
- `Rows`: the insertion that writes nothing when its key is there — `ON CONFLICT DO NOTHING`, `INSERT IGNORE`,
  `INSERT OR IGNORE` — and the columns read back.
- `DbalCatalog`, `DbalReservationStore`, `DbalTransferLog`, `DbalOfferStore` and `DbalTransaction`: the stores of
  `tilttek/exchange` on a DBAL connection, the stock counted under the reward's lock row, the items held by the primary
  key of their table, and the steps of a trade in one transaction; moments in columns and parameters of the type
  `datetime_utc_immutable`, stored, compared and read back in UTC; tested on SQLite, MySQL 8.4 and PostgreSQL 17.
  `DbalReservationStore::transition()` moves a reservation from the status it is in by an update that names it —
  `paying` to `reserved` once its points are paid, an open status to a settled one —; `discard()` deletes a paying
  reservation, and `due()` returns the paying and reserved reservations past their lifetime.
- The configuration `tilttek_exchange`: the store (`doctrine` or `memory`), the connection, the catalogue
  (`configuration` or `doctrine`) and the rewards, the lifetime of a reservation, the services of the conditions and the
  joining dates, the timezone, transfers and trades with their allowance and their delay for new accounts, the inventory
  and the lifetime of an offer, and the kind of a member's reference; rewards, durations, timezones and missing services
  refused when the container is compiled. The stores in memory are tagged `kernel.reset`.
- `PointsHold`: the contract of the points of a reserved reward — held, confirmed at delivery, released at a
  cancellation, a refusal or the expiry — with the held points and the available balance; `SpendingPointsHold`, its
  implementation on `Points`, spends at once and refunds under the reservation's spending key, as `Redemptions` does.
- `ExchangeServices`: `Redemptions`, the `PointsHold` alias, `Transfers` and `Trades` with their guards, the stores and their aliases, the
  erasure, and the commands and forms when their components are installed.
- `DispatchingAnnouncer`: the exchanges' events dispatched to the application's event dispatcher.
- The commands `tilttek:exchange:expire` and `tilttek:exchange:transfers`.
- `RedeemRewardType`, `TransferPointsType` and `OfferType`: the forms that reserve a reward, give points and make an
  offer or a counter-offer.
- `ExchangePrivacy`: the export and erasure of a member's reservations, transfers and offers for `phil/privacy-bundle`.
- The Flex recipe in `recipe/0.1`: the bundle and `config/packages/tilttek_exchange.yaml`, with transfers and trades
  off.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
