# Earning points

## Earning rules

An `EarningRule` gives points for the activities it reads:

```php
use Tilttek\Points\Rule\Cap;
use Tilttek\Points\Rule\EarningRule;
use Tilttek\Points\Rule\Period;

new EarningRule('games', ['game.played'], 10, cap: new Cap(50, Period::Week));
new EarningRule('orders', ['order.paid'], 1);                                    // one point per unit of quantity
new EarningRule('first-order', ['order.paid'], 100, cap: new Cap(100, Period::Ever), perQuantity: false);
new EarningRule('campaigns', ['game.*'], 2, 'stars', context: ['system' => 'dnd5e']);
```

- **The verbs** are patterns of `tilttek/activity`: `game.played`, `game.*`, `*`. A rule reads at least one.
- **The points** are 1 to 1,000,000, multiplied by the activity's quantity unless `perQuantity` is false.
- **The currency** is the default one when none is named; `PointsProjection` refuses a rule in a currency it does not
  know.
- **The cap** limits what the rule gives one member over a period: a day, an ISO week from Monday, a calendar month or
  year — counted in the timezone of the `Rules` —, or ever. An activity that would pass the cap earns what is left,
  and nothing once it is reached.
- **The context conditions** must all be in the activity's context, with the same value and type.

`Rules` holds the rules of an application, by unique name, and the timezone of their periods;
`Rules::fromArray()` builds them from a configuration:

```php
Rules::fromArray([
    'games' => ['verbs' => ['game.played'], 'points' => 10, 'cap' => ['amount' => 50, 'period' => 'week']],
    'orders' => ['verbs' => ['order.paid'], 'points' => 1, 'currency' => 'stars', 'per_quantity' => true, 'context' => ['online' => true]],
], 'America/Toronto');
```

## The projection

`PointsProjection` is a projection of `tilttek/activity`: the `Projector` hands it the entries of the journal its rules
read, and it writes the movements.

- **A recorded activity** earns, for each rule that rewards it, a movement `#earn:<rule>:<activity key>` from the rule's
  issuance account to the member, dated at the activity's moment. An entry handed twice earns once.
- **A cancelled activity** reverses each earning it caused by a movement `#reverse:<earning key>`, dated at the
  activity's moment too, so the reversal frees the cap of the period the activity counted in.

```php
$projector = new Projector($activityLog, $checkpoints);
$projector->catchUp($projection);            // by a scheduled task
new LiveProjections($projector, new Projections([$projection]));   // or as each entry is written
```

## When a rule changes

The journal of activity is never rewritten; the points derived from it are recomputed from it:

```php
$projector->rebuild($projection, new DateTimeImmutable('2026-11-01'));
```

The projection first forgets the derived movements — earned, reversed, expired — whose moment is on or after the date
(all of them without a date), then the journal of that period is replayed under the new rules. The decided movements
are kept: a spending made with points a rule no longer gives stays, and the balance may then be below zero, as after
a cancellation. The expiry is recomputed by the next `expire()`.
