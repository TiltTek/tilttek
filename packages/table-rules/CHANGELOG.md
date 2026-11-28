# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/table-rules` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Rule`: a house rule of a table, its identifier kept from one version to the next, a title of one line up to 200
  characters, a plain text up to 10,000 characters on 100 lines, its line breaks and blanks normalised, and an optional
  category; valid UTF-8 without control characters nor characters that reorder the text shown.
- `RulebookVersion`: a published version, numbered from 1 to 10,000, of up to 200 rules in reading order, 100,000
  characters of text in all, up to 100 variants of the game system kept sorted, the system, a note up to 2,000
  characters, its author and moment, and whether it requires acceptance; the first always does.
- `Rulebook`, `Acceptance`, `Decision` and `AcceptanceState`: the current version, the last version that required
  acceptance, and the last decision of up to 500 players, kept at a revision; each player pending, accepted, declined,
  or outdated when the rules changed since the version accepted.
- `TableRules::publish()`: the next version of a table's rules, holding from now on, refused when it holds the rules,
  variants and system that already hold; the variants checked against a `Contract\VariantCatalogue` when one is given,
  such as `Catalogue\SystemVariantCatalogue`, the rule variants of the `tilttek/rpg-systems` systems.
- `TableRules::accept()`, `decline()` and `forget()`: a player's answer to the version they read, refused once another
  holds, an answer given again changing nothing; a player's decision forgotten.
- `TableRules::version()` and `diff()`: a version by its number, and what changed between two versions.
- `Diff\RulebookDiffer`, `Diff\RulebookDiff`, `Diff\RuleChange`, `Diff\LineChange` and `Diff\LineKind`: the rules added,
  removed, moved and changed, line by line within a text, the variants chosen and dropped, and the system before and
  after, by the longest common subsequence, bounded by the bounds of a version.
- `TableRules::status()` and `roster()`, with `View\PlayerStatus` and `View\RosterView`: where a player stands and what
  changed since the version they answered; where up to 500 players stand and how many in each state, as plain data.
- The events `RulebookPublished`, `RulesAccepted`, `RulesDeclined` and `PlayerForgotten`, by identifiers only, told once
  to a `Contract\TableRulesObserver`.
- `Contract\RulebookStore`, keeping the rulebook at a revision with each version published, and
  `Store\InMemoryRulebookStore` for tests.
- The exceptions `InvalidRules`, `RulesRefused`, `RulesNotFound` and `RulesConflict`, under `TableRulesException`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
