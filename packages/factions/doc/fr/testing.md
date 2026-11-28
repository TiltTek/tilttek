<!-- phil-dev source-sha256=9d9f124bd954beb4464fd28d1d260f81ed10a9738acc6725a20356731721e321 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : les listes et leur
historique vivent dans `Store\InMemoryRosterStore`, l'horloge est la `MockClock` de `symfony/clock`, un observateur des
tests enregistre les événements dans l'ordre, et un magasin des tests refuse chaque écriture pour montrer ce que laisse
un conflit. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

Une application teste son propre `RosterStore` selon les règles du [magasin](extension-points.md#le-magasin) : rien
pour une campagne jamais conservée, une nouvelle liste conservée une fois, une liste périmée refusée quand on la
conserve ou la supprime sans que rien ne soit écrit, la version augmentée de un à chaque écriture, les entrées
conservées dans la même écriture et relues, la plus récente d'abord, pour une faction jusqu'à la limite — les entrées
d'une relation pour ses deux factions —, et l'historique oublié avec la liste.

## Bancs d'essai

`tests/Benchmark/RosterBench.php` mesure, sur la plus grande liste que prend la bibliothèque — 64 factions, chacune avec
16 rangs, une position pour le groupe et 63 personnages et 64 membres, et 512 relations —, la liste vue par son maître
de jeu et par un joueur, chacune transformée en tableau, et une position décalée par le service, qui revérifie toute la
liste. À partir de la racine du monodépôt :

```sh
composer bench
```
