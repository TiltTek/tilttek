<!-- phil-dev source-sha256=2cbbf54e5f3fa3a074c0133714a2bd8e8426dbc7228bd6ffe7daaec2ce9771aa -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent, avec les extensions `mbstring` et `sodium`.
- `tilttek/activity`, `phil/resource` et `psr/clock`, installés avec la bibliothèque.

## Installer

```sh
composer require tilttek/collections
```

La bibliothèque n'a aucune configuration propre. Construisez le catalogue, les stockages et les services dans votre
conteneur, ou installez `tilttek/collections-bundle` sur Symfony, qui le fait à partir de sa configuration.

```php
$catalog = FixedCatalog::fromArray($definition);
$collections = new Collections($catalog, $copies, $completions, $clock, $observer);
$packs = new Packs($collections, $openings, new SecureSeeds(), $clock);
$calendar = new DailyCalendar($catalog, $picks, new DateTimeZone('America/Toronto'), 'a salt of the site', [new FixedDate('10-31', 'pumpkin')], ['crew']);
$daily = new Daily($calendar, $collections, $clock);
$projection = new DailyProjection($daily, ['page.viewed']); // hand it to the Projector of tilttek/activity
```
