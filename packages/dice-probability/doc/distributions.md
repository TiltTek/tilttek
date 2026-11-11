# Distributions

## `Probabilities::distribution()`

```php
public function distribution(Node|Pool $expression, RoundingMode $rounding = RoundingMode::NegativeInfinity): Distribution
```

The expression is a node of `tilttek/dice`, or a pool that holds one expression — what `DiceRoller::parse()` gives. A
pool of several expressions is refused: each has its own distribution. The rounding is that of a roll: how a division,
or a power with a negative exponent, rounds unless `floor()`, `ceil()` or `round()` say otherwise.

`Probabilities` takes the custom dice the expressions may name, the limits they are checked against as for a roll,
and the [settings](explosions-and-estimates.md) of the computation:

```php
new Probabilities(CustomDice $customDice = new CustomDice(), Limits $limits = new Limits(), Settings $settings = new Settings());
```

## `Distribution`

Every value the expression can give and its weight — the number of equally likely ways to roll it —, over a total that
is the sum of the weights: `36` ways for `2d6`, `1296` for `4d6kh3`, `6^30` for `30d6`. Each probability is an exact
[`Fraction`](exact-numbers.md) over that total.

| Member | What it gives |
|---|---|
| `weights` | The weight of each value, by value in ascending order, as `Natural` numbers |
| `total` | The sum of the weights: the ways to roll, or the samples of an estimate |
| `values()`, `minimum()`, `maximum()` | The values, the lowest and the highest |
| `probability($value)` | The probability of exactly that value: `6/36` for a 7 on `2d6` |
| `atLeast($value)`, `atMost($value)` | The probability of that value or more, or that value or less |
| `matching($validator)` | The probability that the value matches a validator of the notation: `new Range(8, 10)` |
| `outcomes()` | Each value as an `Outcome`: its probability, at least and at most — the rows of a table |
| `mean()`, `variance()` | Exact fractions |
| `standardDeviation()` | The square root of the variance, a float |
| `remaining` | The probability that an explosion was cut at its depth — see [Explosions and estimates](explosions-and-estimates.md) |
| `estimate`, `isEstimated()` | How an estimated distribution was drawn; null when it is exact |

## What is computed exactly

| Construct | How |
|---|---|
| Constants, `+ - * / % ^`, negation, `floor ceil round abs min max` | Each pair of outcomes of the independent sides joined, with the integer arithmetic and rounding of a roll |
| `NdS`, `dF`, `dF.1`, `d[a..b]`, custom dice whose faces carry values | A plain die summed one die at a time by a sliding window; other dice by squaring the convolution |
| Rerolls `r` and `ro` | The faces of the die once rerolled: the faces that match left out, or each one rerolled once |
| Explosions `!`, `!!`, `!p`, `!!p`, with a limit or not | Followed die by die up to their limit, or to the depth of the settings |
| Floors, ceilings, transformations, filters judged die by die | Each face turned into what it contributes |
| `kh kl dh dl` | Over the sorted multisets of the dice: a value shown by several dice is taken as many times at once, weighed by the ways to choose those dice, never by listing every roll |
| Successes and failures, `c[all:…]`, `c[any:…]`, `c[total:…]` | What each die contributes, summed, then judged for the whole set |
| Counts and sides rolled first, explosion limits rolled first | A mixture of the distributions of each count, side or limit |
| Lists of values | Judged once: they have one value |

Unique dice, dice all the same that explode, occurrences, groups, filters that judge the whole set, the dice before
(`@`), a selection among dice that explode into dice of their own, groups of expressions, `if` and `switch`, and dice
of the application whose faces the library cannot list are estimated instead — see [Explosions and estimates](explosions-and-estimates.md).

An outcome that cannot be rolled — a division by zero, dice that cannot be rolled once their count is known, an unknown
custom die — makes the distribution impossible: `CannotCompute`, whose previous exception is the `CannotRoll` of the
roll. The faces of a custom die that carry no value have no distribution either, nor a text, a list or `repeat()`,
whose results are not numbers.

## Examples

```php
$probabilities->distribution($dice->parse('2d20kh1'))->atLeast(15);              // 204/400
$probabilities->distribution($dice->parse('6d10>=7f1'))->mean()->reduced();       // 9/5
$probabilities->distribution($dice->parse('1d6/3'), RoundingMode::PositiveInfinity)->values(); // [1, 2]
$probabilities->distribution($dice->parse('3d6c[total:>=12]'))->probability(1);    // 81/216
```
