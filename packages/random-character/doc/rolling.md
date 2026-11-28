# Rolling

`CharacterRoller` is the service. It takes the `Systems` of `tilttek/rpg-systems`, the `Generator` of
`tilttek/generator` the fields draw from — none for rules that draw from none —, a `DiceRoller` of `tilttek/dice` with
the application's limits, the `Random\Randomizer` that draws seeds, and an optional observer.

```php
use Tilttek\RandomCharacter\CharacterRoller;

$roller = new CharacterRoller($systems, $generator);

$roller->check($rules);                     // [] when the rules can be rolled
$character = $roller->roll($rules);         // a seed is drawn
$again = $roller->roll($rules, $character->seed);
```

## Checking

`check()` lists what keeps rules from being rolled, as sentences, and an empty list when nothing does:

- the system is not one of the `Systems`;
- an attribute of the stats is not one of the system's;
- a value of a fixed array falls outside the bounds of an attribute;
- a field draws from a generator the `Generator` does not have, or the roller has no `Generator`.

A generator named by placeholders depends on the values rolled, and is checked when it is drawn. `roll()` checks the
rules first and raises an `InvalidRules` that lists every problem.

## Seeds

A seed is a text of 1 to 256 characters in UTF-8: a table number, a player's choice, the identifier of a session. A
character rolled without one gets 32 hexadecimal digits drawn by the roller's `Randomizer`, a secure engine unless the
application passes another. Every character carries its seed in `RandomCharacter::$seed`; the same seed with the same
rules, system and tables gives the same character.

Each part of the character draws from its own seed, the SHA-256 of the character's seed and the part's name: the stats
from one, each field from another. Adding a field to the rules, or changing one, leaves the stats and the other fields
as they were; a field whose generator is named by a placeholder changes when the field it reads changes. Changing a
table changes the fields drawn from it.

## The roll

The stats are rolled first. `Each` rolls the attributes in the order of the rules; `RolledArray` rolls one value per
attribute and shuffles them; `Array` shuffles the fixed values. A value outside the bounds of its attribute raises a
`RollFailed`, as does a roll that gives no number or exceeds the limits of the `DiceRoller`.

The fields follow, in order. A rolled field rolls its dice `count` times. A generated field replaces its placeholders
with the values before it, then asks the generator for `count` results, different from each other when it is unique,
with a seed of its own. A generator that fails, a unique field given too few different results, or a name that is not
a generator of the catalogue raises a `RollFailed` that keeps the cause as its previous exception.

## The character

`RandomCharacter` holds the key of the rules and of the system, the seed, the attribute values by key in the order of
the rules, the field values by key in the order of the rules, the attributions of the tables the fields drew from —
each once, in the order the roll first used them — and the values changed by hand.

```php
$character->stat('grit');               // 3
$character->field('traits');            // ['patient', 'curious']
$character->attributions;               // list of Tilttek\Generator\Definition\Attribution
$character->toArray();                  // plain arrays, for JSON or a database column
```

## Editing

A rolled character is a starting point. `withStat()` and `withField()` give a new character with one value changed, the
seed kept, and the value's name added once to `$edited`: `stats.grit`, `fields.name`. `isEdited()` says whether the seed
alone still gives this character. An attribute takes any integer from -1,000,000 to 1,000,000 — the system's bounds are
the sheet's to enforce on a hand edit —; a field takes an integer, a text of up to 4,096 characters in UTF-8, or a list
of up to 16 of them. An unknown attribute or field, or any other value, raises an `InvalidEdit`.

## The observer

A `Contract\CharacterObserver` passed to the roller is told of each character rolled, once it is complete, by an
`Event\CharacterRolled`: the key of the rules, the key of the system, and whether the caller gave the seed. The seed and
the values stay out — a caller may have written the seed, and the character belongs where the application keeps its
sheets. Rules that fail to roll tell nothing.
