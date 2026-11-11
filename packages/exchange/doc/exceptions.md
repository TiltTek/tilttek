# Exceptions

Every exception of the library implements `Tilttek\Exchange\Exception\ExchangeException`. The exceptions of
`tilttek/points` — `InsufficientPoints` when a balance does not cover a payment, `UnknownCurrency` — reach the caller
as they are.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidExchange` | A key, a reward, a quantity, a reservation, a transfer, an offer, an allowance, a duration or a timezone is refused | `An exchange key is 1 to 200 bytes; this one is 0.` |
| `UnknownReward` | The catalogue holds no reward by the code | `The catalogue holds no reward "seat".` |
| `UnknownExchange` | No reservation or offer is written under the key | `No offer is written under the key "trade:9".` |
| `ExchangeConflict` | A key already written is given for another reservation, transfer or offer | `The key "gift:1" is already written for another transfer.` |
| `ExchangeClosed` | A reservation or an offer is no longer open for the operation | `The reservation "order:1" is expired; it cannot be confirmed.` |
| `ExchangeRefused` | The rules refuse the exchange; its `refusal` tells why | `"member:3" has not been a member long enough to exchange with others.` |

## Refusals

`ExchangeRefused::$refusal` is a `Refusal`, for the application to tell the member in its own words:

| Refusal | Meaning |
|---|---|
| `OutOfStock` | Every unit is reserved or delivered |
| `NotYetAvailable`, `NoLongerAvailable` | Outside the reward's period |
| `MemberLimitReached` | The member holds as many units as one member may |
| `RequirementUnmet` | A condition of the reward is not met |
| `SelfExchange` | A member exchanging with themselves |
| `NewMember` | An account younger than the delay |
| `AllowanceReached` | The allowance of the period is reached |
| `NotOwned` | An item does not belong to its giver |
| `NotTradable` | An item bound to its member |
| `AlreadyHeld` | An item held by another open offer |
| `NotParty` | A member acting on an offer that is not theirs to act on |
