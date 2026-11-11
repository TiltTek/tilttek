<!-- phil-dev source-sha256=20987ab231eb56e69d36f3c07c202dc1ee0f513357adfc5b1bd4146e136c6cc0 -->
# Points d'extension

| Point | Ce que donne une application | Où |
|---|---|---|
| `Ledger\Ledger` | Le stockage du registre, à la place d'`InMemoryLedger` | [Écrire un registre](writing-a-ledger.md) |
| `Psr\Clock\ClockInterface` | L'horloge qui date chaque mouvement et décide de l'expiration : celle du système en production, une horloge fixe dans les tests | [Tests](testing.md) |

Les devises et les règles de gain sont des valeurs : `Currencies::fromArray()` et `Rules::fromArray()` les lisent dans
une configuration, ou l'application les construit dans le code. Chaque point est passé au constructeur du service qui
l'utilise ; les services ne gardent aucun état propre entre deux appels.
