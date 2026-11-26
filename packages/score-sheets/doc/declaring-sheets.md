# Declaring a sheet

A sheet is declared once per game, as data: a JSON file, a row of a database, or objects built in code. The examples
below use Lantern Harbor, a game invented for this documentation; declare the sheet of a published game only with its
publisher's agreement, or for a game in the public domain.

```json
{
    "game": "lantern-harbor",
    "categories": [
        {"key": "boats", "label": "Boats moored", "counted": false, "min": 0, "max": 12},
        {"key": "lanterns", "label": "Lanterns lit", "counted": false, "min": 0, "max": 40},
        {"key": "harbor", "label": "Harbour", "operation": {"type": "sum", "of": ["boats", "lanterns"]}},
        {"key": "sets", "label": "Sets of lanterns", "counted": false, "min": 0, "max": 10},
        {"key": "set_points", "label": "Points for sets", "operation": {"type": "product", "of": ["sets"], "factor": 4}},
        {"key": "fleet", "label": "Largest fleet", "operation": {"type": "majority", "of": "boats", "points": [6, 3]}},
        {"key": "lighthouse", "label": "Lighthouse", "operation": {"type": "bonus", "of": "lanterns", "at_least": 10, "points": 5}},
        {"key": "coins", "label": "Coins", "counted": false, "min": 0}
    ],
    "tiebreakers": [{"category": "coins", "prefer": "most"}, {"category": "boats", "prefer": "fewest"}],
    "teams": "sum"
}
```

`SheetDefinition::fromJson()` reads it — at most 64 KiB, 16 levels deep —, `fromArray()` reads the decoded array,
`toArray()` writes it back. Every field is checked, and a declaration that does not hold together is refused with
`InvalidSheet`.

## The game

`game` is a key: 1 to 64 ASCII letters, digits, dots, underscores and hyphens, starting with a letter. Category keys
follow the same pattern.

## Categories

A sheet declares 1 to 64 categories, each key once, in the order the sheet shows them.

| Field | Meaning | Default |
|---|---|---|
| `key` | The key of the category | — |
| `label` | What the sheet prints: 1 to 100 characters, no control character | The key |
| `counted` | Whether the category counts in the total | `true` |
| `min`, `max` | The bounds of an entered value, between -1 000 000 and 1 000 000 | The widest |
| `operation` | How the category is computed; none for an entered category | — |

A computed category reads only categories declared before it, and takes no bounds. A category that feeds another — a
count of sets whose points another category computes — is declared with `"counted": false`, so it does not count
twice.

## Operations

| Operation | Declared | Value |
|---|---|---|
| Sum | `{"type": "sum", "of": ["a", "b"]}` | The sum of 1 to 16 categories |
| Product | `{"type": "product", "of": ["sets"], "factor": 4}` | The product of 1 to 16 categories and the factor, 1 by default |
| Majority | `{"type": "majority", "of": "boats", "points": [6, 3], "prefer": "most", "at_least": 1}` | The points of the participant's place: the most — or the fewest — takes the first points, the next value the second, up to 8 places |
| Bonus | `{"type": "bonus", "of": "lanterns", "at_least": 10, "points": 5}` | The points when the category is `at_least`, `at_most` or `equals` the threshold, 0 otherwise |

In a majority, tied participants all take the points of their place, and the places they fill are skipped: two
first, then a third. A participant below `at_least`, when it is given, takes no place. Constants — factors, points,
thresholds — lie between -1 000 000 and 1 000 000; a value too large to compute is refused with `InvalidEntry` rather
than turned into a float.

## Tie-breakers

Up to 8 tie-breakers, read in order among participants of the same total: the most — `"prefer": "most"`, the default
— or the fewest of one declared category. Participants still equal share their rank.

## Teams

`"teams": "sum"` — the default — makes a team's score the sum of its members' totals; `"teams": "best"`, the best of
them.

## In code

```php
$sheet = new SheetDefinition('lantern-harbor', [
    new Category('boats', 'Boats moored', counted: false, min: 0, max: 12),
    new Category('fleet', 'Largest fleet', new Majority('boats', [6, 3])),
], [new Tiebreaker('boats', Direction::Fewest)]);
```
