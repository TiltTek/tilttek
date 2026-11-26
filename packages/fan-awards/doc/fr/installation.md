<!-- phil-dev source-sha256=492147823a7913ff51bc89c98708b2470074303cb51e05efda5c92f15773e011 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- `phil/poll`, qui dépouille chaque phase.
- `phil/resource`, dont les références désignent les votants et les œuvres.
- Une horloge `psr/clock`, comme `symfony/clock`.

## Installer

```sh
composer require tilttek/fan-awards
```

## Câbler la bibliothèque

`FanAwards` reçoit le magasin et l'horloge de l'application ; tout le reste a une valeur par défaut :

```php
use Phil\Poll\CountingMethods;
use Tilttek\FanAwards\FanAwards;

$awards = new FanAwards(
    $store,                         // Contract\AwardStore
    $clock,                         // Psr\Clock\ClockInterface
    CountingMethods::defaults(),    // the counting methods of phil/poll, or the application's
    $nomineeEligibility,            // Contract\NomineeEligibility; Eligibility\AnyNominee by default
    $voterEligibility,              // Contract\VoterEligibility; Eligibility\AnyVoter by default
);
```

`FanAwards` ne garde aucun état d'un appel à l'autre : une seule instance sert toutes les requêtes, en mode *worker*
comme en requête classique. Le magasin et les règles qu'on lui donne tiennent aussi cette promesse, à côté de leur
stockage.
