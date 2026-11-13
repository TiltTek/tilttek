# Exceptions

Every exception of the library implements `Tilttek\DeckBuilder\Exception\DeckBuilderException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidDeck` | An identifier, a name, a quantity, a card, a format, a taboo list, a deck or a version is refused, or does not read | `A deck holds 1 to 999 copies of the card "fox"; 1000 is not.` |
| `UnknownFormat` | The format catalog does not hold a deck's format | `The format "league" is not in the format catalog.` |
| `UnknownDeck` | A deck, or a version of it, was never saved | `The deck "deck-1" has no version 4.` |
| `DeckConflict` | A version saved first by another request, or a copy given an identifier in use | `The deck "deck-2" already exists: a copy takes a new identifier.` |

`InvalidDeck` extends `InvalidArgumentException`; the others extend `RuntimeException`. A deck that breaks its
format's rules raises nothing: the validator reports it.
