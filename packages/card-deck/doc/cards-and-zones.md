# Cards and zones

## Cards and faces

A `Card` has an identity, a `CardId`, and what it shows: one `Face` or several — up to 16, for a card that turns or
transforms — and a back, the `Face` anyone sees when it lies face down. The identity never says what the card shows:
two jokers have two identifiers and one face, and a hidden card is never shown by its identifier.

```php
$card = Card::of('card-17', 'QH');                                     // one face, the back "back"
$moon = new Card(new CardId('m-1'), [new Face('new'), new Face('full')], new Face('night-sky'));
```

Identifiers, face keys, player identifiers and pile names follow one pattern: 1 to 64 ASCII letters, digits, dots,
underscores and hyphens, starting with a letter or a digit. They are opaque: the library compares them and never reads
them. A face key is what the application draws — an image, a text — and a player identifier maps to the application's
accounts: neither ever holds a person's name or address.

## Decks

A `Deck` holds 1 to 1 024 cards, each identifier once, in the order they are declared: the order of the draw pile
before the shuffle that starts a game. `toArray()` and `Deck::fromArray()` write it as plain data and read it back.

`StandardDeck::create()` gives the 52 cards of the French-suited deck — faces `AS` to `KC`, rank `A`, `2` to `10`, `J`,
`Q`, `K` then suit `S`, `H`, `D`, `C` — and as many jokers, `JK`, as asked, with identifiers `card-01` onwards that say
nothing of the face. Any other deck is a list of cards the application declares: the cards of a game of its own, or of
a deck in the public domain.

## Zones

Every card lies in exactly one zone at every moment. A `ZoneId` is written as text:

| Zone | Written | Seen |
|---|---|---|
| The draw pile | `draw` | Always face down; its number of cards only, never its order |
| The discard pile | `discard` | Each card face up or down |
| The table | `table` | Each card face up or down |
| A player's hand | `hand:<player>` | Every card by its player; the cards face up by everyone |
| A named pile | `pile:<name>` | Each card face up or down, for games of free zones — a market, a river, a stock |

The draw pile, the discard pile and the table are open when a game starts; a hand or a named pile opens the first time
a card lands in it, and stays, empty or not. A game opens at most 256 zones. In a zone, the cards are in order, the top
first; a card drawn into a hand goes to its end.

## An empty zone

A zone is empty when it holds no card: `DeckState::isEmpty()` and `count()` say so, whatever the cards are. A draw pile
that holds only jokers is not empty — drawing from it gives a joker — and drawing from an empty one is refused. The
library never reads a face to decide what a zone holds.
