<!-- phil-dev source-sha256=2abc2ece4c9070794dc797898fc53c702f6ae12dd368fae7962e55f1997bdb9d -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : les préparations
vivent dans `Store\InMemoryPreparationStore`, et un observateur des tests enregistre les événements dans l'ordre. Dans
le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

Une application teste son propre `PreparationStore` d'après les règles du [dépôt](extension-points.md#le-dépôt) : rien
pour un identifiant jamais gardé, une nouvelle préparation gardée une fois, une préparation périmée refusée à
l'enregistrement ou à la suppression, la version augmentée de un à chaque écriture, les parties relues dans leur ordre
avec leurs faits, et chaque `PreparationQuery` donnant les préparations que `PreparationQuery::matches()` accepte, par
identifiant.

## Bancs d'essai

`tests/Benchmark/PreparationBench.php` mesure, sur la plus grande préparation que la bibliothèque accepte — 50 scènes
mettant en scène 20 personnages et 10 lieux chacune, 50 secrets de 10 indices, 100 personnages, 50 lieux, 100 notes et
50 éléments —, un secret révélé puis caché de nouveau par le service, ce qui restait reporté dans une préparation vide,
et toute la préparation vérifiée de nouveau par un seul changement. Depuis la racine du monodépôt :

```sh
composer bench
```
