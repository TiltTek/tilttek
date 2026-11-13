<!-- phil-dev source-sha256=6dd400c727681185654553417004ddb9490b138ed1ab78b191563b0919bc5bc0 -->
# Référence

| Type | Rôle |
|---|---|
| `Card` | Une carte telle que le catalogue de cartes la donne : identifiant, nom et son propre nombre d'exemplaires |
| `Format` | Les règles déclarées d'un format : tailles, exemplaires, cartes bannies et restreintes, listes tabou ; lu et écrit en tableaux et en JSON |
| `TabooList` | Des cartes bannies ou restreintes à partir d'un jour |
| `Ruling` | Les règles d'un format un jour donné : ce qui bannit ou restreint une carte, et ses exemplaires permis |
| `Deck` | Un paquet : ses cartes dans l'ordre, son format et son original ; changements par de nouveaux paquets ; lu et écrit en tableaux et en JSON |
| `DeckReference` | Une version d'un paquet, l'original auquel une copie renvoie |
| `DeckValidator` | Vérifie un paquet selon son format un jour donné, et selon une collection |
| `Validation` | Chaque violation d'un paquet, avec la règle appliquée et le jour |
| `Violation` | Une règle qu'un paquet enfreint, avec son message et ses valeurs |
| `ViolationType` | Les sortes de violations |
| `Decks` | Enregistre les versions, donne l'historique et les différences, copie les paquets |
| `DeckVersion` | Une version enregistrée d'un paquet, avec son moment en UTC et sa note |
| `DeckDiff` | Les différences entre deux paquets |
| `Identifiers` | Les formes des codes, des identifiants et des noms |
| `Contract\CardCatalog`, `Contract\FormatCatalog` | Où l'application garde ses cartes et ses formats |
| `Contract\OwnedCards` | Les exemplaires de chaque carte que possède une collection |
| `Contract\DeckStore` | Où les versions des paquets sont gardées |
| `Catalog\InMemoryCardCatalog`, `Catalog\InMemoryFormatCatalog`, `Catalog\OwnedCardList` | Les catalogues et une collection en mémoire |
| `Store\InMemoryDeckStore` | Les versions des paquets en mémoire |
| `Exception\DeckBuilderException` | L'interface de chaque exception de la bibliothèque |
| `Exception\InvalidDeck`, `UnknownFormat`, `UnknownDeck`, `DeckConflict` | Voir [Exceptions](exceptions.md) |

`ArrayInput` est interne.
