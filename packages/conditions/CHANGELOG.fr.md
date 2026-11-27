<!-- phil-dev source-sha256=35a577557dc0f999d6e1f3067a0aff603f5e031766d1087bb9badd9e4dc4b615 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/conditions` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-26

### Ajouté

- `ConditionDefinition` et `Stacking` : un état du système de jeu, déclaré par le site, qu'une nouvelle application
  remplace, élève à la valeur la plus haute, additionne jusqu'à sa valeur la plus haute, ajoute comme instance distincte
  jusqu'à 16, ou laisse tel quel ; une valeur jusqu'à 100, jusqu'à 32 modificateurs et jusqu'à 16 états qu'il entraîne
  avec lui.
- `ConditionCatalogue` : jusqu'à 1 000 états, chaque identifiant une seule fois ; un état n'entraîne que des états du
  catalogue, sans valeur, et ne se ramène jamais lui-même par d'autres.
- `Duration`, `DurationKind`, `Boundary` et `Anchor` : une durée de 1 à 10 000 rounds comptés au début ou à la fin des
  tours de la cible, des tours de la source ou des rounds ; jusqu'à cent ans de temps de jeu ; d'une seconde à 366 jours
  de temps réel ; ou jusqu'au retrait.
- `Decay` : une valeur qui baisse d'une quantité au début ou à la fin des propres tours du personnage touché, l'état
  prenant fin à zéro.
- `Modifier` et `Operation` : une addition, une multiplication de 0 à 1 000 pour cent, un plancher, un plafond ou un
  remplacement d'une statistique, selon la valeur de l'état sur demande ; les additions d'un même type ne gardent que
  leur bonus le plus haut et leur pénalité la plus basse.
- `Effect\EffectCalculator`, `Effect\AppliedModifier`, `Effect\StatValue` et `Rounding` : jusqu'à 1 000 statistiques une
  fois les modificateurs comptés, dans l'ordre des opérations, les multiplications arrondies selon la règle du système,
  les valeurs bornées, avec les modificateurs qui ont fait la différence.
- `ActiveCondition` et `CharacterConditions` : jusqu'à 64 applications sur un personnage, chacune avec son identifiant
  d'instance, sa valeur, sa source, ce qui reste de sa durée et sa fin en temps réel, gardées à une version.
- `Conditions::apply()` : un état appliqué selon sa règle d'empilement, sa valeur et sa source vérifiées, sa durée
  donnée ou la sienne ; ce qui a expiré en temps réel prend fin d'abord.
- `Conditions::remove()`, `removeCondition()` et `setValue()` : une application retirée, chaque application d'un état
  retirée, une valeur changée ou mise à zéro pour la retirer.
- `Conditions::startTurn()`, `endTurn()`, `startRound()`, `endRound()` et `advanceTime()` : les durées en rounds et en
  temps de jeu décomptées, les valeurs diminuées, pour jusqu'à 200 participants, chaque limite comptée une seule fois
  par son identifiant de tic, un participant sans changement laissé non écrit.
- `Conditions::expire()` : les états dont la fin en temps réel est passée terminés, pour une tâche planifiée.
- `Conditions::has()`, `modifiers()` et `effective()` : si un personnage a un état, appliqué ou entraîné par un autre ;
  les modificateurs en vigueur ; les statistiques une fois comptées ; ce qui a expiré en temps réel laissé de côté avant
  qu'il prenne fin.
- `View\ConditionsView` et `View\ConditionState` : les applications, puis les états qu'elles entraînent avec ceux qui
  les entraînent, et la prochaine fin en temps réel, en données simples.
- Les événements `ConditionApplied`, `ConditionChanged` et `ConditionEnded` avec son `EndReason`, par identifiants
  seulement, annoncés une seule fois à un `Contract\ConditionObserver`.
- `Contract\ConditionStore`, gardé à une version, et `Store\InMemoryConditionStore` pour les tests.
- Les exceptions `InvalidCondition`, `ConditionRefused`, `ConditionNotFound` et `ConditionConflict`, sous
  `ConditionsException`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
