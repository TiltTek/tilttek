# Catalogues

A `Catalogue` is the data of a game, apart from the engine that reads it. The application provides its catalogues; the
library ships none.

## Units

A `UnitEntry` is a unit the catalogue offers:

| Value | Meaning | By default |
|---|---|---|
| `code`, `name` | The unit's code and the name shown to people | Required |
| `categories` | Up to 20 categories, each once, from the one that fills a detachment's slot first | None |
| `points` | The points of the unit with its fewest models, 0 to 100 000 | 0 |
| `minModels`, `maxModels` | The fewest and the most models, 1 to 1 000 | 1, and the fewest |
| `pointsPerModel` | The points of each model beyond the fewest, 0 to 100 000 | 0 |
| `optionGroups` | Up to 50 option groups; each option code once in the unit | None |

## Options

An `OptionGroup` is a choice among 1 to 100 options: the fewest and the most selections a unit makes in it, each option
counted as many times as it is taken — `min: 1, max: 1` for *one weapon*, `max: 2` for *up to two upgrades*. An
`Option` has its points each time it is taken, 0 to 100 000, the most times one unit takes it, 1 to 1 000, and
whether its points count once for each model of the unit (`perModel`).

The points of a unit are its own, plus those of each model beyond the fewest, plus those of each option taken.

## Detachments

A `DetachmentType` has 1 to 50 `Slot`s, each for a category with the fewest and the most units of it, and the most
detachments of the type an army takes. A unit fills the slot of its first category the detachment takes; a unit of no
such category is not allowed in it. The catalogue says how many detachments an army takes, 0 to 20, and 1 at least as
its most.

## Constraints

A catalogue declares up to 200 constraints — counts of units and shares of points —, which [Constraints](constraints.md)
describes. A constraint that names a unit names one the catalogue holds.

## Shapes and bounds

| Value | Shape |
|---|---|
| The code of a catalogue, a unit, an option, a group, a detachment type or a category | 1 to 64 lower-case letters, digits, `_`, `.` or `-`, starting with a letter or a digit |
| The version of a catalogue | 1 to 32 ASCII letters, digits, `_`, `.`, `+` or `-`, starting with a letter or a digit |
| A name | 1 to 200 characters of UTF-8, without control characters or surrounding spaces |

A catalogue holds 1 to 2 000 units and 1 to 50 detachment types, each code once.

## Reading a catalogue

`Catalogue::fromArray()` reads the array `toArray()` writes, and `fromJson()` reads JSON of at most 4 MiB, nested at most
15 levels deep. Both refuse an unknown key, a missing key and a value of the wrong type with
`InvalidCatalogue`, never another exception. An invented catalogue, in the shape `toJson()` writes:

```json
{
    "code": "ember-vale",
    "name": "Ember Vale Skirmish",
    "version": "2026.1",
    "units": [
        {"code": "captain", "name": "Captain", "categories": ["hero", "leader"], "points": 60, "option_groups": [
            {"code": "weapon", "name": "Weapon", "min": 1, "max": 1, "options": [
                {"code": "sword", "name": "Sword"},
                {"code": "bow", "name": "Bow", "points": 5}
            ]}
        ]},
        {"code": "scouts", "name": "Scouts", "categories": ["troops"], "points": 50,
         "min_models": 5, "max_models": 10, "points_per_model": 10, "option_groups": [
            {"code": "kit", "name": "Kit", "options": [
                {"code": "long-rifle", "name": "Long rifle", "points": 2, "per_model": true}
            ]}
        ]}
    ],
    "detachment_types": [
        {"code": "vanguard", "name": "Vanguard", "max": 1, "slots": [
            {"category": "hero", "min": 1, "max": 1},
            {"category": "troops", "min": 1, "max": 3}
        ]}
    ],
    "min_detachments": 1,
    "max_detachments": 2,
    "constraints": [
        {"type": "count", "category": "leader", "min": 1},
        {"type": "points-share", "category": "hero", "max_percent": 25}
    ]
}
```

A changed catalogue takes a new `version`: a list records the version it was built with, and the validator says when
the catalogue has moved on.
