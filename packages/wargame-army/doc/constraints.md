# Constraints

A constraint is a rule a list keeps beyond its units' own bounds. A catalogue declares those of the library in its
data; an application adds its own to the validator.

## Selectors

A constraint counts the units a `Constraint\Selector` takes: one unit of the catalogue, `Selector::unit('ogre')`, or
every unit of a category, `Selector::category('hero')`. In a catalogue's data, a constraint has either a `unit` or a
`category`, never both.

## Counts

`Constraint\UnitCount` holds the fewest and the most units selected, in the whole army or in each detachment on its
own (`Scope::Army`, `Scope::Detachment`). It has a minimum above 0, a maximum, or both.

```json
{"type": "count", "category": "leader", "min": 1}
{"type": "count", "unit": "ogre", "max": 1}
{"type": "count", "category": "hero", "scope": "detachment", "min": 1, "max": 1}
```

```text
At least 1 units of the category "leader" in the army; the army has 0.
At most 1 "Ogre" in the army; the army has 2.
At least 1 units of the category "hero" in each detachment; detachment 2 has 0.
```

## Points shares

`Constraint\PointsShare` holds the share of the list's points limit the units selected may cost, in whole percents
from 0 to 100: a least share, a most share, or both. The shares are compared exactly, in integers: 33 % of a limit of
101 points allows 33 points and refuses 34; a least share rounds up.

```json
{"type": "points-share", "category": "hero", "max_percent": 25}
{"type": "points-share", "category": "troops", "min_percent": 25}
```

```text
Units of the category "hero" cost at most 25 % of the points limit, 75 points; the list spends 105.
```

## An application's own constraints

A class that implements `Contract\Constraint` receives the list, the catalogue and the `Costing`, and returns its
violations, each a `Violation` of `Rule::Constraint` with its message and values. It is passed to the validator, which
checks it on every list after the catalogue's constraints:

```php
final readonly class ClubNightCap implements Constraint
{
    public function check(ArmyList $list, Catalogue $catalogue, Costing $costing): array
    {
        return $costing->total > 400
            ? [new Violation(Rule::Constraint, 'Club nights are played at 400 points or less.', ['points' => $costing->total])]
            : [];
    }
}

$validator = new ArmyValidator($catalogues, new ClubNightCap());
```

A constraint keeps nothing between two calls. `Costing::count()` and `Costing::points()` sum the units a selector takes
without walking the list again.
