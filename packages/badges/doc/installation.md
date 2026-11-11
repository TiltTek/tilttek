# Installation

## Requirements

- PHP 8.5 or later, with the `mbstring` extension.
- `tilttek/activity`, the journal of the members' activity that the criteria read.
- `phil/resource`, the opaque reference of a member (`member:44`).
- `psr/clock`, the interface of the clock that stamps a manual award or a consent; any PSR-20 clock does, such as
  `symfony/clock`.

## Installing

```sh
composer require tilttek/badges
```

Nothing is registered and nothing is configured: build the `Catalog`, the `Evaluator`, `BadgeProjection` and `Badges`
over four stores where you use them. A Symfony application installs `tilttek/badges-bundle`, which keeps the stores in
Doctrine DBAL and builds everything from its configuration.

```php
use Tilttek\Badges\BadgeProjection;
use Tilttek\Badges\Badges;
use Tilttek\Badges\Catalog;
use Tilttek\Badges\Evaluator;
use Tilttek\Badges\Store\InMemoryAwardStore;
use Tilttek\Badges\Store\InMemoryGrantStore;
use Tilttek\Badges\Store\InMemoryProfileStore;
use Tilttek\Badges\Store\InMemoryProgressStore;

$catalog = Catalog::fromArray($badges, 'America/Toronto');
$progress = new InMemoryProgressStore();
$awards = new InMemoryAwardStore();
$evaluator = new Evaluator($catalog, $progress, $measures); // $measures only when a criterion reads one

$projection = new BadgeProjection($evaluator, $progress, $awards, $observer);
$badges = new Badges($evaluator, $progress, $awards, new InMemoryProfileStore(), new InMemoryGrantStore(), $clock);
```

The stores in memory forget everything with the process: they are for tests, scripts and examples. An application
that keeps its badges writes the four stores on its storage ([Writing a store](writing-a-store.md)) or installs the
bundle.
