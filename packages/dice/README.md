# tilttek/dice

**Dice notation parsed, checked, normalised and rolled — and every die kept to show how the result came.**

tilttek/dice reads a dice expression such as `4d6kh3`, `d20 adv`, `3d10!!`, `6d10>=7f1`, `8d10c[>=6]-@c[=1]`,
`ceil(1d6/3)`, `(1d8+2)*2, 1d4` or `8d10; @1c[>=6]; "$2 successes"`, refuses an invalid one with the position of the
error, writes it back in a canonical form, and rolls it. Instructions refer to each other, branch on a result, draw from
lists of texts and write sentences: a result is a number, a text or a list. A roll keeps each die, each reroll, each
explosion, what was kept or dropped, and each total. It also rolls custom dice whose faces are symbols, reads and writes
the notation of other languages (`2W6` in German), reads a command of Rolisteam's DiceParser, resolves the macros an
application keeps (`$attack`), writes an expression in the dialect of a virtual tabletop, and makes rolls that anyone
can verify or that replay die for die. It does not draw dice, animate them, keep a journal or know who rolls: a page, a
bundle or an application does that.

It is for any PHP code that rolls dice for people: a dice roller page, a play-by-post forum, a character sheet, a
virtual table, a game book. `tilttek/dice-bundle` integrates it with Symfony; an application without Symfony takes the
library alone.

It is a plain PHP library with no dependency beyond the `ctype`, `mbstring` and `sodium` extensions. Its randomness is
a `Random\Randomizer` the caller passes: a secure engine in production, a fixed seed in tests, the seed of a verifiable
or replayable roll. The parser is a hand-written lexer and recursive-descent parser, without regular expressions on
the input.

The design is small: a `DiceParser` gives a `Pool` of expression trees — a `RolisteamReader` too, from DiceParser's
notation —, a `Roller` rolls a pool into a `PoolResult`, a `NotationWriter` and the `Dialect` classes write a pool
back, `FairRoller` and `Replayer` make verifiable and replayable rolls, and `DiceRoller` puts them in one object.

## Status

| Part | State |
|---|---|
| Notation: dice, `d%`, Fudge dice weighted or not, dice on a range, custom dice, counts and sides rolled first, lists of values, the dice before, pools | Implemented and tested |
| Modifiers: rerolls repeated or once, unique dice, explosions spread, compound, penetrating or limited, dice all the same, floors and ceilings, transformations, filters, keep and drop, advantage, successes and failures, occurrences, groups, critical marks, look tokens, sorting | Implemented and tested |
| Validators: values, ranges, comparisons, remainders, `&`, `|` and `^`, and their scope | Implemented and tested |
| Integer arithmetic with the rounding the caller gives; `floor`, `ceil`, `round`, `abs`, `min`, `max`, `repeat`, remainder and power | Implemented and tested |
| Errors with their position; limits on dice, sides, explosions, rerolls, repetitions, instructions, length and texts | Implemented and tested |
| Canonical form and the notations of other languages (German, Danish, Norwegian, Swedish built in) | Implemented and tested |
| Rolls that keep every die; custom dice counted by symbol and summed when their faces count | Implemented and tested |
| Verifiable rolls by seed commitment; replayable rolls | Implemented and tested |
| Dialects: Roll20, Foundry VTT, Avrae and Rolisteam's DiceParser, with the constructs each cannot write reported | Implemented and tested |
| Instructions, references, the dice of instructions bound or merged, groups, text lists, sentences, `if` and `switch`, `repeat()`, comments | Implemented and tested |
| The reader of Rolisteam's DiceParser notation, fuzzed | Implemented and tested |
| Macros named by token, `$name`, resolved by the application | Implemented and tested |
| Exceptions under one `DiceException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Notation](doc/notation.md) | Every construct, the validators and their scope, the order of the modifiers, the canonical form, and the errors. |
| [Instructions and results](doc/instructions.md) | Instructions and references, groups, text lists, texts and sentences, branches, repetitions, comments, and the results that are not numbers. |
| [Rolling](doc/rolling.md) | `Roller`, the randomness, the results and what they keep, the repeated operators, the arithmetic and its rounding. |
| [Custom dice](doc/custom-dice.md) | Faces, labels, shapes and colours, the accessibility rule, tallies. |
| [Languages](doc/languages.md) | `Notation` and `Notations`: the letters of a language, read and written. |
| [Dialects](doc/dialects.md) | Writing a pool for Roll20, Foundry VTT, Avrae and Rolisteam's DiceParser, and adding a dialect. |
| [Rolisteam's DiceParser](doc/rolisteam.md) | Reading a DiceParser command into the library's tree, what is read and what is refused. |
| [Macros](doc/macros.md) | `$name` read as a token, the `Macros` contract, its rules and its errors. |
| [Verifiable and replayable rolls](doc/verifiable-and-replayable.md) | Seeds, commitments, client seeds and nonces; replay identifiers. |
| [Limits](doc/limits.md) | The bounds against abuse and where they are checked. |
| [Extension points](doc/extension-points.md) | What an application gives the library: macros, dialects, notations, custom dice, limits, randomness. |
| [Exceptions](doc/exceptions.md) | `DiceException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that rolls dice. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require tilttek/dice
```

```php
use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\Dice\DiceRoller;

$dice = new DiceRoller();
$pool = $dice->parse('4D6KH3, d20 adv');
$randomizer = new Randomizer(new Secure());

$pool->canonical();                                   // "4d6kh3, 2d20kh1"
$result = $dice->roll($pool, $randomizer);
$result->totals();                                    // [14, 17], for instance
$dice->translate($pool, 'roll20')->text();            // "/r 4d6kh3\n/r 2d20kh1"
$dice->write($dice->parse('2W6+1', 'de'));            // "2d6+1"
$dice->roll($dice->parse('1d6/3'), $randomizer, RoundingMode::PositiveInfinity)->totals(); // [2], rounded up
$dice->roll($dice->parse('1d20i[>=15]{"hit"}{"miss"}'), $randomizer)->outputs();         // ["miss"], for instance
```

## Requirements

PHP 8.5 or later, with the `ctype`, `mbstring` and `sodium` extensions.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
