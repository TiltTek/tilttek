<!-- phil-dev source-sha256=94c14f301916cf91bcc1da471e22648755ee6439d61270860f41bb415b1f46a3 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que l'application donne | Où |
|---|---|---|
| `Contract\CardCatalog` | Ses cartes ; `Catalog\InMemoryCardCatalog` les garde en mémoire | [Catalogues et licences](catalogues-and-licences.md) |
| `Contract\FormatCatalog` | Ses formats ; `Catalog\InMemoryFormatCatalog` les garde en mémoire | [Formats et règles](formats-and-rules.md) |
| `Contract\OwnedCards` | Une collection ; `Catalog\OwnedCardList` en lit une depuis un tableau | [Construire et valider des paquets](building-and-validating.md#des-paquets-limités-à-une-collection) |
| `Contract\DeckStore` | Les versions des paquets ; `Store\InMemoryDeckStore` les garde en mémoire | [Historique et copies](history-and-copies.md) |

Chaque point est passé à un constructeur ; `DeckValidator` et `Decks` ne gardent aucun état entre deux appels.

## Implémenter un entrepôt

- `add()` retourne false, sans rien écrire, quand l'entrepôt contient cette version de ce paquet : une clé unique sur
  l'identifiant du paquet et le numéro de la version le garantit.
- `latest()` donne la version la plus haute ; `versions()` les liste de la première à la dernière.
- Une ligne garde `DeckVersion::$deck->toJson()`, la version, `savedAt` en UTC et la note ; `Deck::fromJson()` relit le
  paquet.
