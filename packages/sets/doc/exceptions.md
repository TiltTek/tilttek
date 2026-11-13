# Exceptions

Every exception of the library implements `Tilttek\Sets\Exception\SetsException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidSet` | A code, a member, a version, a tier, a reward, holdings or a holder is refused, or a definition does not read | `The tier "gold" requires 30 members; the set "dice" has 20.` |
| `UnknownSet` | A set, or a version of it, was never defined | `The set "dice" has no version 4.` |
| `DefinitionConflict` | A version defined again with other content, or a version older than the latest | `The set "dice" already has a version 1 with other content: a changed set takes a new version.` |

`InvalidSet` extends `InvalidArgumentException`; the others extend `RuntimeException`.
