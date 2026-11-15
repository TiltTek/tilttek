<!-- phil-dev source-sha256=76782950e2aa216dbaff817606ec6dd34585e74e89ed16fb9c0be859eeb06cc9 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `mbstring` et `sodium`.
- `phil/resource` 0.1, dont `ResourceReference` nomme chaque membre par la référence stable que lui donne le site.
- `tilttek/points` 0.1 ou 0.2, pour le séquestre sur son registre et pour `InsufficientPoints`.
- `psr/clock` et `symfony/clock`, pour l'heure de chaque mouvement ; `psr/event-dispatcher`, pour les événements.

## Installer

```sh
composer require tilttek/friend-challenges
```

## Brancher la bibliothèque

`FriendChallenges` reçoit quatre ports, et au besoin un répartiteur d'événements, les règles du site et une horloge :

```php
use Symfony\Component\Clock\NativeClock;
use Tilttek\FriendChallenges\ChallengeRules;
use Tilttek\FriendChallenges\Escrow\PointsPrizeEscrow;
use Tilttek\FriendChallenges\FriendChallenges;

$challenges = new FriendChallenges(
    $store,                          // Contract\ChallengeStore: where the challenges are kept
    new PointsPrizeEscrow($points),  // Contract\PrizeEscrow: where the prizes are held, here the ledger of tilttek/points
    $friendship,                     // Contract\Friendship: who is a friend of whom
    $moderation,                     // Contract\Moderation: where reports go, and who moderates
    $dispatcher,                     // Psr\EventDispatcher\EventDispatcherInterface, or null to dispatch nothing
    new ChallengeRules(),            // how long a challenge lasts, how long its review, the largest prize
    new NativeClock(),               // Psr\Clock\ClockInterface: the time of each move, kept in UTC
);
```

`ChallengeRules` reçoit, en secondes, le plus court défi — une heure par défaut, une minute au moins —, le plus long —
trente jours par défaut, un an au plus —, l'examen qui suit la fin — trois jours par défaut, trente au plus —, et le
plus gros lot, 10 000 points par défaut.

`FriendChallenges` ne conserve rien entre deux appels : une seule instance sert toutes les requêtes d'un *worker*.
Lancez `expireDue()` depuis une tâche planifiée, toutes les quelques minutes : elle termine les défis dont l'examen est
fini et rend leur lot, 100 à la fois par défaut et 500 au plus.
