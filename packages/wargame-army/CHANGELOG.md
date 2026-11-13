# Changelog

All notable changes to `tilttek/wargame-army` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `Catalogue`, `UnitEntry`, `OptionGroup`, `Option`, `DetachmentType` and `Slot`: the data of a game apart from the
  engine — up to 2 000 units with their categories, points, models and option groups, up to 50 detachment types with
  their slots by category, the detachments an army takes and up to 200 declared constraints —, read from and written
  to arrays and JSON of at most 4 MiB, every fault an `InvalidCatalogue`.
- `ArmyList`, `Detachment` and `UnitSelection`: a list with its catalogue and version, its points limit and up to 20
  detachments of up to 200 units, changed by new lists, read from and written to arrays and JSON, every fault an
  `InvalidArmyList`.
- `Costing`: the points of each unit — its own, its models beyond the fewest and its options, for the unit or each
  model —, of each detachment and of the list, and the count and points of the units a selector takes.
- `ArmyValidator`, `Validation`, `Violation` and `Rule`: a list checked against its catalogue one declared rule at a
  time — catalogue and version, detachments, types, slots, models, options, option groups, points limit, constraints —,
  every violation returned with a sentence people read and the values it was built from.
- `Constraint\UnitCount`, `Constraint\PointsShare`, `Constraint\Selector`, `Constraint\Scope`,
  `Contract\Constraint` and `Contract\DeclaredConstraint`: counts of units in the army or each detachment, shares of the
  points limit compared exactly, and the application's own constraints added to the validator.
- `Export\TextExport` and `Export\HtmlExport`: a list as plain text and as a fragment of escaped HTML, with its
  violations when a validation is given.
- `Contract\CatalogueProvider` and `Catalog\InMemoryCatalogueProvider`: the port to the application's catalogues and its
  adapter in memory.
- `Identifiers`: the shapes of codes, versions, identifiers and names.
- No publisher's catalogue is bundled or imported: the application provides its own, as
  `doc/catalogues-and-licences.md` says.
- The fuzz targets of a catalogue and a list, and the benchmarks of the largest list.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
