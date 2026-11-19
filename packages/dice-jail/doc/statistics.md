# The statistics

## The test

`Statistics\GoodnessOfFit::test()` runs Pearson's chi-squared test of goodness of fit: it compares how many times
each face came up with how many times a fair die would show it over as many rolls.

```php
use Tilttek\DiceJail\Statistics\{ExpectedFaces, GoodnessOfFit, Tally};

$result = new GoodnessOfFit()->test(Tally::of([20, 3, 17, 1, 1, 12]), ExpectedFaces::uniform(20));
```

- `Tally::of()` counts faces, one value per roll; `Tally::ofRolledDice()` counts every roll of the `RolledDie` of
  `tilttek/dice`, rerolled and exploded ones included. A tally holds at most 1 000 000 rolls.
- `ExpectedFaces::uniform($sides)` and `ExpectedFaces::range($lowest, $highest)` are fair dice whose faces are equally
  likely; `ExpectedFaces::fromDistribution()` takes the exact distribution `tilttek/dice-probability` computes, for a
  die with weighted or custom faces, or a total such as `2d6` judged as one outcome per roll. An estimated
  distribution, or one that cuts its explosions, is refused: the test needs exact probabilities. A die has 2 to
  1 000 faces.

The statistic is Σ (observed − expected)² / expected, with one degree of freedom fewer than the faces. Its p-value is
the upper tail of the chi-squared law, computed by the regularized incomplete gamma function with Γ exact for the
whole and half-whole arguments a die gives; it matches the published tables to nine digits.

## The minimum of rolls

The test is trusted when every face is expected at least five times: 30 rolls for a d6, 100 for a d20, 180 for 2d6
(whose 12 comes once in 36). Below that, the verdict is `NotEnoughRolls`, the p-value is null and
`FitResult::rollsStillNeeded()` says how many rolls are missing.

## The verdicts

| Verdict | p-value | What it means |
|---|---|---|
| `NotEnoughRolls` | none | The test cannot be trusted yet. |
| `NothingUnusual` | 0.01 or more | A fair die rolls this unevenly at least once in 100 series: chance explains it. |
| `SomewhatUnusual` | from 0.001 to 0.01 | Between once in 1 000 and once in 100 series. |
| `VeryUnusual` | below 0.001 | Fewer than once in 1 000 series. |

## Reading them honestly

- A p-value is how rare the rolls would be **if the die were fair**, never the probability that the die is loaded.
- One fair die in a hundred is « somewhat unusual » by chance alone, and one in a thousand « very unusual ». A table
  that tests many dice, or the same die again and again, will meet them.
- After an unusual verdict, judge **new rolls alone** — `DieAssessor::assess($die, $expected, since: $release)` —: the
  rolls that raised the suspicion cannot also confirm it.
- The test looks at how the faces are spread, not at whether the die rolls low. A die can roll below its average
  without being unusual; `FitResult::observedMean` and `expectedMean` show the gap without judging it.
- **Digital dice** draw from one random generator shared by every die: no digital die is luckier than another, and
  an unusual verdict there is chance, or a fault of the generator for every die alike.

`FitResult` also lists each `FaceCount` — face, observed, expected — for a chart.
