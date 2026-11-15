# Changelog

All notable changes to `tilttek/exchange` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.0-alpha - 2026-11-14

### Changed

- A reservation is written `ReservationStatus::Paying` and becomes `Reserved` once its points are spent, so that no
  cancellation nor expiry settles it between its writing and its payment; `ReservationStatus::isOpen()` tells a
  paying or reserved reservation, and the units of a paying one are held.
- `ReservationStore::settle()` is replaced by `transition()`, a compare-and-set from one status to another;
  `discard()` deletes a paying reservation, and `due()` returns the paying and reserved reservations past their
  lifetime. A store of the application implements `transition()` in place of `settle()`.
- `Redemptions::confirm()` and `cancel()` throw `ReservationPending` while the points are being paid; a payment that
  finds its reservation settled or gone gives its points back, and `RewardReserved` is told once, by the call that
  made the reservation reserved.
- `tilttek/points` 0.2 accepted as well as 0.1.

### Fixed

- Points could be spent for a reservation already cancelled or expired, when the cancellation or the expiry ran
  between the writing of the reservation and the spending of its points.

## 0.1.0-alpha - 2026-11-11

### Added

- `ExchangeKey`: the key the application gives a reservation, a transfer or an offer, 1 to 200 bytes, never starting
  with the `#` of the ledger's derived keys, so that the same key is one exchange.
- `Reward`, `Catalog`, `WritableCatalog` and `InMemoryCatalog`: rewards with a price in a currency of points, a stock
  counting every unit ever available, a period of availability, a limit per member and conditions;
  `InMemoryCatalog::fromArray()` from a configuration.
- `Requirements` and `NoRequirements`: the conditions of the rewards, checked by the application.
- `ReservationStatus`, `Reservation`, `ReservationStore` and `InMemoryReservationStore`: reservations written once by
  key, their units checked against the stock and the member's limit in the same step, settled once, the due ones oldest
  first, a member's reservations and their erasure. `InMemoryReservationStore::reset()` empties the store, for a
  long-running process.
- `Redemptions`: rewards reserved by spending their points on the ledger, confirmed once delivered, cancelled or
  expired with their points given back, the reasons a member cannot reserve, the units left, and a reservation asked
  again resumed where it stopped.
- `Allowance`, `Guards`, `MemberSince` and `NoMemberSince`: no exchange with oneself, a delay for new accounts from the
  joining dates the application tells, and an allowance per day, ISO week, month, year or ever in a timezone.
- `Transfer`, `TransferLog`, `InMemoryTransferLog` and `Transfers`: points given between members within the guards,
  written once by key to a journal the application examines, with what a member gave in the current period.
  `InMemoryTransferLog::reset()` empties the log.
- `ItemMove`, `Inventory`, `InMemoryInventory`, `OfferStatus`, `Offer`, `OfferStore`, `InMemoryOfferStore` and `Trades`:
  offers of items, and points on either side, whose offered items are held until they close; acceptance by the recipient
  in one transaction after checking ownership, tradability, holds and balances; declines, withdrawals, counter-offers
  that close the first offer and open their own, and expiry. `InMemoryInventory::reset()` and
  `InMemoryOfferStore::reset()` empty them.
- `Transaction` and `ImmediateTransaction`: the steps of an exchange as one unit.
- `Announcer`, `SilentAnnouncer` and the events `RewardReserved`, `ReservationConfirmed`, `ReservationCancelled`,
  `ReservationExpired`, `PointsTransferred`, `OfferMade`, `OfferAccepted`, `OfferDeclined`, `OfferWithdrawn`,
  `OfferCountered` and `OfferExpired`, each told once.
- `Erasure` and `ExchangeErasure`: a member's reservations deleted, their open offers withdrawn, and `erased:member` in
  their place in the transfers and offers they had with other members.
- `Refusal` and `ExchangeException`, implemented by `InvalidExchange`, `UnknownReward`, `UnknownExchange`,
  `ExchangeConflict`, `ExchangeClosed` and `ExchangeRefused`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
