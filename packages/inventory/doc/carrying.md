# Carrying rules and loads

## What a system counts

The carrying rules of a game system are a `CarryRules`: the `Measure` it counts — `Weight` or `Slots` —, the capacity
of a carrier when the application gives none, its load bands from the lightest, and whether a carrier may go beyond the
last band.

```php
use Tilttek\Inventory\CarryRules;
use Tilttek\Inventory\LoadBand;
use Tilttek\Inventory\Measure;

// Weight, three bands as fractions of the capacity
$variant = new CarryRules(Measure::Weight, 150, [
    new LoadBand('unencumbered', 1, 3),
    new LoadBand('encumbered', 2, 3),
    new LoadBand('heavily-encumbered', 1),
]);

// Slots, never beyond them
$slots = new CarryRules(Measure::Slots, 10, [new LoadBand('fine', 1)], refuseOverCapacity: true);
```

A band has a key and ends at a fraction of the carrier's capacity, numerator and denominator 1 to 1000, so that a third
is exact; a fraction above 1 declares a band beyond the capacity that a system still allows. Rules hold 1 to 10 bands,
under distinct keys, each ending above the one before; the default capacity is 0 to 1,000,000,000,000. Every
computation is in whole numbers.

## The rule book

`Inventories` reads the rules of an inventory's system from a `Contract\RuleBook`. `Rules\StaticRuleBook` takes them by
system key; an application builds them from the systems it knows and the variants a table plays with:

```php
use Tilttek\Inventory\Rules\StaticRuleBook;

$book = new StaticRuleBook(['srd-5.2' => $variant, 'knave' => $slots]);
```

An inventory without a system, or whose system has no rules in the book, is never weighed: `load()` gives null and
`loads()` an empty list. Changing an inventory's system refuses nothing: the loads tell what the new rules make of it.

## Capacities

The capacity of each carrier comes from a `Contract\CarrierCapacity`, by the measure the rules count. The default,
`Rules\DefaultCapacity`, gives the rules' default to every carrier; an application reads it from the character's sheet:

```php
use Phil\Resource\ResourceReference;
use Tilttek\Inventory\CarryRules;
use Tilttek\Inventory\Contract\CarrierCapacity;
use Tilttek\Inventory\Inventory;

final readonly class SheetCapacity implements CarrierCapacity
{
    public function __construct(private Sheets $sheets) {}

    public function capacity(Inventory $inventory, ResourceReference $carrier, CarryRules $rules): int
    {
        $strength = $this->sheets->score($carrier, 'strength');

        return null === $strength ? $rules->defaultCapacity : 15 * $strength;
    }
}
```

A capacity is 0 to 1,000,000,000,000; another value throws `InvalidInventory`.

## Loads

`load()` weighs what a carrier carries in an inventory: the items directly on it, each with what it holds — the
contents of a container count unless they weigh nothing, however deep:

```php
$load = $inventories->load($inventory, $ada);
$load->carried;      // 46
$load->capacity;     // 150
$load->band?->key;   // 'unencumbered', null over the last band
$load->limit;        // 150, the most the last band allows
$load->room();       // 104 before the last band is passed
$load->isOver();     // false

$inventories->loads($inventory);   // each carrier holding something, in the order of their references
```

A carrier is in the lightest band whose end its load does not pass. When the rules refuse going over capacity, an act
that would put the carrier holding the destination beyond the last band — adding, increasing, moving, transferring — is
refused with `Refusal::OverCapacity`; an act that lightens a carrier is never refused, even one already over after its
rules or its capacity changed. Otherwise a load over the last band is told, never refused.

Items in the stash are never weighed. An inventory weighs carriers within itself: a character who carries items in its
own inventory and in its party's has two loads, which the application adds when its game counts them together.

## Container fills

`fill()` tells what a container holds against its bounds, by both measures, whatever the system counts:

```php
$fill = $inventories->fill($inventory, 'pack');
$fill->weight;        // what is directly inside, each container inside with its contents
$fill->slots;
$fill->weightLeft();  // null without a bound by weight
$fill->slotsLeft();
```

`fill()` on an item that is not a container is refused with `Refusal::NotAContainer`.
