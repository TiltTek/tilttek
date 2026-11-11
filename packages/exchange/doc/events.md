# Events

`Redemptions`, `Transfers` and `Trades` tell their `Announcer` what happened, once, when it happens; an exchange asked
again tells nothing. `SilentAnnouncer` tells no one; the bundle dispatches the events to the application's event
dispatcher. Every event implements `Event\ExchangeEvent`.

| Event | When | Carries |
|---|---|---|
| `RewardReserved` | The points of a reservation are spent: deliver now | `reservation` |
| `ReservationConfirmed` | The application confirmed the delivery | `reservation` |
| `ReservationCancelled` | The application cancelled; the points are back | `reservation` |
| `ReservationExpired` | The reservation outlived its lifetime; the points are back | `reservation` |
| `PointsTransferred` | A member gave points to another | `transfer` |
| `OfferMade` | A member offered a trade | `offer` |
| `OfferAccepted` | Both sides changed hands | `offer` |
| `OfferDeclined` | The recipient declined | `offer` |
| `OfferWithdrawn` | The proposer withdrew | `offer` |
| `OfferCountered` | The recipient answered with their own offer | `offer`, closed, and `counter`, open |
| `OfferExpired` | The offer outlived its lifetime | `offer` |

Delivering a reward is the application's affair: a listener of `RewardReserved` sends the code, applies the discount
or books the seat, then confirms the reservation, or cancels it when it cannot.
