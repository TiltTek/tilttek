# The tower in PHP

`DiceTower` is a public service. A table, a roller and a member are `Phil\Resource\ResourceReference`s the application
gives: `table:7`, `member:42`.

## Rolling and dropping

```php
use Phil\Resource\ResourceReference;
use Tilttek\DiceTower\Application\DiceTower;

$entry = $tower->roll(ResourceReference::of('table', '7'), '4d6kh3', ResourceReference::of('member', '42'), locale: 'fr');
```

`roll()` reads the expression in the notation of the locale, rolls it in the journal of `TilttekDiceBundle` — owned by
the roller, with the rounding asked for — and drops it in the table's tower. `drop()` drops a roll the journal already
holds, by its identifier, and raises `UnknownRoll` for one it does not hold:

```php
$recorded = $diceRolls->rollReplayable('1d20+5', $member);
$entry = $tower->drop($table, $recorded->id(), $member);
```

A dropped roll is kept in the log with a position after every earlier drop, pushed to the table's topic, and handed to
the relays. A hub or a relay that fails is logged; the roll stays in the log.

## Reading the entries

```php
$tower->entries($table);       // the last entries, the earliest first
$tower->entries($table, 41);   // the entries after the position 41
$tower->entry($table, 42);     // one entry, or null
```

Both lists hold as many entries as the `history` at most. A roll erased from the journal since it was dropped is left
out. The positions grow across every tower, with gaps between the entries of one table: a viewer remembers the last
position it saw and asks for those after it.

## The payload

`TowerEntry::toArray()` is what a subscriber receives, as JSON:

```json
{
    "position": 42,
    "table": "table:7",
    "roll": "0d0000000e0000000f00000010000000",
    "roller": "member:42",
    "rolled_at": "2026-11-12T20:00:00+00:00",
    "results": [
        {
            "expression": "2d{coin}",
            "total": 1,
            "output": 1,
            "sets": [
                {"notation": "2d{coin}", "die": "coin", "dice": [
                    {"value": 1, "face": "heads", "kept": true},
                    {"value": 0, "face": "tails", "kept": true}
                ]}
            ]
        }
    ]
}
```

It carries identifiers and the result only. The roller is the opaque reference the application gave: the page turns
it into a name for the viewers allowed to see it, never the hub. The summary of the result is read with bounds — no
deeper than 16 levels of the journal's detail, no more than 500 dice — and skips what does not have the journal's
shape.

## Privacy

When a person is erased, erase their rolls from the journal and their drops from the towers:

```php
$diceRolls->eraseOwner($member);
$tower->eraseRoller($member);
```

An entry whose roll was erased from the journal is no longer shown, even before its drop is erased.

## Long-running processes

Nothing is kept between two requests: the order of a tower and the entries a viewer missed come from the log, read
from its store each time. The memory store, for tests, is emptied between two requests of a worker by the
`kernel.reset` tag.
