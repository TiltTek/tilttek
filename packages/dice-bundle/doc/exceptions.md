# Exceptions

The bundle raises the library's exceptions, which implement `Tilttek\Dice\Exception\DiceException`, and two of its own:

| Exception | Raised when | Message |
|---|---|---|
| `Application\Exception\UnknownRoll` | `replay()` names a roll the journal does not hold | `No recorded roll has the identifier "…".` |
| `Application\Exception\InvalidMacro` | `define()` gets a name that is not one, or a level the shelves give no owner for | `The shelves give no owner for the level "member".` |
| `Tilttek\Dice\Exception\InvalidExpression` | An input does not parse, names a macro that is not there, or a macro that does not parse or names itself | `No macro is named "sword" at position 2 of "1+$sword".` |
| `Tilttek\Dice\Exception\LimitExceeded` | An input or a roll goes past a limit; an input expands too many macros | `The macros an input expands are limited to 32; this asks for 35.` |
| `Tilttek\Dice\Exception\CannotRoll` | An unknown die, faces without value in arithmetic, a division by zero | `No die named "bone" is declared.` |
| `Tilttek\Dice\Exception\InvalidDefinition` | A configured face, die or notation is invalid, when the container is compiled; an unknown dialect | `Two faces of the die "coin" share an identifier.` |

`UnknownRoll` and `InvalidMacro` implement `DiceException` too. The Twig filters catch the library's exceptions: `dice_notation` gives
the input back, `dice_dialect` gives null.
