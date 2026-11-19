# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Service or alias | Public | What it is |
|---|---|---|
| `Tilttek\LuckTokens\LuckTokens` | Yes | Grant, spend, pass, start a session, erase a member; the balances and the ledger |
| `Tilttek\LuckTokensBundle\Application\LockedLuckTokens` | Yes, with `lock.enabled` | The same operations under a Symfony lock |
| `Tilttek\LuckTokens\Contract\TokenLedger` | Yes | `DbalTokenLedger`, or the library's `InMemoryTokenLedger` with `store: memory` |
| `Tilttek\LuckTokens\Contract\TokenRules` | Yes | `ConfiguredTokenRules`, `DbalTokenRules`, or the service of `rules_service` |
| `Tilttek\LuckTokens\Contract\HolderAccess` | Yes | `SelfAccess`, `SecurityHolderAccess`, or the service of `holder_access_service` |
| `Tilttek\LuckTokensBundle\Infrastructure\Doctrine\DbalTokenRules` | No, autowired by its class | Save and remove the rules of a table, with `rules: doctrine` |
| `Tilttek\LuckTokensBundle\Infrastructure\Security\HolderControlVoter` | No, tagged `security.voter` | Each holder controls their own tokens, with `holder_access: security` |
| `Tilttek\LuckTokensBundle\Infrastructure\Privacy\LuckTokensPrivacy` | No, tagged `phil_privacy.eraser` | The erasure of a member, with `phil/privacy-bundle` |
| `Tilttek\LuckTokensBundle\Infrastructure\Doctrine\LuckTokensSchemaListener` | No, tagged `doctrine.event_listener` | The tables in the generated schema, with the ORM |

The application provides, by service id in the configuration: the `TokenRules` of its tables, with `rules: service`;
the `HolderAccess` of its characters, with `holder_access: service`, or a security voter on `HolderControl`, with
`holder_access: security`; the clock; the lock factory. It may replace an alias with a store of its own, which passes
the same behaviours as `StoresTestCase` in the bundle's tests.

The bundle uses the `clock` service, the `Psr\EventDispatcher\EventDispatcherInterface` alias of FrameworkBundle when
there is one — without it, nothing is dispatched —, `doctrine.dbal.<connection>_connection`, `lock.factory` and
`security.authorization_checker`.

Every service keeps nothing between two requests, except the memory ledger of `store: memory`, which is meant for tests
and scripts.
