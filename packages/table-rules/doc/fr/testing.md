<!-- phil-dev source-sha256=54f1222e37d85a171f56a2177171a8a8cc974aa6c1ae3b43784f2664596bf34a -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : les livres de règles
vivent dans `Store\InMemoryRulebookStore`, et l'heure est la `MockClock` de `symfony/clock`, avancée par chaque test
jusqu'au moment qu'il lui faut. Les tests couvrent chaque borne à sa limite et un cran au-delà, chaque état d'un
joueur, et la comparaison des règles ajoutées, retirées, déplacées et modifiées ligne par ligne. Dans le monodépôt,
Infection tient le paquet à 100 % de mutants couverts.

Une application teste son propre `RulebookStore` contre les règles du [magasin](extension-points.md#le-magasin) : rien
pour une table jamais gardée, un nouveau livre de règles gardé une fois, un livre périmé refusé, la révision augmentée
de un à chaque écriture, une version gardée seulement avec son livre de règles, et les versions, les décisions et les
moments relus tels qu'ils ont été gardés.

Elle teste son `VariantCatalogue` en publiant une version avec une variante que son système offre, et une qu'il
n'offre pas.
