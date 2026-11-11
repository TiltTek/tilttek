# Installation

## Requirements

- PHP 8.5 or later, with the `mbstring` and `sodium` extensions.
- `tilttek/activity`, `phil/resource` and `psr/clock`, installed with the library.

## Installing

```sh
composer require tilttek/collections
```

The library has no configuration of its own. Build the catalog, the stores and the services in your container, or
install `tilttek/collections-bundle` on Symfony, which does it from its configuration.

```php
$catalog = FixedCatalog::fromArray($definition);
$collections = new Collections($catalog, $copies, $completions, $clock, $observer);
$packs = new Packs($collections, $openings, new SecureSeeds(), $clock);
$calendar = new DailyCalendar($catalog, $picks, new DateTimeZone('America/Toronto'), 'a salt of the site', [new FixedDate('10-31', 'pumpkin')], ['crew']);
$daily = new Daily($calendar, $collections, $clock);
$projection = new DailyProjection($daily, ['page.viewed']); // hand it to the Projector of tilttek/activity
```
