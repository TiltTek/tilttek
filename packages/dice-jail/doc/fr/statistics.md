<!-- phil-dev source-sha256=b825ee30f3685f3450113db381e5df9d3e145ae68e1e94910a0c31a2e64ac68a -->
# Les statistiques

## Le test

`Statistics\GoodnessOfFit::test()` exécute le test d'adéquation du khi carré de Pearson : il compare combien de fois
chaque face est sortie avec combien de fois un dé équitable la montrerait sur autant de lancers.

```php
use Tilttek\DiceJail\Statistics\{ExpectedFaces, GoodnessOfFit, Tally};

$result = new GoodnessOfFit()->test(Tally::of([20, 3, 17, 1, 1, 12]), ExpectedFaces::uniform(20));
```

- `Tally::of()` compte les faces, une valeur par lancer ; `Tally::ofRolledDice()` compte chaque lancer du `RolledDie`
  de `tilttek/dice`, relances et explosions comprises. Un décompte contient au plus 1 000 000 de lancers.
- `ExpectedFaces::uniform($sides)` et `ExpectedFaces::range($lowest, $highest)` sont des dés équitables dont les faces
  sont également probables ; `ExpectedFaces::fromDistribution()` prend la distribution exacte que
  `tilttek/dice-probability` calcule, pour un dé aux faces pondérées ou personnalisées, ou pour un total comme `2d6`
  jugé comme une seule issue par lancer. Une distribution estimée, ou dont les explosions sont tronquées, est refusée :
  le test exige des probabilités exactes. Un dé a de 2 à 1 000 faces.

La statistique est Σ (observé − attendu)² / attendu, avec un degré de liberté de moins que les faces. Sa valeur p est la
queue supérieure de la loi du khi carré, calculée par la fonction gamma incomplète régularisée, avec Γ exacte pour les
arguments entiers et demi-entiers qu'un dé donne ; elle concorde avec les tables publiées à neuf chiffres.

## Le minimum de lancers

On se fie au test quand chaque face est attendue au moins cinq fois : 30 lancers pour un d6, 100 pour un d20, 180 pour
2d6 (dont le 12 sort une fois sur 36). En dessous, le verdict est `NotEnoughRolls`, la valeur p est nulle et
`FitResult::rollsStillNeeded()` dit combien de lancers manquent.

## Les verdicts

| Verdict | Valeur p | Ce qu'il signifie |
|---|---|---|
| `NotEnoughRolls` | aucune | On ne peut pas encore se fier au test. |
| `NothingUnusual` | 0.01 ou plus | Un dé équitable roule aussi inégalement au moins une série sur 100 : le hasard l'explique. |
| `SomewhatUnusual` | de 0.001 à 0.01 | Entre une série sur 1 000 et une série sur 100. |
| `VeryUnusual` | sous 0.001 | Moins d'une série sur 1 000. |

## Les lire honnêtement

- Une valeur p dit à quel point les lancers seraient rares **si le dé était équitable**, jamais la probabilité que le
  dé soit pipé.
- Un dé équitable sur cent est « plutôt inhabituel » par pur hasard, et un sur mille « très inhabituel ». Une table qui
  teste beaucoup de dés, ou le même dé encore et encore, en rencontrera.
- Après un verdict inhabituel, jugez **les nouveaux lancers seuls** — `DieAssessor::assess($die, $expected, since:
  $release)` — : les lancers qui ont soulevé le soupçon ne peuvent pas aussi le confirmer.
- Le test regarde comment les faces sont réparties, pas si le dé roule bas. Un dé peut rouler sous sa moyenne sans être
  inhabituel ; `FitResult::observedMean` et `expectedMean` montrent l'écart sans le juger.
- **Les dés numériques** tirent d'un seul générateur aléatoire partagé par tous les dés : aucun dé numérique n'est plus
  chanceux qu'un autre, et un verdict inhabituel y relève du hasard, ou d'une défaillance du générateur pour tous les
  dés de la même façon.

`FitResult` liste aussi chaque `FaceCount` — face, observée, attendue — pour un graphique.
