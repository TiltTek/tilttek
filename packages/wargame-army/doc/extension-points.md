# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `Contract\CatalogueProvider` | Its catalogues, by code; `Catalog\InMemoryCatalogueProvider` keeps them in memory | [Catalogues and licences](catalogues-and-licences.md) |
| `Contract\Constraint` | Its own rules, checked on every list after the catalogue's | [Constraints](constraints.md#an-applications-own-constraints) |

Every point is passed to a constructor; `ArmyValidator` keeps no state between two calls, and a constraint keeps none
either. `Contract\DeclaredConstraint` is the interface of the constraints a catalogue declares in its data, which the
library reads: `Constraint\UnitCount` and `Constraint\PointsShare`.
