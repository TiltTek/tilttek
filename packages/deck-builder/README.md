# tilttek/deck-builder

**Card decks built to a format's declared rules — sizes, copies, banned and restricted cards, dated taboo lists —,
validated with every violation readable, versioned with their differences, copied with a link to the original and
limited to the cards a collection owns, with no publisher's catalogue bundled.**

A format declares its rules: the smallest and the largest deck, the copies allowed of each card, the cards it bans or
restricts, and taboo lists that change them from a given day. The library checks a deck against the rules that apply
on the day asked — a tournament's day, or today — and returns every violation at once, each with a sentence people
read and the values it was built from: *The deck has 3 copies of "Ember Fox" (fox); the taboo list "autumn" from
2026-10-01 restricts it to 2.* Given a collection, it also checks, or trims, the deck to the copies the collection
owns.

Each change to a deck is saved as a new version, never rewritten: the history lists them and tells the differences
between two of them. A copy starts a new deck that links to the version it was copied from.

The cards, the formats and the collections are the application's, behind ports: the library ships no card data. It is
a plain PHP library, with no dependency beyond PHP, in-memory adapters for its ports, and nothing kept between two
calls.

## Status

| Part | State |
|---|---|
| `Format`, `TabooList`, `Ruling`: sizes, copies, banned and restricted cards, dated taboo lists, read from and written to arrays and JSON | Implemented and tested |
| `Deck`, `DeckReference`: cards in the order they were added, changes by new decks, copies, limits to a collection, read from and written to arrays and JSON | Implemented and tested |
| `DeckValidator`, `Validation`, `Violation`, `ViolationType`: every violation, readable, on a given day, against a collection | Implemented and tested |
| `Decks`, `DeckVersion`, `DeckDiff`: versions never rewritten, history, differences, copies | Implemented and tested |
| `Contract\CardCatalog`, `Contract\FormatCatalog`, `Contract\OwnedCards`, `Contract\DeckStore` and their in-memory adapters | Implemented and tested |
| Card data of any game | Not provided: the application provides its own, as [Catalogues and licences](#catalogues-and-licences) says |
| A database adapter | Not provided: an application implements `Contract\DeckStore` |

## Catalogues and licences

The library imports no publisher's catalogue, and ships no card list, no format and no taboo list of a published
game. A card catalog is data its publisher owns: an application provides its own — cards it wrote, invented cards, or
cards under a licence it holds that allows this use —, through `Contract\CardCatalog` and `Contract\FormatCatalog`.
The examples and tests use invented cards only. [Catalogues and licences](doc/catalogues-and-licences.md) says how an
application provides its catalog.

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Formats and rules](doc/formats-and-rules.md) | Sizes, copies, banned and restricted cards, taboo lists, and the rules of a day. |
| [Building and validating decks](doc/building-and-validating.md) | Decks, the validator, its violations and collections. |
| [History and copies](doc/history-and-copies.md) | Versions, the history, differences and copies. |
| [Catalogues and licences](doc/catalogues-and-licences.md) | Why no catalogue is bundled, and how an application provides its own. |
| [Extension points](doc/extension-points.md) | What an application gives the library. |
| [Exceptions](doc/exceptions.md) | `DeckBuilderException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, fuzz targets and benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\DeckBuilder\{Card, Deck, DeckValidator, Decks, Format, TabooList};
use Tilttek\DeckBuilder\Catalog\{InMemoryCardCatalog, InMemoryFormatCatalog, OwnedCardList};
use Tilttek\DeckBuilder\Store\InMemoryDeckStore;

$validator = new DeckValidator(
    new InMemoryCardCatalog(new Card('fox', 'Ember Fox'), new Card('den', 'Fox Den', copyLimit: 1)),
    new InMemoryFormatCatalog(new Format('league', 'League', minSize: 40, maxSize: 60, copies: 3,
        tabooLists: [new TabooList('autumn', new DateTimeImmutable('2026-10-01'), restricted: ['fox' => 2])],
    )),
);

$deck = new Deck('deck-1', 'Foxes', 'league')->withCard('fox', 3)->withCard('den', 2);
$validation = $validator->validate($deck, new DateTimeImmutable('2026-11-14'), new OwnedCardList(['fox' => 3]));
$validation->isValid();    // false
$validation->messages();   // "The deck has 5 cards; the format "league" needs at least 40.", …

$decks = new Decks(new InMemoryDeckStore());
$decks->save($deck, new DateTimeImmutable(), 'first draft');               // version 1
$decks->save($deck->withoutCard('den'), new DateTimeImmutable());          // version 2
$decks->diff('deck-1', 1, 2)->lines();                                     // ['den: 2 → 1']
$decks->copy('deck-1', 'deck-2', 'My foxes', new DateTimeImmutable());     // links to deck-1, version 2
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
