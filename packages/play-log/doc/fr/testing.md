<!-- phil-dev source-sha256=e650a3230ddab9251556b49be89af8948318a24d774061c0194b6db847359422 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : les parties vivent
dans `Store\InMemoryPlayStore`, le catalogue dans le stockage en mémoire de `tilttek/game-catalog`, et son horloge est
la `MockClock` de `symfony/clock`. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

Une application teste son propre `PlayStore` selon les règles du [stockage](extension-points.md#le-stockage) : aucune
partie pour un identifiant jamais gardé, une nouvelle partie gardée une fois, une partie périmée refusée à
l'enregistrement et à la suppression, la version augmentée de un à chaque écriture, les places relues dans leur ordre,
et chaque `PlayQuery` donnant les parties que `PlayQuery::matches()` accepte, les plus récentes d'abord, celles dont la
date est inconnue en dernier.
