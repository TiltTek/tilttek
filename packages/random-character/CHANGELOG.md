# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/random-character` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `CreationRules`, `Stats`, `StatMethod`, `Field` and `FieldKind`: the creation rules of a system, with up to 32
  attributes filled by a roll each, a rolled array or a fixed array dealt at random, and up to 64 fields drawn from a
  generator of `tilttek/generator` or rolled with `tilttek/dice`, up to 16 values each, unique on demand.
- Generators named by the fields before: `names.{lineage}` and `names.{lineage.key}`, checked when the rules are built
  and when the character is rolled.
- `RulesCatalogue`: up to 1,000 rules, by key and by system.
- `CharacterRoller`: rules checked against the systems of `tilttek/rpg-systems` and the generators, every problem
  listed; characters rolled within the bounds of the system's attributes, from a seed given or drawn, each part from a
  seed of its own.
- `RandomCharacter`: the character with its seed, its attributions each once, and its edits marked by value.
- `Contract\CharacterObserver` and `Event\CharacterRolled`: each roll told by the keys of the rules and of the system.
- `Format\RulesReader` and `Format\Syntax`: the YAML and JSON documents of the format `tilttek-random-character/1`,
  every refusal at its place.
- Exceptions under one `RandomCharacterException` interface.
- The example `examples/lanterns.yaml` and its tables, a fuzz target of the reader and benchmarks of a roll.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
