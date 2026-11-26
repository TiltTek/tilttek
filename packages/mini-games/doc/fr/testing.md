<!-- phil-dev source-sha256=86c0f9a500e50b0a9ac79b043b3af898bdfb07409ddea9a6778e6ab56bce66fe -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, tourne sans réseau : sens des scores et records, commandes, jours et leurs
numéros, graine quotidienne, grilles et symboles, fins, historiques, registre, jeux quotidiens à des tables du moteur
— le même jeu pour chaque joueur d'une journée, le score envoyé une fois, la graine montrée une fois la journée
terminée —, et les deux jeux d'exemple, leurs coups, refus, issues, vues, partages et données corrompues. Dans le
monodépôt, Infection tient la bibliothèque à 100 % des mutants couverts.

`tests/Fuzz/code.php` est la cible de fuzzing des essais de Taureaux et Vaches : tout texte est refusé par
`InvalidMiniGame`, ou lu comme un code de chiffres différents qui est le texte lui-même et qui marque tous les taureaux
contre lui-même. `composer fuzz` la lance.

## Banc d'essai

`tests/Benchmark/DailyGameBench.php` joue un jeu quotidien complet de Taureaux et Vaches, dix essais suivis chacun de
leur vue, puis écrit son partage, avec le magasin des journaux en mémoire : environ 0,5 ms sur PHP 8.5 sans cache
d'opcodes.

## Tester un mini-jeu

Les méthodes d'un mini-jeu sont pures : construisez un `GameState` à la main et appelez-les, ou jouez-le à `Tables` avec
le magasin des journaux en mémoire et une graine fixe, ce qui donne la même mise en place à chaque exécution.

```php
$tables = new Tables(new MiniGames($game)->games(), new InMemoryLogStore());
$state = $tables->open('my-game', 't-1', ['u-1'], seed: str_repeat('ab', 32));
```
