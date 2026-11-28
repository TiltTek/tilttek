# tilttek/initiative-cards

**Turn order dealt from a card deck each round: hidden combatants, defeated ones faded and skipped, players ending their
own turns and naming who goes next.**

tilttek/initiative-cards keeps the turn order of a fight in a role-playing game the way a table does with a deck of
cards. At each round, the game master deals a card to every combatant still standing, and the turns follow the cards:
with the 52-card deck and its jokers, a joker first, then the ace down to the two, and two cards of one rank by the order
of the suits — spades, hearts, diamonds, clubs, or another order. A joker shuffles the deck again once its round is over,
and the discard pile is shuffled back whenever the draw pile runs short. A combatant may draw up to three cards and keep
the best.

- **The players end their own turns.** The member who controls the combatant holding the turn ends it — the game master
  may end any — and may name who goes next among those who have not acted, as in a "popcorn" initiative.
- **Hidden combatants.** The game master hides a combatant: the players' view leaves it out until it is revealed.
- **The defeated fade.** A defeated combatant stays in the order, faded, and its turns are skipped until it stands again.
- **Combatants come and go.** One who joins in a round draws at once and waits after the turn in progress, by its card;
  one who leaves discards its card.

The cards, decks and shuffles are those of `tilttek/card-deck`; any deck works with a ranking of its faces written by
the application. The facts of an encounter — started, a round dealt or ended, a turn started or ended, a combatant
joining, leaving, defeated, revived, hidden or revealed, the deck reshuffled, the encounter ended — go to an observer,
each once, by identifiers only.

It is a plain PHP library: one service, value objects, ports and a store kept in memory for tests.

## Status

| Part | State |
|---|---|
| `Encounter`, `Combatant`: an encounter, its piles and its combatants in turn order, bounded and checked | Implemented and tested |
| `InitiativeTracker`: start, deal, end a turn and name the next, join, leave, defeat, revive, hide, reveal, end | Implemented and tested |
| `StandardRanking` and `Contract\CardRanking`: the 52-card deck and its jokers, the order of the suits, other decks | Implemented and tested |
| `View\InitiativeView` and `View\Slot`: what the game master and the players see | Implemented and tested |
| `Store\InMemoryEncounterStore`: the store of the tests | Implemented and tested |
| A Symfony bundle: the Doctrine store, Messenger, Mercure and the Twig components | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Encounters, rounds and turns](doc/encounters.md) | Dealing, the ranking, the reshuffle, turns, the next named, joining, defeat, hidden combatants and views. |
| [Extension points](doc/extension-points.md) | The store, the ranking, the observer and its events, the randomizer. |
| [Exceptions](doc/exceptions.md) | `InitiativeException` and its exceptions. |
| [Testing](doc/testing.md) | The library's tests. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\CardDeck\StandardDeck;
use Tilttek\InitiativeCards\Combatant;
use Tilttek\InitiativeCards\InitiativeTracker;

$tracker = new InitiativeTracker($store, observer: $observer);    // your EncounterStore and InitiativeObserver

$tracker->start('fight-12', 'member-1', StandardDeck::create(jokers: 2), [
    new Combatant('hero-ada', 'member-2'),                        // a character and the player who controls it
    new Combatant('hero-bo', 'member-3', draws: 2),               // draws two cards, keeps the best
    new Combatant('ogre', 'member-1', hidden: true),              // the game master's, unseen by the players
]);

$encounter = $tracker->deal('fight-12', 'member-1');             // round 1: a card each, the turns follow the cards
$current = $encounter->current();                                 // the combatant holding the turn

$tracker->endTurn('fight-12', 'hero-ada', 'member-2', next: 'hero-bo');   // ends Ada's turn, Bo goes next
$tracker->defeat('fight-12', 'ogre', 'member-1');                 // faded, its turns skipped
$view = $tracker->view('fight-12', 'member-3');                   // send $view->toArray() to that player
```

## Requirements

PHP 8.5 or later, with `tilttek/card-deck`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
