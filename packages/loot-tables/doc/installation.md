# Installation

## Requirements

PHP 8.5 or later, the `mbstring` extension, and `tilttek/dice`, which rolls the dice and makes them verifiable. The
library has no other dependency.

## Installing

```sh
composer require tilttek/loot-tables
```

A Symfony application takes `tilttek/loot-tables-bundle`, which reads the catalogue from the configuration, stores the
draws in Doctrine and draws the options of a commerce order once it is paid.

## First draw

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
