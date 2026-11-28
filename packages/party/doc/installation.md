# Installation

## Requirements

- PHP 8.5 or later, with `ext-mbstring`.
- `phil/resource`, whose references name the parties, the campaigns and the members.
- `tilttek/rpg-systems`, whose terms give the roles of a system.
- A `psr/clock` clock, such as `symfony/clock`.

## Installing

```sh
composer require tilttek/party
```

## Wiring the library

`Parties` takes the application's store and clock. Everything else has a default:

```php
use Tilttek\Party\Limits;
use Tilttek\Party\Parties;

$limits = new Limits(membersPerParty: 12, partiesPerMember: 10, lootPerParty: 500, notesPerParty: 200);

$parties = new Parties(
    $store,                                // Contract\PartyStore
    $clock,                                // Psr\Clock\ClockInterface
    $limits,
    [$observer],                           // Contract\PartyObserver, told of each change
);
```

`Store\InMemoryPartyStore` serves tests and applications that rebuild their parties at each start; an application that
keeps them implements `Contract\PartyStore` on its storage, as [extension points](extension-points.md) describe.

`Parties` and `Composition\CompositionAdvisor` keep no state between two calls: one instance serves every request, in a
worker as in a request. The store and observers they are given keep that promise too, beside their storage.
