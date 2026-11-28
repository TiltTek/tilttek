# Initiative

Once prepared, an encounter is played in an initiative tracker: a deck of initiative cards, a tracker of rolls, a list
the game master reads from. The library depends on none; it hands the tracker an `Initiative\InitiativeRoster` through
the port `Contract\InitiativeHandOff`.

## The roster

`InitiativeRoster` holds the encounter's identifier, its game master, its game system, and an `Initiative\RosterEntry`
for each combatant, in the order the game master set — the copies of a participant one after the other:

| Value | What it is |
|---|---|
| `combatant` | The combatant's identifier: the participant's, or `wolves.2` for a copy. |
| `participant` | The identifier of the participant it is a copy of. |
| `kind` | Character, adversary or ally. |
| `controller` | The member who ends its turns. |
| `hidden` | Whether the players do not see it. |
| `initiativeBonus` | What it adds to its initiative, or null. |
| `ref` | The identifier of the application's record, or null. |

The tracker decides the order of the turns: by the cards dealt, by rolls with the bonus, or as given. `toArray()` gives
the roster as plain data.

## Launching

`EncounterPlanner::launch()` keeps the encounter launched first — so that two launches at once are one, the second
refused with `EncounterConflict` — then hands the roster to the tracker, tells `EncounterLaunched`, and returns the
roster. When the tracker is not reached, the encounter is launched all the same: `roster()` gives the roster again, to
hand it once the tracker answers. Without a hand-off, `launch()` only returns the roster.

## An adapter for tilttek/initiative-cards

`tilttek/initiative-cards` deals a card to each combatant in each round. Its adapter is a few lines of the application:

```php
use Tilttek\CardDeck\Deck;
use Tilttek\Encounter\Contract\InitiativeHandOff;
use Tilttek\Encounter\Initiative\InitiativeRoster;
use Tilttek\Encounter\Initiative\RosterEntry;
use Tilttek\InitiativeCards\Combatant;
use Tilttek\InitiativeCards\InitiativeTracker;

final readonly class InitiativeCardsHandOff implements InitiativeHandOff
{
    public function __construct(private InitiativeTracker $tracker, private Deck $deck)
    {
    }

    public function handOff(InitiativeRoster $roster): void
    {
        $this->tracker->start($roster->encounter, $roster->gameMaster, $this->deck, array_map(
            static fn (RosterEntry $entry): Combatant => new Combatant($entry->combatant, $entry->controller, $entry->hidden),
            $roster->entries,
        ));
    }
}
```

The tracker refuses an encounter it already runs, so handing the same roster again is safe. A tracker of rolls reads
`initiativeBonus` instead of dealing cards.
