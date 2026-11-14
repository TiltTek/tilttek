# Elo

`Elo\EloSystem` rates by the Elo formula. Its identifier, kept with each rating, is `elo`.

## The formula

Each side meets every other side of the result. Against side `b`, side `a` scores 1 when ranked above it, ½ for the same
rank and 0 when ranked below, and expects

```text
E = 1 / (1 + 10^((Rb − Ra) / s))
```

where `Ra` and `Rb` are the ratings of the sides — the mean of their members' — and `s` the scale. Each member of side
`a` moves by

```text
K × Σ (score − E) / (sides − 1)
```

with the K of that member's games and rating, rounded once to the hundredth. A duel is the classic Elo; a game of
several players is a round of duels between every pair, weighed as one game; a team game is the same between the means
of the teams, each member moving by their own K.

## Settings

```php
new EloSystem(
    k: KFactor::chess(),   // or new KFactor(32), the default
    initial: 1500,         // the rating of a new member, 0 to 10 000
    scale: 400,            // 1 to 10 000
    floor: null,           // the lowest rating, 0 to the initial rating
);
```

## The K-factor

`KFactor` gives a fixed K, or a K that decreases. Its steps are read in the order given; the first that applies gives
the K, and the K given first is used when none does. A step applies to a member with fewer rated games than
`beforeGames`, or rated below `belowRating`, or both when both are given. K is 1 to 1 000.

```php
new KFactor(32);                                                       // always 32
new KFactor(10, new KFactorStep(40, beforeGames: 30), new KFactorStep(20, belowRating: 2400));
KFactor::chess();                                                      // the same: the chess federation's K for adults
```

A high K for the first games lets a newcomer reach their level quickly; a low K at the top keeps the strongest ratings
steady.

## Values

A rating is kept in hundredths of a point, `EloRating::$hundredths`, and each change is rounded once: the ratings carry
no fraction from one game to the next, so a replay gives the same values. The standings show `MemberRating::value()`,
the rating to two decimals.
