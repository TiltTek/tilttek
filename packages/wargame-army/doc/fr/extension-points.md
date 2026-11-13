<!-- phil-dev source-sha256=c1f1d666ee16a62c117e6bc00aac5928d3e0562f928f34f9d66a185a506e9d53 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que l'application donne | Où |
|---|---|---|
| `Contract\CatalogueProvider` | Ses catalogues, par code ; `Catalog\InMemoryCatalogueProvider` les garde en mémoire | [Catalogues et licences](catalogues-and-licences.md) |
| `Contract\Constraint` | Ses propres règles, vérifiées sur chaque liste après celles du catalogue | [Contraintes](constraints.md#les-contraintes-propres-à-une-application) |

Chaque point est passé à un constructeur ; `ArmyValidator` ne garde aucun état entre deux appels, et une contrainte
n'en garde pas non plus. `Contract\DeclaredConstraint` est l'interface des contraintes qu'un catalogue déclare dans ses
données, que la bibliothèque lit : `Constraint\UnitCount` et `Constraint\PointsShare`.
