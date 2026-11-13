# Hidden rolls

## Rolling out of sight

```php
$hidden = $rolls->roll(
    'scene:12',                          // the scene: 1 to 128 letters, digits, dots, colons, underscores or hyphens
    '2d6+1',                             // a Pool, or an input in the notation of the locale
    roller: 'seat:gm',                   // who rolled, an opaque reference, public
    context: 'The rope bridge holds?',   // what the roll was for, sealed with the result
    rounding: RoundingMode::NegativeInfinity,
    locale: 'en',
);
```

`roll()` draws a seed of 32 bytes from the secure randomizer, rolls the expression with `DiceRoller::rollVerifiable()`
— the seed, the roll's identifier as the client seed, nonce 0 and the rounding of its divisions —, keeps the
`HiddenRoll` sealed in the store and appends the entry of its commitment to the scene's journal. The `HiddenRoll`
returned is the game master's: `$hidden->opening->totals()` gives the totals, `$hidden->opening->result` the whole
result as `PoolResult::toArray()` writes it.

The identifier of a roll is derived from its scene and its position in the journal: 32 hexadecimal digits, the first
half of the SHA-256 of `["tilttek-secret-rolls/1", scene, position]`. It is known before the roll is made, which
binds the client seed of the roll to its place in the journal.

The roller and the context are text of 1 to 320 and 1 to 1 024 bytes without a control character. The roller is
public, in the entry of the commitment: give an opaque reference, a seat or a member identifier, never a name or an
address. The context is sealed with the result and revealed with it.

## What the table sees

| Moment | The table sees | The store keeps sealed |
|---|---|---|
| The roll is made | The entry `committed`: the roll's identifier, its commitment, the roller and the time | The opening: expression, rounding, seed, result, context |
| The roll is revealed | The entry `revealed`: the whole opening | The opening, still |
| The scene is closed | The entry `closed` | The openings of the rolls withheld |

The commitment tells nothing of the result: the 32 secret bytes of the seed are hashed with it.

## Revealing

```php
$rolls->reveal('scene:12', $roll, $otherRoll);   // entries `revealed`, in the order given, each roll once
```

A roll is revealed once; a roll the scene did not commit, or whose opening the store no longer keeps, is refused, and
nothing of the call is appended when one roll is refused. `hidden($roll)` gives the game master the sealed roll again.

## Closing a scene

```php
$rolls->close('scene:12', $roll);        // reveals the rolls given — a partial reveal —, then closes
$rolls->close('scene:12');               // reveals none, then closes
$rolls->closeRevealingAll('scene:12');   // reveals every roll not revealed yet, then closes
```

A closed scene takes no more rolls and no second closing. The rolls it did not reveal stay withheld, listed by
`SceneJournal::withheld()`, their commitments in the journal: `reveal()` still publishes them after the scene, when the
game master chooses to.

## Limits

A scene takes a roll while its journal holds fewer than 4 999 entries (`SceneJournal::MAX_ROLLS`), so that a journal —
each roll committed and revealed once, and the closing — never holds more than 9 999 entries, and reading or checking
it stays bounded. The expression is read within the limits of the `DiceRoller`; an opening's expression is at most
4 096 bytes.
