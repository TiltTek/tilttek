# tilttek/exchange-bundle

**Rewards, gifts of points and trades of items for a Symfony application's members, kept in the database.**

tilttek/exchange-bundle integrates `tilttek/exchange` with Symfony, on the points of `tilttek/points-bundle`. It keeps
the reservations, the journal of transfers and the offers with the items they hold in tables on a Doctrine DBAL
connection, added to the schema the ORM generates so that the application's migrations create them, and the catalogue
of rewards in the configuration or in a table the application edits. It builds `Redemptions` from the configuration,
the `PointsHold` contract that holds, keeps and gives back the points of a reserved reward,
and `Transfers` and `Trades` when the application enables them, with their guards — an allowance per period, a delay
for new accounts —, checked when the container is compiled. The steps of a trade run in one transaction of the
connection, the ledger of points included when it is kept there. The events reach the application's event dispatcher,
where a listener delivers the rewards; three forms reserve a reward, give points and make an offer; a command expires
the reservations and the offers, another shows the journal of transfers; a module answers the export and erasure
requests of `phil/privacy-bundle` for a member's exchanges.

It is for a Symfony application whose members spend their points on rewards, give them to each other or trade the
items of their collections, and wants each exchange written once, all or nothing.

It requires Symfony's DependencyInjection and Config, Doctrine DBAL, the PSR event dispatcher, `tilttek/exchange`,
`tilttek/points` and `phil/resource`, and the `Points` and `Currencies` services that `tilttek/points-bundle` registers.
The ORM, the Console, the Form component and `phil/privacy-bundle` are optional: the schema listener, the commands, the
forms and the privacy module are registered only when they are installed.

The design follows the other bundles of the monorepo: an `AbstractBundle` with its configuration, services declared by
`ExchangeServices`, DBAL stores and stores in memory for tests, tables added by a schema listener.

## Status

| Part | State |
|---|---|
| Configuration `tilttek_exchange`: store, connection, catalogue, rewards, lifetimes, conditions, joining dates, timezone, transfers, trades, member kind | Implemented and tested |
| `DbalCatalog`, `DbalReservationStore`, `DbalTransferLog`, `DbalOfferStore` and `DbalTransaction`, with their seven tables | Implemented and tested |
| Moments stored in UTC by the type `datetime_utc_immutable` | Implemented and tested |
| The DBAL stores on SQLite, MySQL 8.4 and PostgreSQL 17 | Implemented and tested |
| The tables in the schema the ORM generates, for the migrations | Implemented and tested |
| `PointsHold`, the points of a reserved reward held, kept or given back, and `SpendingPointsHold` on `Points` | Implemented and tested |
| The events through the application's dispatcher | Implemented and tested |
| Forms `RedeemRewardType`, `TransferPointsType` and `OfferType` | Implemented and tested |
| Commands `tilttek:exchange:expire` and `tilttek:exchange:transfers` | Implemented and tested |
| Export and erasure of a member for `phil/privacy-bundle` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, enabling the bundle, the migration. |
| [Configuration](doc/configuration.md) | Every key of `tilttek_exchange`, with its default. |
| [Rewards](doc/rewards.md) | The catalogue, reserving from a controller, delivering from the event, the scheduled expiry, the points a reservation holds. |
| [Transfers and trades](doc/transfers-and-trades.md) | Enabling them, their guards, the inventory, the joining dates. |
| [The exchanges in the database](doc/database.md) | The tables, the locks and holds, the idempotent insertions per platform, the transaction. |
| [Events](doc/events.md) | Listening to the exchanges' events. |
| [Forms](doc/forms.md) | The three forms and their data. |
| [Commands](doc/commands.md) | `tilttek:exchange:expire` and `tilttek:exchange:transfers`. |
| [Privacy](doc/privacy.md) | The export and the erasure of a member's exchanges. |
| [Extension points](doc/extension-points.md) | The services, aliases and tags the application uses. |
| [Exceptions](doc/exceptions.md) | What the bundle raises. |
| [Testing](doc/testing.md) | The bundle's tests, on SQLite, MySQL and PostgreSQL, and how to test an application that exchanges. |
| [Reference](doc/reference.md) | Every class of the bundle, with its role. |

## Quick start

```sh
composer require tilttek/points-bundle tilttek/exchange-bundle
```

```yaml
# config/packages/tilttek_exchange.yaml
tilttek_exchange:
    rewards:
        discount-10: { price: 100 }
        seat: { price: 250, stock: 30, per_member: 1 }
    transfers:
        enabled: true
        allowance: { limit: 500, period: week }
```

```php
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\Reservation\Redemptions;

final readonly class BookSeat
{
    public function __construct(private Redemptions $redemptions) {}

    public function __invoke(int $memberId, string $orderId): void
    {
        $this->redemptions->reserve(ResourceReference::of('member', (string) $memberId), 'seat', 'order:'.$orderId.':seat');
    }
}
```

Then `bin/console doctrine:migrations:diff` writes the migration of the tables, and a scheduler runs
`bin/console tilttek:exchange:expire`.

## Requirements

PHP 8.5 or later, Symfony 8.2, Doctrine DBAL 4.5.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
