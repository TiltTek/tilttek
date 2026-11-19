# Physical dice

At a convention, the buyer rolls physical dice in front of the cashier. The register's page shows the form
`PhysicalDiceType` with `<twig:TilttekLootTables:PhysicalDice>`, as [the screens](screens.md) explain, and
`PhysicalDice::enter()` records the codes on the order line, one shop code per unit:

```php
$physicalDice->enter($order, $line->reference, 'loot', ['352614', '114263']);
$orders->save($order);
```

- Each code is read by `ShopCode` against the box the option draws: one character per die, `1`–`9`, then `A` for 10 up
  to `K` for 20, in upper or lower case.
- The order line records the `DrawText` of each unit as its draws, and the bundle keeps each as a draw of physical
  dice, without a seed nor a proof.
- A unit already committed and not revealed takes the dice in place of its seed.
- Refused with `LootDrawRefused`: a line the order does not have, an option that is not a box left to draw here — a
  text, a box drawn already, an option of another drawer —, or not one code per unit. A code that does not read is
  refused with `InvalidCode` before anything is recorded.

`PhysicalDice::toEnter()` gives the box the option draws and the number of units of the line, what the form asks for,
and refuses the same lines and options as `enter()`.

The order is placed, as for any draw; storing it after is the application's.
