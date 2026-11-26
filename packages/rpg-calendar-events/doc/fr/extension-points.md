<!-- phil-dev source-sha256=ee9e3a89fb00808e8719be7bf6b54417be45bc6d31bc6ec4bd83e6488f79ac57 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que donne une application | Où |
|---|---|---|
| `Entry` | Ses propres entrées, construites en code | [Référence](reference.md#entrées) |
| `EntryReader` | Ses propres lignes, venues de la configuration, d'un fichier ou d'une base de données, sous la forme des données fournies | [Données](data.md#ajouter-un-événement) |
| `Catalog::shipped($zone, $own)`, `Catalog::with()` | Les entrées fournies et les siennes dans un seul catalogue | [Référence](reference.md#les-questions-du-catalogue) |
| `new Catalog($entries, $zone)` | Seulement les entrées qu'elle choisit : certaines de `ShippedData::entries()`, ou aucune | [Référence](reference.md#les-questions-du-catalogue) |
| `Phil\Almanac\Rule\Rule` | Une règle à elle, implémentée pour `phil/almanac` et donnée à une `Entry` | [Référence](reference.md#entrées) |
| `DataCheck` | La vérification de ses propres lignes avant de les lire | [Données](data.md#ajouter-un-événement) |
| `PromptDay::$index` | L'invite de chaque jour d'une période thématique, que le paquet ne fournit pas | [Référence](reference.md#jours-dinvites) |

Les entrées, les filtres, les occurrences et les jours d'invites sont des valeurs ; le catalogue ne garde aucun état
entre deux questions.
