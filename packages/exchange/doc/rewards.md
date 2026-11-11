# Rewards

## The catalogue

A `Reward` is what a member gets for points, named by a code of 1 to 100 lower-case letters, digits, `_`, `.` or `-`:

| Field | Meaning |
|---|---|
| `price` | Points per unit, from 1 to the most a movement of the ledger moves. |
| `currency` | The currency of the price; the default currency of `tilttek/points` when null. |
| `stock` | Every unit the reward ever has; unlimited when null. Units reserved and delivered use it up, units of a cancelled or expired reservation come back. |
| `availableFrom`, `availableUntil` | The period of availability, start included, end excluded; open on a side left null. |
| `perMember` | How many units one member may hold, reserved or delivered; unlimited when null. |
| `requires` | Conditions the member meets, every one of them — `badge:helper`, `level:5` —, checked by the application's `Requirements`. |

A `Catalog` gives a reward by its code and every reward sorted by code. `InMemoryCatalog` is built from rewards or from
a configuration, and a `WritableCatalog` also saves and removes rewards; removing a reward leaves its reservations as
they are.

```php
$catalog = InMemoryCatalog::fromArray([
    'discount-10' => ['price' => 100],
    'seat' => ['price' => 250, 'stock' => 30, 'per_member' => 1, 'available_until' => '2026-12-01', 'requires' => ['badge:helper']],
]);
```

The conditions are names the application chooses and answers from its badges, its levels or anything else, through
`Requirements::meets($member, $requirement)`. Without it, `NoRequirements` meets none: a reward with conditions is had
by no one until the application gives its own.

## Reserving, confirming, cancelling

`Redemptions::reserve($member, $code, $key, $quantity)` checks the period and the conditions, then writes the
reservation, whose units the store checks against the stock and the member's limit in the same step, and spends the
points on the ledger under `exchange:reservation:` and the key, to the redemption account `reward:` and the code. When
the points cannot be paid, the reservation is deleted, its units are free again, and `InsufficientPoints` reaches the
caller.

The application then delivers — from the `RewardReserved` event or right away — and calls `confirm($key)`: the points
stay spent. Or it calls `cancel($key)`: the points come back to the member by a refund of the ledger, and the units to
the stock. A confirmed reservation cannot be cancelled, nor a cancelled one confirmed.

```php
$reservation = $redemptions->reserve($member, 'seat', 'order:2026-0193:seat');

try {
    $tickets->issue($reservation);
    $redemptions->confirm('order:2026-0193:seat');
} catch (TicketingDown) {
    $redemptions->cancel('order:2026-0193:seat');
}
```

## The same call twice

The key makes each step safe to run twice. The same key reserves once and returns the reservation, even when its
reward has left the catalogue since; a reservation interrupted before its spending was written is paid when asked
again. The same key for another member, reward or quantity is an `ExchangeConflict`. A confirmed reservation confirmed
again, a cancelled one cancelled again, are returned as they are.

## Expiry

A reservation waits for its confirmation for its lifetime, 30 minutes unless `Redemptions` is given another ISO 8601
duration. `expireDue()` expires the reservations past their lifetime and gives their points back: the task a scheduler
runs. A confirmation or a cancellation that comes after the lifetime expires the reservation first, and is refused with
`ExchangeClosed`.

## Before reserving

`refusal($member, $code, $quantity)` tells why a member cannot reserve now — the period, a condition, the stock, the
limit —, or null, the balance aside; `remaining($code)` tells the units left, or null for an unlimited reward. A page
of rewards shows them; the reservation checks again as it writes.
