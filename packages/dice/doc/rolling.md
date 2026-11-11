# Rolling

`Roller::roll(Pool, Randomizer, RoundingMode)` rolls a pool with the randomness it is given:

```php
use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\Dice\Parser\DiceParser;
use Tilttek\Dice\Rolling\Roller;

$result = new Roller()->roll(new DiceParser()->parse('4d6kh3'), new Randomizer(new Secure()));
```

Use a secure engine in production. A seeded engine — `Mt19937`, `Xoshiro256StarStar` — gives the same dice for the
same seed, which is what tests, [verifiable and replayable rolls](verifiable-and-replayable.md) rely on.

Before any die is rolled, the pool is checked against the [limits](limits.md), its custom dice are found
(`CannotRoll` names an unknown one), and custom dice whose faces carry no value are refused inside arithmetic — in an
operation, a function, a count or sides rolled first, the limit of an explosion, a group, the count of a list or of
repetitions, the subject of a branch. A branch or `repeat()` gives such dice as they are, the labels of their faces.

## What a roll keeps

A `PoolResult` holds one `ExpressionResult` per instruction, with its `output()` — a number, a text or a list — and its
`total()` — null for custom dice whose faces carry no value, a text, a list, or dice given to a merge —, the tree of
results that gave it — `ConstantResult`, `OperationResult`, `NegationResult`, `FunctionResult`, `DiceResult`,
`TextResult`, `ListResult`, `BranchResult` and `ReferenceResult` ([instructions and results](instructions.md)) —, and
the `rounding` it was rolled with. `NodeResult::sets()` lists the sets of dice of a result, in the order they were
rolled or listed.

A `DiceResult` is a set of dice rolled, a list of values, the dice before or those of instructions judged again, or the
totals of a group. It keeps every `RolledDie`,
in the order they fell or the order a sort gave:

| Property | Meaning |
|---|---|
| `value` | The die's final value; for a custom die, the face's value, or 0 |
| `rolls` | The rolls the die is made of: one, or several for a die that compounds its explosions; `natural()` is their sum, the die as it fell |
| `face` | The `Face` of a custom die |
| `origin` | `initial`, `reroll` or `explosion` (`DieOrigin`) |
| `kept` | Whether a filter or a selection kept the die |
| `rerolled` | Whether a reroll replaced it — it no longer counts |
| `exploded` | Whether it made one more die roll |
| `success` | Whether it matched the condition of successes, when the expression counts them die by die |
| `failure` | Whether it took a success away |
| `critical` | Its critical mark, `critical-success` or `critical-failure` (`Mark`), for display |
| `look` | Its look token, for display |

`counts()` says whether a die counts — kept and not rerolled —; `DiceResult::kept()` gives the dice that count and
`tally()` counts the faces of custom dice by identifier. `DiceResult::groups` lists the sets of occurrences or the
groups that reach a sum, by their values; `DiceResult::parts` holds what was rolled before the dice — `count`, `sides`,
`explosion-limit` —, or the expressions of a group. `DiceResult::notices` says what the roll could not do exactly:
`greedy-groups` (`Notice`) when groups that reach a sum were found greedily.

`PoolResult::toArray()` writes the whole roll as plain arrays, which a journal keeps and a verification compares;
`equals()` compares two results.

## Rerolls, explosions and selections

A repeated reroll rerolls a die until it no longer matches; a reroll once does not reroll the die that replaces it,
but that die may explode. A unique die compares itself with the dice of the set that count, those rolled before it.
Explosions repeat until a die does not trigger one or its limit is reached; a rolled limit below 0 is 0. Dice all the
same roll again, as many as the set rolled, while the dice of the last batch that count all show the same value.

Every die that a repeated operator rolls is counted across the whole pool: explosions of every kind, and the dice of
dice all the same, against the limit of explosions; rerolls, repeated, once or unique, against the limit of rerolls;
the dice whose count is rolled, the values of lists and the items drawn from text lists, against the limit of dice;
the repetitions of `repeat()`, against the limit of repetitions; the texts of sentences, against the limit of their
characters.

A selection chooses among the dice that count, the rerolled ones left out; of equal dice, the first rolled is chosen
first. The dice before, `@`, are the dice that counted in the set rolled or listed just before in the same expression,
each as a new die with its value, face and rolls; `@` with no set before it in the expression raises `CannotRoll`.

Groups that reach a sum are the most the dice can form, up to 12 dice: each group is one from which no die can leave
with the group still reaching the sum, and of the ways to form the most, the search keeps the first it finds, from the
highest die. Beyond 12 dice, one greedy pass finds them, and the result says so with the notice `greedy-groups`: a die
that reaches the sum alone is a group; otherwise the highest die left starts a group, which the lowest die that
completes it closes, or the highest die left grows. Each group lists its dice from the highest, the groups by their
highest die. Occurrences list the widest sets first and, of equal width, the highest.

## Instructions and branches

The instructions are rolled in order; each sees the results of those before it, never their `@`. The dice of an
instruction taken again, bound or merged, are new dice with the value, face and rolls of the dice that counted; their
critical marks fall on the lowest and highest of their values. Only the branch that holds is rolled; the cases of a
switch are judged in order, and an exclusive switch stops at the first that holds.

## Count and sides rolled first

A count or sides rolled first are rolled before the dice, their results kept in the parts. The dice are then checked
as if written: a count below 1, sides below 1, or modifiers the dice cannot take raise `CannotRoll`; sides beyond the
limit raise `LimitExceeded`.

## Arithmetic and rounding

Arithmetic is on integers. A division rounds as the `RoundingMode` passed to the roll says — `NegativeInfinity` by
default, so `-7/2` is `-4` —; a game rounds up for one side and down for the other by passing the mode of the one who
rolls. `floor()`, `ceil()` and `round()` say how the divisions inside them round, whatever the roll says; `round()`
rounds halves away from zero. `abs()`, `min()`, `max()` and `repeat()` keep the rounding of the roll. Every mode of
PHP's `RoundingMode` is supported, computed without floating point.

A power with a negative exponent is one divided by the power, rounded the same way: `2^-1` is 0 rounded down, 1
rounded up. A remainder has the sign of the divisor. A division or a remainder by zero, zero to a negative power, or a
result beyond the range of an integer raises `CannotRoll`.
