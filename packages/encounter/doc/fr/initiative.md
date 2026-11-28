<!-- phil-dev source-sha256=04fe4a7f414a024204a169195b2f9fb4d619f79ffd14fac1c54f8a23b904f3f0 -->
# Initiative

Une fois préparée, une rencontre se joue dans un suivi d'initiative : un jeu de cartes d'initiative, un suivi de jets,
une liste que lit le meneur de jeu. La bibliothèque n'en dépend d'aucun ; elle remet au suivi une
`Initiative\InitiativeRoster` par le port `Contract\InitiativeHandOff`.

## La liste

`InitiativeRoster` contient l'identifiant de la rencontre, son meneur de jeu, son système de jeu, et une
`Initiative\RosterEntry` pour chaque combattant, dans l'ordre que le meneur de jeu a fixé — les copies d'un participant
l'une après l'autre :

| Valeur | Ce que c'est |
|---|---|
| `combatant` | L'identifiant du combattant : celui du participant, ou `wolves.2` pour une copie. |
| `participant` | L'identifiant du participant dont il est une copie. |
| `kind` | Personnage, adversaire ou allié. |
| `controller` | Le membre qui termine ses tours. |
| `hidden` | Si les joueurs ne le voient pas. |
| `initiativeBonus` | Ce qu'il ajoute à son initiative, ou null. |
| `ref` | L'identifiant de la fiche de l'application, ou null. |

Le suivi décide de l'ordre des tours : par les cartes distribuées, par des jets avec le bonus, ou tel que donné.
`toArray()` donne la liste en données simples.

## Lancer

`EncounterPlanner::launch()` garde d'abord la rencontre lancée — de sorte que deux lancements simultanés n'en font
qu'un, le second refusé avec `EncounterConflict` — puis remet la liste au suivi, annonce `EncounterLaunched` et retourne
la liste. Quand le suivi n'est pas joint, la rencontre est lancée quand même : `roster()` redonne la liste, pour la
remettre une fois que le suivi répond. Sans remise, `launch()` ne fait que retourner la liste.

## Un adaptateur pour tilttek/initiative-cards

`tilttek/initiative-cards` distribue une carte à chaque combattant à chaque ronde. Son adaptateur tient en quelques
lignes de l'application :

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

Le suivi refuse une rencontre qu'il mène déjà : remettre la même liste est donc sans danger. Un suivi de jets lit
`initiativeBonus` au lieu de distribuer des cartes.
