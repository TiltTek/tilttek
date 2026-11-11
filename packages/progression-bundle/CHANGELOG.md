# Changelog

All notable changes to `tilttek/progression-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

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
