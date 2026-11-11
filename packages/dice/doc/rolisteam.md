# Rolisteam's DiceParser

`RolisteamReader` reads a command written in the notation of Rolisteam's DiceParser — pasted as it was written, the
roll command left out — into the library's tree: the same `Pool` the library's own notation gives, rolled, written and
translated the same way.

```php
use Tilttek\Dice\DiceRoller;

$dice = new DiceRoller();
$pool = $dice->readRolisteam('5D10K3; 8d10c[>=6]-@c[=1]');
$dice->write($pool);   // "5d10!kh3, 8d10>=6-@=1"
```

The reader writes the command's tokens again in the library's notation, then parses them: a token both notations
write the same is kept, a token DiceParser writes otherwise is replaced, and the parser does the rest. Texts, the names
of functions and macros, and what is written between brackets — values, lists, items of a text list — are kept as they
are. An error is placed in the command as it was written: `Unexpected "x" at position 5 of "2d6r[x]".` The command is
within the length of the [limits](limits.md), and so is the pool it gives, rolled within the others.

## What is read

| DiceParser | Example | The library's notation |
|---|---|---|
| Keep the highest, the lowest | `4d6k3`, `4d6kl1` | `4d6kh3`, `4d6kl1` |
| Keep and explode | `5D10K3` | `5d10!kh3` |
| Reroll once, until it no longer matches | `4d6r[1]`, `4d6R[<3]` | `4d6ro1`, `4d6r<3` |
| Explosion, limited | `3d10e10`, `3d10e[>8]`, `3d10e(3)[10]` | `3d10!10`, `3d10!>8`, `3d10!10(3)` |
| Reroll and add | `3d10a[10]` | `3d10a10` |
| Count | `6d10c[>=7]`, `6d10c>7`, `6d10c10` | `6d10>=7`, `6d10>7`, `6d10=10` |
| Sort, descending and ascending | `10d6s`, `10d6sl` | `10d6sd`, `10d6sa` |
| Transformation, the value of every other die last | `5d10T[>5]{15}[<2]{1}{0}` | `5d10t[>5:15,<2:1,<1|>0:0]` |
| Paint | `8d10p[2:blue]` | `8d10look[2:blue]` |
| Occurrences of a width and a height | `10d10o2,7` | `10d10o2[>=7]` |
| Scope of a validator | `[*>2]`, `[.6]`, `[:>=30]`, `[?>5]` | `[all:>2]`, `[any:6]`, `[total:>=30]`, `[>5]` |

The constructs both notations write alike are read as they are: dice `3D6`, dice on a range `1d[-1..1]`, lists of
values `[10,25]k1`, filters `f[…]`, unique dice `u`, dice all the same `t`, groups `g`, the dice before `@`,
instructions separated by `;`, `$1` and `$1[0]`, `@1`, `m[…]` and `b[…]`, text lists `L[…]`, texts and their
placeholders, `i[…]{…}{…}`, `S[…]{…}` and `S^`, the functions `floor`, `ceil`, `round`, `abs`, `min`, `max` and
`repeat`, the operators `+ - * / % ^`, comments `#`. A paint token names a style of the page, as a
[look token](notation.md#modifiers) does: `blue` is one, `#0000ff` is not.

The modifiers of a set of dice apply in the library's order, whatever order they are written in; a division rounds as
the roll says ([rolling](rolling.md)), since the library computes in integers.

## What is refused

| Construct | Message |
|---|---|
| The split of explosions, `y` | `The split of explosions "y" is not read` |
| A merge or a bind of every instruction, `m` or `b` without brackets | `"m" is read with the instructions it takes: m[1,2]` |
| Anything the library's notation does not parse once written again | The parser's message, at the position of the token in the command |

## Macros

`read($input, $macros)` reads the macros a command names, `$name`, as the parser does: see [macros](macros.md). A
macro's name is never written again: `$fire` stays the macro `fire`, its `r` and its `e` untouched.

## Writing in DiceParser's notation

The dialect `diceparser` writes a pool back in DiceParser's notation, reporting what it cannot write: see
[dialects](dialects.md#rolisteams-diceparser).
