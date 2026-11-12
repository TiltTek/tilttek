<!-- phil-dev source-sha256=0a175a12619e1dae07ded0a586f2d8112dc07c2f2ba2a9cae84272cc358394b5 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite couvre les jours et leurs jours de la semaine de part et d'autre de 1970, le jour d'aujourd'hui de l'auteur dans
son fuseau horaire, les objectifs et leurs refus, les quatre courbes, les jours d'écriture et les dates mises de côté,
les plans — premier jour, jours manqués, jours d'avance, compte du jour, arrondi dont la somme tombe juste, jours de
poids 0, jours sans écriture, avant le début, objectif atteint, échéance dépassée —, la consignation, le retrait et la
correction des comptes, l'objectif atteint annoncé une fois, l'effacement, et les magasins en mémoire par
`GoalStoreTestCase` et `LogStoreTestCase`, les contrats que réussit tout magasin. `tests/Benchmark/PlannerBench.php`
mesure un plan d'un mois et un de dix ans. Dans le monodépôt, Infection maintient la bibliothèque à 100 % des mutants
couverts.

## Tester du code qui consigne des comptes

Construisez `WritingGoals` avec les magasins en mémoire, une horloge que vous déplacez —
`Symfony\Component\Clock\MockClock` — et un répartiteur qui note ses événements :

```php
$clock = new MockClock('2026-11-03 03:30:00', 'UTC');
$goals = new WritingGoals(new InMemoryGoalStore(), new InMemoryLogStore(), $clock, $dispatcher);
$goals->save($goal);

self::assertSame('2026-11-02', $goals->plan($goal->id)->today->toString()); // in America/Montreal
```

Pour tester un plan seul, appelez `Planner::plan()` avec un `Progress::fromArray()` et le jour de votre choix.
