# tilttek/points-bundle

**The points of a Symfony application's members, earned from their activity and kept in the database line by line.**

tilttek/points-bundle integrates `tilttek/points` with Symfony. It keeps the double-entry ledger in a table on a
Doctrine DBAL connection, with the lock rows that make a payment check the balance one at a time, and adds both tables
to the schema the ORM generates so that the application's migrations create them. It builds the currencies and the
earning rules from the configuration, checked when the container is compiled, and registers the projection that turns
the members' activity into points, which `tilttek/activity-bundle` keeps up to date as the journal is written, catches
up and rebuilds. `Points` gives the application balances and histories, grants, spendings, refunds and transfers; two
commands expire the credits due and show a member's history; a module answers the export and erasure requests of
`phil/privacy-bundle` for a member's points.

It is for a Symfony application that rewards what its members do with points they spend on rewards or give to each
other, and wants every balance explained by its movements.

It requires Symfony's DependencyInjection and Config, Doctrine DBAL, `tilttek/points` and `phil/resource`.
`tilttek/activity-bundle` is what feeds the earning rules; without it, points move only by what the application
decides. The ORM, the Console and `phil/privacy-bundle` are optional: the schema listener, the commands and the privacy
module are registered only when they are installed.

The design follows the other bundles of the monorepo: an `AbstractBundle` with its configuration, services declared by
`PointsServices`, a DBAL ledger and a ledger in memory for tests, tables added by a schema listener.

## Status

| Part | State |
|---|---|
| Configuration `tilttek_points`: store, connection, currencies, default currency, timezone, rules, projection, batch size, member kind | Implemented and tested |
| `DbalLedger`, with the tables `tilttek_points_movement` and `tilttek_points_lock` | Implemented and tested |
| The tables in the schema the ORM generates, for the migrations | Implemented and tested |
| The projection tagged for `tilttek/activity-bundle` | Implemented and tested |
| Commands `tilttek:points:expire` and `tilttek:points:history` | Implemented and tested |
| Export and erasure of a member for `phil/privacy-bundle` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, enabling the bundle, the migration. |
| [Configuration](doc/configuration.md) | Every key of `tilttek_points`, with its default. |
| [The ledger in the database](doc/database.md) | The two tables, the idempotent insertion and the lock per platform, dates. |
| [Earning from the activity](doc/earning.md) | The projection with `tilttek/activity-bundle`, live or by command, and rebuilds. |
| [Moving points](doc/moving-points.md) | `Points` in a controller or a handler: balances, histories, spendings, refunds, transfers. |
| [Commands](doc/commands.md) | `tilttek:points:expire` and `tilttek:points:history`. |
| [Privacy](doc/privacy.md) | The export and the erasure of a member's points. |
| [Extension points](doc/extension-points.md) | The services, aliases and tags the application uses. |
| [Exceptions](doc/exceptions.md) | What the bundle raises. |
| [Testing](doc/testing.md) | The bundle's tests and how to test an application that moves points. |
| [Reference](doc/reference.md) | Every class of the bundle, with its role. |

## Quick start

```sh
composer require tilttek/activity-bundle tilttek/points-bundle
```

```yaml
# config/packages/tilttek_points.yaml
tilttek_points:
    currencies:
        points: ~
    timezone: America/Toronto
    rules:
        games: { verbs: ['game.played'], points: 10, cap: { amount: 50, period: week } }
        comments: { verbs: ['news.commented'], points: 2 }
```

```php
use Phil\Resource\ResourceReference;
use Tilttek\Points\Points;

final readonly class RedeemReward
{
    public function __construct(private Points $points) {}

    public function __invoke(int $memberId, Reward $reward): void
    {
        $this->points->spend(ResourceReference::of('member', (string) $memberId), $reward->price, 'reward:'.$reward->id.':'.$memberId, 'reward:'.$reward->id);
    }
}
```

Then `bin/console doctrine:migrations:diff` writes the migration of the tables.

## Requirements

PHP 8.5 or later, Symfony 8.2, Doctrine DBAL 4.5.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
