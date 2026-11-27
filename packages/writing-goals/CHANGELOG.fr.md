<!-- phil-dev source-sha256=f228ff8cf15aae4d212fb8365c254ee6e6788127c86c34d6084127dbc13aaf05 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/writing-goals` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.0-alpha - 2026-11-14

### Modifié

- `Contract\LogStore` garde l'avis d'un objectif atteint tant qu'il est dû : `markReached($goal, $day)`,
  `undelivered($goal)` et `markDelivered($goal, $day)` ; `forget()` le retire avec les décomptes. Un stockage écrit pour
  0.1 implémente les trois méthodes.
- `GoalReached` est distribué au moins une fois : noté comme dû quand l'objectif est atteint, et remis une fois
  distribué sans exception. Un écouteur qui lève une exception le laisse dû ; le prochain décompte inscrit ou corrigé
  vers l'objectif le distribue de nouveau. Les écouteurs doivent être idempotents, par `idempotencyKey()`.

### Ajouté

- `WritingGoals::deliver()` : le `GoalReached` de l'objectif encore dû distribué de nouveau, pour reprendre après
  l'échec d'un écouteur.

### Corrigé

- Un écouteur de `GoalReached` qui a échoué ne perd plus l'événement d'un objectif déjà atteint.

## 0.1.0-alpha - 2026-11-12

### Ajouté

- `Goal`, `Day` et `Unit` : une cible en mots ou en pages d'un premier jour à une échéance, tous deux compris, jusqu'à 3
  660 jours, dans le fuseau horaire de l'auteur.
- `Curve` et `CurveShape` : linéaire, chargée au début, chargée à la fin, ou de 1 à 366 poids que l'auteur dessine,
  étirés sur les jours d'écriture.
- `WritingDays` : les jours de la semaine où l'auteur écrit, moins jusqu'à 366 dates mises de côté.
- `Planner`, `Plan` et `DailyQuota` : le quota quotidien recalculé à partir de ce qui reste, en proportion du poids de
  chaque jour dans le plan entier, en nombres entiers dont la somme lui est égale ; un jour manqué ne fait que remodeler
  les jours suivants, et aucune série n'est comptée.
- `Progress` : les décomptes de chaque jour.
- `WritingGoals` : des objectifs gardés et modifiés, des décomptes inscrits, repris et corrigés au jour de l'auteur ou à
  un jour antérieur, des plans, et l'effacement d'un auteur.
- `Event\GoalReached` : distribué quand un décompte amène un objectif à sa cible, pour un badge ou une célébration.
- `Contract\GoalStore`, `Contract\LogStore`, `Store\InMemoryGoalStore` et `Store\InMemoryLogStore`, avec
  `GoalStoreTestCase` et `LogStoreTestCase`, les contrats que passe tout stockage.
- Des exceptions sous `Exception\WritingGoalsException` : `InvalidGoal` et `UnknownGoal`.
- `tests/Benchmark/PlannerBench.php` : un plan d'un mois et un de dix ans.
- Le squelette du paquet : `composer.json`, `phil-dev.php` et `composer-dependency-analyser.php`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
