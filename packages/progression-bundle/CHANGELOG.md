# Changelog

All notable changes to `tilttek/progression-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.2-alpha - 2026-11-25

### Security

- `twig/twig` older than 3.31.1 refused, for the security fixes of that version: its sandbox checks the policy in more
  places, and a template can no longer replace the content of a `Markup`.

## 0.2.1-alpha - 2026-11-24

### Changed

- Requires the latest minor of each dependency: `doctrine/orm` 3.8, `doctrine/dbal` 4.6, `doctrine/doctrine-bundle` 3.4,
  `twig/twig` 3.31.

## 0.2.0-alpha - 2026-11-14

### Changed

- `DbalExperienceStore` and `DbalMilestoneStore` implement the notices of `tilttek/progression` 0.2: a `delivered`
  column on `tilttek_progression_gain` and `tilttek_progression_milestone`, true by default for the rows already there,
  false on insertion and turned true once the observer was told; `ofMember()` reads a member's rows for the export.
  An existing table gains the column through the application's next migration.
- `LevelUp`, `ReachedMilestone` and `StreakBroken` are dispatched at least once: a listener that failed is called again
  when the projection is handed the entry again, with the subject's next activity, or through
  `ProgressionProjection::deliver()`. Listeners must be idempotent.
- The bundle requires `tilttek/progression` `^0.2@dev`.

### Fixed

- The export of a member lists the gains and the milestones their gestures brought to a character, which the erasure
  already deleted.
- `ProgressionPrivacy` implements `Phil\PrivacyBundle\Application\Contract\PersonalDataExporter` and `PersonalDataEraser`,
  the interfaces `phil/privacy-bundle` exposes, and the bundle registers it when they exist: the export and the
  erasure of a member now reach it.

## 0.1.0-alpha - 2026-11-11

### Added

- `DbalExperienceStore`, `DbalMilestoneStore`, `DbalStreakStore` and `Rows`: the three stores on Doctrine DBAL,
  each row written once by a statement that writes nothing on a duplicate, totals by sums, dates in UTC.
- `ProgressionTables` and `ProgressionSchemaListener`: the five tables of the gains, the milestones, the streak
  gestures, the frozen units and the streak states, added to the schema the ORM generates for their connection.
- `TilttekProgressionBundle`: the `tilttek_progression` configuration — the store, the connection, the timezone, the
  tracks with their curves, subjects, seasons, rules and milestones, the streaks with their unit, grace and context,
  the context key of a member's timezone, the projection's name, the translation domain and the kind of a member —,
  the names kept as written, checked when the container is compiled.
- `ProgressionServices`: the stores on Doctrine DBAL or in memory, the tracks, the streaks, the timezone resolver,
  `StreakKeeper`, `Progression` and the projection tagged `tilttek_activity.projection`. The stores in memory are tagged
  `kernel.reset`. The projection forgets its replay when the kernel resets.
- `DispatchingObserver`, `LevelUp` and `LevelDown`: the level changes, the milestones reached and the broken streaks
  as events of the application's dispatcher.
- `ProgressionExtension` and the templates `bar.html.twig` and `streak.html.twig`, with their English and French
  translations.
- `SweepCommand` (`tilttek:progression:sweep`) and `ShowCommand` (`tilttek:progression:show`).
- `ProgressionPrivacy`: the export and erasure of a member's progression for `phil/privacy-bundle`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
