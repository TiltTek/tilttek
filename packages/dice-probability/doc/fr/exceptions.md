<!-- phil-dev source-sha256=a5a6b07eed148575da121d19df5097c341cc2c0be41f860e8b7c18729dadaba0 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\DiceProbability\Exception\ProbabilityException`. Les exceptions
de `tilttek/dice` passent sans changement : une limite dépassée est son `LimitExceeded`.

## `CannotCompute`

| Message | Quand |
|---|---|
| `An outcome of the expression cannot be rolled: …` | Un résultat divise par zéro, donne des dés qui ne peuvent pas être lancés une fois leur nombre connu, ou nomme un dé inconnu ; l'exception précédente est le `CannotRoll` du jet |
| `The faces of the die "…" carry no value: they have no distribution.` | Un dé personnalisé dont les faces sont des symboles |
| `A distribution is that of one expression; the pool holds 2.` | Un groupe de plusieurs expressions |
| `An event is judged on the faces of one set of dice, such as "4d6".` | Un événement sur une expression qui n'est pas un seul ensemble de dés |

## Arguments invalides

`Natural`, `Fraction`, `Settings`, `Estimate`, `Distribution`, `Faces` et les événements refusent un argument invalide
avec l'`InvalidArgumentException` de PHP : un nombre naturel négatif, un dénominateur nul, un réglage sous 1, une
distribution sans valeurs, un nombre négatif, un double d'un seul dé, une suite d'une seule valeur.
