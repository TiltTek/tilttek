<!-- phil-dev source-sha256=7d142728b857788dba8cf1bf6ec8d2965877621a17c3d7c1a2c8c2c3b6a184b6 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

| Service ou alias | Public | Ce que c'est |
|---|---|---|
| `Tilttek\LuckTokens\LuckTokens` | Oui | Octroyer, dépenser, passer, commencer une session, effacer un membre ; les soldes et le registre |
| `Tilttek\LuckTokensBundle\Application\LockedLuckTokens` | Oui, avec `lock.enabled` | Les mêmes opérations sous un verrou Symfony |
| `Tilttek\LuckTokens\Contract\TokenLedger` | Oui | `DbalTokenLedger`, ou `InMemoryTokenLedger` de la bibliothèque avec `store: memory` |
| `Tilttek\LuckTokens\Contract\TokenRules` | Oui | `ConfiguredTokenRules`, `DbalTokenRules`, ou le service de `rules_service` |
| `Tilttek\LuckTokens\Contract\HolderAccess` | Oui | `SelfAccess`, `SecurityHolderAccess`, ou le service de `holder_access_service` |
| `Tilttek\LuckTokensBundle\Infrastructure\Doctrine\DbalTokenRules` | Non, autocâblé par sa classe | Enregistrer et retirer les règles d'une table, avec `rules: doctrine` |
| `Tilttek\LuckTokensBundle\Infrastructure\Security\HolderControlVoter` | Non, étiqueté `security.voter` | Chaque détenteur contrôle ses propres jetons, avec `holder_access: security` |
| `Tilttek\LuckTokensBundle\Infrastructure\Privacy\LuckTokensPrivacy` | Non, étiqueté `phil_privacy.eraser` | L'effacement d'un membre, avec `phil/privacy-bundle` |
| `Tilttek\LuckTokensBundle\Infrastructure\Doctrine\LuckTokensSchemaListener` | Non, étiqueté `doctrine.event_listener` | Les tables dans le schéma généré, avec l'ORM |

L'application fournit, par identifiant de service dans la configuration : les `TokenRules` de ses tables, avec
`rules: service` ; le `HolderAccess` de ses personnages, avec `holder_access: service`, ou un voter de sécurité sur
`HolderControl`, avec `holder_access: security` ; l'horloge ; la fabrique de verrous. Elle peut remplacer un alias par un
stockage à elle, qui passe les mêmes comportements que `StoresTestCase` dans les tests du bundle.

Le bundle utilise le service `clock`, l'alias `Psr\EventDispatcher\EventDispatcherInterface` de FrameworkBundle quand il
y en a un — sans lui, rien n'est réparti —, `doctrine.dbal.<connection>_connection`, `lock.factory` et
`security.authorization_checker`.

Chaque service ne garde rien entre deux requêtes, sauf le registre en mémoire de `store: memory`, qui est destiné aux
tests et aux scripts.
