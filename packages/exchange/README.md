# tilttek/exchange

**Rewards your members buy with points, points they give each other, and items they trade, safely and once.**

tilttek/exchange handles the three exchanges of a gamified site. A member exchanges points for a reward of the
catalogue — a discount, a digital item, an access, a seat at an event — in two steps: a reservation spends the points
on the ledger of `tilttek/points` and holds the unit, then the application confirms once it has delivered, or cancels
and the points come back; a reservation left unconfirmed expires. A reward has its price in a currency of points, its
stock, its period of availability, a limit per member and conditions, like a badge or a level, that the application
checks. Members may give each other points, and trade items of their collections — virtual cards, the dice of a set
— by offers, counter-offers and acceptance: the items offered are held from the offer on, and on acceptance both
sides change hands in one transaction, or nothing moves. Guards keep it fair: no exchange with oneself, a delay for a
new account, an allowance per period, and a journal of transfers the application examines. Every step is safe to run
twice, and points are never turned into money.

It is for an application that lets its members spend and share what they earn — a store's loyalty programme, a
community site, a game site — and wants exchanges that never lose a point or an item. `tilttek/exchange-bundle`
integrates it with Symfony and keeps everything in Doctrine DBAL; an application without Symfony takes the library
alone and writes its own stores, or uses the ones in memory.

It is a plain PHP library whose dependencies are `tilttek/points`, the ledger that rewards and transfers move points
on, `phil/resource`, the opaque references of members and items, and `psr/clock`. Items live in the application's
collections, reached through an `Inventory` it implements; the conditions of the rewards and the joining dates of the
members come from the application too: the library reads no account, no badge, no level.

The design is small: `Redemptions` reserves, confirms, cancels and expires the `Reservation`s of the `Reward`s of a
`Catalog`; `Transfers` gives points within `Guards` and writes the `Transfer`s to a `TransferLog`; `Trades` opens,
accepts, declines, withdraws, counters and expires `Offer`s kept by an `OfferStore`; an `Announcer` is told each
event; `ExchangeErasure` erases a member.

## Status

| Part | State |
|---|---|
| `Reward`, `Catalog`, `InMemoryCatalog`, `Requirements`: prices, stock, availability, limits and conditions | Implemented and tested |
| `Redemptions` and `ReservationStore`: reservations in two steps, stock and limits checked as they are written, expiry | Implemented and tested |
| `Transfers` and `TransferLog`: points given between members, the journal | Implemented and tested |
| `Trades`, `OfferStore` and `Inventory`: offers holding their items, acceptance in one transaction, counter-offers, expiry | Implemented and tested |
| `Guards` and `Allowance`: no self-exchange, delay for new accounts, allowance per period | Implemented and tested |
| Events through an `Announcer` | Implemented and tested |
| `ExchangeErasure`: the erasure of a member | Implemented and tested |
| Exceptions under one `ExchangeException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Rewards](doc/rewards.md) | The catalogue, reservations, confirmations, cancellations, expiry, stock, limits and conditions. |
| [Transfers](doc/transfers.md) | Points given between members and the journal. |
| [Trades](doc/trades.md) | Offers, counter-offers, acceptance, the held items and the inventory. |
| [Guards](doc/guards.md) | Self-exchange, new accounts, allowances. |
| [Events](doc/events.md) | What the announcer is told, and when. |
| [Erasure](doc/erasure.md) | What erasing a member removes and what it keeps. |
| [Writing a store](doc/writing-a-store.md) | The contracts of the stores, the transaction and the inventory. |
| [Extension points](doc/extension-points.md) | What an application gives the library: stores, the transaction, the inventory, the catalogue, conditions, the announcer, the clock. |
| [Exceptions](doc/exceptions.md) | `ExchangeException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that exchanges. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require tilttek/exchange
```

```php
use Phil\Resource\ResourceReference;
use Symfony\Component\Clock\NativeClock;
use Tilttek\Exchange\Reservation\InMemoryReservationStore;
use Tilttek\Exchange\Reservation\Redemptions;
use Tilttek\Exchange\Reward\InMemoryCatalog;
use Tilttek\Exchange\Reward\Reward;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\Points;

$clock = new NativeClock();
$currencies = new Currencies(new Currency('points'));
$points = new Points(new InMemoryLedger(), $clock, $currencies);
$redemptions = new Redemptions(new InMemoryCatalog(new Reward('discount-10', 100, stock: 50)), new InMemoryReservationStore(), $points, $currencies, $clock);

$member = ResourceReference::of('member', '44');
$points->grant($member, 150, 'welcome:44');

$redemptions->reserve($member, 'discount-10', 'order:2026-0193:reward'); // 100 points spent, one unit held
$redemptions->confirm('order:2026-0193:reward');                         // delivered: the points stay spent
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
