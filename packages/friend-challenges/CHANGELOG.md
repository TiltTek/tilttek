# Changelog

All notable changes to `tilttek/friend-challenges` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-20

### Changed

- `tilttek/points` 0.2 required: 0.1 is no longer accepted.

## 0.1.0-alpha - 2026-11-14

### Added

- `FriendChallenges`: challenges created with their prize held, the same creation sent again giving back the same
  challenge; invitees checked as friends; taking part, declining unseen by the creator, proofs and their review by the
  creator, a vote or a moderator; cancelling before any proof, expiring once the review is over, `expireDue()` for a
  scheduled task; reports to the moderators, freezing, unfreezing, removing a challenge or a proof; each move kept at
  the next version only, tried three times against other writers, settled in the escrow, then announced; `settle()`
  to make an escrow move again after a failure; `erase()` to take a member out of their challenges in bounded batches.
- `Pseudonym`: the open or frozen challenge of an erased creator removed and its prize given back, the waiting proofs
  of an erased participant removed, then a reference of the kind `erased`, drawn for each challenge, in their place;
  a challenge whose creator or winner was erased never settled again; a reference of the kind `erased` given by a
  caller — creator, invitee, participant, judge, author of a report, moderator, member to erase — refused with
  `InvalidChallenge` before anything is written, reported or any point moves.
- `Challenge`, `ChallengeState`, `Visibility`, `Validation`, `Proof` and `ProofState`: an explicit state machine —
  open, frozen, won, cancelled, expired, removed —, each move checked for its member, its state and its time; 50
  invitees, 100 participants, 200 proofs and 5 a participant at most; times kept in UTC to the microsecond and ordered,
  never compared with `==` or `===`; a record written and read back, checked field by field and as a whole.
- `ChallengeDraft`, `ChallengeRules` and `Prize`: identifiers, titles and terms checked; a challenge between friends
  by default; a length, a review and a largest prize set by the site; a prize in a unit of points, never money.
- `Contract\PrizeEscrow`, `PrizeHold` and `Payout`: the prize reserved, confirmed to the winner with the rest given
  back, or cancelled, each move keyed by its hold and following the `PointsHold` contract of
  `tilttek/exchange-bundle`; `Escrow\PointsPrizeEscrow` on the ledger of `tilttek/points` and
  `Escrow\InMemoryPrizeEscrow`, with an adapter on `tilttek/exchange-bundle` in the documentation.
- `Contract\ChallengeStore`, `Contract\Friendship` and `Contract\Moderation`, with `Report` and `ReportReason`, and
  their memory implementations; the store lists the challenges that name a member.
- `Event\ChallengeCreated`, `MemberInvited`, `ParticipantJoined`, `ProofSubmitted`, `ProofDecided`, `ChallengeWon`,
  `ChallengeEnded`, `ChallengeFrozen` and `ChallengeUnfrozen`, dispatched through PSR-14 and carrying identifiers only;
  `ChallengeWon` for the celebration of the PHIL UX kit.
- `Exception\FriendChallengesException`, with `InvalidChallenge`, `ChallengeRefused`, `ChallengeConflict` and
  `EscrowConflict`.
- Fuzz targets for the record of a challenge and for its state machine, and benchmarks of a full challenge.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
