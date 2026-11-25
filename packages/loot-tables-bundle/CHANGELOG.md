# Changelog

All notable changes to `tilttek/loot-tables-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-24

### Changed

- Requires the latest minor of each dependency: `doctrine/orm` 3.8, `doctrine/dbal` 4.6, `doctrine/doctrine-bundle` 3.4,
  `twig/twig` 3.31, `symfony/ux-twig-component` 3.8, `symfony/service-contracts` 3.8,
  `symfony/translation-contracts` 3.8.

### Fixed

- It requires `phil/commerce-bundle` 0.5, the version published the same day; its requirement of 0.2 or 0.3 kept it
  from installing beside the published versions.

## 0.1.0-alpha - 2026-11-20

### Added

- `TilttekLootTablesBundle` and its configuration: the tables and the boxes, read into a `LootCatalog` and checked when
  the container is compiled; whether a draw must be committed before the payment; whether the draws are committed
  when the order is placed; where the draws are kept; where the recipes of the UX kit are.
- `Drawers`: the drawers an option names to be drawn here, `loot-box:<key>` for a box of the catalogue and `loot-text`
  for a text drawn among the entries of its option, and the box each draws.
- `SecureSeeds`: the seeds of the draws, 32 bytes drawn from `Random\Engine\Secure`, the only engine it takes.
- `LootCommitments`: one draw committed per unit of each option drawn here when the order is placed, its seed kept
  secret and its commitment shown, the entries of a text kept as they were; committing again keeps the first.
- `CommitOnPlace`: the listener of the commerce core's order workflow that commits the draws as soon as the order is
  placed, turned off by `commit_on_place: false`.
- `LootOptionDrawer`: the commerce option drawer that reveals each unit once after payment, from the server's seed
  alone with the identifier of the draw as its client seed, the order line keeping the draw text of a box or the text
  drawn; a unit never committed is refused unless `require_commitment` is off.
- `PhysicalDice`: the physical dice of each unit entered at a register as shop codes, recorded on the order line and
  kept as draws of physical dice; the box and the number of units a form asks for.
- `LootResults`: the draws of an order and the draw of an identifier, the result of each with its entries, its shop
  code, its verification against the commitment shown, and the draw rolled again from the seed it revealed.
- `LootPrint`, `PrintedDraw` and `PrintState`: what is printed of each draw of an order, read from `LootResults` — its
  entries with their images and slots, the text drawn, the shop code, and where it stands.
- `ReplayDrawCommand`, `tilttek:loot-tables:replay`: a revealed draw rolled again from its seed, checked against what
  was kept and against its commitment.
- `PhysicalDiceType`, the form of the physical dice, one field per unit, each code read against the box before
  anything is entered, and the component `TilttekLootTables:PhysicalDice` that draws it on the register's page.
- The Twig component `TilttekLootTables:Commitments`: the draws of an order on the buyer's page without JavaScript,
  each a `Card` of the UX kit with a `Badge` of where it stands, its commitment, and once revealed its seed, its
  entries and its shop code; `KitComponents` and `KitSubdirectoryPass` name the recipes after the Toolkit's
  subdirectory; translations in English and French.
- `LootSheetPdf`: the production sheet of an order's draws in PDF, written from `LootPrint`, through
  `phil/paged-media` when it is installed.
- `LootDraw` and `LootDrawRepository`: the draws in the Doctrine table `tilttek_loot_draw`, their seeds published once
  revealed, or in memory for tests, reset with the kernel; `LootDrawRefused`.
- The Flex recipe: the bundle registered and its configuration copied.
- Documentation: installation, configuration, drawing orders, physical dice, results, screens, production sheet,
  exceptions, extension points, testing and reference.
- Community files: contribution guide, code of conduct, security policy and MIT licence.
