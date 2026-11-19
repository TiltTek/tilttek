# Drawing orders

## The drawers of the options

A catalogue of the commerce core declares, per product, its options; an option drawn after payment names its drawer.
The bundle draws two kinds:

| Drawer | On | Draws |
|---|---|---|
| `loot-box:<key>` | a draw option: `OptionDefinition::draw('loot', 'Loot', Drawers::box('starter'))` | the box of that key in the catalogue — the product declares its tables through it |
| `loot-text` | a text option drawn among its entries: `OptionDefinition::text(…, drawer: Drawers::TEXT)` | one of the option's entries, with a die of as many sides |

A box the catalogue does not hold is not supported, so the commerce core refuses the order's draws with its
`DrawRefused` rather than drawing nothing.

## Committed when the order is placed

When the order workflow of the commerce core takes its `place` transition, `CommitOnPlace` listens to
`workflow.phil_commerce_order.completed.place` and calls `LootCommitments::commit()`: the commitments exist before the
buyer is asked to pay, without anything for the application to call.

```php
$lifecycle->place($order, $clock->now()); // the draws of the order are committed here
```

- One draw per unit of each option left to draw here: a seed of 32 bytes drawn by `SecureSeeds` from
  `Random\Engine\Secure`, the operating system's cryptographic generator, kept secret, and its commitment, the SHA-256
  of the seed. Its identifier is derived from the order, the line, the option and the unit.
- A text keeps the entries of its option as they are at that moment, so that a list changed later does not change
  what was committed. A text option whose entries a die cannot draw from — fewer than two — is refused with
  `LootDrawRefused`, and the transition with it.
- Committing again commits nothing more: each unit keeps its first commitment, and the draws are returned again.
- With the Doctrine store, each commitment is flushed as it is saved, with whatever else the entity manager holds.

With `commit_on_place: false`, the listener is not registered, and the application calls
`LootCommitments::commit($order)` itself, once the order is placed and before the buyer pays:

```php
foreach ($commitments->commit($order) as $draw) {
    $draw->commitment();   // shown to the buyer, on the order page and in the confirmation
}
```

## The server's seed alone

A draw rolls from the seed of the server alone, and nothing is asked of the buyer's browser. The verifiable rolls of
`tilttek/dice` take a client seed; the bundle gives them the identifier of the draw, which is public and fixed before
the seed is drawn, so that anyone holding the proof rolls the dice again with the same inputs.

The server's seed, committed before the order is paid, is enough: the commitment is shown before the payment, so the
seed behind it, and with it every face the dice will give, is fixed before the shop is paid, and changing it afterwards
breaks the proof. Asking the buyer for a seed of their own would add a step on the buyer's page, a script that must
run in their browser, and a value to keep for each unit; the bundle does without that dependency on the browser.

What the proof shows is that the draw was fixed before the payment and not changed after. It does not show that the
shop could not have drawn several seeds before committing to one: the bundle draws exactly one per unit, from the
cryptographic generator, when the order is placed.

## Drawn once the order is paid

Once the order's payments are `Paid`, the application calls the commerce core's `OptionDraws::draw()`, which asks
`LootOptionDrawer` for each unit:

- the unit is revealed from its committed seed through the verifiable rolls of `tilttek/dice`, once; asked again, it
  gives the same draw, without rolling;
- the order line keeps, for a box, its `DrawText` — `3-6: Gold ring, Bare head` —, and for a text, the text itself;
- the draw keeps its faces and its proof, which publishes the seed once, so that anyone may roll the draw again, as
  [the results](results.md) explain.

A unit never committed is refused with `LootDrawRefused`, so that no draw is made without a commitment shown first.
With `require_commitment: false`, it is committed and revealed at once: still verifiable, but its commitment was never
shown before the payment.

Nobody sees a draw before the payment: the seed stays in the store until the order is paid, and the dice are rolled
only then.
