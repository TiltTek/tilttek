<!-- phil-dev source-sha256=2e531fb0fa90fbe347c2780f3ee6d89cebbe7276a19ad5400d58b029ba9b6399 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- `phil/resource`, dont les références nomment les inventaires, leurs propriétaires et les porteurs.
- Une horloge `psr/clock`, comme `symfony/clock`.

## Installation

```sh
composer require tilttek/inventory
```

## Câbler la bibliothèque

`Inventories` reçoit le dépôt et l'horloge de l'application. Tout le reste a une valeur par défaut :

```php
use Tilttek\Inventory\Inventories;
use Tilttek\Inventory\Limits;
use Tilttek\Inventory\Rules\DefaultCapacity;
use Tilttek\Inventory\Rules\StaticRuleBook;

$limits = new Limits(inventoriesPerOwner: 20, itemsPerInventory: 1000, containerDepth: 4, logPerInventory: 1000);

$inventories = new Inventories(
    $store,                                // Contract\InventoryStore
    $clock,                                // Psr\Clock\ClockInterface
    new StaticRuleBook(['srd-5.2' => $rules]), // Contract\RuleBook: the carrying rules by system
    new DefaultCapacity(),                 // Contract\CarrierCapacity: each carrier's capacity
    $limits,
    [$observer],                           // Contract\InventoryObserver, told of each change
);
```

Sans livre de règles, aucun inventaire n'est pesé : les objets, les contenants et le journal fonctionnent de la même
façon, et `load()` donne null.

`Store\InMemoryInventoryStore` sert les tests et les applications qui reconstruisent leurs inventaires à chaque
démarrage ; une application qui les conserve implémente `Contract\InventoryStore` sur son stockage, comme le décrivent
les [points d'extension](extension-points.md).

`Inventories` ne garde aucun état entre deux appels : une seule instance sert toutes les requêtes, en mode *worker*
comme en requête classique. Le dépôt, le livre de règles, la capacité et les observateurs qu'il reçoit tiennent aussi
cette promesse, à côté de leur stockage.
