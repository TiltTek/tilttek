# Changelog

All notable changes to `tilttek/dice-tower` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-24

### Changed

- Requires the latest minor of each dependency: `doctrine/orm` 3.8, `doctrine/dbal` 4.6, `twig/twig` 3.31,
  `symfony/ux-twig-component` 3.8, `symfony/stimulus-bundle` 3.8.
- `tilttek/dice` 0.3 required: 0.2 is no longer accepted.

## 0.1.0-alpha - 2026-11-20

### Added

- `TilttekDiceTowerBundle` and its configuration: the history a viewer reads at once, the prefix of the topics, the
  service that says who may watch a table, the Mercure hub and its name, the message bus of the relays, the store and
  entity manager of the log, the subdirectory of the UX kit's recipes; `TilttekDiceBundle` required, whose journal
  holds the rolls.
- The log of the towers: `TowerDrop`, mapped for Doctrine in `tilttek_dice_tower_drop`, its position given by the
  database; `TowerDropRepository`, in Doctrine or in memory, emptied between two requests of a long-running process.
- `DiceTower`: an expression rolled in the journal and dropped in a table's tower, or a roll of the journal dropped by
  its identifier; the last entries of a table, or those after a position, as many as the history, a roll erased from
  the journal left out; the drops of a roller erased.
- `TowerEntry` and `RollSummary`: an entry as everyone at the table sees it, with identifiers and the bounded summary of
  its result only — each instruction's expression, total and output, and each set of dice with its custom die and
  every die's value, face and whether it counted.
- The private topic of each table, `TowerTopics`; `MercureTowerPublisher`, which pushes each entry to it and logs a
  hub that cannot be reached; `TowerSubscriptions`, which gives an allowed viewer the topic of the table only, in the
  authorization cookie, and the URL to subscribe to.
- `TowerEntriesController` and its route, `GET /dice-tower/{table}/entries?after=`, for allowed viewers only, never
  cached.
- The ports `TableAccess`, `TowerPublisher` and `TowerRelay`; `NoTableAccess`, which lets nobody watch, and
  `NoTowerPublisher`; `TowerRelays`, which hands each entry to every relay, at once or through a message bus as a
  `RelayDrop` of the table and the position, read again from the log by `RelayDropHandler`.
- Twig: `dice_tower()`, `dice_tower_topic()` and `dice_tower_kit()`; the overlay, a card of the UX kit with the last
  entries as a log, each face by its label and each die that did not count said in words; translations in English and
  French.
- The Stimulus controller of the overlay: each new entry added once and in order, the missed entries read from the log
  when the subscription opens, the live entries held back meanwhile, and each entry dispatched as
  `tilttek--dice-tower--tower:drop`.
- The Flex recipe in `recipe/0.1`: the bundle, `config/packages/tilttek_dice_tower.yaml` and the route of the missed
  entries.
- Documentation: installation, configuration, the tower in PHP, the overlay, extension points, testing and reference.
- Community files: contribution guide, code of conduct, security policy and MIT licence.
