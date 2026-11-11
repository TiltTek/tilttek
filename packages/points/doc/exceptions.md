# Exceptions

Every exception of the library implements `Tilttek\Points\Exception\PointsException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidPoints` | An account, a currency, a lifetime, a key, an amount, a rule, a cap, a timezone, a read or a batch size is refused | `"Stars" is not a currency code: 1 to 32 lower-case letters, digits and "_", starting with a letter.` |
| `MovementConflict` | A key already written is written again for another movement | `The movement key "gift:1" is already written for another movement.` |
| `InsufficientPoints` | A spending or a transfer is larger than the payer's balance | `The account "member:member:44" holds 30 points; 31 cannot be paid from it.` |
| `UnknownCurrency` | A currency is asked for by a code none has | `No currency of points is named "crowns".` |
| `UnknownMovement` | A refund names no spending | `No spending is written under the key "order:2".` |

The verb patterns and the context conditions of a rule are checked by `tilttek/activity`, which raises its own
`InvalidActivity`.
