# Rolling

`Rolling\TableRoller` rolls a table with its die through `tilttek/dice` and finds the row the total falls on. It takes a
`DiceRoller` of `tilttek/dice` — the default limits when none is given — and an optional `Contract\TableObserver`, and
keeps nothing between two calls.

## Rolling a table

```php
use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\ReferenceTables\Rolling\TableRoller;

$roll = new TableRoller()->roll($catalogue->get('reaction'), new Randomizer(new Secure()));

$roll->natural;                         // what the die gave, 8
$roll->total;                           // the value looked up, 8
$roll->row->display('attitude', 'fr');  // "Incertain"
$roll->dice?->toArray();                // every die, as tilttek/dice keeps them
```

The randomness is the `Random\Randomizer` the caller passes: a secure engine in production, a seeded engine in tests,
the same seed giving the same roll. A table without a die raises `LookupRefused`; so does a die whose first instruction
gives no number — a text, custom dice whose faces carry no value. The die is rolled within the limits of the
`DiceRoller`, which raises its own `DiceException` beyond them.

`TableRoll` holds the table's key, the row and its position in the table's `$rows`, the die's value, the modifier, the
total, whether the total was clamped, and the `PoolResult` of `tilttek/dice`; `isPhysical()` says whether the die was
rolled at the table instead.

## Modifiers and clamping

```php
$roll = $roller->roll($table, $randomizer, modifier: 2, clamp: true);
```

The modifier, from −1,000,000 to 1,000,000, is added to the die before the lookup: a reaction roll of 2d6 plus a bonus.
Many tables say that a total above the last row reads the last row, and one below the first reads the first: with
`clamp`, such a total takes the row at that end, and `TableRoll::$clamped` is true. A total that falls in a gap between
two rows is refused with `UnknownRow` either way, as it is without `clamp` beyond the ends.

## Dice rolled at the table

```php
$roll = $roller->enter($table, 9, modifier: 1);
```

A player rolls real dice and the game master enters the value: `enter()` finds the row the same way, with the same
modifier and clamping, and gives a `TableRoll` without `PoolResult`. It also serves a table without a die: a level
entered on the advancement table. The value lies from −1,000,000 to 1,000,000.

## The dice of a cell

```php
$number = $roller->rollCell($roll->row, 'number', $randomizer)->totals()[0];
```

A wandering monster table gives, with each encounter, how many appear: a `dice` cell. `rollCell()` rolls it and gives
the `PoolResult` of `tilttek/dice`; a cell that holds no dice raises `LookupRefused`, a missing cell `UnknownTable`.

## The observer

Each roll and each value entered is told, once its row is found, to the `Contract\TableObserver` the roller was given,
as an `Event\TableRolled`:

| Field | Holds |
|---|---|
| `table` | The table's key. |
| `position` | The row's position in the table's `$rows`, from 0. |
| `total` | The value looked up. |
| `physical` | Whether the die was rolled at the table and entered. |

The event carries identifiers and numbers, never the text of the row: an application sends a Messenger message from
it — a journal of the session, a push to the players' screens — and reads the row from its catalogue. A refused roll
tells nothing; a cell rolled is not an event.
