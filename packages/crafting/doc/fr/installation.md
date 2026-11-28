<!-- phil-dev source-sha256=553c8afdd38f211c8b52758372e71df334d45837b2be3ba87ca774589b08ed56 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- `phil/resource`, dont les références nomment les détenteurs.
- Une horloge `psr/clock`, comme `symfony/clock`.

## Installer

```sh
composer require tilttek/crafting
```

## Câbler la bibliothèque

`Crafter` reçoit le livre de recettes, l'inventaire de l'application et son horloge :

```php
use Tilttek\Crafting\Crafter;
use Tilttek\Crafting\Policy\ProtectedItems;
use Tilttek\Crafting\RecipeBook;

$book = RecipeBook::fromArray($definition, new ProtectedItems(['gold-die']));

$crafter = new Crafter(
    $book,
    $inventory,                            // Contract\Inventory
    $clock,                                // Psr\Clock\ClockInterface
    [$observer],                           // Contract\CraftObserver, told of each craft
);
```

Le livre vient de la configuration du site ou de sa base de données, comme le décrivent les [recettes](recipes.md).
`Store\InMemoryInventory` sert les tests et les applications qui reconstruisent leurs inventaires à chaque démarrage ;
une application implémente `Contract\Inventory` sur le stockage qui garde déjà ses objets, comme le décrivent les
[points d'extension](extension-points.md).

`Crafter` et `RecipeBook` ne gardent aucun état entre deux appels : une seule instance sert toutes les requêtes, en
mode *worker* comme en requête ordinaire. L'inventaire et les observateurs qu'on leur donne tiennent aussi cette
promesse, à côté de leur stockage.
