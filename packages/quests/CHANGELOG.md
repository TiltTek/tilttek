# Changelog

All notable changes to `tilttek/quests` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.3.0-alpha - 2026-11-17

### Changed

- `Contract\TeamMembership`, and `NoTeamMembership` under which no member belongs to any team: `Quests` takes one
  as its third argument and asks it, before a member joins a team quest under a team — or accepts it again —, whether
  the member belongs to that team; a member who does not is refused with `QuestRefused`, and no enrolment is written.
  Without one, `Quests` uses `NoTeamMembership`, so a team quest is refused rather than accepted by default. To move an
  application: implement `TeamMembership` over its records of the teams, answering on the server, and pass it to
  `Quests`; personal, community and duel quests are unchanged.

## 0.2.0-alpha - 2026-11-14

### Changed

- `Store\DuelStore::transition()`: a store keeps a duel only if the one under its key still has the status it was
  read in, in one atomic step; a store written for 0.1 implements it.
- `Duels` accepts, declines, withdraws and expires a duel only if it still waits for an answer — or, for an expiry, is
  still accepted —: of two answers given at the same moment, one is kept, the other is refused with `QuestRefused`, or
  returns the duel when it gave the same answer. The sweep leaves, and does not report, a duel answered since it read
  it.
- `QuestProjection` wins a duel only if it is still accepted: a duel that expired since it was read is not won, and no
  completion is written for it.

## 0.1.0-alpha - 2026-11-11

### Added

- `Names`, `Scope`, `StepMeasure` and `Step`: steps that read verbs by pattern and context conditions, counting the
  activities or totalling their quantities up to a target of 1 to 10^12, a step asking for one activity being a first
  time.
- `Reward`, `QuestKind`, `Admission` and `Quest`: quests of 1 to 50 steps, ordered or free, of four genres — personal,
  community, team, duel —, followed automatically or on acceptance, following another quest, declaring up to 20
  rewards, and for a duel the length it lasts once accepted.
- `Window`, `Schedule`, `Occurrence`, `Permanent`, `Dated` and `ScheduleReader`: quests open always, between two dates
  or from or until one, or at dates a schedule of the application's own computes, each occurrence named by a code.
- `QuestReader`: quests from plain values — steps, kind, ordered, admission, opens and closes, after, rewards, duel
  length — read in a timezone, unknown keys refused, schedule readers adding keys of their own.
- `Catalog`, `WritableCatalog`, `InMemoryCatalog` and `Chains`: the quests of an application by code, from code or a
  configuration, every chain checked — the quest followed exists, is of the same kind, and no chain loops.
- `Contribution`, `Enrolment` and `Completion`: what an activity brought to a step, a member's acceptance under a team
  and abandonment, a quest accomplished per occurrence and subject — the member, the team or `community:<quest>`.
- `StepProgress`, `QuestProgress`, `Finish` and `ProgressCalculator`: a step done at the contribution that reaches its
  target, ordered steps counting from the moment the step before was done, the quest accomplished with its last step,
  and the members whose activity counted.
- `Evaluator`: the subject a member's activity advances, the time a chained quest is open for it, whether a member's
  activity counts, and the progress of a member, a team or the community within each member's enrolment.
- `QuestCompleted` and `QuestObserver`: a quest accomplished with its rewards and the members they go to, a duel won,
  a duel expired.
- `QuestProjection`: a projection of `tilttek/activity` that brings each activity to the steps that accept it while the
  quest is open and the member's activity counts, accomplishes quests and opens those that follow, decides duels, takes
  back a cancelled activity's contributions, and forgets a period before a rebuild without telling again what is
  accomplished again. `QuestProjection::clearReplay()` forgets what the last reset kept for its replay.
- `Quests` and `Erasure`: acceptance — under a team for a team quest —, abandonment and taking up again, the status and
  the board of a member, the journal of accomplished quests, and the export and erasure of a member, `Erasure` telling
  what erasing a member removed.
- `DuelStatus`, `Duel`, `DuelStanding` and `Duels`: duels sent under the application's key, accepted, declined or
  withdrawn, lasting their quest's length or until it closes, offers that run out, the sweep of the duels whose time is
  up, and how far each side is.
- `ContributionStore`, `EnrolmentStore`, `CompletionStore` and `DuelStore`, with `InMemoryContributionStore`,
  `InMemoryEnrolmentStore`, `InMemoryCompletionStore` and `InMemoryDuelStore`: contributions written once per quest,
  step and activity, read by quest, members and period; enrolments per quest and member; the journal of accomplished
  quests; duels by key, those due and those reopened before a rebuild; each with its erasure of a member. The
  implementations in memory empty themselves with `reset()`, for a long-running process.
- `QuestsException`, implemented by `InvalidQuest`, `UnknownQuest`, `UnknownDuel` and `QuestRefused`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
