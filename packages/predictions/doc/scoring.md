# Scoring

## By choice

The member picks one outcome. A right pick earns the question's points; a wrong one earns nothing and costs nothing.

## By probability

The member gives each outcome a probability, in whole percents summing to 100. Once the outcome is declared, the
forecast is scored by the Brier score: the sum, over the outcomes, of the square of the gap between the probability
given and what happened — 100 % for the outcome that happened, 0 % for the others. A perfect forecast scores 0; full
confidence in an outcome that did not happen scores 2.

The library computes it in whole numbers, in ten-thousandths, so that no rounding of floating-point numbers changes a
member's points: from 0 to 20 000. The points are the share of the question's points left once the score is taken from
20 000, rounded half up:

```text
points = round(maximum × (20000 − score) / 20000)
```

The Brier score is a proper scoring rule: a member earns the most, on average, by giving the probabilities they
believe. A bold 100 % earns everything when right and nothing when wrong; a cautious forecast earns some points either
way.

## Worked values

For a question of 100 points:

| Forecast | Outcome | Score | Points |
|---|---|---|---|
| yes 100 %, no 0 % | yes | 0 | 100 |
| yes 100 %, no 0 % | no | 20 000 | 0 |
| yes 50 %, no 50 % | either | 5 000 | 75 |
| yes 70 %, no 30 % | yes | 1 800 | 91 |
| yes 70 %, no 30 % | no | 9 800 | 51 |
| home 60 %, draw 30 %, away 10 % | home | 2 600 | 87 |
| home 60 %, draw 30 %, away 10 % | away | 12 600 | 37 |

`Brier::score()` and `Brier::points()` compute them for an application that shows a member their score.
