<!-- phil-dev source-sha256=0ee537ff85edad321fd8f944c60dead0c3e08c910b120017dbc2ba7cd455c848 -->
# Exceptions

Toute exception de la bibliothèque implémente `Tilttek\Sets\Exception\SetsException`.

| Exception | Lancée quand | Message, par exemple |
|---|---|---|
| `InvalidSet` | Un code, un membre, une version, un palier, une récompense, des possessions, un détenteur ou la limite d'une remise en attente est refusé, ou une définition ne se lit pas | `The tier "gold" requires 30 members; the set "dice" has 20.` |
| `UnknownSet` | Un ensemble, ou une version de celui-ci, n'a jamais été défini | `The set "dice" has no version 4.` |
| `DefinitionConflict` | Une version est définie de nouveau avec un autre contenu, ou une version est plus ancienne que la dernière | `The set "dice" already has a version 1 with other content: a changed set takes a new version.` |
| `DeliveryFailed` | Un gestionnaire a levé une exception pour un détenteur ou plus pendant `Sets::deliverPending()` ; elle porte `$delivered`, `$failedHolders` et `$holders`, et la première exception levée comme exception précédente | `A handler threw for 1 of the 3 holders told; 5 completions were delivered all the same. The first exception is the previous one.` |
| `UnsupportedStore` | `Sets::deliverPending()` est appelée avec un stockage de complétions qui n'implémente pas `Contract\PendingDeliveries` | `The completion store App\CompletionStore does not implement Tilttek\Sets\Contract\PendingDeliveries: it cannot list the holders with undelivered completions.` |

`InvalidSet` étend `InvalidArgumentException`, `UnsupportedStore` étend `LogicException`, et les autres étendent
`RuntimeException`.
