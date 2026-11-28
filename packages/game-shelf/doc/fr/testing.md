<!-- phil-dev source-sha256=e52ffca8a9cc613fa7dbbdfcde73e54421dca48eb008ee44b7f97d86208c1973 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : les étagères vivent
dans `Store\InMemoryShelfStore`, le catalogue dans le stockage en mémoire de `tilttek/game-catalog`, et son horloge est
la `MockClock` de `symfony/clock`. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

Une application teste son propre `ShelfStore` selon les règles du [stockage](extension-points.md#le-stockage) : une
étagère vide à la version 0 pour un membre qui n'a jamais rien gardé, une nouvelle étagère gardée une fois, une étagère
périmée refusée, la version augmentée de un à chaque écriture, et les souhaits relus dans leur ordre.
