<!-- phil-dev source-sha256=f48e784501a02408971e8df8e3b5efa995515e41dddfa7ee2ede6f09d84ed2fe -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau ni base de données : les définitions et leurs refus, les
options, les phases sur Symfony Workflow et leur diagramme, les tours de chaque ordre, l'initiative et les retards, le
moteur avec un petit jeu de course et avec la scène de jeu de rôle, le journal écrit et relu, les rejeux déterministes
— une table de trente coups rejouée depuis son JSON jusqu'à un état égal —, les rejeux refusés pour une version
changée ou un jeu qui tire autrement, les rejeux vérifiés contre l'engagement sur le germe, la reprise, les préférences et les statistiques, les états écrits
puis relus avec tout le reste refusé, et `Tables` avec ses magasins, son cache, ses coups concurrents et son éditeur : la reprise
refusée une fois la partie terminée, un état périmé du même nombre de coups jamais servi ni réécrit quand deux
écrivains s'entrecroisent, un journal relu quand il change pendant la lecture, et une table lue une fois par requête.
Dans le monodépôt, Infection maintient la bibliothèque à 100 % des mutants couverts.

`tests/Fuzz/log.php` est la cible de fuzzing des journaux : tout texte JSON est refusé avec les exceptions de la
bibliothèque ou donne un journal qui se rejoue, et dont le journal écrit puis relu se rejoue jusqu'au même état.
`tests/Fuzz/state.php` est celle des états, et `tests/Fuzz/snapshot.php` celle des instantanés qu'un cache partagé redonne : tout texte JSON
est refusé avec les exceptions de la bibliothèque ou donne un état, ou un instantané, qui, écrit puis relu, est le même.
`composer fuzz` les exécute toutes les trois.

`tests/Benchmark/ReplayBench.php` mesure un coup joué sur un état, un journal de 100 et de 1 000 coups rejoué, et un
journal relu depuis ses valeurs simples.

## Tester un jeu

Donnez un germe à la table, pour que son hasard soit le même à chaque exécution, jouez les coups et vérifiez l'état ;
vérifiez ensuite que le journal se rejoue jusqu'au même état :

```php
$engine = new Engine();
$table = $engine->start($game, 'table:1', ['ann', 'bob'], [], str_repeat('0', 64));
$table = $engine->play($game, $table, new Move('race.roll', 'ann'));

self::assertEquals($table->state, $engine->replay($game, GameLog::fromArray($table->log->toArray())));
```

Le test de rejeu attrape un jeu qui lit une horloge, un service ou une variable globale : son rejeu tire ou décide
autrement.
