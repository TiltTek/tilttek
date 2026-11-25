# Changelog

All notable changes to `tilttek/leaderboards-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-24

### Changed

- The development requirements accept `phil/privacy-bundle` 0.2, its latest published minor version, and no earlier
  one, so that the package installs on its own beside the published version of that bundle.
- Requires the latest minor of each dependency: `doctrine/orm` 3.8, `doctrine/dbal` 4.6, `doctrine/doctrine-bundle` 3.4,
  `twig/twig` 3.31, `symfony/ux-twig-component` 3.8.

## 0.1.0-alpha - 2026-11-15

### Added

- `LeaderboardsTables` and `LeaderboardsSchemaListener`: the nine tables of the contributions, the scores, the
  snapshots, their lines, the members' choices of appearance, the tiers, the memberships and the settled weeks of the
  leagues, and the leads taken, added to the schema the ORM generates for their connection.
- `DbalScoreStore::keep()`: a reported score inserted when the member holds none, or else written by an `UPDATE`
  whose condition is the comparison of `tilttek/leaderboards`, so that two reports at the same moment never let the
  older or lower one win.
- `DbalScoreStore`, `DbalSnapshotStore`, `DbalAppearanceStore`, `DbalLeagueStore`, `DbalLeadStore` and `Rows`: the
  five stores on Doctrine DBAL, a contribution, a snapshot, a membership and a lead written once by a statement that writes nothing on a
  duplicate, a score and its tie-breaks replaced in one transaction, the first scores read in the order of the board's
  ranking among everyone or a circle, the contributions older than a window taken out, a member placed among their
  cohort, a week settled once in one transaction, the moments in columns of the type `datetime_utc_immutable`, the
  column `placing` quoted by the platform; tested on SQLite, MySQL 8.4 and PostgreSQL 17.
- `TilttekLeaderboardsBundle`: the `tilttek_leaderboards` configuration — the store, the scores in Valkey, the
  connection, the timezone, the boards with their measures, verbs, context conditions, periods, sliding windows,
  timezones, seasons, ties, tie-breaks, snapshot sizes, leagues and how their divisions are formed, rankings by object,
  titles and the luck of teams, the projection's name, the schedule, the secret of the luck, the subdirectory of the
  kit's recipes, the celebration and when a first place is told — `celebrate_on`, `period_close` by default or
  `score_change` —, the translation domain and the kind of a member —, the boards' names
  kept as written, checked when the container is compiled.
- `LeaderboardsServices`: the stores on Doctrine DBAL or in memory, the scores in Valkey on a client Symfony Cache
  connects from a DSN or on the application's own client named by `valkey.client` — the redis extension, Relay,
  Predis or valkey-glide-php —, with common commands only and no persistence command, the boards, `Leagues`, `Leaderboards` and the projection tagged `tilttek_activity.projection`, given the
  clock. The stores in memory are tagged `kernel.reset`.
- `DispatchingObserver`, `SnapshotTaken`, `LeagueSettled`, `TopReached`, `Summit` and `CelebrateOn`: a snapshot
  taken, a week of a league settled and a member who reached the top — first of a snapshot once the period closed, in
  the lead of the period under way as their score changed, or promoted into the highest tier — as events of the
  application's dispatcher, once per board, period and member, each carrying how the member chose to appear.
- `LeadWatch`, `Lead`, `LeadStore` and `InMemoryLeadStore`: with `celebrate_on: score_change`, the store of scores
  that watches the lead — a score kept in the period under way that puts its member first among at least two, when
  they were not first before —, the leads claimed once per board, period and member, so that a rebuild tells nothing
  new; a reported score goes through the watched store's `keep()`, and takes the lead only when it was kept.
- `LeaderboardsExtension`, `LeagueView`, `CelebrationView`, `KitComponents`, `KitSubdirectoryPass` and the templates
  `standing.html.twig`, `division.html.twig`, `league.html.twig`, `teams.html.twig` and `celebration.html.twig`: a
  standing, a snapshot, a division, a member's league screen, a ranking of teams and the celebration of a member who
  reached the top — the last three built of the recipes `Card`, `Badge`, `Empty` and `Celebration` of a UX Toolkit kit,
  named after the Toolkit's subdirectory; the celebration accessible, its message in a polite live region, no motion
  where it is reduced, no sound, shown to the member alone, configured by `celebration`, after the close of the period
  or while the member leads the period under way —, the titles a member holds, with their English and French translations.
- `SnapshotCommand` (`tilttek:leaderboards:snapshot`), `SettleCommand` (`tilttek:leaderboards:settle`), `SlideCommand`
  (`tilttek:leaderboards:slide`) and `ShowCommand` (`tilttek:leaderboards:show`).
- `LeaderboardsSchedule`, `TakeDue` and `TakeDueHandler`: the schedule `tilttek_leaderboards` on Symfony Scheduler,
  which takes the snapshots, settles the weeks and slides the windows due at the configured frequency.
- `LeaderboardsPrivacy`: the export and erasure of a member's scores, lines, choice, tiers, memberships and leads for
  `phil/privacy-bundle`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
