# Reference

| Type | Role |
|---|---|
| `DiceRoller` | The library in one object: parse, read DiceParser, write, translate, roll, roll verifiably, roll to replay |
| `Limits` | The bounds against abuse |
| `Parser\DiceParser` | An input into a `Pool`, in a notation, within the limits |
| `Parser\Lexer`, `Token`, `TokenType` | The input read character by character |
| `Reader\RolisteamReader` | A command of Rolisteam's DiceParser into a `Pool` ([Rolisteam's DiceParser](rolisteam.md)) |
| `Reader\RolisteamRewriter` | Internal: the command's tokens written again in the library's notation, and the positions kept |
| `Contract\Macros` | The macros an input names, `$name` ([macros](macros.md)) |
| `Expression\Pool` | One or more instructions and their comments; `canonical()` |
| `Expression\Node` | A node: `Constant`, `Dice`, `ComputedDice`, `ValueList`, `PreviousDice`, `Operation`, `Negation`, `FunctionCall`, `Reference`, `InstructionDice`, `Group`, `TextList`, `Sentence`, `Conditional`, `Choice` |
| `Expression\Dice` | A count, a die and its modifiers |
| `Expression\ComputedDice` | Dice whose count or sides are rolled first |
| `Expression\ValueList`, `PreviousDice` | Values taken as dice; the dice of the set before, judged again |
| `Expression\Operator` | `+ - * / % ^` and their precedence |
| `Expression\FunctionCall`, `MathFunction` | `floor ceil round abs min max repeat` and their arguments |
| `Expression\Reference`, `InstructionDice` | The result of an instruction before, or its item; the dice of instructions, bound or merged |
| `Expression\Group` | Expressions whose totals are judged as dice |
| `Expression\TextList`, `ListItem` | Items drawn from a list of texts, by weight or range |
| `Expression\Sentence`, `Placeholder`, `PlaceholderKind` | A text and the placeholders replaced when it is rolled |
| `Expression\Conditional`, `Choice`, `SwitchCase` | `if` and `switch`, their conditions and branches |
| `Expression\Modifiers` | The modifiers of a set of dice, their order and their checks |
| `Expression\Reroll`, `Repetition`, `Explosion`, `Transform`, `Selection`, `SelectionMode`, `Occurrences`, `Critical`, `Look`, `Sort` | The modifiers |
| `Expression\Validator`, `Comparison`, `Comparator`, `Range`, `Modulo`, `Composite`, `Junction` | Which values a modifier applies to |
| `Expression\Condition`, `Scope` | A validator and what it is checked against |
| `Die\DieType`, `NumericDie` | What a die is; the dice that take modifiers and roll their faces |
| `Die\StandardDie`, `FudgeDie`, `RangeDie`, `CustomDie` | Numbered, Fudge, range and custom dice |
| `Custom\Face`, `Shape`, `DieDefinition`, `CustomDice` | Custom dice declared by the application |
| `Notation\Notation`, `Notations` | The letters of a language |
| `Notation\ExpressionWriter`, `NotationWriter` | An expression written back, canonical or in a language |
| `Rolling\Roller` | A pool rolled with the randomness and the rounding given |
| `Rolling\Arithmetic` | Integer arithmetic, divisions rounded by a `RoundingMode` |
| `Result\PoolResult`, `ExpressionResult`, `NodeResult` | A roll, its outputs, its totals and its rounding |
| `Result\DiceResult`, `ConstantResult`, `OperationResult`, `NegationResult`, `FunctionResult` | The result of each node that gives a number |
| `Result\TextResult`, `ListResult`, `BranchResult`, `ReferenceResult` | A text, a list, a branch, a reference |
| `Result\Outputs`, `Notice` | Outputs written or flattened; what a roll could not do exactly |
| `Result\RolledDie`, `DieOrigin`, `Mark` | One die as it fell, and what became of it |
| `Fairness\Seed`, `Commitment` | A seed and its SHA-256 |
| `Fairness\FairRoller`, `VerifiableRoll` | Verifiable rolls and their verification |
| `Replay\Replayer`, `ReplayableRoll` | Rolls replayed die for die |
| `Dialect\Dialect`, `Dialects` | The notation of a virtual tabletop, and the registry |
| `Dialect\Roll20`, `FoundryVtt`, `Avrae`, `RolisteamDiceParser` | The built-in dialects |
| `Dialect\Translation`, `Unsupported` | A pool in a dialect, what it could not write, and its notices |
| `Exception\DiceException` and its five exceptions | [Exceptions](exceptions.md) |
