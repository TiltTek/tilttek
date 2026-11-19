<!-- phil-dev source-sha256=604f2f9c1f20b9df792b4e1e57d8f64f9b6d5eec24e40bdac13b1be4341bf6f4 -->
# Configuration

```yaml
# config/packages/tilttek_luck_tokens.yaml
tilttek_luck_tokens:
    store: doctrine               # or memory, for tests
    connection: default           # the DBAL connection of the ledgers and of the rules kept there
    rules: configuration          # or doctrine, or service
    rules_service: ~              # with rules: service
    tables:
        'table:12':
            game_masters: ['member:7']
            kinds:
                - { key: inspiration, name: Inspiration, cap: 1, transferable: true }
                - { key: bennies, name: Bennies, cap: 5, scope: player, refresh: each-session, refresh_to: 3,
                    carry_over: 0, grants: anyone, transferable: true }
    clock: clock                  # the service of a Psr\Clock\ClockInterface
    max_entries: 100000           # the most entries the ledger of a table takes
    holder_access: self           # or security, or service
    holder_access_service: ~      # with holder_access: service
    lock:
        enabled: false
        factory: lock.factory     # the service of a Symfony\Component\Lock\LockFactory
        scope: holder             # or table
        ttl: 30.0                 # seconds, should the process die holding a lock
    privacy:
        member_kind: member       # a data subject 44 is the member member:44
```

| Clé | Valeur par défaut | Ce qu'elle fait |
|---|---|---|
| `store` | `doctrine` | Où les registres sont gardés : `doctrine` — `DbalTokenLedger` — ou `memory` — `InMemoryTokenLedger` de la bibliothèque, pour les tests et les scripts. |
| `connection` | `default` | La connexion DBAL, `doctrine.dbal.<name>_connection`. |
| `rules` | `configuration` | `configuration` : les tables ci-dessous, lues une fois par `ConfiguredTokenRules`. `doctrine` : `DbalTokenRules`, que l'application remplit à partir des réglages de chaque table ; il exige le stockage `doctrine`. `service` : le service que nomme `rules_service`. |
| `rules_service` | null | L'identifiant d'un service qui implémente `Tilttek\LuckTokens\Contract\TokenRules` ; obligatoire avec `rules: service`. |
| `tables` | aucune | Les règles de chaque table, par sa référence : ses meneurs de jeu et ses sortes, comme les lit `TableRules::fromArray()`. Seulement avec `rules: configuration`. |
| `clock` | `clock` | L'horloge des entrées ; FrameworkBundle enregistre `clock`. |
| `max_entries` | 100 000 | La taille d'un registre, 1 ou plus. |
| `holder_access` | `self` | `self` : chaque détenteur dépense et passe ses propres jetons. `security` : les voters de sécurité décident, voir [Qui contrôle qui](holder-access.md). `service` : le service que nomme `holder_access_service`. Les meneurs de jeu d'une table le peuvent toujours. |
| `holder_access_service` | null | L'identifiant d'un service qui implémente `Tilttek\LuckTokens\Contract\HolderAccess` ; obligatoire avec `holder_access: service`. |
| `lock.enabled` | false | Enregistre `LockedLuckTokens`, voir [Verrous](locks.md). |
| `lock.factory` | `lock.factory` | La fabrique de verrous ; FrameworkBundle enregistre `lock.factory` dès que `framework.lock` est défini. |
| `lock.scope` | `holder` | `holder` ou `table`. |
| `lock.ttl` | 30.0 | La durée de vie maximale d'un verrou, 1 seconde ou plus. |
| `privacy.member_kind` | `member` | La sorte de la référence que représente un sujet de données de `phil/privacy-bundle`. |

Une sorte de jeton prend `key`, `name` et `cap`, et facultativement `scope` (`character` ou `player`), `refresh`
(`never` ou `each-session`), `refresh_to`, `carry_over`, `grants` (`game-masters` ou `anyone`) et `transferable` ; une
clé omise prend la valeur par défaut de la bibliothèque.

## Vérifié à la compilation du conteneur

Le conteneur refuse, plutôt que la première opération :

- des tables qui ne sont pas des règles que prend la bibliothèque — une sorte sans plafond, un montant de
  rafraîchissement sur une sorte jamais rafraîchie, une référence qui n'en est pas une —, avec `InvalidTokenRule` ;
- des `tables` données alors que les règles viennent d'ailleurs, des règles en base de données avec le stockage
  `memory`, `service` sans l'identifiant du service, `security` sans le composant Security et les verrous sans le
  composant Lock, avec `InvalidConfigurationException`.
