# Building and validating decks

## A deck

A `Deck` holds its identifier, its name, the code of the format it is built for, and its cards: up to 1 000 distinct
cards, each with 1 to 999 copies, in the order they were added. A deck never changes; each change gives a new deck:

| Method | Gives |
|---|---|
| `withCard($card, $quantity = 1)` | More copies of a card, added at the end when the deck did not hold it |
| `withoutCard($card, $quantity = 1)` | Fewer copies; none left removes the card |
| `withQuantity($card, $quantity)` | That many copies, in the card's place; 0 removes it |
| `renamed($name)` | Another name |
| `limitedTo($owned)` | No more copies of each card than the collection owns |
| `copiedAs($id, $name, $original)` | A copy linked to its original; `Decks::copy()` calls it |

`size()` counts every copy; `quantityOf($card)` gives one card's. A deck holds any card identifier: whether the card
exists and is allowed is the validator's question, so that a deck being built is never refused for a card removed
since.

`Deck::fromArray()` and `fromJson()` read what `toArray()` and `toJson()` write — a database column, a form, an
export —, every fault an `InvalidDeck`.

## The validator

`DeckValidator::validate($deck, $on, $owned = null)` checks a deck against the rules of its format on that day:

1. the size of the deck, against the smallest and the largest deck;
2. each card, in the deck's order: unknown to the card catalog, banned, restricted below its quantity, or beyond its
   copies;
3. each card the catalog knows, against the copies the collection owns, when a collection is given.

Every violation is returned, none stops the others. A format the format catalog does not hold throws `UnknownFormat`:
the deck cannot be checked at all.

`Validation` gives `isValid()`, `messages()` — the sentences in their order — and `ofType()`, with the deck, the
`Ruling` applied and the day. Each `Violation` carries its `ViolationType`, its message, the card it is about and the
values the message was built from, for an application that writes its own messages or translates them.

| `ViolationType` | Message, for instance | Values |
|---|---|---|
| `TooFewCards` | `The deck has 38 cards; the format "league" needs at least 40.` | `size`, `limit` |
| `TooManyCards` | `The deck has 61 cards; the format "league" allows at most 60.` | `size`, `limit` |
| `Banned` | `"Old Relic" (old-relic) is banned by the format "league".` | `card`, `quantity`, `by` |
| `Restricted` | `The deck has 2 copies of "Storm Call" (storm-call); the format "league" restricts it to 1.` | `card`, `quantity`, `limit`, `by` |
| `TooManyCopies` | `The deck has 4 copies of "Ember Fox" (ember-fox); at most 3 are allowed.` | `card`, `quantity`, `limit` |
| `UnknownCard` | `The card "ghost" is not in the card catalog.` | `card`, `quantity` |
| `NotOwned` | `The deck has 3 copies of "Ember Fox" (ember-fox); the collection owns 2.` | `card`, `quantity`, `owned` |

## Decks limited to a collection

A collection is the application's: a member's shelf, a club's boxes, what a member brought to the table. It reaches
the library through `Contract\OwnedCards`, which tells the copies owned of one card; `Catalog\OwnedCardList` holds a
collection read once from an array. The validator reports each card beyond the copies owned; `Deck::limitedTo()` gives
the deck trimmed to them, for a deck to play with what is at hand.
