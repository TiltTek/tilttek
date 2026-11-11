# Changelog

All notable changes to `tilttek/dice-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `RecordedRoll`, the row of the table `tilttek_dice_roll` — identifier, canonical expression, seed, rounding, result,
  owner and date — and its port `RecordedRollRepository`, on Doctrine or in memory. The journal in memory empties itself
  when the kernel resets.
- `DiceRolls`: parse, write and translate in the reader's notation, read a command of Rolisteam's DiceParser, plain
  rolls from a secure source, replayable rolls kept and replayed with their rounding, verifiable rolls and their
  verification, each with the rounding the caller gives, a member's rolls and their erasure; `Replay` and
  `UnknownRoll`.
- `DiceMacro`, the row of the table `tilttek_dice_macro` — level, owner, name and expression —, `MacroLevel` and the
  port `DiceMacroRepository`, on Doctrine or in memory. The macros in memory empty themselves when the kernel resets.
- `DiceMacros` and `MacroShelves`: macros of a site, a table and a member, named `$name` and resolved by token into
  their tree, looked for on the member's shelf, then the table's, then the site's, the names of a macro from its own
  level on; a macro defined only when it parses, one that names itself refused, 32 expansions per input at most,
  removal, listing and erasure by owner; `InvalidMacro`.
- `DiceAppearance`, the extension point that dresses a member's dice, with `DieAppearance` and `NoDiceAppearance`.
- `TilttekDiceBundle`, alias `tilttek_dice`: limits — dice, sides, explosions, instructions, length, rerolls,
  repetitions and the characters of a text —, custom dice, notations, appearance service, translation domain, store and
  entity manager; the tables of the journal and the macros mapped for Doctrine.
- `DiceServices`: the library's `Limits`, `CustomDice`, `Notations`, `Dialects` and `DiceRoller`, a secure randomizer,
  the journal, the macros, the appearance, `DiceRolls` and `DiceMacros`; custom dice and notations built once when the
  container is compiled; dialects added by the `tilttek_dice.dialect` tag.
- `DiceExtension`: the Twig filters `dice_notation` and `dice_dialect`, the functions `dice_roll`, `dice_roll_detail`
  and `dice_text`, and the template of an accessible detail of a roll, with every set of dice of the roll and the
  texts and lists it gave, translated and escaped by the template itself, even where the application turns
  autoescaping off.
- The `DiceExpression` constraint and its validator, and the `DiceExpressionType` field.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
