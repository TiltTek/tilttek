<!-- phil-dev source-sha256=8b251418cccce87fae9f3d693b7f601971ece78124602361b4c2634766900616 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- `tilttek/points`, le grand livre où les points sont écrits.
- `tilttek/exchange`, dont le port `MemberSince` indique quand un membre est arrivé.
- `phil/resource`, dont les références désignent les membres.
- Une horloge `psr/clock`, comme `symfony/clock`.

## Installer

```sh
composer require tilttek/referrals
```

## Câbler la bibliothèque

`Referrals` reçoit le magasin, les points, les premières actions de l'application et l'horloge ; tout le reste a une
valeur par défaut :

```php
use Tilttek\Referrals\Referrals;
use Tilttek\Referrals\Terms;

$referrals = new Referrals(
    $store,                 // Contract\ReferralStore; Store\InMemoryReferralStore for tests
    $points,                // Tilttek\Points\Points
    $firstActions,          // Contract\FirstActions: the newcomer's first real action
    $clock,                 // Psr\Clock\ClockInterface
    new Terms(
        referrerPoints: 100,
        newcomerPoints: 50,
        maxRewards: 10,
        referrerSeniority: 'P7D', // the referrer has been a member for a week
        newcomerWindow: 'P2D',    // the newcomer joined in the last two days
    ),
    $memberSince,           // Tilttek\Exchange\MemberSince; read only when a guard on dates is set
    $samePerson,            // Contract\SamePerson; Contract\NoSamePersonSignal by default
);
```

Le même `MemberSince` sert `tilttek/exchange` et `tilttek/referrals` : une application l'implémente une seule fois.
Sans lui, un garde-fou sur les dates refuse tous les membres, puisqu'aucune date d'arrivée n'est connue.

`Referrals` ne garde aucun état entre deux appels : une seule instance sert toutes les requêtes, en mode *worker* comme
en mode requête. Le magasin et les ports qu'elle reçoit tiennent aussi cette promesse.

## Le magasin

`Store\InMemoryReferralStore` ne conserve rien au-delà du processus : il sert aux tests. Une application conserve ses
parrainages dans sa base de données, par son propre `Contract\ReferralStore`, dont le contrat est décrit dans
[Points d'extension](extension-points.md) : les contraintes d'unicité, et le règlement en une seule étape.
