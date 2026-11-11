# Changelog

All notable changes to `tilttek/dice` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- The expression model: `Pool` of instructions with their comments, `Dice`, `ComputedDice` whose count or sides are
  rolled first, `ValueList`, `PreviousDice`, `Constant`, `Operation` with `Operator` — `+ - * / % ^` —, `Negation`,
  `FunctionCall` with `MathFunction` — `floor ceil round abs min max repeat`, every function but the three that round
  keeping the rounding of the roll —, and the dice `StandardDie`, `FudgeDie` weighted or not, `RangeDie` and
  `CustomDie`, numeric dice rolling their own faces.
- The language of instructions: `Reference` to the result of an instruction before or its item, `InstructionDice`
  taking the dice of instructions again, bound or merged, `Group` judging the totals of its expressions as dice,
  `TextList` with its `ListItem`s drawn by weight or range, unique or joined, `Sentence` with its `Placeholder`s and
  `PlaceholderKind`, `Conditional` and `Choice` with its `SwitchCase`s, exclusive or not, with a default branch and
  the scope of their conditions.
- `Modifiers`, applied in a fixed order: `Reroll` repeated or once (`Repetition`), unique dice, `Explosion` spread,
  compound or penetrating and limited by a number or a roll, dice all the same, floors and ceilings, `Transform`,
  filters, `Selection` with `SelectionMode`, successes and failures, `Occurrences`, groups reaching a sum, `Critical`
  marks, `Look` tokens and `Sort`; only numeric dice take modifiers, contradictory modifiers are refused, and a roll
  that would never end is refused.
- Validators: `Validator`, `Comparison` with `Comparator` — `!=` included —, `Range`, `Modulo`, `Composite` with
  `Junction` — `&`, `|`, `^` —, and `Condition` with its `Scope`: each final value, each die, all, any, the total.
- Custom dice: `Face` with its label, value, colour, `Shape` and image — never told by its colour alone —,
  `DieDefinition` and `CustomDice`; a roll tallies faces by symbol and sums them when they carry values.
- `Notation` and `Notations`: the letters of a language, German, Danish, Norwegian and Swedish built in, found by
  locale with a fallback on the language.
- `Lexer` and `DiceParser`: the notation read without regular expressions, letters in either case, `d%`, `dF` and
  `dF.1`, `d[a..b]`, `d{name}`, counts and sides between parentheses, lists `[…]`, `@`, `kh kl dh dl`, `k` and `d`
  alone, `adv` and `dis`, `r` and `ro`, `u` and `uo`, `!`, `!!`, `!p` and their limit, `a`, `t`, `min`, `max`,
  `t[…]`, `f[…]`, successes by comparison or `c[…]`, failures `f`, `o`, `g`, `cs`, `cf`, `look[…]`, `s`, `sa`, `sd`,
  validators between brackets with their scope, functions, integer arithmetic with parentheses, negation, remainder
  and power; instructions separated by `;` or `,`, `$1` and `$1[0]`, `@1`, `b[…]` and `m[…]`, groups `{…}`, text
  lists `L[…]` with `u`, `n`, weights and ranges, texts between double quotes with their escapes and placeholders,
  `i[…]{…}{…}`, `S[…]{…}` and `S^`, `repeat()`, comments `#`, `//` and between brackets, macros `$name` read as one
  token and resolved by `Contract\Macros` into their tree — a macro of several instructions written alone being the
  whole pool —; a text or a list refused in arithmetic, a reference only to an instruction before; errors with their
  position and reason; nesting bounded; a fuzz target.
- `RolisteamReader`: a command of Rolisteam's DiceParser read into the library's tree — `k`, `kl`, `K`, `r`, `R`, `e`
  and its limit, `a`, `c`, `s`, `sl`, `T[…]{…}`, `p[…]`, `o2,7`, the scopes `*`, `.`, `:` and `?` of a validator,
  and every construct both notations write alike —, its tokens written again in the library's notation, macros
  included, errors placed in the command as written, `y` and the merge or bind of every instruction refused; a fuzz
  target.
- `Limits`: dice, sides, explosions, rerolls, repetitions, instructions, length and the characters of a text, refused
  rather than truncated; rolled counts and sides checked once rolled, every repeated operator counted, the items drawn
  from text lists counted as dice, every branch counted when parsed.
- `NotationWriter` and `ExpressionWriter`: the canonical form of every construct, with every count written, the
  modifiers in the order they apply, no more parentheses than needed, the items of a text list quoted when they need
  it — and written alone by `writeItem()` — and the escapes a text needs.
- `Roller`, `Arithmetic` and the results `PoolResult`, `ExpressionResult`, `DiceResult`, `ConstantResult`,
  `OperationResult`, `NegationResult`, `FunctionResult`, `TextResult`, `ListResult`, `BranchResult`,
  `ReferenceResult` and `RolledDie` with `DieOrigin` and `Mark`: a result is a number, a text or a list, written by
  `Outputs`; every die kept with its rolls, every modifier applied, explosions, rerolls and repetitions counted across
  the pool, stable selections, occurrences listed, groups that reach a sum the most the dice form up to 12 dice and
  found greedily beyond with a `Notice`, instructions rolled in order and merged instructions giving no total, only the
  branch that holds rolled, integer arithmetic whose divisions round as the caller says — down by default — unless
  `floor()`, `ceil()` or `round()` say otherwise, overflow refused, plain arrays for a journal.
- `Seed`, `Commitment`, `FairRoller` and `VerifiableRoll`: verifiable rolls by seed commitment, a client seed and a
  nonce, on `Xoshiro256**` seeded by an HMAC-SHA256, verified with their rounding, the whole language covered.
- `Replayer` and `ReplayableRoll`: rolls with an identifier, a seed and a rounding, replayed die for die.
- `Dialect`, `Translation`, `Unsupported` and `Dialects`, with `Roll20`, `FoundryVtt`, `Avrae` and
  `RolisteamDiceParser`: a pool written in a table's notation — rerolls, explosions, floors, successes and failures,
  critical marks, sorting, lists, functions, remainders and powers where the table writes them the same —, one command
  per expression or, for DiceParser, every instruction in one command with references, branches, text lists,
  sentences, groups and `repeat()`, divisions written rounded down with a notice when the roll rounds another way, and
  what a dialect cannot write reported, the constructs of instructions included; `separator()` and `rangeDie()` for a
  dialect to override.
- `DiceRoller`, the library in one object: parsing with the macros given, reading a command of DiceParser, rolling and
  translating with the rounding the caller gives.
- `DiceException`, implemented by `InvalidExpression` with its input, position and reason, `LimitExceeded`,
  `CannotRoll`, `InvalidDefinition` and `UntranslatableExpression`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
