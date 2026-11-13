<!-- phil-dev source-sha256=5de1a792d7cc497fa308504cf7e11ee82ecd4dfe9bfb3f063df8dfe014b788a4 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\WargameArmy\Exception\WargameArmyException`.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidCatalogue` | Une unité, une option, un groupe, un type de détachement, un emplacement, une contrainte ou un catalogue est refusé, ou ne se lit pas | `The group "weapon" has two options "sword".` |
| `InvalidArmyList` | Une liste d'armée, un détachement ou une sélection d'unité contient une valeur hors de ses bornes, ou ne se lit pas | `The models of a unit is from 1 to 1000; 0 is not.` |
| `UnknownCatalogue` | L'application ne fournit pas le catalogue d'une liste | `The catalogue "ember-vale" is not provided.` |

`InvalidCatalogue` et `InvalidArmyList` étendent `InvalidArgumentException` ; `UnknownCatalogue` étend
`RuntimeException`. Une liste qui enfreint les règles de son catalogue ne lève rien : le validateur la signale.
