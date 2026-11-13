<!-- phil-dev source-sha256=34dbd68830783f727b1d80a9db7ed2d5dee2e37d60a00d8c55894a8e220e6b3b -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau ni base de données : les formes des codes, des versions et
des noms, chaque borne d'un catalogue et d'une liste, les catalogues et les listes écrits puis relus avec tout le reste
refusé, les points avec figurines et options, chaque règle du validateur et son message, les comptes et les parts de
points à leurs bornes, les contraintes propres à l'application, et le texte et le HTML échappé de l'exportation. Dans le
monodépôt, Infection maintient la bibliothèque à 100 % des mutants couverts. Le catalogue des tests est inventé.

`tests/Fuzz/army-catalogue.php` et `tests/Fuzz/army-list.php` sont les cibles de fuzzing d'un catalogue et d'une
liste : tout texte se lit comme un catalogue ou une liste qui s'écrit de nouveau à l'identique, ou est refusé avec son
exception, jamais avec une autre ; la liste est ensuite vérifiée et exportée. `composer fuzz` les exécute.

`tests/Benchmark/WargameArmyBench.php` mesure la plus grande liste — 20 détachements de 200 unités avec dix options
chacune — validée selon un catalogue de 2 000 unités et de 200 contraintes, son exportation HTML, et le catalogue lu
depuis du JSON.

## Tester une application

Donnez au validateur un fournisseur en mémoire d'un catalogue inventé :

```php
$validator = new ArmyValidator(new InMemoryCatalogueProvider(Catalogue::fromJson($inventedCatalogue)));
```
