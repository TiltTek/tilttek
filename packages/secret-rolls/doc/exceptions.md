# Exceptions

Every exception of the library implements `Tilttek\SecretRolls\Exception\SecretRollsException`. `roll()` also lets the
exceptions of `tilttek/dice` through: `InvalidExpression`, `LimitExceeded` and `CannotRoll`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidSecretRoll` | A scene, a roller or a context is refused, or an opening, a hidden roll, a journal entry or a journal does not read | `A scene is 1 to 128 letters, digits, dots, colons, underscores or hyphens.` |
| `SecretRollRefused` | A roll in a closed or full scene, the reveal of a roll the scene never committed, revealed already or no longer sealed, or a second closing | `The roll 3f2a… was revealed already; a roll is revealed once.` |
| `JournalConflict` | An entry does not come at the position after the last one of its scene: two writers appended at once | `The journal of the scene cave expects position 4; an entry came at position 3.` |

`InvalidSecretRoll` extends `InvalidArgumentException`, `SecretRollRefused` extends `DomainException` and
`JournalConflict` extends `RuntimeException`.
