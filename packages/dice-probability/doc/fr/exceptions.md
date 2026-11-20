<!-- phil-dev source-sha256=070315089eff1c190548979bec46ff8849b905fe59e0d6c18a93568f79d8176d -->
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
| `The estimate would roll more than 200000 dice over its 20000 samples: lower the samples, or raise the roll limit.` | Une estimation qui dépasse la limite de dés des `Settings` : `100d6!`, `100d1000` aux valeurs par défaut |
| `The estimate would take more than 20000000 steps to search the groups of its 20000 samples: lower the samples, or raise the group work limit.` | Une estimation dont la recherche des groupes dépasse la limite de travail des groupes des `Settings` : `10d10g10`, `12d6g12` aux valeurs par défaut |

## Arguments invalides

`Natural`, `Fraction`, `Settings`, `Estimate`, `Distribution`, `Faces` et les événements refusent un argument invalide
avec l'`InvalidArgumentException` de PHP : un nombre naturel négatif, un dénominateur nul, un réglage sous 1, une
distribution sans valeurs, un nombre négatif, un double d'un seul dé, une suite d'une seule valeur.
