# Installation

## Requirements

- PHP 8.5 or later.
- `phil/poll`, which counts every phase.
- `phil/resource`, whose references name the voters and the works.
- A `psr/clock` clock, such as `symfony/clock`.

## Installing

```sh
composer require tilttek/fan-awards
```

## Wiring the library

`FanAwards` takes the application's store and clock; everything else has a default:

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

`FanAwards` keeps no state between two calls: one instance serves every request, in a worker as in a request. The
store and the rules it is given keep that promise too, beside their storage.
