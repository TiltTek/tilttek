<!-- phil-dev source-sha256=c5c96f51990ab1113ce2d2cbbe8d4004546704dc5787d0550be13fd85ac1672d -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que l'application fournit | Où |
|---|---|---|
| `Contract\RuleStore` | Un dépôt à elle, dans une base de données ou un moteur de recherche | [Dépôts](stores.md#un-dépôt-à-vous) |
| `Rule` | Des règles construites en code, d'une source que `RulesFromSystem` ne lit pas | [Règles](rules.md) |
| `Message\ReindexRules` | Le *handler* qui indexe de nouveau les règles d'un système dans son propre dépôt | [Dépôts](stores.md#un-dépôt-à-vous) |
| `AttributionRenderer` | Le moteur d'attribution de `tilttek/rpg-systems` donné à `RulesLookup`, pour ses mentions | [Rechercher](searching.md#attribution) |

Les règles, les identifiants et les requêtes sont des valeurs. Les services ne gardent aucun état entre deux appels.
