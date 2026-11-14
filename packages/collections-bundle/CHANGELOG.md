# Changelog

All notable changes to `tilttek/collections-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

### Changed

- `tilttek/dice-bundle` 0.2 accepted as well as 0.1.

## 0.1.0-alpha - 2026-11-11

### Added

- `AlmanacDateRule`: an item of the day on the dates a rule of `phil/almanac` computes, for 1 to 366 days.
- `CollectionsTables`, `CollectionsSchemaListener` and `Rows`: the six tables — copies, completions, openings, choices,
  daily, definitions — added to the schema the ORM generates for their connection, the insertion that writes nothing
  on a duplicate per platform, and the reading of texts, integers, booleans, dates, lists and references.
- `DbalCopyStore`, `DbalCompletionStore`, `DbalOpeningStore`, `DbalChoiceStore` and `DbalDailyStore`: the library's
  stores on DBAL, a copy reassigned by an update that names its owner.
- `DbalCatalog`: the catalog in `tilttek_collections_definition`, each definition as JSON in the order it was first
  defined, the whole catalog checked before a definition is written or removed.
- `TilttekCollectionsBundle`: the `tilttek_collections` configuration — store, connection, timezone, locale, catalog
  in configuration or in the database (not both), rarities, items, sets and packs by code with their keys kept as
  written, the item of the day (enabled, verbs, a secret salt of 32 bytes or more or one derived from `kernel.secret`, sets,
  fixed and computed dates, projection), translation domain
  and member kind —, and the privacy module when `phil/privacy-bundle` is installed.
- `CollectionsServices`: the five stores on DBAL or in memory, the catalog checked when the container is compiled or
  read from the database, the timezone and every date checked too, `Collections`, `Packs` with secure seeds,
  `DiceShelf`, `DailyCalendar`, `Daily`, `MemberData`, the projection tagged for `tilttek/activity-bundle` when the item
  of the day is enabled, and the inventory, dice appearance, commands and Twig functions when their packages are
  installed. The stores in memory are tagged `kernel.reset`.
- `CollectionsInventory`: the copies as the inventory of `tilttek/exchange`, a refusal of the collections turned into
  its `NotOwned`.
- `CollectionDiceAppearance`: the die a member chose as the appearance `tilttek/dice-bundle` shows, named in the
  configured locale.
- `DispatchingObserver` and `CopyGranted`: a copy given and the library's `SetCompleted` dispatched as events.
- `CollectionsExtension` and the templates `album.html.twig`, `daily.html.twig` and `odds.html.twig`, with their
  English and French translations.
- `DailyCommand`, `GrantCommand` and `AlbumCommand`: `tilttek:collections:daily`, `tilttek:collections:grant` and
  `tilttek:collections:album`.
- `CollectionsPrivacy`: the module `tilttek_collections` of `phil/privacy-bundle`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
