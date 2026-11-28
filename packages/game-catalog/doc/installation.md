# Installation

```sh
composer require tilttek/game-catalog
```

The package needs PHP 8.5 or later, with `mbstring`, and `psr/clock`: the services read the time from a PSR-20 clock,
such as the one of `symfony/clock`.

The library holds no state and no storage: an application gives the services a `Contract\CatalogStore` of its own,
which keeps the entries, the links, the corrections, the ballots and the history in its database, as
[the extension points](extension-points.md) explain. `Store\InMemoryCatalogStore` is for tests.

```php
use Tilttek\GameCatalog\Catalog;
use Tilttek\GameCatalog\Corrections;
use Tilttek\GameCatalog\Polls;

$catalog = new Catalog($store, $clock, $observer, $resolver);   // observer and resolver are optional
$corrections = new Corrections($store, $clock, $observer);
$polls = new Polls($store);
```
