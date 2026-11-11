<!-- phil-dev source-sha256=b07d6343d6f151b53b6a732eb78a030c0405f7a85bd306346e1376775f4d2a9e -->
# Installation

## Prérequis

PHP 8.5 ou plus récent, l'extension `mbstring`, et `tilttek/dice`, qui lance les dés et les rend vérifiables. La
bibliothèque n'a aucune autre dépendance.

## Installer

```sh
composer require tilttek/loot-tables
```

Une application Symfony prend `tilttek/loot-tables-bundle`, qui lit le catalogue dans la configuration, stocke les
tirages dans Doctrine et tire les options d'une commande de commerce une fois qu'elle est payée.

## Premier tirage

```php
use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\LootTables\Draw\LootDrawer;
use Tilttek\LootTables\Draw\PendingDraw;
use Tilttek\LootTables\LootBox;
use Tilttek\LootTables\LootEntry;
use Tilttek\LootTables\LootTable;

$rings = new LootTable('rings', 'Rings', [
    new LootEntry('copper', 'Copper ring', '/loot/copper.png', 'finger'),
    new LootEntry('silver', 'Silver ring', '/loot/silver.png', 'finger'),
    new LootEntry('gold', 'Gold ring', '/loot/gold.png', 'finger'),
    new LootEntry('none', 'Nothing at all'),
]);
$box = new LootBox('ring-box', 'Ring box', [$rings, $rings]);   // 1d4, 1d4

$pending = PendingDraw::open(new Randomizer(new Secure()));   // its commitment shown to the buyer
$revealed = new LootDrawer()->reveal($box, $pending, $buyerClientSeed);
$revealed->result->entries();                                   // two entries of the rings table
```
