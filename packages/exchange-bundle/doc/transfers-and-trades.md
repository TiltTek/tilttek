# Transfers and trades

Both are off until the configuration enables them.

## Transfers

```yaml
tilttek_exchange:
    member_since: App\Members\JoiningDates
    transfers:
        enabled: true
        allowance: { limit: 500, period: week }
        new_member_delay: P7D
```

`Transfers` becomes a public service, the journal command appears, and the guards are built from the configuration:
no gift to oneself, accounts of at least the delay on both sides, and at most the allowance per period, counted in
`timezone`.

## Trades

```yaml
tilttek_exchange:
    trades:
        enabled: true
        inventory: App\Collections\CardInventory
        offer_lifetime: P3D
        allowance: { limit: 10, period: day }
```

`Trades` becomes a public service. The inventory is the application's service implementing
`Tilttek\Exchange\Trade\Inventory` over its collections: who owns an item, whether it may change hands, and the moves of
an accepted trade. Keep it on the exchanges' connection: an acceptance runs in a `DbalTransaction`, and the items, the
points and the offer then commit together.

## The joining dates

`member_since` names the application's service implementing `Tilttek\Exchange\MemberSince`, which tells when a member
joined. It is needed as soon as a `new_member_delay` is set; a member it does not know is refused.
