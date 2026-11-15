<!-- phil-dev source-sha256=d45b68587fd86efe3f6f753b101e90ab1c4c5ad13a3cd2d00719681d92014481 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `sodium`.
- `phil/resource` 0.1, dont `ResourceReference` nomme chaque membre par la référence stable que lui donne le site, ainsi
  que le sujet de chaque prime.
- `tilttek/points` 0.1 ou 0.2, pour le séquestre sur son registre et pour `InsufficientPoints`.
- `psr/clock` et `symfony/clock`, pour l'heure de chaque mouvement ; `psr/event-dispatcher`, pour les événements.

## Installer

```sh
composer require tilttek/bounties
```

## Brancher la bibliothèque

`Bounties` reçoit deux ports, et au besoin un répartiteur d'événements, les règles du site et une horloge :

```php
use Symfony\Component\Clock\NativeClock;
use Tilttek\Bounties\Bounties;
use Tilttek\Bounties\BountyRules;
use Tilttek\Bounties\Escrow\PointsBountyEscrow;

$bounties = new Bounties(
    $store,                           // Contract\BountyStore: where the bounties are kept
    new PointsBountyEscrow($points),  // Contract\BountyEscrow: where the points are held, here the ledger of tilttek/points
    $dispatcher,                      // Psr\EventDispatcher\EventDispatcherInterface, or null to dispatch nothing
    new BountyRules(),                // length, grace, smallest and largest bounty
    new NativeClock(),                // Psr\Clock\ClockInterface: the time of each move, kept in UTC
);
```

`BountyRules` reçoit, en secondes, la plus courte prime — un jour par défaut, une heure au moins —, la plus longue —
trente jours par défaut, quatre-vingt-dix au plus —, le délai de grâce après la fin pendant lequel le commanditaire
attribue encore — un jour par défaut, une semaine au plus —, puis le plus petit et le plus grand montant, 10 et 10 000
points par défaut.

`Bounties` ne conserve rien entre deux appels : une seule instance sert toutes les requêtes d'un *worker*. Lancez
`expireDue()` depuis une tâche planifiée, toutes les quelques minutes : elle règle les primes dont le délai de grâce est
passé, 100 à la fois par défaut et 500 au plus.
