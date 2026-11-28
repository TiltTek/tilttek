<!-- phil-dev source-sha256=e512950e57aa8680d7f312c88de553f9919125cb40c32a2d7ebc8a8d53f19e6b -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, tourne sans réseau : les tours et leurs données, les fins, le registre, les tables
avec le minuteur de tour et les délais tardifs, le gobelet de dés, l'estimateur et la poursuite d'une cargaison avec des
dés réglés à la main, et les trois jeux — chaque coup, refus, délai, issue, vue et donnée corrompue. Une partie entière
de Crazy Eights est jouée à une table, puis rejouée à partir de son journal carte pour carte. Dans le monodépôt,
Infection tient la bibliothèque à 100 % des mutants couverts.

`tests/Fuzz/crazy-eights-move.php` est la cible de fuzzing des coups de Crazy Eights : toute charge utile JSON d'un jeu,
d'une pige, d'un passe ou d'un délai est refusée par une exception du moteur, ou jouée, après quoi aucune carte n'est
perdue et la vue ne contient aucune carte d'une autre main. `composer fuzz` la lance.

## Bancs d'essai

`tests/Benchmark/CrazyEightsBench.php` construit la vue d'un siège à une table de quatre, ce qui rejoue les gestes des
cartes, comme le fait chaque coup et chaque vue. Sur PHP 8.5 sans cache d'opcodes, avec des mélanges, le geste le plus
lourd : environ 2,5 ms après 200 gestes — une longue partie — et environ 12 ms à la borne de 1 000.

## Tester un jeu de taverne

Les méthodes d'un jeu sont pures : construisez un `GameState` à la main et appelez-les, ou jouez-le à `Tables` avec le
magasin des journaux en mémoire et une graine fixe, ce qui donne les mêmes dés et les mêmes cartes à chaque exécution.
Un test dispose les cartes de Crazy Eights en écrivant les gestes de ses données — une carte déplacée dans une main, sur
la défausse.

```php
$tavern = new TavernTables(new Tables($games->games(), new InMemoryLogStore()), $games, new InMemoryTurnTimer());
$tavern->open('pig', 't-1', ['ann', 'bob'], seed: str_repeat('ab', 32));
```
