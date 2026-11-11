# Extension points

| Point | What an application gives | Where |
|---|---|---|
| `Contract\Macros` | The macros an input names, `$name`, resolved into their pool | [Macros](macros.md) |
| `Dialect\Dialect` | The notation of another table, added to `Dialects` or replacing a built-in one | [Dialects](dialects.md#adding-a-dialect) |
| `Notation\Notation` | The letters of another language, added to `Notations` | [Languages](languages.md) |
| `Custom\DieDefinition` | Dice whose faces are symbols, declared in `CustomDice` | [Custom dice](custom-dice.md) |
| `Limits` | The bounds against abuse | [Limits](limits.md) |
| `Random\Randomizer` | The randomness of a roll: a secure engine, a seed | [Rolling](rolling.md) |

Every point is passed to `DiceRoller` or to the call that uses it; the library keeps nothing between two calls.
