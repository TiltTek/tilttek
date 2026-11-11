# Explosions and estimates

## `Settings`

```php
new Settings(int $explosionDepth = 10, int $workLimit = 500_000, int $samples = 20_000, int $seed = 0);
```

| Setting | What it bounds |
|---|---|
| `explosionDepth` | How many explosions per die the exact computation follows |
| `workLimit` | How many operations on weights an exact computation does at most — each weight multiplied or added counts one |
| `samples` | How many rolls an estimate is drawn from |
| `seed` | The seed of those rolls: the same seed gives the same estimate |

Each bound is at least 1.

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

The cost of an exact computation, as a guide: `30d6` is about 2 400 operations and `20d100` about 21 000; a selection
among many dice of many sides grows fastest, and `50d20kh10` goes past the default limit.
