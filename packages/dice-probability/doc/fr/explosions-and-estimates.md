<!-- phil-dev source-sha256=aa6744d041e680885dc94437fdfe29acfa014dfceea46f3a33573bc45a1d4aeb -->
# Explosions et estimations

## `Settings`

```php
new Settings(int $explosionDepth = 10, int $workLimit = 500_000, int $samples = 20_000, int $seed = 0);
```

| Réglage | Ce qu'il borne |
|---|---|
| `explosionDepth` | Combien d'explosions par dé le calcul exact suit |
| `workLimit` | Combien d'opérations sur les poids un calcul exact fait au plus ; chaque poids multiplié ou additionné compte pour une |
| `samples` | De combien de jets une estimation est tirée |
| `seed` | Le germe de ces jets : le même germe donne la même estimation |

Chaque borne vaut au moins 1.

## Explosions

Une explosion sans limite pourrait continuer sans fin. Le calcul suit chaque dé jusqu'à `explosionDepth` explosions.
Les valeurs des jets qui auraient explosé plus loin sont comptées telles qu'elles sont à ce point, un peu trop basses.
Leur probabilité est le `remaining` de la distribution : pour `1d6!` à la profondeur par défaut, `1/362797056`, soit une
chance sur six à la puissance onze ; pour un ensemble de dés, la probabilité que l'un d'eux soit allé aussi loin. Une
explosion dont la limite est au plus égale à la profondeur (`1d6!(3)`) est exacte, et rien ne reste.

## Estimations

Au-delà de la limite de travail, ou pour une construction que le calcul exact ne suit pas, le résultat est tiré de
`samples` jets du lanceur propre à la bibliothèque, avec le germe `seed`. Chaque poids est le nombre de jets qui ont
donné la valeur, sur le nombre de jets. Le résultat l'indique :

```php
$distribution->isEstimated();          // true
$distribution->estimate->reason;       // EstimateReason::TooLarge or EstimateReason::Unsupported
$distribution->estimate->samples;      // 20000
$distribution->estimate->margin();     // 0.0069: each probability is within 0.69 points, 19 times out of 20
```

La marge est la demi-largeur de l'intervalle de confiance à 95 % d'une probabilité dans le pire cas, soit une demie :
`1.96 × √(0.25 / samples)`. Une estimation n'indique aucun `remaining` : ses jets explosent aussi loin que les limites
d'un jet le permettent.

| Raison | Quand |
|---|---|
| `TooLarge` | Le calcul exact dépasserait la limite de travail : `50d20kh10`, `100d100` avec des sélections |
| `Unsupported` | Dés uniques, dés tous identiques qui explosent, occurrences, groupes, filtres de l'ensemble, dés précédents, une sélection parmi des dés qui explosent en dés propres, des valeurs égales qui contribuent différemment à une sélection, dés de l'application dont les faces ne sont pas connues |

Le coût d'un calcul exact, à titre indicatif : `30d6` demande environ 2 400 opérations et `20d100` environ 21 000. Une
sélection parmi de nombreux dés à nombreuses faces croît le plus vite, et `50d20kh10` dépasse la limite par défaut.
