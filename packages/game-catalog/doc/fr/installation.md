<!-- phil-dev source-sha256=f44a6b2c907745f1ff2bc5e698577863ace361e8e86531c4e91fcc9ed58e50ae -->
# Installation

```sh
composer require tilttek/game-catalog
```

Le paquet exige PHP 8.5 ou une version plus récente, avec `mbstring`, et `psr/clock` : les services lisent l'heure
d'une horloge PSR-20, comme celle de `symfony/clock`.

La bibliothèque ne garde ni état ni stockage : l'application donne aux services un `Contract\CatalogStore` à elle, qui
garde dans sa base de données les entrées, les liens, les corrections, les bulletins et l'historique, comme l'expliquent
[les points d'extension](extension-points.md). `Store\InMemoryCatalogStore` sert aux tests.

```php
use Tilttek\GameCatalog\Catalog;
use Tilttek\GameCatalog\Corrections;
use Tilttek\GameCatalog\Polls;

$catalog = new Catalog($store, $clock, $observer, $resolver);   // observer and resolver are optional
$corrections = new Corrections($store, $clock, $observer);
$polls = new Polls($store);
```
