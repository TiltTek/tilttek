# Exceptions

Every exception of the library implements `Tilttek\DiceProbability\Exception\ProbabilityException`. The exceptions of
`tilttek/dice` pass through unchanged: a limit exceeded is its `LimitExceeded`.

## `CannotCompute`

| Message | When |
|---|---|
| `An outcome of the expression cannot be rolled: …` | An outcome divides by zero, gives dice that cannot be rolled once their count is known, or names an unknown die; the previous exception is the `CannotRoll` of the roll |
| `The faces of the die "…" carry no value: they have no distribution.` | A custom die whose faces are symbols |
| `A distribution is that of one expression; the pool holds 2.` | A pool of several expressions |
| `An event is judged on the faces of one set of dice, such as "4d6".` | An event on an expression that is not one set of dice |

## Invalid arguments

`Natural`, `Fraction`, `Settings`, `Estimate`, `Distribution`, `Faces` and the events refuse an invalid argument with
PHP's `InvalidArgumentException`: a negative natural number, a zero denominator, a setting below 1, a distribution
without values, a negative count, a double of one die, a straight of one value.
