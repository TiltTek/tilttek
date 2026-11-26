<!-- phil-dev source-sha256=cf426f4d7791e87ffc31fd15d3752220403b8c585683d777c88b0dc44bbc5061 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : les conditions
vivent dans `Store\InMemoryConditionStore`, et l'heure est le `MockClock` de `symfony/clock`, avancé par chaque test au
moment voulu — la fin d'une condition en temps réel, à la milliseconde près. Un petit catalogue des tests contient une
condition de chaque sorte : remplacée, la plus haute, additionnée, séparée, ignorée, en déclin, entraînée par d'autres,
en temps de jeu, en temps réel et sur les tours de la source. Dans le monodépôt, Infection maintient le paquet à 100 %
des mutants couverts.

Une application teste son propre `ConditionStore` contre [les règles du magasin](extension-points.md#le-magasin) :
rien pour un personnage jamais gardé, des conditions neuves gardées une fois, des conditions périmées refusées, la
version augmentée de un à chaque écriture, et les applications, durées, moments et dernier tic relus tels qu'ils ont été
gardés.

Elle teste son catalogue en le construisant : une définition ou un catalogue dont les règles se contredisent est refusé
à la construction, avec la raison.
