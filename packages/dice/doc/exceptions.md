# Exceptions

Every exception of the library implements `Tilttek\Dice\Exception\DiceException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidExpression` | An input or a command of DiceParser does not parse, or names a macro that is not there; `input` and `position` say where, `reason` says why | `Unexpected "x" at position 3 of "2d6x".` |
| `LimitExceeded` | An input or a roll goes past a limit | `The dice of a roll are limited to 100; this asks for 120.` |
| `CannotRoll` | A die is unknown, faces without value enter arithmetic, rolled dice cannot be rolled, `@` has no set before it, a text or a list where a number is computed, an item or an instruction that is not there, a rolled count a list or `repeat()` cannot use, a division by zero, an overflow | `No die named "bone" is declared.` |
| `InvalidDefinition` | A face, a die, a modifier, a validator, a function, a notation, limits, a seed or a commitment is invalid; an unknown dialect | `The face "red" is told by its colour alone: give it a shape or an image too.` |
| `UntranslatableExpression` | The text of an incomplete translation is asked for | `avrae cannot write 4dF.` |
