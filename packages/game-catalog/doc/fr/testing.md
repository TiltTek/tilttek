<!-- phil-dev source-sha256=8ecba137768f71fe6298c97cdd0ed4db1f71336fb87969682eb9437fcb95b140 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau :

- les valeurs et leurs refus : chaque champ d'une entrée à ses bornes et un cran au-delà, les identifiants de chaque
  base, les codes-barres de chaque longueur avec leur chiffre de contrôle, les origines, les errata, les liens, les
  bulletins et les corrections ;
- l'entrée : les corrections gardées à côté des valeurs de la source, les champs qui lui sont rendus, les identifiants
  et les errata ;
- les services : la synchronisation depuis une source, l'alimentation jusqu'à une limite, les liens jusqu'à leur
  limite, les codes-barres identifiés une fois puis localement, les corrections acceptées, refusées, en conflit et
  forcées, les votes comptés, les expériences figées ;
- l'entrepôt en mémoire : les versions, les index, les liens, les corrections, les bulletins et l'historique ;
- le lecteur de collection : un export avec guillemets et virgules, des en-têtes sans égard à la casse, chaque refus
  d'un fichier et d'une ligne, les limites atteintes exactement et dépassées ; et l'importateur.

La suite d'intégration, sous `tests/Integration`, branche les services sur un seul entrepôt, comme le fait une
application : une source alimente le catalogue, une entrée est ajoutée à la main et reliée, des membres votent et
corrigent, la source renvoie ses entrées, un membre importe son export, et une boîte est numérisée deux fois.

Dans le monodépôt, Infection garde la bibliothèque à 100 % des mutants couverts.

## Fuzzing

Le lecteur de collection a une cible de fuzzing, `collection-file`, nourrie par les germes de
`tests/Fuzz/corpus/collection-file` : quel que soit le fichier, le lecteur renvoie ses lignes dans ses limites, ou le
refuse par une exception de la bibliothèque ; chaque ligne contient un identifiant de sa base, des statuts sans
répétition, ainsi qu'une priorité et des parties jouées dans leurs bornes.

```sh
composer fuzz -- collection-file
```

## Complexité

Pour R lignes de C cellules, le lecteur est en O(R · C) et tient le fichier une fois en mémoire, borné par
`Limits::bytes`. Synchroniser une entrée est en O(I + E) pour ses I identifiants et ses E errata — au plus 3 et
500 —, compter les bulletins d'une entrée est en O(B) pour ses B bulletins d'au plus 30 nombres de joueurs chacun, et
relier lit les liens des deux entrées, au plus 200 chacune.

## Tester du code qui utilise le catalogue

Utilisez `Store\InMemoryCatalogStore` et une horloge fixe :

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\GameCatalog\Catalog;
use Tilttek\GameCatalog\Details;
use Tilttek\GameCatalog\Store\InMemoryCatalogStore;

$catalog = new Catalog(new InMemoryCatalogStore(), new MockClock('2027-04-01 09:00 UTC'));
$catalog->add('tide', new Details('Tide'));

self::assertSame('Tide', $catalog->sheet('tide')->details()->name);
```
