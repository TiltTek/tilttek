# Changelog

All notable changes to `tilttek/bounties` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-14

### Added

- `Bounties`: bounties offered with their points held, the same offer sent again giving back the same bounty, one open
  bounty a subject and five open bounties a sponsor by default; claims, awards — to one claim or shared —, closing
  with the rest given back, cancelling before any claim, settling once the grace is over, `expireDue()` for a
  scheduled task; each move kept at the next version only, tried three times against other writers, settled in the
  escrow, then announced; `settle()` to make an escrow move again after a failure; `erase()` to take a member out of
  their bounties in bounded batches.
- `Pseudonym`: the open bounty of an erased sponsor settled, the claims of an erased claimant on an open bounty
  withdrawn with their awards, then a reference of the kind `erased`, drawn for each bounty, in their place; a bounty
  whose sponsor or a winner was erased never settled again; a reference of the kind `erased` given by a caller —
  sponsor, claimant, member to erase, subject of a draft — refused with `InvalidBounty` before anything is written or
  any point moves.
- `Bounty`, `BountyState`, `Claim` and `Award`: an explicit state machine — open, awarded, expired, cancelled —, each
  move checked for its member, its state and its time; settled at once when the whole amount is awarded; 200 claims, 5
  a member and 20 awards at most; payouts gathered by winner; times kept in UTC to the microsecond and ordered, never
  compared with `==` or `===`; a record written and read back, checked field by field and as a whole.
- `BountyDraft`, `BountyReason`, `BountyRules` and `Prize`: a required and bounded length, a grace, a smallest and
  largest amount and the open bounties a sponsor set by the site; a reason from a list; an amount in a unit of points,
  never money, with no field and no adapter for money.
- `Contract\BountyEscrow`, `BountyHold` and `Payout`: the points reserved, confirmed to the winners with the rest given
  back, or cancelled, each move keyed by its hold and following the `PointsHold` contract of
  `tilttek/exchange-bundle`; `Escrow\PointsBountyEscrow` on the ledger of `tilttek/points` and
  `Escrow\InMemoryBountyEscrow`, with an adapter on `tilttek/exchange-bundle` in the documentation.
- `Contract\BountyStore`, with `Store\InMemoryBountyStore`: the open bounty of a subject, the open bounties of a
  sponsor, and the bounties that name a member.
- `Event\BountyOffered`, `BountyClaimed`, `AwardGiven`, `BountyAwarded` and `BountyClosed`, dispatched through PSR-14
  and carrying identifiers only; `BountyAwarded` for the celebration of the PHIL UX kit.
- `Exception\BountiesException`, with `InvalidBounty`, `BountyRefused`, `BountyConflict` and `EscrowConflict`.
- Fuzz targets for the record of a bounty and for its state machine, and benchmarks of a full bounty.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
