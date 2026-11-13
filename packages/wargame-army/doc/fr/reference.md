<!-- phil-dev source-sha256=c36cedd87b3c6c050e8c52ea67f3e267339d579ce3c598365237e42a8d42c4cc -->
# Référence

| Type | Rôle |
|---|---|
| `Catalogue` | Les données d'un jeu : unités, types de détachement, détachements pris et contraintes ; lu et écrit en tableaux et en JSON |
| `UnitEntry` | Une unité : catégories, points, figurines et groupes d'options |
| `OptionGroup` | Un choix parmi des options, avec ses nombres minimal et maximal de sélections |
| `Option` | Une option : ses points, pour l'unité ou pour chaque figurine, et le nombre maximal de fois où elle est prise |
| `DetachmentType` | Une sorte de détachement : ses emplacements et le maximum qu'une armée en prend |
| `Slot` | Le plus petit et le plus grand nombre d'unités d'une catégorie qu'un détachement prend |
| `ArmyList` | Une liste : son catalogue et sa version, sa limite de points et ses détachements ; lue et écrite en tableaux et en JSON |
| `Detachment` | Un détachement d'une liste : son type, son nom et ses unités |
| `UnitSelection` | Une unité choisie : ses figurines et ses options |
| `Costing` | Les points de chaque unité, de chaque détachement et de la liste |
| `ArmyValidator` | Vérifie une liste selon son catalogue, une règle à la fois |
| `Validation` | Chaque violation d'une liste, avec ses points |
| `Violation` | Une règle qu'une liste enfreint, avec son message et ses valeurs |
| `Rule` | Les règles qu'une liste peut enfreindre |
| `Identifiers` | Les formes des codes, des versions, des identifiants et des noms |
| `Constraint\Selector` | Une unité, ou les unités d'une catégorie |
| `Constraint\Scope` | L'armée, ou chaque détachement |
| `Constraint\UnitCount` | Le plus petit et le plus grand nombre d'unités sélectionnées |
| `Constraint\PointsShare` | La part de la limite de points que coûtent les unités sélectionnées |
| `Contract\Constraint` | Une règle qu'une liste respecte : celle du catalogue, ou celle propre à l'application |
| `Contract\DeclaredConstraint` | Une contrainte qu'un catalogue déclare dans ses données |
| `Contract\CatalogueProvider` | Où l'application garde ses catalogues |
| `Catalog\InMemoryCatalogueProvider` | Des catalogues en mémoire |
| `Export\TextExport` | Une liste en texte brut |
| `Export\HtmlExport` | Une liste en fragment de HTML échappé |
| `Exception\WargameArmyException` | L'interface de chaque exception de la bibliothèque |
| `Exception\InvalidCatalogue`, `InvalidArmyList`, `UnknownCatalogue` | Voir [Exceptions](exceptions.md) |

`ArrayInput`, `Fault` et `Export\Sheet` sont internes.
