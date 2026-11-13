# Defining sets

## Members

A `SetDefinition` holds its code, its version and its members, 1 to 1 000 of them, each named once by a stable
identifier the application gives: the code of a badge, an item, a sticker. The order of the members is the order the
set is shown in, and the order of `Evaluation::$owned` and `Evaluation::$missing`.

| Value | Shape |
|---|---|
| The code of a set, a tier or a reward kind | 1 to 64 lower-case letters, digits, `_`, `.` or `-`, starting with a letter or a digit |
| A member | 1 to 255 ASCII letters, digits, `_`, `.`, `:`, `/` or `-`, starting with a letter or a digit; never an `@` |
| A version | 1 to 1 000 000 |

## Tiers

A `Tier` counts the members a holder owns at a level or higher: `new Tier('bronze', required: 5)` is *5 of 20*,
`new Tier('gold', level: 2)` is *all 20 at level 2*. A set declares up to 20 tiers, from the easiest to the hardest:
each tier's level and number required are never lower than those of the tier before, and at least one of them is
higher, so that a holder who reaches a tier has reached every tier before it. A number required larger than the set
is refused.

A set redone at higher levels — a shiny variant, a second printing — is one set with one tier per level; the
application says at which level a holder owns each member.

The completion of the set itself is owning every member, at any level. It needs no tier.

## Rewards

A `Reward` is what a set or a tier declares, never grants: a kind — `points`, `badge`, `item`, or any the application
branches on —, an opaque reference of 1 to 255 bytes without whitespace, and a quantity of 1 to 1 000 000 000. A set
and each tier declare up to 20. The events carry them; the application grants them.

```php
new SetDefinition('dragons', 1, ['red', 'blue', 'green'],
    tiers: [new Tier('first', required: 1, rewards: [new Reward('points', 'xp', 5)])],
    rewards: [new Reward('badge', 'dragon-tamer'), new Reward('item', 'golden-egg')],
);
```

## Versions

`Sets::define()` adds a version of a set:

- a version never defined is added, and `define()` returns true;
- the same version with the same content returns false: defining the sets of the configuration at each boot is safe;
- the same version with other content throws `DefinitionConflict`: a changed set takes a new version;
- a version older than the latest throws `DefinitionConflict`, unless it is defined already with the same content.

`Sets::definition($code)` gives the latest version, `Sets::definition($code, $version)` a given one. Progress is
always measured against the latest version; completions keep the version they were earned under.

## Reading a definition

`SetDefinition::fromArray()` reads the array `toArray()` writes — from the application's configuration, a form or
a database column —, and `fromJson()` reads JSON of at most 1 MiB, nested no deeper than a definition. Both refuse an unknown
key, a missing key and a value of the wrong type with `InvalidSet`, never another exception.

```json
{
    "code": "starter-dice",
    "version": 2,
    "members": ["d4", "d6", "d8", "d10", "d12", "d20"],
    "tiers": [{"code": "half", "required": 3}, {"code": "shiny", "level": 2}],
    "rewards": [{"kind": "points", "reference": "xp", "quantity": 50}]
}
```

`level` defaults to 1, `required` to every member, `quantity` to 1, `tiers` and `rewards` to none.
