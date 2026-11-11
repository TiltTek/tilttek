# Notation

An expression is parsed, checked and normalised before it is rolled. Letters may be written in either case. An input
holds one instruction or more; instructions, references, groups, text lists, texts, branches and comments are described
in [instructions and results](instructions.md).

## Dice

| Construct | Examples | Meaning |
|---|---|---|
| Dice | `2d6`, `d20`, `d%` | A count — 1 when left out — and the sides; `d%` is `d100` |
| Fudge dice | `4dF`, `4dF.2`, `4dF.1` | Faces −1, 0 and +1: two of each, or one −1, four blank faces and one +1 with `.1` |
| Dice on a range | `4d[-1..1]`, `3d[0..9]` | Every integer from one bound to the other, both included |
| Custom dice | `3d{chromatic}` | A die the application declares, named between braces ([custom dice](custom-dice.md)) |
| Count and sides rolled first | `(1d4+1)d6`, `3d(2*6)`, `(1d4)dF` | An expression between parentheses gives the count, or the sides of a numbered die |
| A list of values | `[10,25,43,8]kh1`, `[-1,3]` | Integers taken as dice already rolled, which take the modifiers that judge dice |
| The dice before | `8d10>=6-@=1` | The dice that counted in the set rolled or listed just before, in the same expression, judged again |

A count or a number of sides between parentheses that is a constant is the plain form: `(3)d6` is `3d6`. A rolled count
below 1, or rolled sides below 1, cannot be rolled ([rolling](rolling.md)).

## Modifiers

| Construct | Examples | Meaning |
|---|---|---|
| Reroll | `4d6r1`, `4d6r<3`, `4d6ro1` | Reroll each die on a face or matching a validator, until it no longer does; `ro` rerolls once |
| Unique | `4d6u`, `4d6uo` | Reroll each die that shows a value an earlier die of the set shows, until it differs; `uo` once |
| Explosions | `3d6!`, `3d6!5`, `3d6!>=5` | Roll one more die for each die on the highest face, on a face, or matching a validator, and again for each new die that does |
| Compound explosions | `3d10!!`, `3d10!!>8` | The dice an explosion rolls are added to the die that exploded, which stays one die |
| Penetrating explosions | `2d6!p`, `2d6!!p` | Each die an explosion adds counts one less; a die still explodes on the face as it fell |
| Limited explosions | `3d10!(3)`, `3d10!>8(1d4)` | At most so many explosions per die, the limit written or rolled first |
| Reroll and add | `3d10a10` | Once, roll one more die and add it to the die that matches: `3d10!!10(1)` |
| Dice all the same | `2d6t` | When every die of the set shows the same value, roll as many dice again and add them, as long as they come out all the same |
| Floor, ceiling | `4d6min3`, `4d6max5`, `4dFmin-1` | Each die counts at least, or at most, this value |
| Transformation | `5d10t[>5:15,<=5:0]` | A die that matches the first condition takes its value |
| Filter | `4d6f[!=6]`, `4d6f[all:>1]` | The dice that do not match are dropped |
| Keep, drop | `4d6kh3`, `4d6kl1`, `4d6dh1`, `4d6dl1` | Keep or drop the highest or the lowest; `k` alone keeps the highest, `d` alone drops the lowest |
| Advantage | `d20 adv`, `d20 dis` | `2d20kh1` and `2d20kl1`, for one die only |
| Successes | `6d10>=7`, `6d10=10`, `8d10c[6..10]` | Count the dice that match, instead of summing them |
| Failures | `6d10>=7f1`, `6d10>=7f<3` | Each die that is not a success and matches takes one success away |
| Occurrences | `10d10o`, `10d10o3[>=7]` | The sets of dice of the same value — at least two, or the width written —, of a height that matches when one is given: the value is the number of sets |
| Groups | `5d10g10` | Groups of dice that each reach the sum — the most the dice form, up to 12 dice ([rolling](rolling.md)) —: the value is the number of groups |
| Critical marks | `1d20cs`, `1d20cs>19`, `1d20cf1` | Mark a critical success — the highest face as it fell — or failure — the lowest —, for display |
| Look tokens | `8d10look[2:accent,muted]` | A token of the page's look for the next dice, or for every die left when no count is given |
| Sort | `4d6s`, `4d6sa`, `4d6sd` | List the dice by value, ascending or descending |

A face alone, a comparison written with its sign, or a validator between brackets says which dice a reroll, an
explosion or a critical mark applies to: `r1`, `r<3`, `r[1|2]`. Comparisons keep the plain sense of their sign: `>5` is
strictly greater than 5, `>=5` is 5 or more, `=6` is 6. Failures take a face or a comparison; `f[…]` is the filter.

A look token is 1 to 32 lower-case letters, digits or hyphens, starting with a letter: it names a style the page
defines — `accent`, `muted` —, never a colour.

## Validators and their scope

Between brackets, a validator says which values match:

