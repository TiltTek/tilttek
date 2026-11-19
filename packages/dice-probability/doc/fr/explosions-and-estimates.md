<!-- phil-dev source-sha256=4ef0c568b1264731c5475e732a2e94eafe70b6b6a69071277ed979dc9f1f59ca -->
# Explosions et estimations

## `Settings`

```php
new Settings(int $explosionDepth = 10, int $workLimit = 500_000, int $samples = 20_000, int $seed = 0, int $rollLimit = 200_000, int $groupWorkLimit = 20_000_000);
```

| Réglage | Ce qu'il borne |
|---|---|
| `explosionDepth` | Combien d'explosions par dé le calcul exact suit |
| `workLimit` | Combien de travail un calcul exact fait au plus ; chaque poids multiplié ou additionné compte pour une opération tant que les poids sont petits, davantage à mesure qu'ils grandissent |
| `samples` | De combien de jets une estimation est tirée |
| `seed` | Le germe de ces jets : le même germe donne la même estimation |
| `rollLimit` | Combien de dés une estimation lance au plus sur tous ses échantillons, explosions et relances comprises |
| `groupWorkLimit` | Combien d'étapes une estimation fait au plus sur tous ses échantillons pour chercher les groupes qui atteignent une somme, `10d10g10` |

Chaque borne vaut au moins 1. Ensemble, la limite de travail, la limite de dés et la limite de travail des groupes
bornent le travail de toute expression : aux valeurs par défaut, environ une seconde de processeur pour le calcul exact, et autant pour une
estimation.

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

Le coût d'un calcul exact, à titre indicatif : `30d6` demande environ 7 200 opérations et `10d6!` environ 300 000. Une
sélection parmi de nombreux dés à nombreuses faces croît le plus vite, et `50d20kh10` dépasse la limite par défaut. Les
poids de nombreux dés sont des nombres de nombreux membres : une opération sur eux compte une de plus par tranche de
seize produits de membres, et une fraction ramenée à sa plus simple expression (la probabilité qu'une explosion ait été
coupée, par exemple) le cube de sa taille en membres. Une sélection ou un événement sur les multiensembles de `n` dés construit d'abord les
coefficients binomiaux jusqu'à `n`, environ `n²/2` additions, comptées de la même façon.

## La limite de dés

Une estimation lance `samples` fois, et chaque jet peut lancer de nombreux dés : `100d6!` en lance cent vingt en
moyenne, soit 2,4 millions de dés sur vingt mille échantillons. Au-delà de `rollLimit` dés, l'estimation s'arrête et
l'appel lève `CannotCompute` (voir [Exceptions](exceptions.md)) au lieu de continuer. Baissez les échantillons, ou
relevez la limite de dés, pour les expressions qui en ont besoin :

```php
new Probabilities(settings: new Settings(samples: 2_000));          // 100d6!: about 240 000 dice, still too many
new Probabilities(settings: new Settings(rollLimit: 3_000_000));    // 100d6!: allowed, about ten seconds
```

## La limite de travail des groupes

Un ensemble qui forme des groupes atteignant une somme, `10d10g10`, est toujours estimé, et chaque échantillon cherche le
plus de groupes que ses dés forment. Pour les `n` dés qui comptent dans un ensemble, cette recherche prend environ
`n × 2ⁿ` étapes jusqu'à douze dés, et une passe gloutonne d'environ `n²` étapes au-delà, comme le fait le lanceur de
`tilttek/dice`. Les étapes de chaque échantillon sont comptées ; au-delà de `groupWorkLimit`, l'estimation s'arrête et
l'appel lève `CannotCompute`. Aux valeurs par défaut, environ une seconde de recherche :

| Expression | Étapes par échantillon | À 20 000 échantillons |
|---|---|---|
| `6d6g10` | 384 | 7,7 millions : estimée |
| `10d10g10` | 10 240 | 205 millions : refusée |
| `12d6g12` | 49 152 | 983 millions : refusée |

```php
new Probabilities(settings: new Settings(samples: 1_000));               // 10d10g10: 10.2 million steps, estimated
new Probabilities(settings: new Settings(groupWorkLimit: 250_000_000));  // 10d10g10: allowed, about ten seconds
```
