# Installation

## Requirements

- PHP 8.5 or later.
- `psr/event-dispatcher`: the dispatcher of the events, optional.

## Installing

```sh
composer require tilttek/rating
```

## Wiring the library

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

One `Ratings` serves the games rated with one system; the store keeps which system computed each rating, and refuses to
mix them. `Ratings`, the systems and their settings keep nothing between two calls, so one instance serves every
request of a worker; `InMemoryRatingStore` keeps everything in the process, for tests and scripts.
