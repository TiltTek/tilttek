# Reference

| Type | Role |
|---|---|
| `Catalogue` | The data of a game: units, detachment types, detachments taken and constraints; read from and written to arrays and JSON |
| `UnitEntry` | A unit: categories, points, models and option groups |
| `OptionGroup` | A choice among options, with its fewest and most selections |
| `Option` | An option: its points, for the unit or each model, and the most times it is taken |
| `DetachmentType` | A kind of detachment: its slots and the most of it an army takes |
| `Slot` | The fewest and the most units of a category a detachment takes |
| `ArmyList` | A list: its catalogue and version, its points limit and its detachments; read from and written to arrays and JSON |
| `Detachment` | A detachment of a list: its type, its name and its units |
| `UnitSelection` | A unit chosen: its models and its options |
| `Costing` | The points of each unit, detachment and list |
| `ArmyValidator` | Checks a list against its catalogue, one rule at a time |
| `Validation` | Every violation of a list, with its points |
| `Violation` | A rule a list breaks, with its message and values |
| `Rule` | The rules a list may break |
| `Identifiers` | The shapes of codes, versions, identifiers and names |
| `Constraint\Selector` | One unit, or the units of a category |
| `Constraint\Scope` | The army, or each detachment |
| `Constraint\UnitCount` | The fewest and the most units selected |
| `Constraint\PointsShare` | The share of the points limit the units selected cost |
| `Contract\Constraint` | A rule a list keeps: the catalogue's, or the application's own |
| `Contract\DeclaredConstraint` | A constraint a catalogue declares in its data |
| `Contract\CatalogueProvider` | Where the application keeps its catalogues |
| `Catalog\InMemoryCatalogueProvider` | Catalogues in memory |
| `Export\TextExport` | A list as plain text |
| `Export\HtmlExport` | A list as a fragment of escaped HTML |
| `Exception\WargameArmyException` | The interface of every exception of the library |
| `Exception\InvalidCatalogue`, `InvalidArmyList`, `UnknownCatalogue` | See [Exceptions](exceptions.md) |

`ArrayInput`, `Fault` and `Export\Sheet` are internal.
