# Macros

An input may name macros: `$attack + 2` takes the tree of the macro `attack` as an operand. The parser reads the name
as one token — a `$` and the letters and digits written against it, lower-cased —, and asks the `Macros` it was given
for the macro's pool; it never replaces text, so that a macro `a` leaves `aa`, `$aa` and `2d6a10` alone, and a macro
keeps its own parentheses: `$attack*2` is `(1d20+5)*2` when `attack` is `1d20+5`.

```php
use Tilttek\Dice\Contract\Macros;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Expression\Pool;

final class SheetMacros implements Macros
{
    public function __construct(private DiceRoller $dice) {}

    public function pool(string $name): ?Pool
    {
        return 'attack' === $name ? $this->dice->parse('1d20+5', macros: $this) : null;
    }
}

$dice = new DiceRoller();
$dice->write($dice->parse('$attack*2', macros: new SheetMacros($dice)));   // "(1d20+5)*2"
```

| Rule | Example |
|---|---|
| A name is a letter, then letters and digits, 32 at most | `$attack`, `$Stealth2` is `stealth2` |
| A macro of one instruction is an operand anywhere an operand is | `1d20i[>10]{$attack}` |
| A macro of several instructions is the whole input, written alone | `$init` is `1d20, $1+2` |
| An unknown name is refused at its `$` | `No macro is named "sword" at position 2 of "1+$sword".` |
| A macro that does not parse is refused with its reason | `The macro "broken" does not parse: Unexpected "x"` |
| `$` followed by digits stays a reference | `$1` |

Without `Macros`, `$attack` is refused as it always was. `Macros::pool()` resolves the macros a macro names, and raises
`InvalidExpression` for one that names itself; the pool the input gives is checked against the
[limits](limits.md) once its macros are in it. `tilttek/dice-bundle` keeps macros for a site, a table and a member.
