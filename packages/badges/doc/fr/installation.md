<!-- phil-dev source-sha256=fcffebb15c6defd0b456885d6eefe889ee5afb83da998de8cff19aeb6f61b4f2 -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent, avec l'extension `mbstring`.
- `tilttek/activity`, le journal de l'activité des membres que lisent les critères.
- `phil/resource`, la référence opaque d'un membre (`member:44`).
- `psr/clock`, l'interface de l'horloge qui date une attribution manuelle ou un consentement. Toute horloge PSR-20
  convient, comme `symfony/clock`.

## Installer

```sh
composer require tilttek/badges
```

Rien n'est enregistré et rien n'est configuré : construisez le `Catalog`, l'`Evaluator`, `BadgeProjection` et `Badges`
sur quatre stockages là où vous les utilisez. Une application Symfony installe `tilttek/badges-bundle`. Celui-ci garde
les stockages dans Doctrine DBAL et construit le tout à partir de sa configuration.

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

Les stockages en mémoire oublient tout à la fin du processus : ils servent aux tests, aux scripts et aux exemples. Une
application qui conserve ses badges écrit les quatre stockages sur son propre stockage
([Écrire un stockage](writing-a-store.md)) ou installe le bundle.
