<!-- phil-dev source-sha256=4c328827b64a2b9f7d8e23a6677e2358e3fcc75cfb19ded0e801fb4eed771590 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau ni base de données : les formes des codes, des membres et
des détenteurs, les détenteurs référencés par une adresse courriel refusés, chaque borne d'une définition, les paliers
dans le désordre, les définitions écrites puis relues et tout le reste refusé, les évaluations à plusieurs niveaux, les
versions définies de nouveau, réécrites ou plus anciennes, les complétions enregistrées et annoncées une seule fois,
conservées quand une nouvelle version agrandit l'ensemble, par détenteur, effacées, et les stockages. Dans le
monodépôt, Infection maintient la bibliothèque à 100 % des mutants couverts.

`tests/Fuzz/definition.php` est la cible de fuzzing d'une définition : tout texte se lit comme une définition qui
s'écrit en revenant à elle-même, ou est refusé avec `InvalidSet`, jamais avec une autre exception. `composer fuzz` la
lance.

`tests/Benchmark/SetsBench.php` mesure le plus grand ensemble — 1 000 membres et 20 paliers, chaque palier atteint —
par rapport à des possessions de 5 000 membres, et sa définition relue depuis du JSON.

## Tester une application

Donnez à `Sets` les stockages en mémoire, une horloge à vous et un répartiteur qui garde les événements, puis vérifiez
ce que l'application accorde quand elle les entend :

```php
$sets = new Sets(new InMemoryDefinitionStore(), new InMemoryCompletionStore(), $frozenClock, $recordingDispatcher);
```
