# Exceptions

Every exception of the library implements `Tilttek\Sets\Exception\SetsException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidSet` | A code, a member, a version, a tier, a reward, holdings, a holder or the limit of a pending delivery is refused, or a definition does not read | `The tier "gold" requires 30 members; the set "dice" has 20.` |
| `UnknownSet` | A set, or a version of it, was never defined | `The set "dice" has no version 4.` |
| `DefinitionConflict` | A version defined again with other content, or a version older than the latest | `The set "dice" already has a version 1 with other content: a changed set takes a new version.` |
| `DeliveryFailed` | A handler threw for one holder or more during `Sets::deliverPending()`; it carries `$delivered`, `$failedHolders` and `$holders`, and the first exception thrown as its previous one | `A handler threw for 1 of the 3 holders told; 5 completions were delivered all the same. The first exception is the previous one.` |
| `UnsupportedStore` | `Sets::deliverPending()` called with a completion store that does not implement `Contract\PendingDeliveries` | `The completion store App\CompletionStore does not implement Tilttek\Sets\Contract\PendingDeliveries: it cannot list the holders with undelivered completions.` |

`InvalidSet` extends `InvalidArgumentException`, `UnsupportedStore` extends `LogicException`, and the others extend
`RuntimeException`.
