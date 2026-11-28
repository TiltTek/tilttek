<!-- phil-dev source-sha256=07d68746f9aa6b5ad489735d1dcf4fc4c17c9da04a64f37dde142d78a9fb5427 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- `phil/resource`, dont les références désignent les membres.
- Une horloge `psr/clock`, comme `symfony/clock`.

## Installer

```sh
composer require tilttek/celebrations
```

## Câbler la bibliothèque

`Celebrations` reçoit le dépôt et l'horloge de l'application. Tout le reste a un défaut :

```php
use Tilttek\Celebrations\Celebrations;
use Tilttek\Celebrations\Limits;

$limits = new Limits(pendingPerMember: 200, entriesPerMember: 500, shownPerGroup: 10);

$celebrations = new Celebrations(
    $store,                                // Contract\CelebrationStore
    $clock,                                // Psr\Clock\ClockInterface
    $limits,
    [$observer],                           // Contract\CelebrationObserver, told of each change
);
```

`Store\InMemoryCelebrationStore` sert les tests et les applications qui reconstruisent leurs boîtes à chaque démarrage ;
une application qui les garde implémente `Contract\CelebrationStore` sur son stockage, comme le décrivent les
[points d'extension](extension-points.md).

`Celebrations` ne garde aucun état entre deux appels : une instance sert toutes les requêtes, en mode *worker* comme en
mode requête. Le dépôt et les observateurs qu'on lui donne tiennent aussi cette promesse, à côté de leur stockage.

Les réussites viennent des écouteurs de l'application, un par paquet de récompenses qu'elle utilise :
[célébrer](celebrating.md) en montre un.