| Validator | Example | Matches |
|---|---|---|
| A value | `[6]`, `[-1]` | That value |
| A comparison | `[>=6]`, `[!=1]` | The values the comparison holds for, `!=` included |
| A range | `[8..10]`, `[-1..1]` | The values from one bound to the other, both included |
| A remainder | `[%2=0]`, `[%3!=0]` | The values whose remainder by the divisor holds the comparison; the remainder has the sign of the divisor |
| Composites | `[>2&<6]`, `[1|6]`, `[%2=0^>4]` | Both (`&`), either (`|`), exactly one (`^`); `&` binds tighter, parentheses group |

A condition — the successes `c[…]`, the filter `f[…]` and the transformations `t[…]` — may write its scope before the
validator, with a colon:

| Scope | Example | Checked against |
|---|---|---|
| Each final value (default) | `c[>=6]` | Each die's value once its floor, ceiling and transformation apply |
| Each die | `c[die:6]` | Each die as it fell, before its floor, ceiling or transformation |
| All | `c[all:>2]` | Every die that counts: one success or none; kept or dropped together |
| Any | `c[any:6]` | At least one die that counts |
| Total | `c[total:>=10]` | The sum of the dice that count |

A transformation judges each die. Successes judged together count no failure.

## The order of the modifiers

Whatever order they are written in, the modifiers of a set of dice apply in this order. While each die is rolled: the
reroll, the reroll of a value already shown, the explosion. Once every die is rolled: the explosion of dice all the
same. Then, on the dice rolled or listed: the floor and the ceiling, the transformations, the filter, the selection,
the successes and the failures — or the occurrences, or the groups —, the critical marks, the look tokens, and the
order the dice are listed in.

Each modifier may be given once. A set counts successes, occurrences or groups, one of them at most; failures need
successes judged die by die; a floor stays at or below its ceiling. A roll that would never end is refused: an explosion
every face would trigger, unless it is limited (`d6!>=1`), or a repeated reroll of every face (`d6r<7`); repeated unique
dice need as many faces as dice; dice all the same explode from two dice. A selection keeps from one to all of the dice,
or drops from one to all but one. A list of values, the dice before, the dice of instructions and a group take no
modifier that rolls dice; the dice before and those of instructions keep or drop what they can of the dice they find.

## Arithmetic and functions

| Construct | Examples | Meaning |
|---|---|---|
| Operations | `2d6+3`, `(1d8+2)*2`, `-1d4`, `10/3`, `10%3` | Integers; `*`, `/` and `%` bind tighter than `+` and `-` |
| Power | `2^10`, `2^-1`, `-2^2`, `2^3^2` | Binds tighter than a negation — `-2^2` is −4 — and from the right |
| Rounding | `floor(1d6/3)`, `ceil(1d6/3)`, `round(7/2)` | The divisions inside round down, up, or to the nearest, halves away from zero |
| Absolute value | `abs(1-1d6)` | |
| Smallest, largest | `min(1d6, 3)`, `max(1d20, 1d20)` | Of two arguments or more |
| Repetitions | `repeat(4d6kh3, 6)`, `repeat(1d6, 1d4)` | The first argument rolled as many times as the second says, into a list |
| Instructions | `2d6, 1d8`, `2d6; 1d8` | Several instructions rolled together, each with its own result |

A division rounds as the roll says — down by default ([rolling](rolling.md)) —, unless `floor()`, `ceil()` or
`round()` around it say otherwise; every other function keeps the rounding of the roll. A power with a negative
exponent is one divided by the power, rounded the same way. A text or a list takes no part in arithmetic.
A remainder has the sign of the divisor: `-7%3` is 2.

## The canonical form

`Pool::canonical()` writes an expression back with lower-case letters — but the `F` of Fudge dice and the `L` and `S`
of [text lists and switches](instructions.md) —, every count written, the modifiers in the order they apply, the
instructions separated by `", "`, and no more parentheses than the tree needs. A threshold on
one face writes the face alone (`r1`, `!6`), or with `=` when it is negative (`r=-1`); successes judged on each final
value by a comparison write it alone (`>=7`), any other `c[…]`; `s` is written `sa`, `a10` is written `!!10(1)`, `o`
writes its width.

| Input | Canonical |
|---|---|
| `4D6KH3` | `4d6kh3` |
| `d% + d4` | `1d100+1d4` |
| `d20 adv` | `2d20kh1` |
| `4d6kh3!r1` | `4d6r1!kh3` |
| `8d10c[>=6]-@c[=1]` | `8d10>=6-@=1` |
| `3d10a10` | `3d10!!10(1)` |
| `min(1d6,3)` | `min(1d6, 3)` |
| `((2))` | `2` |
| `3d{Chromatic}` | `3d{chromatic}` |

The canonical form parses back to the same tree.

## Errors

An invalid input raises `InvalidExpression`, whose `position` is the offset of the error, in characters from 0, and
whose message names it: `Unexpected "x" at position 3 of "2d6x".` Numbers are at most 1,000,000,000; parentheses,
negations, functions, groups, branches and the parentheses of validators nest at most 32 deep. An input that parses but
goes past a limit raises `LimitExceeded` ([limits](limits.md)).
