<!-- phil-dev source-sha256=841b9b040c2e5779222c257f4d81632799655fbed9cd6a19639deb90cd62e5f9 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce qu'une application donne | Fourni |
|---|---|---|
| `Contract\TokenRules` | Les règles de chaque table : depuis ses réglages, une base de données | `Store\InMemoryTokenRules` |
| `Contract\TokenLedger` | Le stockage des registres | `Store\InMemoryTokenLedger` |
| `Contract\HolderAccess` | Qui contrôle les jetons de qui : un joueur ceux de son personnage | `Store\SelfAccess` |
| `Psr\EventDispatcher\EventDispatcherInterface` | Où vont les événements | aucun : rien n'est réparti |
| `Psr\Clock\ClockInterface` | L'heure des entrées | `Symfony\Component\Clock\NativeClock` |

Chaque point est passé au constructeur de `LuckTokens`, qui ne garde rien entre deux appels.

## Écrire un registre

Un `TokenLedger` garde les entrées de chaque table dans l'ordre de leurs positions, à partir de 1, et n'en supprime
jamais ; le seul changement qu'il fait à une entrée est l'effacement d'un membre. Ce que fait toute implémentation :

- `append($table, $after, $entries)` écrit les entrées d'une opération, toutes ou aucune, si et seulement si la dernière
  position de la table est encore `$after` ; sinon elle lance `LedgerConflict` et n'écrit rien. Dans une base de
  données, une clé unique sur la table et la position, dans une seule transaction, donne exactement cela : l'insertion
  du second écrivain échoue.
- Elle refuse, avec `InvalidTokenRule`, une entrée d'une autre table ou non numérotée à partir de `$after + 1`.
- `last()` donne la dernière position, 0 s'il n'y en a pas.
- `balance()` donne le solde de la dernière entrée du détenteur pour la sorte, 0 s'il n'y en a pas ; un index sur la
  table, la sorte, le détenteur et la position y répond sans lire le registre.
- `holders()` liste les détenteurs des entrées qui ont une sorte, dans l'ordre où ils apparaissent d'abord.
- `operation()` donne les entrées d'une opération ; `hasSession()` indique si le début d'une session s'y trouve.
- `entries()` liste toutes les entrées, pour la vérification.
- `eraseHolder($member, $pseudonym)` écrit le pseudonyme à la place du membre, comme détenteur et comme acteur, dans
  chaque entrée de chaque table, ainsi que dans les soldes et les détenteurs qu'il indexe, et efface la raison des
  entrées que le membre détient, toutes ou aucune ; les positions, les changements, les soldes et l'ordre restent. Il
  renvoie le nombre d'entrées réécrites, chacune comptée une fois, 0 si aucune ne nomme le membre, et refuse un
  pseudonyme égal au membre avec `InvalidTokenRule`. Dans une base de données, deux mises à jour dans une même
  transaction — la colonne du détenteur avec la raison, puis celle de l'acteur — donnent exactement cela, avec un index
  sur chacune. `LedgerEntry::pseudonymized()` donne l'entrée réécrite.

`LedgerEntry::toArray()` donne la ligne à conserver et `LedgerEntry::fromArray()` la relit, vérifiée. Les tests du
registre en mémoire, dans `tests/Unit/Store/StoresTest.php` et `tests/Unit/ErasureTest.php`, tiennent ces comportements.

## Les événements

Chaque événement est réparti une fois ses entrées dans le registre, jamais pour une opération envoyée de nouveau :

| Événement | Porte |
|---|---|
| `Event\TokensGranted` | L'entrée, et `amount()` |
| `Event\TokensSpent` | L'entrée, et `amount()`, toujours positif |
| `Event\TokensTransferred` | Les entrées sortante et entrante, et `amount()` |
| `Event\SessionStarted` | L'entrée du début, et les rafraîchissements |
| `Event\HolderErased` | Le pseudonyme et le nombre d'entrées réécrites ; jamais le membre ni une table |

Un écouteur qui offre une relance quand un jeton est dépensé lit la sorte et la raison de `TokensSpent::$entry`. Un
écouteur qui pousse le changement à la table par Messenger envoie la table, l'opération et la position — le *handler*
relit l'entrée dans le registre.
