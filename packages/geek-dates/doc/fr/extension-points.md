<!-- phil-dev source-sha256=c3fb0a562b7fa665edd7f6bb9e077ae3a1be45eb65589373347a7bd5b962c604 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que donne une application | Où |
|---|---|---|
| `Entry` | Ses propres entrées, construites en code | [Référence](reference.md#entrées) |
| `EntryReader` | Ses propres lignes, venues de la configuration, d'un fichier ou d'une base de données, sous la forme des données fournies | [Données](data.md#ajouter-une-entrée) |
| `Catalog::shipped($zone, $own)`, `Catalog::with()` | Les entrées fournies et les siennes dans un seul catalogue | [Référence](reference.md#les-questions-du-catalogue) |
| `new Catalog($entries, $zone)` | Seulement les entrées qu'elle choisit : certaines de `ShippedData::entries()`, ou aucune | [Référence](reference.md#les-questions-du-catalogue) |
| `Phil\Almanac\Rule\Rule` | Une règle à elle, implémentée pour `phil/almanac` et donnée à une `Entry` | [Référence](reference.md#entrées) |
| `DataCheck` | La vérification de ses propres lignes avant de les lire | [Données](data.md#ajouter-une-entrée) |

Les entrées, les filtres et les occurrences sont des valeurs ; le catalogue ne garde aucun état entre deux questions.
