<!-- phil-dev source-sha256=8cd918a8f768bb9fb4e43352f804f35b4630c20df07aea4f910a11fb016ef639 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\DeckBuilder\Exception\DeckBuilderException`.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidDeck` | Un identifiant, un nom, une quantité, une carte, un format, une liste tabou, un paquet ou une version est refusé, ou ne se lit pas | `A deck holds 1 to 999 copies of the card "fox"; 1000 is not.` |
| `UnknownFormat` | Le catalogue de formats ne contient pas le format d'un paquet | `The format "league" is not in the format catalog.` |
| `UnknownDeck` | Un paquet, ou une de ses versions, n'a jamais été enregistré | `The deck "deck-1" has no version 4.` |
| `DeckConflict` | Une version enregistrée d'abord par une autre requête, ou une copie dont l'identifiant est déjà pris | `The deck "deck-2" already exists: a copy takes a new identifier.` |

`InvalidDeck` étend `InvalidArgumentException` ; les autres étendent `RuntimeException`. Un paquet qui enfreint les
règles de son format ne lève rien : le validateur le signale.
