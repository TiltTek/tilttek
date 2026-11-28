<!-- phil-dev source-sha256=929f6056dbbe9acf58471f6ce50b5e5d5479591ff2d38c2d458cc83e30a38309 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : les soirées vivent
dans `Store\InMemoryNightStore`, le catalogue dans l'entrepôt en mémoire de `tilttek/game-catalog`, et l'horloge est le
`MockClock` de `symfony/clock`. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

Une application teste son propre `NightStore` selon les règles de [l'entrepôt](extension-points.md#lentrepôt) : une
nouvelle soirée conservée une fois, une soirée périmée refusée, la version augmentée de un à chaque écriture.
