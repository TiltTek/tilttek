<!-- phil-dev source-sha256=180520812c83a92c318491be46bdbcc646ab024052f5b1f03d9ee7ba9978300c -->
# Référence

| Type | Rôle |
|---|---|
| `Probabilities` | Le point d'entrée : `distribution()`, `compare()` et `chance()` |
| `Settings` | La profondeur d'explosion, la limite de travail, les échantillons et le germe d'une estimation |
| `Distribution` | Chaque valeur d'une expression et son poids ; probabilités, au moins, au plus, moyenne, variance, écart type |
| `Outcome` | Une valeur d'une distribution avec sa probabilité, au moins et au plus |
| `Versus` | Deux distributions l'une contre l'autre : la première plus haute, une égalité, la seconde plus haute |
| `Chance` | La probabilité d'un événement |
| `Estimate` | Comment un résultat estimé a été tiré, et sa marge |
| `EstimateReason` | `TooLarge` ou `Unsupported` |
| `Exact\Natural` | Un nombre naturel de toute taille |
| `Exact\Fraction` | Un nombre rationnel exact |
| `Event\Event` | Ce que les faces d'un ensemble montrent ou non |
| `Event\Faces` | Les faces d'un ensemble, comptées par valeur |
| `Event\Count` | Combien de dés montrent une face, comparé à un nombre |
| `Event\OfAKind` | Un double, un triple, ou plus de dés identiques |
| `Event\Straight` | Des valeurs consécutives |
| `Event\AllEqual`, `Event\AllDifferent`, `Event\AllMatch` | Tous les dés identiques, tous différents, ou tous conformes |
| `Event\AnyOf`, `Event\AllOf`, `Event\Not` | Des événements composés |
| `Exception\ProbabilityException` | L'interface des exceptions de la bibliothèque |
| `Exception\CannotCompute` | Aucune distribution ni probabilité ne peut être donnée |

Les classes de `Computation\` (le calculateur, ses poids, son budget, les multiensembles et l'échantillonneur) sont
internes.
