# tilttek/card-deck

**Card decks for online play: shuffled from a verifiable seed, drawn, dealt and discarded, with each player sent only
the cards they may see.**

tilttek/card-deck keeps the cards of a game on the server. A deck is an ordered list of cards; every card lies in one
zone — the draw pile, a player's hand, the discard pile, the table, or a pile the game names — face up or face down.
A card has an identity, separate from what it shows: one face or several, and a back. Every gesture at the table is a
recorded event — shuffle, draw from the top, the bottom or at random, deal face down or face up, discard, flip, turn
to another face, move, collect a zone into another — and replaying the journal of a game from its seed gives the same
state, card for card.

The shuffles and the random draws come from a seed of `tilttek/dice`: its commitment is published when the game is
opened, the players add a seed of their own, and the seed is revealed when the game is finished, so anyone can check
that the deck was not arranged. A player is sent a view of the game, never the game: their own hand, the cards face
up, the number of cards of every zone — and never the order of the draw pile, a hidden card or the seed.

It is for an application that plays card games online — a card game, the initiative cards of a role-playing game, the
games of a game night — and must not trust the browser with what a player may not see. It is a plain PHP library
whose one dependency is `tilttek/dice`, for the seed and its commitment.

## Status

| Part | State |
|---|---|
| `Card`, `Face`, `Deck`, `StandardDeck`: cards of one face or several and a back, decks of up to 1 024 cards, the 52-card deck and its jokers | Implemented and tested |
| `ZoneId`: the draw pile, the discard pile, the table, hands and named piles | Implemented and tested |
| `Gesture\*`: shuffle, draw, deal, discard, flip, turn a face, move, collect, and their codec | Implemented and tested |
| `DeckState`: the state of a game, its gestures, its replay and its fingerprint | Implemented and tested |
| `Shuffler`: the Fisher-Yates shuffle on the randomizer of a seed | Implemented and tested |
| `View\*`: what a player or a spectator may see | Implemented and tested |
| `CardTable`, `GameRecord`, `Reveal`: open, start, play against a version, view, finish and verify a game | Implemented and tested |
| `Contract\GameStore`, `Store\InMemoryGameStore`: the store port, and the store in memory for tests | Implemented and tested |
| A store on Doctrine DBAL, a Symfony bundle, a Mercure push of the views | Not started: adapters of the application, or of a future bundle |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Cards and zones](doc/cards-and-zones.md) | Cards, faces, decks, zones, and an empty zone. |
| [Gestures](doc/gestures.md) | Every gesture, what it does and when it is refused; replaying a journal. |
| [Fairness](doc/fairness.md) | The seed, its commitment, the shuffle, the random draws, and verifying a finished game. |
| [Player views](doc/player-views.md) | What a player sees, what they never see, and how to send it. |
| [Games and stores](doc/games-and-stores.md) | `CardTable`, versions, the store port and writing a store. |
| [Extension points](doc/extension-points.md) | What an application gives the library. |
| [Exceptions](doc/exceptions.md) | `CardDeckException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, its fuzz target and its benchmarks. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```php
use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\CardDeck\CardTable;
use Tilttek\CardDeck\GameId;
use Tilttek\CardDeck\Gesture\Deal;
use Tilttek\CardDeck\Gesture\Discard;
use Tilttek\CardDeck\PlayerId;
use Tilttek\CardDeck\StandardDeck;
use Tilttek\Dice\Fairness\Seed;

$table = new CardTable($store); // your Contract\GameStore
$game = new GameId('g-7f3a');
$alice = new PlayerId('u-101');
$bob = new PlayerId('u-102');

$commitment = $table->open($game, StandardDeck::create(jokers: 2), Seed::generate(new Randomizer(new Secure())));
// Publish $commitment->hex, then let the players choose their seed.
$table->start($game, $clientSeed);

$table->play($game, 1, new Deal([$alice, $bob], 7));
$view = $table->view($game, $alice);           // send $view->toArray() to Alice, and nothing else

$table->play($game, $view->version, new Discard($cardAliceChose));
$reveal = $table->finish($game);               // publish $reveal->toArray(): anyone can verify the game
```

## Requirements

PHP 8.5 or later, and `tilttek/dice` 0.3.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
