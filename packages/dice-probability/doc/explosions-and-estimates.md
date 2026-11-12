# Explosions and estimates

## `Settings`

```php
new Settings(int $explosionDepth = 10, int $workLimit = 500_000, int $samples = 20_000, int $seed = 0, int $rollLimit = 200_000);
```

| Setting | What it bounds |
|---|---|
| `explosionDepth` | How many explosions per die the exact computation follows |
| `workLimit` | How much work an exact computation does at most — each weight multiplied or added counts one while the weights are small, more as they grow |
| `samples` | How many rolls an estimate is drawn from |
| `seed` | The seed of those rolls: the same seed gives the same estimate |
| `rollLimit` | How many dice an estimate rolls at most over all its samples, explosions and rerolls included |

Each bound is at least 1. Together, the work limit and the roll limit bound the work of any expression: at the
defaults, about a second of processor time for the exact computation and as much for an estimate.

## Explosions

An explosion without limit could go on forever. The computation follows each die to `explosionDepth` explosions; the
values of the rolls that would have exploded further are counted as they stand there, a little low. Their probability
is the distribution's `remaining`: for `1d6!` at the default depth, `1/362797056`, one chance in six to the eleventh
power; for a set of dice, the probability that any of them went that far. An explosion whose limit is at most the depth
— `1d6!(3)` — is exact, and nothing remains.

## Estimates

Past the work limit, or for a construct the exact computation does not follow, the result is drawn from `samples` rolls
of the library's own roller, seeded with `seed`: each weight is the number of rolls that gave the value, over the number
of rolls. The result says so:

```php
$distribution->isEstimated();          // true
$distribution->estimate->reason;       // EstimateReason::TooLarge or EstimateReason::Unsupported
$distribution->estimate->samples;      // 20000
$distribution->estimate->margin();     // 0.0069: each probability is within 0.69 points, 19 times out of 20
```

The margin is the half-width of the 95 % confidence interval of a probability in the worst case, one half:
`1.96 × √(0.25 / samples)`. An estimate states no `remaining`: its rolls explode as far as the limits of a roll let
them.

| Reason | When |
|---|---|
| `TooLarge` | The exact computation would go past the work limit: `50d20kh10`, `100d100` with selections |
| `Unsupported` | Unique dice, dice all the same that explode, occurrences, groups, filters of the whole set, the dice before, a selection among dice that explode into dice of their own, equal values that contribute differently to a selection, dice of the application whose faces are not known |

The cost of an exact computation, as a guide: `30d6` is about 7 200 operations and `10d6!` about 300 000; a selection
among many dice of many sides grows fastest, and `50d20kh10` goes past the default limit. The weights of many dice are
numbers of many limbs: an operation on them counts one more for every sixteen products of limbs, and a fraction brought
to its lowest terms — the probability that an explosion was cut, for one — the cube of its size in limbs.

## The roll limit

An estimate rolls `samples` times, and each roll may throw many dice: `100d6!` throws a hundred and twenty on average,
2.4 million dice over twenty thousand samples. Past `rollLimit` dice, the estimate stops and the call throws
`CannotCompute` (see [Exceptions](exceptions.md)) rather than run on. Lower the samples, or raise the roll limit, for
the expressions that need it:

```php
new Probabilities(settings: new Settings(samples: 2_000));          // 100d6!: about 240 000 dice, still too many
new Probabilities(settings: new Settings(rollLimit: 3_000_000));    // 100d6!: allowed, about ten seconds
```
