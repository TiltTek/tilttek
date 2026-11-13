# Army lists and validation

## A list

An `ArmyList` holds its identifier, its name, the code and the version of its catalogue, its points limit — 1 to
1 000 000 —, and up to 20 detachments. `ArmyList::for($catalogue, $id, $name, $limit)` starts an empty list at the
catalogue's current version. A `Detachment` has the code of its type, an optional name the player gives it, and up to
200 units. A `UnitSelection` has the code of its unit, its number of models, 1 to 1 000, and up to 100 options taken,
each with a count of 1 to 1 000.

A list never changes; each change gives a new list: `withDetachment()`, `withDetachmentAt()`, `withoutDetachment()`,
`renamed()`, `withPointsLimit()`, `withCatalogueVersion()`, and on a detachment `withUnit()`, `withUnitAt()`,
`withoutUnit()`, and on a unit `withModels()` and `withOption()`. Positions count from 0.

A list holds any code: whether a unit or an option exists and is allowed is the validator's question, so that a list
being built is never refused for a unit a new version of the catalogue removed. `ArmyList::fromArray()` and
`fromJson()` read what `toArray()` and `toJson()` write, every fault an `InvalidArmyList`.

## Points

`Costing::of($list, $catalogue)` gives the points of each unit (`$units`, by detachment), of each detachment
(`$detachments`) and of the list (`$total`); `count()` and `points()` sum the units a selector takes. A unit or an option
the catalogue does not hold costs nothing; the validator reports it.

## The validator

`ArmyValidator::validate($list)` finds the list's catalogue through the provider — `UnknownCatalogue` when there is
none — and `validateAgainst($list, $catalogue)` checks a list against a catalogue given. The rules come in this order:

1. the catalogue the list names, and the version it was built with;
2. the number of detachments, and the most detachments of each type;
3. each detachment, in order: its type, then each unit — unknown, its models, its options, its option groups, the slot
   it fills —, then the slots of the detachment;
4. the points limit;
5. the constraints the catalogue declares, then the application's.

Every violation is returned, none stops the others. `Validation` gives `isValid()`, `messages()` and `ofRule()`, with
the list, the catalogue and the `Costing`. Each `Violation` carries its `Rule`, its message and the values it was built
from — `detachment` and `unit` counted from 1, `count`, `min`, `max`, `points`… —, for an application that writes its
own messages or translates them.

| `Rule` | Message, for instance |
|---|---|
| `Catalogue` | `The list is built with the catalogue "other"; it is checked against "ember-vale".` |
| `CatalogueVersion` | `The list was built with version 2025.9 of the catalogue "ember-vale"; the catalogue is at version 2026.1.` |
| `DetachmentCount` | `The army has 3 detachments; the catalogue "ember-vale" takes at most 2.` |
| `DetachmentLimit` | `The army has 2 detachments "Vanguard"; the catalogue allows at most 1.` |
| `UnknownDetachment` | `Detachment 3 "swamp": the catalogue has no detachment type "swamp".` |
| `UnknownUnit` | `Detachment 1 "Vanguard", unit 4 "dragon": the catalogue has no unit "dragon".` |
| `NotAllowed` | `Detachment 2 "Raid", unit 1 "Ogre": the detachment type "Raid" takes no unit of its categories.` |
| `Slot` | `Detachment 1 "Vanguard": 0 units of the category "troops"; the slot takes at least 1.` |
| `Models` | `Detachment 2 "Second", unit 1 "Scouts": 11 models; the unit takes 5 to 10.` |
| `UnknownOption` | `Detachment 1 "Vanguard", unit 1 "Captain": the unit has no option "axe".` |
| `OptionLimit` | `Detachment 1 "Vanguard", unit 1 "Captain": the option "Banner" is taken 3 times; at most 1 time.` |
| `OptionGroup` | `Detachment 1 "Vanguard", unit 1 "Captain": 0 selections in "Weapon"; the group takes at least 1.` |
| `PointsLimit` | `The list costs 579 points; its limit is 300.` |
| `Constraint` | `At most 1 "Ogre" in the army; the army has 2.` |
