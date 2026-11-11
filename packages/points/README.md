# tilttek/points

**Points your members earn, spend and give, kept in a ledger where every point comes from somewhere and goes somewhere.**

tilttek/points keeps the members' points in a double-entry ledger: each movement debits one account and credits
another — a member's account, the account a rule issues from, the account of a reward, the account of expired points
—, so a balance is always the sum of the movements, never a field that someone writes. Earning rules turn the
members' activity, recorded by `tilttek/activity`, into points — *ten points per game, fifty at most a week* —, in
one or several currencies the application names; a cancelled activity reverses what it earned. Points can expire,
first in first out; a spending or a transfer never makes a balance negative, while a cancellation that does is written
as it is. When a rule changes, the points derived from the activity are recomputed from the journal over the period
the application chooses, and the movements decided by people — spendings, transfers, grants — stay as they are.
Erasing a member deletes their movements and keeps the other members' points. It is not money: points are never
converted into a currency, and nothing here handles a payment.

It is for an application that rewards what its members do — a community site, a store's loyalty programme, a game
site — and wants balances it can explain line by line. `tilttek/points-bundle` integrates it with Symfony and keeps
the ledger in Doctrine DBAL; an application without Symfony takes the library alone and writes its own `Ledger`, or
uses the one in memory.

It is a plain PHP library whose dependencies are `tilttek/activity`, the journal the rules read, `phil/resource`, the
opaque reference of a member, and `psr/clock`, the clock that stamps each movement. Members are references the
application gives, `member:44`: the library never reads a name, an e-mail or an account.

The design is small: an `Account` and a `Movement` between two of them, kept as `LedgerEntry` lines by a `Ledger`;
`Points` reads balances and histories and writes what the application decides; `PointsProjection` reads the journal
of activity through `EarningRule`s; `Lots` follows what is left of each credit for the expiry.

## Status

| Part | State |
|---|---|
| `Account`, `Currency`, `Currencies`, `MovementKey`, `Movement`, `LedgerEntry`: the ledger's values, checked | Implemented and tested |
| `Ledger` and `InMemoryLedger`: written once by key, covered payments, balances, net between two accounts, statements, rebuilds, erasure | Implemented and tested |
| `Points`: balances, histories, grants, revocations, spendings, refunds, transfers, expiry, erasure | Implemented and tested |
| `EarningRule`, `Cap`, `Period`, `Rules`: points per gesture, caps per member and period in a timezone, context conditions | Implemented and tested |
| `PointsProjection`: the activity turned into points, cancellations reversed, rebuilds | Implemented and tested |
| `Lots` and `Lot`: expiry first in first out, debts paid by the next credits | Implemented and tested |
| Exceptions under one `PointsException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [The ledger](doc/ledger.md) | Accounts, movements, keys, balances and histories. |
| [Earning points](doc/earning.md) | Earning rules, caps, conditions, the projection, cancellations and rebuilds. |
| [Spending and giving](doc/spending.md) | Grants, revocations, spendings, refunds, transfers and the balance that never goes negative. |
| [Expiry](doc/expiry.md) | Lifetimes, first in first out, debts, and the task that expires every member. |
| [Erasure](doc/erasure.md) | What erasing a member removes and what it keeps. |
| [Writing a ledger](doc/writing-a-ledger.md) | The `Ledger` contract an application's storage implements. |
| [Extension points](doc/extension-points.md) | What an application gives the library: the ledger and the clock. |
| [Exceptions](doc/exceptions.md) | `PointsException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that moves points. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require tilttek/points
```

```php
use Phil\Resource\ResourceReference;
use Symfony\Component\Clock\NativeClock;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\Points;

$points = new Points(new InMemoryLedger(), new NativeClock(), new Currencies(new Currency('points')));
$member = ResourceReference::of('member', '44');

$points->grant($member, 50, 'welcome:44');
$points->spend($member, 30, 'order:2026-0193:redeem', 'order:2026-0193');
$points->balance($member); // 20
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
