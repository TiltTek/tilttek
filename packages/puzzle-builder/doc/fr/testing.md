<!-- phil-dev source-sha256=e1ed2a1b442d0081dabe74b2e5a0fd1c558449cce85553b33f53884cd43c952e -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau ni base de données : des réponses normalisées étape par
étape et option par option ; des réponses acceptées et partielles, et des clés refusées à chaque borne ; des énigmes,
des déverrouillages et des indices refusés à chaque borne ; des scénarios avec des cycles, des énigmes hors d'atteinte,
une exigence ou une finale manquante, et chaque champ mal formé de leur JSON ; des propositions justes, partielles,
fausses, mal formées et limitées ; des indices palier après palier ; des énigmes ouvertes et sautées par l'animateur ;
la vue, vérifiée pour ne contenir aucune réponse ; un avancement écrit et relu, et refusé champ par champ ; les deux
limiteurs. Dans le monodépôt, Infection maintient la bibliothèque à 100 % des mutants couverts.

`tests/Fuzz/scenario.php` est la cible de fuzzing d'un scénario : tout texte donne un scénario dont l'ordre contient
chaque énigme une fois, chacune après celles qu'elle exige, ou est refusé avec `InvalidScenario`, jamais une autre
exception. `composer fuzz` l'exécute.

`tests/Benchmark/PuzzleEngineBench.php` mesure, sur une chaîne de 200 énigmes, la lecture et la vérification du scénario
depuis le JSON, une proposition sur la dernière énigme quand 199 sont résolues, et la vue de cette partie.

## Tester une application

Donnez à `PuzzleEngine` un limiteur en mémoire et une horloge qui ne bouge pas :

```php
$clock = new MockClock('2026-11-13 20:00 UTC');
$engine = new PuzzleEngine(new InMemoryGuessLimiter(10, 60, $clock), $dispatcher, $clock);
```

`$clock->sleep(60)` termine une fenêtre du limiteur. Un test d'une vérification maison passe ses propositions par
`PuzzleEngine::guess()` et vérifie `GuessResult::$outcome`, puis que `PuzzleEngine::view()` ne contient aucune de ses
réponses.
