# Dialects

A virtual tabletop reads some constructs differently from the library's notation. A `Dialect` writes a pool its way,
and reports what it cannot write rather than writing it wrong.

```php
$translation = $dice->translate($dice->parse('4d6ro1, 6d10>=7'), 'roll20');
$translation->isComplete();   // true
$translation->text();         // "/r 4d6ro1\n/r 6d10>7"
```

A `Translation` holds one expression per expression of the pool, since a table rolls each with its own total — but for
DiceParser, which rolls them in one command; `commands()` puts the table's roll command before each, `text()` joins
them by lines. When a construct cannot be written, `unsupported()` lists it — in the library's notation, with the
reason — and `commands()` and `text()` raise `UntranslatableExpression`.

| Construct | Roll20 | Foundry VTT | Avrae |
|---|---|---|---|
| Command | `/r ` | `/r ` | `!r ` |
| Reroll once, `ro1`, `ro<3` | `ro1`, `ro<2` | `r=1`, `r<3` | `ro1`, `ro<3` |
| Repeated reroll, `r1`, `r<3` | `r1`, `r<2` | `rr=1`, `rr<3` | `rr1`, `rr<3` |
| Explosion, `!`, `!>=5` | `!`, `!>5` | `x`, `x>=5` | `e6`, `e>4` |
| Compound, penetrating, `!!`, `!p` | `!!`, `!p` | reported | reported |
| Explosion once, `!(1)` | reported | `xo` | reported |
| Floor, ceiling, `min3`, `max5` | reported | `min3`, `max5` | `mi3`, `ma5` |
| Keep, drop | `kh kl dh dl` | `kh kl dh dl` | `kh kl ph pl` |
| Successes, `>=7` | `>7` | `cs>=7` | reported |
| Failures, `f1` | `f1` | `df=1` | reported |
| Critical marks, `cs>19`, `cf` | `cs>20`, `cf1` | reported | reported |
| Sort, `sa`, `sd` | `sa`, `sd` | reported | reported |
| A list of values, `[10,25]kh1` | `{10,25}kh1` | `{10,25}kh1` | `(10, 25)kh1` |
| Fudge dice | `dF` | `dF` | reported |
| Division | `floor(a/b)` | `floor(a/b)` | `a//b` |
| `ceil()`, `round()` | `ceil(a/b)`, `round(a/b)` | `ceil(a/b)`, `round(a/b)` | the argument alone, its divisions reported |
| `floor()` | `floor(x)` | `floor(x)` | the argument alone |
| `abs()` | `abs(x)` | `abs(x)` | reported |
| `min()`, `max()` | reported | `min(a, b)`, `max(a, b)` | reported |
| Remainder, `a%b` | `a%b` | `a%b` | `a%b` |
| Power, `a^b` | `(a**b)` | `(a**b)` | reported |

Roll20's comparison points include their threshold (`>5` is 5 or more), so a strict comparison is written one step
further; Avrae's selectors exclude theirs. A critical mark without a face is written on the highest or the lowest face.

The arithmetic of a table is not always the library's, and a dialect writes only what gives the same result:

- A division is written rounded down, as a roll does by default. `translate($pool, $rounding)` takes the rounding of
  the roll: when it is another one, the division is still written rounded down, and `Translation::$notices` says so —
  `1d6/2 is written rounded down; the roll rounds it PositiveInfinity.` —, the translation staying complete. Inside
  `ceil()` and `round()`, a division is written rounded their way.
- Roll20 and Foundry round halves up, the library away from zero: a division inside `round()` is written only when
  neither operand can be negative, as far as the expression shows.
- Roll20 and Foundry's `%` keeps the sign of the dividend, the library's the sign of the divisor: a remainder is
  written plainly when neither operand can be negative, through a second remainder — `(a%b+b)%b` — when the divisor
  is a constant, and reported otherwise. Avrae's `%` has the sign of the divisor already.
- A power is written when its exponent cannot be negative, since the table does not round `2**-1`.

Every other construct of the library — repeated unique dice, dice all the same, transformations, filters, validators
other than a plain comparison, scopes, occurrences, groups, look tokens, dice on a range, weighted Fudge dice, a count
or sides rolled first, the dice before, limits of explosions beyond what a table writes — is reported by Roll20, Foundry
VTT and Avrae, and so are the constructs of [instructions](instructions.md): references, the dice of instructions,
groups, text lists, sentences, branches and `repeat()`. The dialects follow each table's public dice notation; a table
that changes its notation changes its dialect.

## Rolisteam's DiceParser

The dialect `diceparser` writes the notation [read](rolisteam.md) by `readRolisteam()`. DiceParser rolls every
instruction of a command together, so a translation holds one expression, the instructions separated by `;`, after
the command `!`; references, the dice of one instruction, groups, text lists, sentences, branches and `repeat()` are
written.

```php
$translation = $dice->translate($dice->parse('4d6dl1, $1+2, 3d10!!10(1)'), 'diceparser');
$translation->text();   // "!4d6k3;$1+2;3d10a[10]"
```

| Construct | DiceParser |
|---|---|
| Reroll once, repeated | `r[1]`, `R[<3]` |
| Explosion, limited, on the highest face | `e[>8]`, `e(3)[10]`, `e[10]` |
| Compound explosion once | `a[10]` |
| Unique dice, dice all the same | `u`, `t` |
| Transformations | `T[>5]{15}[<=2]{-1}` |
| Filter, successes | `f[*>1]`, `c[>=7]` |
| Keep | `k3`, `kl1` |
| Drop, for a known number of dice | `4d6dl1` is `4d6k3` |
| Occurrences from a height | `o3,7` |
| Groups, look tokens, sorts | `g10`, `p[2:accent]`, `s`, `sl` |
| Fudge dice, dice on a range | `d[-1..1]`, `d[0..9]` |
| Scope of a condition | `*` all, `.` any, `:` the total |
| Division | `floor(a/b)`, `ceil(a/b)`, `round(a/b)` |
| Power | `(a^b)` |

It reports a unique reroll once, a compound explosion more than once, a penetrating explosion, an explosion of sides
rolled first without its face, floors and ceilings, failures, critical marks, a condition on each die as it fell,
occurrences judged otherwise than from a height, dropped dice whose number the expression does not show, the dice of
several instructions, weighted Fudge dice, custom dice, a remainder that may be negative, a power whose exponent may
be negative, and `round()` of a division that may be negative. What it writes reads back to the same text.

## Adding a dialect

Extend `Dialect`: give it a `name()` and its `command()`, write the `modifiers()` of a set of dice and the `division()`
rounded as a function says; override `fudge()`, `rangeDie()`, `valueList()`, `call()`, `modulo()`, `power()`, `group()`,
`reference()`, `instructionDice()`, `textList()`, `sentence()`, `conditional()` or `choice()` when the table writes
them, and call `unsupported()` — or `unwritten()` for several modifiers at once — for what it cannot write. The helpers
`plain()`, `plainSuccess()`, `bound()`, `nonNegative()`, `function()` and `rounded()` cover what the built-in dialects
share. A table that rolls every instruction of a command together returns its separator from `separator()`; the
translation then holds one expression. Add it to `Dialects`, where a dialect of the same name replaces the built-in
one.
