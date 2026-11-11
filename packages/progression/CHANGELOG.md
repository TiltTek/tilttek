# Changelog

All notable changes to `tilttek/progression` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `Curve` and `Level`: the experience each level starts at, linear, quadratic, exponential or a table the application
  writes, 2 to 1000 levels and at most 10^15 experience; where an amount of experience stands — the level, the
  experience it started at, the next one, what is gained and missing, the percent through it.
- `Period`, `Cap`, `ExperienceRule` and `Gain`: experience per unit of an activity's quantity or per activity, for
  verbs by pattern and context conditions, capped per subject over a day, an ISO week, a month, a year or ever; the
  experience an activity brought a subject on a track, by a rule, in a season.
- `Season`, `SeasonSchedule`, `SeasonLength`, `CalendarSeasons` and `NamedSeasons`: seasons that follow the calendar by
  month (`2026-11`), quarter (`2026-q4`) or year (`2026`) in a timezone, or named and dated ones that never overlap and
  may leave time between them.
- `Subject`, `Milestone`, `Track` and `Tracks`: tracks whose experience goes to the member or to the gesture's object,
  with their rules, curve, seasons and milestones of experience or level; `Tracks::fromArray()` from a configuration,
  with the timezone of caps and calendar seasons; `Names`, the check of the names of tracks, rules, milestones, seasons
  and streaks — 1 to 64 lower-case letters, digits, `_`, `.` or `-`, starting with a letter.
- `StreakUnit`, `Grace`, `StreakRule`, `StreakRules`, `StreakStatus` and `StreakCalculator`: days or ISO weeks in a
  row, a grace of so many missed units in a window, frozen units, the current unit not over yet, the current and the
  best run, counted without visiting the units where nothing can change; `StreakRules::fromArray()` from a configuration.
- `TimezoneResolver`, `FixedTimezone` and `ContextTimezone`: the timezone a member's day is read in, one for all or
  the one the activity's context carries, written by the application from the member's profile.
- `LevelChange`, `ReachedMilestone` and `ProgressionObserver`: what the projection and the sweep tell — a level up or
  down, a milestone reached, a streak broken.
- `ExperienceStore`, `MilestoneStore` and `StreakStore`, with `InMemoryExperienceStore`, `InMemoryMilestoneStore` and
  `InMemoryStreakStore`: gains written once per track, rule and activity, totals by season as `Total`, the experience a
  rule gave in a period, totals before a rebuild; milestones reached once per subject and season; streak gestures once
  per streak and activity, frozen units, and the state that tells when a streak breaks; each with its erasure of a
  member. The implementations in memory empty themselves with `reset()`, for a long-running process.
- `StreakState`, `StreakGesture`, `StreakBroken` and `StreakKeeper`: the gestures recorded and the state brought up to
  date, a broken run told once — by a later gesture or by the sweep, in the member's timezone —, cancellations
  recounted silently, frozen and thawed units.
- `ProgressionProjection`: a projection of `tilttek/activity` that brings each activity's experience to its tracks in
  their seasons up to the caps, tells the levels crossed and the milestones reached, makes the members' streak units
  active, takes back what a cancelled activity brought, and forgets the experience and streak gestures of a period
  before a rebuild, telling then only the levels above those held before. `ProgressionProjection::clearReplay()` forgets
  what the last reset kept for its replay.
- `Progression`, `Standing`, `SeasonResult` and `Erasure`: standings on one or every track, the seasons past, the
  milestones reached, the gains for an export, streaks, frozen and thawed days, the sweep of the broken streaks, and
  the erasure of a member with the gains, milestones and streaks it removed.
- `ProgressionException`, implemented by `InvalidProgression`, `UnknownTrack` and `UnknownStreak`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
