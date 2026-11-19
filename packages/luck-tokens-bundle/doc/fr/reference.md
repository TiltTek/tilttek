<!-- phil-dev source-sha256=d8a055af9824cd29edfc5f997554ed295b91bb9443a7258fc269470309a737d4 -->
# Référence

| Classe | Rôle |
|---|---|
| `TilttekLuckTokensBundle` | Le bundle, sa configuration `tilttek_luck_tokens`, et le module d'effacement quand `phil/privacy-bundle` est installé |
| `Application\LockedLuckTokens` | Les opérations de `LuckTokens` sous un verrou Symfony |
| `Application\LockScope` | `Holder` ou `Table` : ce que couvre un verrou |
| `Infrastructure\DependencyInjection\LuckTokensServices` | Les services, enregistrés à partir de la configuration et vérifiés |
| `Infrastructure\Doctrine\DbalTokenLedger` | Les registres dans `tilttek_luck_tokens_entry` |
| `Infrastructure\Doctrine\DbalTokenRules` | Les règles des tables dans `tilttek_luck_tokens_rules` ; `save()` et `remove()` |
| `Infrastructure\Doctrine\LuckTokensTables` | Les deux tables, pour une plateforme ; `configure()` les ajoute à un schéma |
| `Infrastructure\Doctrine\LuckTokensSchemaListener` | Ajoute les tables au schéma que l'ORM génère pour leur connexion |
| `Infrastructure\Doctrine\Rows` | Interne : les valeurs d'une ligne telles que les donnent les pilotes |
| `Infrastructure\Rules\ConfiguredTokenRules` | Les règles des tables de la configuration |
| `Infrastructure\Security\SecurityHolderAccess` | L'accès des acteurs aux détenteurs, demandé aux voters de sécurité |
| `Infrastructure\Security\HolderControlVoter` | Accorde à chaque détenteur ses propres jetons, s'abstient autrement ; `CONTROL` est `TILTTEK_LUCK_TOKENS_CONTROL` |
| `Infrastructure\Security\HolderControl` | Le sujet des voters : l'acteur et le détenteur |
| `Infrastructure\Privacy\LuckTokensPrivacy` | L'effacement d'un membre pour `phil/privacy-bundle`, module `tilttek_luck_tokens` |

## Exceptions

Le bundle lance les exceptions de la bibliothèque — `LedgerConflict` quand d'autres écrivains ont tenu un registre
occupé trois fois de suite, `InvalidTokenRule` pour des règles ou une ligne qu'il ne peut pas lire —, et
`InvalidConfigurationException` pour une configuration qui ne peut pas fonctionner, à la compilation du conteneur.
`LockConflictedException` et `LockAcquiringException` de Symfony atteignent l'appelant de `LockedLuckTokens` quand le
stockage des verrous échoue.
