<!-- phil-dev source-sha256=fd7db118870c2ab35e0629e664760085957cc48bdfffa98fba6db12ccdba81ab -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- `phil/resource`, la référence opaque d'un membre.
- `psr/clock`, l'horloge où se lisent les jours, et `psr/event-dispatcher`, qui informe l'application de chaque première
  ouverture. Une application Symfony passe ses services `clock` et `event_dispatcher`.

## Installer

```sh
composer require tilttek/advent-calendar
```

## Brancher la bibliothèque à la main

Un magasin, une horloge et un répartiteur font `AdventCalendar` :

```php
use Symfony\Component\Clock\Clock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tilttek\AdventCalendar\AdventCalendar;
use Tilttek\AdventCalendar\Event\BoxOpened;
use Tilttek\AdventCalendar\Store\InMemoryOpeningStore;

$dispatcher = new EventDispatcher();
$dispatcher->addListener(BoxOpened::class, $grantSurprise);

$advent = new AdventCalendar(new InMemoryOpeningStore(), new Clock(), $dispatcher);
```

Dans une application Symfony, enregistrez `AdventCalendar` comme service avec l'`OpeningStore` propre à l'application,
et l'écouteur de `BoxOpened` avec `#[AsEventListener]`. Les calendriers eux-mêmes sont des valeurs que l'application
construit à partir de sa configuration ou de sa base de données, à chaque requête : `Calendar::of()` est peu coûteux et
ne garde aucun état.

Le magasin en mémoire oublie tout avec le processus : une application écrit le sien sur son stockage
([Écrire un magasin](writing-a-store.md)).
