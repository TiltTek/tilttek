<!-- phil-dev source-sha256=3f65db5be985058beee68c5cf464be9e5c98399c66c87b46a15de1386f82049b -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `LootEntry` | `final readonly class` | Une entrée : clé, nom, image, emplacement |
| `LootTable` | `final readonly class` | Les entrées lancées avec un seul dé |
| `LootBox` | `final readonly class` | Les tables qu'un produit lance, dans l'ordre |
| `LootCatalog` | `final readonly class` | Les tables et les boîtes d'une application, par clé |
| `Reading\CatalogReader` | `final readonly class` | Un catalogue lu à partir de simples tableaux |
| `Draw\PendingDraw` | `final readonly class` | Un tirage engagé : germe secret et *nonce* ; vérifie le germe du client d'une révélation |
| `Draw\LootDrawer` | `final readonly class` | Révèle une boîte à partir d'un tirage en attente, vérifie une preuve |
| `Draw\RevealedDraw` | `final readonly class` | Un tirage révélé, son résultat et son jet ; sa preuve |
| `Draw\LootResult` | `final readonly class` | Une face et une entrée par table, et leur source |
| `Draw\Pick` | `final readonly class` | La face et l'entrée d'une table |
| `Draw\DrawSource` | `enum` | `Verifiable` ou `Physical` |
| `Code\ShopCode` | `final readonly class` | Un caractère par dé, écrit et lu |
| `Code\DrawText` | `final readonly class` | Les faces et les noms sur une ligne, écrits et lus |
| `Exception\LootException` | `interface` | Chaque exception de la bibliothèque |
| `Exception\InvalidLoot` | `final class` | Une définition refusée |
| `Exception\UnknownLoot` | `final class` | Une boîte ou une table que le catalogue ne contient pas |
| `Exception\InvalidCode` | `final class` | Un code de boutique ou un texte de tirage illisible |
