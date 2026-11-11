# Changelog

All notable changes to `tilttek/points` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `AccountType` and `Account`: members' accounts named by their `phil/resource` reference, issuance accounts named by
  a rule or a source, redemption accounts named by what points pay for, the expiry account and the account that
  stands for an erased member; written as `type:name` and read back.
- `Currency` and `Currencies`: currencies named by the application, each with the lifetime of its credits as an ISO
  8601 duration or none, the first one the default; `Currencies::fromArray()` from a configuration.
- `MovementKey` and `MovementKind`: the key that makes a movement written once, the application's keys kept apart from
  the `#`-prefixed keys the library derives, and the kinds of movement, three of them derived from the activity and
  recomputed by a rebuild.
- `Movement`, `LedgerEntry`, `Recorded` and `Erasure`: points moving from a debited account to a credited one, from 1
  to 10^15, with the moment they count at, the activity that caused them and the movement they undo; the entry at its
  position, its plain values for an export, and what writing and erasing returned.
- `Lot` and `Lots`: what is left of each credit, the debits taken from the lot they name, then from the oldest live
  lots, then from the others, and the debt the next credits pay first.
- `Period`, `Cap`, `EarningRule` and `Rules`: points per unit of an activity's quantity or per activity, in a
  currency, for verbs by pattern and context conditions, capped per member over a day, an ISO week, a month, a year or
  ever, counted in the rules' timezone; `Rules::fromArray()` from a configuration.
- `Ledger`, `InMemoryLedger` and `Reading`: a movement written once by key and its key refused for another movement, a
  payment written only when the balance covers it, balances, the net between two accounts over a period, statements in
  order, the members credited before a cutoff, the derived movements forgotten before a rebuild, and the erasure of a
  member that keeps the transfers with other members. `InMemoryLedger::reset()` empties the ledger, for a long-running
  process.
- `Points`: balances and histories by currency, grants and revocations from a source, spendings on a redemption,
  refunds, transfers between members, the credits due expired before a payment is checked, the expiry of one member
  or of every member, and the erasure of a member.
- `PointsProjection`: a projection of `tilttek/activity` that turns each recorded activity into the points its rules
  give, once per rule and activity, reverses them when the activity is cancelled, and forgets the derived movements of
  a period before a rebuild.
- `PointsException`, implemented by `InvalidPoints`, `MovementConflict`, `InsufficientPoints`, `UnknownCurrency` and
  `UnknownMovement`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
