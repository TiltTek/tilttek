# Reference

| Class | Role |
|---|---|
| `TilttekLuckTokensBundle` | The bundle, its configuration `tilttek_luck_tokens`, and the erasure module when `phil/privacy-bundle` is installed |
| `Application\LockedLuckTokens` | The operations of `LuckTokens` under a Symfony lock |
| `Application\LockScope` | `Holder` or `Table`: what one lock covers |
| `Infrastructure\DependencyInjection\LuckTokensServices` | The services, registered from the configuration and checked |
| `Infrastructure\Doctrine\DbalTokenLedger` | The ledgers in `tilttek_luck_tokens_entry` |
| `Infrastructure\Doctrine\DbalTokenRules` | The rules of the tables in `tilttek_luck_tokens_rules`; `save()` and `remove()` |
| `Infrastructure\Doctrine\LuckTokensTables` | The two tables, for a platform; `configure()` adds them to a schema |
| `Infrastructure\Doctrine\LuckTokensSchemaListener` | Adds the tables to the schema the ORM generates for their connection |
| `Infrastructure\Doctrine\Rows` | Internal: the values of a row as the drivers give them |
| `Infrastructure\Rules\ConfiguredTokenRules` | The rules of the tables of the configuration |
| `Infrastructure\Security\SecurityHolderAccess` | The access of actors to holders, asked of the security voters |
| `Infrastructure\Security\HolderControlVoter` | Grants each holder their own tokens, abstains otherwise; `CONTROL` is `TILTTEK_LUCK_TOKENS_CONTROL` |
| `Infrastructure\Security\HolderControl` | The subject of the voters: the actor and the holder |
| `Infrastructure\Privacy\LuckTokensPrivacy` | The erasure of a member for `phil/privacy-bundle`, module `tilttek_luck_tokens` |

## Exceptions

The bundle raises the library's exceptions — `LedgerConflict` when other writers kept a ledger busy three times in a
row, `InvalidTokenRule` for rules or a row it cannot read —, and `InvalidConfigurationException` for a configuration
that cannot work, when the container is compiled. Symfony's `LockConflictedException` and `LockAcquiringException`
reach the caller of `LockedLuckTokens` when the store of the locks fails.
