# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep the encounters | Implement `Contract\EncounterStore`, and pass it to `InitiativeTracker`. |
| Order the cards of another deck | Implement `Contract\CardRanking`, and pass it to `InitiativeTracker`. |
| Act on what happened | Implement `Contract\InitiativeObserver`, and pass it to `InitiativeTracker`. |
| Give the shuffles their randomness | Pass a `Random\Randomizer`. |

## The store

`Contract\EncounterStore` keeps the encounters, each at a version:

- `encounter()` gives one by its identifier, or null;
- `save()` keeps one at the next version and returns it. One at version 0 is new, and the store has none of its
  identifier; any other is kept only when the store holds it at that version, and `EncounterConflict` is raised
  otherwise, nothing kept.

Two players ending turns at once both read version 4: the first save keeps version 5, and the second is refused with
`EncounterConflict`; the application reads the encounter anew, and the turn the second player meant to end is no longer
the current one.

`Store\InMemoryEncounterStore` keeps them in memory, as long as the object lasts: it is for tests. An application keeps
them in its database. An encounter holds strings, integers, booleans, the lists of card identifiers of its piles, its
combatants — strings, an integer and booleans each — and its deck, which `Deck::toArray()` writes and `Deck::fromArray()`
reads back.

## The ranking

`Contract\CardRanking` orders the faces of a deck:

- `ranks()` says whether it knows a face: an encounter starts only on a deck whose every face it knows;
- `compare()` is below zero when the first face acts before the second, above zero when after, zero when they tie;
- `reshuffles()` says whether dealing the face shuffles the discard pile back before the next round.

The tracker reads the first face of each card. A deck of numbered cards, the highest first:

```php
use Override;
use Tilttek\CardDeck\Face;
use Tilttek\InitiativeCards\Contract\CardRanking;

final readonly class NumberedRanking implements CardRanking
{
    #[Override]
    public function ranks(Face $face): bool
    {
        return 1 === preg_match('/^n([1-9][0-9]?)$/D', $face->key);
    }

    #[Override]
    public function compare(Face $one, Face $other): int
    {
        return (int) substr($other->key, 1) <=> (int) substr($one->key, 1);
    }

    #[Override]
    public function reshuffles(Face $face): bool
    {
        return 'n1' === $face->key;
    }
}
```

## The observer and its events

`Contract\InitiativeObserver::notify()` is told each fact once the encounter is kept, by identifiers only:

| Event | When | Carries |
|---|---|---|
| `Event\EncounterStarted` | An encounter was started. | |
| `Event\RoundDealt` | A round was dealt. | the round |
| `Event\DeckReshuffled` | The discard pile was shuffled back into the draw pile. | |
| `Event\TurnStarted` | A combatant's turn started. | the combatant, whether it is hidden |
| `Event\TurnEnded` | A combatant's turn ended. | the combatant |
| `Event\RoundEnded` | Every combatant standing has acted. | the round |
| `Event\CombatantJoined` | A combatant joined. | the combatant, whether it is hidden |
| `Event\CombatantLeft` | A combatant left. | the combatant |
| `Event\CombatantDefeated` | A combatant was defeated. | the combatant |
| `Event\CombatantRevived` | A defeated combatant stands again. | the combatant |
| `Event\CombatantRevealed` | A hidden combatant was revealed. | the combatant |
| `Event\CombatantHidden` | A combatant was hidden. | the combatant |
| `Event\EncounterEnded` | The encounter was ended. | |

Every event implements `Event\InitiativeEvent`, whose `encounter` is an identifier; the events of one combatant implement
`Event\CombatantEvent`, which adds `combatant`. A change comes before the turn it passes on: `TurnEnded`, then
`TurnStarted` or `RoundEnded`. An application sends a message from them — through Messenger, with the identifiers alone —
and reads the rest from the store: a push of each member's view after every event, a notice to the player whose turn
starts unless the combatant is hidden.

## The randomizer

The shuffles of `start()`, `deal()` and `join()` use the `Random\Randomizer` given, through the Fisher-Yates shuffle of
`tilttek/card-deck`. By default it is one on the `Secure` engine. A test gives one on a seeded engine, such as
`Mt19937`, to repeat its shuffles.
