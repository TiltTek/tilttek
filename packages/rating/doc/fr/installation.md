<!-- phil-dev source-sha256=542e372ffde8d786a5e5e459f6dd76b78a07ed01201b78166442ca43b99c8588 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- `psr/event-dispatcher` : le répartiteur des événements, facultatif.

## Installer

```sh
composer require tilttek/rating
```

## Brancher la bibliothèque

```php
use Tilttek\Rating\Elo\{EloSystem, KFactor};
use Tilttek\Rating\OpenSkill\{OpenSkillModel, OpenSkillSystem};
use Tilttek\Rating\Ratings;

$chess = new Ratings(
    $store,              // Contract\RatingStore: the members' ratings
    new EloSystem(KFactor::chess()),
    $dispatcher,         // a PSR-14 dispatcher, told of each change; null to dispatch nothing
    $source,             // Contract\PlaySource: the results, for a replay; null without replays
    celebrateAfter: 5,   // the rated games before a new highest rating is celebrated
    provisionalGames: 10, // the rated games a rating stays provisional for, 0 to 1 000
);

$boardGames = new Ratings($store, new OpenSkillSystem(OpenSkillModel::PlackettLuce), $dispatcher, $source);
```

Un `Ratings` sert les jeux cotés par un seul système ; le dépôt de stockage garde quel système a calculé chaque cote, et
refuse de les mélanger. `Ratings`, les systèmes et leurs réglages ne gardent rien entre deux appels : une instance sert
donc chaque requête d'un *worker* ; `InMemoryRatingStore` garde tout dans le processus, pour les tests et les scripts.
