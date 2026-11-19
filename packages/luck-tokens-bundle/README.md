# tilttek/luck-tokens-bundle

**Luck, inspiration and fate tokens for a Symfony application's tables, every move kept in the database.**

tilttek/luck-tokens-bundle integrates `tilttek/luck-tokens` with Symfony. It keeps the ledgers of the tables, and their
rules when the application edits them, in tables on a Doctrine DBAL connection, added to the schema the ORM generates
so that the application's migrations create them. The rules may also come from the configuration or from a service of
the application. A balance is read from an index, never by adding up the ledger; two writers at once on a table are
told apart by the primary key, and the second one reads the ledger again. An optional Symfony lock runs the operations
of a holder, or of a whole table, one after the other. The events reach the application's event dispatcher; who spends
the tokens of whom can be decided by the security voters; a member who leaves is erased under a pseudonym, through
`phil/privacy-bundle` or a listener of the application.

It is for a Symfony application whose tables hand out tokens — inspiration, bennies, fate points — and wants each one
granted, spent and passed once, with an audit the game master and the players can read.

It requires Symfony's DependencyInjection and Config, Doctrine DBAL, the PSR event dispatcher, `tilttek/luck-tokens`
and `phil/resource`. The ORM, the Lock and Security components and `phil/privacy-bundle` are optional: the schema
listener, the locks, the access through the voters and the erasure module are registered only when they are installed
and, for the locks and the voters, asked for.

The design follows the other bundles of the monorepo: an `AbstractBundle` with its configuration, services declared by
`LuckTokensServices`, DBAL stores and the library's memory stores for tests, tables added by a schema listener.

## Status

| Part | State |
|---|---|
| Configuration `tilttek_luck_tokens`: store, connection, rules, tables, clock, size of a ledger, access, locks, member kind | Implemented and tested |
| `DbalTokenLedger` and `DbalTokenRules`, with their two tables | Implemented and tested |
| The DBAL stores on SQLite, MySQL 8.4 and PostgreSQL 17, four processes at once included | Implemented and tested |
| The tables in the schema the ORM generates, for the migrations | Implemented and tested |
| `LockedLuckTokens`: the operations under a Symfony lock, by holder or by table | Implemented and tested |
| The events through the application's dispatcher | Implemented and tested |
| `SecurityHolderAccess` and `HolderControlVoter`: the access asked of the security voters | Implemented and tested |
| Erasure of a member under a pseudonym for `phil/privacy-bundle` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, enabling the bundle, the migration. |
| [Configuration](doc/configuration.md) | Every key of `tilttek_luck_tokens`, with its default. |
| [The ledgers in the database](doc/database.md) | The tables, the indexes, writers at once, the rules kept there. |
| [Locks](doc/locks.md) | `LockedLuckTokens`, the scopes, the store of the locks. |
| [Who controls whom](doc/holder-access.md) | Each holder their own, the security voters, a service of the application. |
| [Events](doc/events.md) | Listening to the tokens' events. |
| [Erasing a member](doc/erasing-a-member.md) | The pseudonym, the privacy bundle, a listener of the application. |
| [Extension points](doc/extension-points.md) | The services, aliases and tags the application uses. |
| [Testing](doc/testing.md) | The bundle's tests, on SQLite, MySQL and PostgreSQL, its benchmark, and testing an application. |
| [Reference](doc/reference.md) | Every class of the bundle, with its role. |

## Quick start

```sh
composer require tilttek/luck-tokens-bundle
```

```yaml
# config/packages/tilttek_luck_tokens.yaml
tilttek_luck_tokens:
    tables:
        'table:12':
            game_masters: ['member:7']
            kinds:
                - { key: inspiration, name: Inspiration, cap: 1, transferable: true }
                - { key: fate, name: Fate points, cap: 10, refresh: each-session, refresh_to: 3 }
```

```php
use Phil\Resource\ResourceReference;
use Tilttek\LuckTokens\LuckTokens;

final readonly class Reroll
{
    public function __construct(private LuckTokens $tokens) {}

    public function __invoke(int $memberId, string $operation): void
    {
        $member = ResourceReference::of('member', (string) $memberId);
        $this->tokens->spend(ResourceReference::of('table', '12'), 'inspiration', $member, 1, $member, $operation, 'Reroll');
    }
}
```

Then `bin/console doctrine:migrations:diff` writes the migration of the ledger's table.

## Requirements

PHP 8.5 or later, Symfony 8.2, Doctrine DBAL 4.5.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
