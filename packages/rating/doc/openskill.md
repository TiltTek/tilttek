# OpenSkill

`OpenSkill\OpenSkillSystem` rates by the Bayesian approximation of online ranking published by Ruby C. Weng and
Chih-Jen Lin (*A Bayesian Approximation Method for Online Ranking*, Journal of Machine Learning Research 12, 2011). Its
identifier is that of its model: `openskill-pl` or `openskill-tm`.

## Skills and uncertainty

A member's skill is a normal distribution: its mean `mu` and its deviation `sigma`. A new member starts at `mu = 25`
and `sigma = 25 / 3`. Before each game, `sigma` grows by `tau`, so that a skill can still change after many games. A
side's mean is the sum of its members' means, and its variance the sum of their variances.

The model gives each side a correction of its mean, Ω, and of its variance, Δ. Each member takes the share of its own
variance in the side's, `σ² / σ²side`:

```text
mu ← mu + σ² / σ²side × Ω
σ² ← σ² × max(1 − σ² / σ²side × Δ, κ)
```

so that the most uncertain member of a team moves most, and `κ` keeps some uncertainty in every case.

## The models

| Model | `OpenSkillModel::` | Reads a result as |
|---|---|---|
| Plackett-Luce | `PlackettLuce` | The order of finish as a sequence of choices: each side is chosen first among those left in proportion to `e^(mu/c)`. Sides of the same rank share each choice. |
| Thurstone-Mosteller | `ThurstoneMosteller` | Every pair of sides as a duel of two normal performances, with a draw margin. |

Plackett-Luce suits games of many players or teams and is the default; Thurstone-Mosteller suits duels and games where
draws are common.

## Settings

```php
new OpenSkillSystem(
    model: OpenSkillModel::PlackettLuce,
    mu: 25.0,              // the mean skill of a new member, 1 to 1 000
    sigma: null,           // its deviation; mu / 3
    beta: null,            // the deviation of one performance around the skill; sigma / 2
    tau: null,             // the growth of the deviation before each game; mu / 300
    kappa: 0.0001,         // the smallest share of a variance kept
    drawMargin: 0.1,       // the margin of a draw, Thurstone-Mosteller only
    z: 3.0,                // the deviations taken off for the conservative estimate, 0 to 10
    provisionalSigma: null, // the deviation above which a skill is provisional, above 0; none by default
);
```

`provisionalSigma` marks a skill still uncertain as provisional, whatever the games played: `Ratings::isProvisional()`
reads it, beside the games `provisionalGames` asks for; see
[Provisional ratings](privacy-and-standings.md#provisional-ratings).

## The conservative estimate

The standings order the members by `mu − z × sigma`, a skill the member has at least with the confidence `z` gives —
about 99.9 % for 3 — in hundredths. A new member starts at 0 and climbs as their results confirm their level, rather
than topping the standings after one lucky win. `SkillRating::conservative($z)` gives it for another `z`.

## Values

`mu` and `sigma` are kept in millionths, `SkillRating::$mu` and `SkillRating::$sigma`, rounded once per game, so a
replay gives the same values. The normal distribution is computed by the library: the complementary error function by
its Maclaurin series below 2 and its continued fraction above, to the precision of a float; a correction too far in the
tail for a float takes its limit.
