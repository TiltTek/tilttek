# Changelog

All notable changes to `tilttek/quests-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.3.0-alpha - 2026-11-17

### Changed

- `Tilttek\Quests\Contract\TeamMembership` is a service of the bundle, given to `Quests` for `tilttek/quests` 0.3:
  the library's `NoTeamMembership`, which refuses every team quest, until the application aliases the interface to
  its own service. An application that uses team quests aliases it before upgrading.
- It requires `tilttek/quests` 0.3.

## 0.2.0-alpha - 2026-11-14

### Changed

- `DbalDuelStore::transition()`: an `UPDATE … WHERE status = ?` that changes a duel only from the status it was read
  in, for `tilttek/quests` whose answers, expiries and wins no longer overwrite one another.
- The bundle requires `tilttek/quests` `^0.2@dev`, whose `Store\DuelStore::transition()` `DbalDuelStore` implements.

### Fixed

- `QuestsPrivacy` implements `Phil\PrivacyBundle\Application\Contract\PersonalDataExporter` and `PersonalDataEraser`,
  the interfaces `phil/privacy-bundle` exposes, and the bundle registers it when they exist: the export and the
  erasure of a member now reach it.

## 0.1.0-alpha - 2026-11-11

### Added

- `AlmanacSchedule` and `AlmanacScheduleReader`: quests that come back at dates `phil/almanac` computes — from a rule,
  until another rule or for so many days —, each occurrence named by the date it opens on.
- `DbalContributionStore`, `DbalEnrolmentStore`, `DbalCompletionStore` and `DbalDuelStore`: the four stores of the
  library on Doctrine DBAL, dates in UTC.
- `DbalCatalog`: quests kept in the database as JSON definitions, read by the bundle's reader, chains checked on save
  and removal.
- `QuestsTables`, `QuestsSchemaListener` and `Rows`: the five tables — contributions, enrolments, completions, duels,
  definitions — added to the schema the ORM generates for their connection, and the insertion written once per
  platform.
- `TilttekQuestsBundle` and its configuration `tilttek_quests`: the store — Doctrine DBAL or memory —, the connection,
  the timezone of the quests' dates, the catalog — configured or kept in the database, not both —, the quests checked
  when the container is compiled, the lifetime of a duel offer, the name of the projection, the translation domain and
  the kind of a member for the privacy module. The stores in memory are tagged `kernel.reset`. The projection forgets
  its replay when the kernel resets.
- `QuestsServices`: the stores, the reader, the catalog, `Quests`, `Duels`, and the projection tagged for
  `tilttek/activity-bundle`, with the schema listener, the commands and the Twig functions when their components are
  installed.
- `DispatchingObserver`, `DuelWon` and `DuelExpired`: the library's `QuestCompleted` dispatched as it is, with the
  rewards and their members, and the duels won and expired.
- `QuestsExtension` and the templates `list.html.twig` and `detail.html.twig`, with their texts in English and French.
- `ListCommand`, `ShowCommand` and `ExpireCommand`: `tilttek:quests:list`, `tilttek:quests:show` and
  `tilttek:quests:expire`.
- `QuestsPrivacy`: the export and erasure of a member for `phil/privacy-bundle`, module `tilttek_quests`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
