# Installation

## Requirements

- PHP 8.5 or later.
- `tilttek/points`, the ledger the points are written in.
- `tilttek/exchange`, whose `MemberSince` port tells when a member joined.
- `phil/resource`, whose references name the members.
- A `psr/clock` clock, such as `symfony/clock`.

## Installing

```sh
composer require tilttek/referrals
```

## Wiring the library

`Referrals` takes the store, the points, the application's first actions and the clock; everything else has a
default:

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

The same `MemberSince` serves `tilttek/exchange` and `tilttek/referrals`: an application implements it once. Without
it, a guard on dates refuses every member, since no joining date is known.

`Referrals` keeps no state between two calls: one instance serves every request, in a worker as in a request. The
store and the ports it is given keep that promise too.

## The store

`Store\InMemoryReferralStore` keeps nothing past the process: it serves tests. An application keeps its referrals in
its database, through its own `Contract\ReferralStore`, whose contract [Extension points](extension-points.md)
describes: the unique constraints, and the settlement in one step.
