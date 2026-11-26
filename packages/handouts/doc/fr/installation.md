<!-- phil-dev source-sha256=f41fecc65cb5929018235bf1137b8ed897e4d918d850f3b3108b3b183e7b1488 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `ext-mbstring`.
- `phil/resource`, dont les références nomment les documents, les tables, les joueurs et les fichiers.
- Une horloge `psr/clock`, comme `symfony/clock`.

## Installer

```sh
composer require tilttek/handouts
```

## Câbler la bibliothèque

`Handouts` reçoit le dépôt et l'horloge de l'application. Tout le reste a une valeur par défaut :

```php
use Tilttek\Handouts\Handouts;
use Tilttek\Handouts\Limits;
use Tilttek\Handouts\MediaTypes;

$limits = new Limits(handoutsPerTable: 200, attachmentsPerHandout: 10, audiencePerHandout: 50, releaseBatch: 100);

$handouts = new Handouts(
    $store,                                // Contract\HandoutStore
    $clock,                                // Psr\Clock\ClockInterface
    $limits,
    new MediaTypes(),                      // the media types attachments may have
    [$observer],                           // Contract\HandoutObserver, told of each change
);
```

`Store\InMemoryHandoutStore` sert les tests et les applications qui reconstruisent leurs documents à chaque démarrage ;
une application qui les garde implémente `Contract\HandoutStore` sur son stockage, comme l'expliquent les
[points d'extension](extension-points.md).

`Handouts` ne garde aucun état entre deux appels : une seule instance sert toutes les requêtes, dans un worker comme
dans une requête. Le dépôt et les observateurs qu'on lui donne tiennent eux aussi cette promesse, à côté de leur
stockage.

## Déclencher les révélations planifiées

Une révélation planifiée à un moment a lieu quand l'application appelle `releaseDue()` après ce moment : son
planificateur l'appelle chaque minute, ou au rythme dont ses tables ont besoin. Chaque appel révèle au plus
`Limits::$releaseBatch` révélations planifiées, le plus ancien moment d'abord ; le planificateur rappelle tant qu'un
appel en révèle autant.
