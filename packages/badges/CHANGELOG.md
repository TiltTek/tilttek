# Changelog

All notable changes to `tilttek/badges` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.0-alpha - 2026-11-14

### Changed

- A notice of the projection can no longer be lost when the observer fails. `AwardStore` keeps the notice each award
  is owed: an award earned by the activity is kept with its notice due until `markDelivered()`, listed by
  `undelivered()`; an award withdrawn by `withdraw()` leaves the awards held but stays, listed by `withdrawn()`, until
  the observer was told and `remove()` deletes it. A store written for 0.1 implements these four methods; `of()` leaves
  out the withdrawn awards, and `forgetDerived()` returns only the awards held whose notice was given.
- `BadgeProjection` gives the notices a member is owed before evaluating their activity or cancellation, so that the
  request repeated after a failure, or the next catch-up, tells the observer again, and a rebuild announces an award
  whose first notice was never given. The observer is told at least once, and must be idempotent.
- `AwardStore::withdraw()` is one atomic step that always leaves the notice of the withdrawal due, and returns true,
  for an award the member holds, even when the award's own notice is still due: that notice may be under way in
  another process. `markDelivered()` leaves the notice of a withdrawal due. The observer may thus be told of the
  withdrawal of an award it was never told of.
- `Badges::award()` no longer returns an award the store did not keep. When an award made at the same moment took the
  number, a badge that is not repeatable returns the award kept, and a repeatable badge takes the next number, at most
  `Badges::AWARD_ATTEMPTS` times.
- `Badges::share()` shares the award of the highest number, rather than the one whose activity is dated last.

### Added

- `BadgeProjection::deliver()`: every notice a member is owed given to the observer, to resume after a failure.
- `AwardConflict`, raised when other awards took the number of a repeatable badge awarded by hand at every attempt.
- The delivery of the notices documented: at-least-once delivery, idempotent observers and resuming.

## 0.1.0-alpha - 2026-11-11

### Added

- `Badge` and `Tier`: a badge's code, name and description — texts or translation keys —, image, criterion, and whether
  it is repeatable, hidden or revocable; the series and rank that link the badges of a tier.
- `Criterion`, `Leaf`, `Reading` and `Scope`: a criterion as a tree whose leaves read the activity by path; the verbs
  by pattern, the context conditions and the period of the activities a leaf reads.
- `Count`, `Total`, `Streak`, `Measure`, `Manual` and `AtLeast`: a number of activities and first times, a total of
  their quantities, days in a row in the catalog's timezone, a value the application measures through `Measures`, an
  award by hand, and a combination of all or some criteria.
- `CriterionReader` and `Catalog`: criteria and badges from plain values, as a configuration holds them, every code and
  rank of a series once, paths of at most 64 bytes, the verb patterns the badges read and the series by rank.
- `Progress`: how far a member is towards a badge, the value shown at most the target, and how many times the target is
  reached, once at most unless the badge is repeatable.
- `Contribution`, `Award`, `Standing`, `Profile`, `Showcase` and `Erasure`: what an activity brings to a criterion, a
  badge held with its number, moment, cause and whether it was awarded by hand, where a member stands with a badge,
  what they show, what anyone may see, and what erasing them removed.
- `ProgressStore`, `AwardStore`, `ProfileStore` and `GrantStore`, with their implementations in memory: contributions
  written once per badge, path and activity and removed with their activity, awards once per number, profiles removed
  when empty, consents given and withdrawn; what a rebuild forgets, and the erasure of a member. The implementations in
  memory empty themselves with `reset()`, for a long-running process.
- `Evaluator`: a member's progress read from the contributions kept and the application's measures.
- `BadgeProjection` and `AwardObserver`: a projection of `tilttek/activity` that brings each activity to the leaves that
  accept it, awards a badge when its target is reached and again for a repeatable badge, at most
  `MAX_AWARDS_PER_ACTIVITY` awards (10, or the bound given) for one activity, withdraws a revocable badge
  when its activity is cancelled, leaves a badge held by hand as it is, and forgets the contributions and derived awards
  of a period before a rebuild without announcing again the awards earned again. `BadgeProjection::clearReplay()`
  forgets what the last reset kept for its replay.
- `Badges`: awards, progress and the overview that leaves out the hidden badges not earned, manual awards and their
  withdrawal, the badges shown, the showcase of a limited size, the title, sharing through a `ShareChannel` at the
  member's request, the consents of other applications and their reading, and the erasure of a member.
- `BadgesException`, implemented by `InvalidBadge`, `UnknownBadge`, `NotEarned` and `AccessDenied`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
