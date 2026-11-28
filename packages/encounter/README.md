# tilttek/encounter

**Prepare game encounters balanced by the difficulty rules each game system declares, then hand the combatants to any
initiative tracker.**

tilttek/encounter is the game master's workbench for an encounter before it is played: who takes part, how hard it is
for these characters, and who then acts in which order. It hard-codes no game: the rules of difficulty are those the
game system declares, and the initiative tracker is any the application plugs in.

- **Participants.** Characters, adversaries and allies, each with the rating the system gives it — a level, a
  challenge — the member who plays it, whether the players see it, its initiative bonus, and the identifier of the
  application's record. Several copies of one model take one line: three wolves become `wolves.1` to `wolves.3`.
- **An order set by the game master.** A participant is added last, or right before or after another one, and moves
  the same way: no numbers to keep in sequence.
- **Difficulty by the system's rules.** Each game system declares its rules: by budget — each character brings an amount
  to each band, each adversary costs one, a multiplier by their number — or by level — a budget for a standard party,
  adjusted by its size, and a cost by how far each adversary stands above the party. The assessment gives the cost, the
  band reached, and what may still be added before the next band. The rules are read from the difficulty numbers of
  the `tilttek/rpg-systems` system — those of the SRD 5.2 and Pathfinder Second Edition come with their import
  profiles —, from data the application brings, or written as a class of the application.
- **A hand-off to any tracker.** Launching keeps the encounter launched — it no longer changes — and hands its roster
  to an initiative tracker through a port: a deck of initiative cards, a tracker of rolls. The library depends on none.

The facts of an encounter — prepared, changed, launched, forgotten — go to an observer, each once, by identifiers only.
Every value is bounded: 100 combatants, 12 bands, 1,000 ratings per table.

It is a plain PHP library: one service, two kinds of declared rules and their reader, value objects, ports and a store
kept in memory for tests.

## Status

| Part | State |
|---|---|
| `Participant`, `Encounter`: the participants and the encounter, bounded and checked | Implemented and tested |
| `EncounterPlanner`: prepare, retitle, add, change, move, remove, assess, launch and forget | Implemented and tested |
| `Difficulty\BudgetRules`, `Difficulty\LevelRules` and `Difficulty\Declaration`: the rules a system declares | Implemented and tested |
| `Initiative\InitiativeRoster` and the port `Contract\InitiativeHandOff` | Implemented and tested |
| `Store\InMemoryEncounterStore`: the store of the tests | Implemented and tested |
| A Symfony bundle: the Doctrine store, Messenger messages and the screens of the game master | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Encounters and participants](doc/encounters.md) | Preparing an encounter, its participants, their order, launching and forgetting. |
| [Difficulty](doc/difficulty.md) | The rules a system declares, by budget or by level, their data, and the assessment. |
| [Initiative](doc/initiative.md) | The roster handed to a tracker, and an adapter for `tilttek/initiative-cards`. |
| [Extension points](doc/extension-points.md) | The store, the observer and its events, the rules of difficulty and the tracker. |
| [Exceptions](doc/exceptions.md) | `EncounterException` and its exceptions. |
| [Testing](doc/testing.md) | The library's tests and its fuzz target. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\Encounter\Difficulty\Declaration;
use Tilttek\Encounter\EncounterPlanner;
use Tilttek\Encounter\Kind;
use Tilttek\Encounter\Participant;
use Tilttek\Encounter\Rulebooks;

$rulebooks = Rulebooks::fromSystems($systems);                      // the difficulty numbers of each system $rulebooks
= Rulebooks::fromSystems($systems, Declaration::RECORD, Declaration::read($rulesOfMySystem)); // and data of its own
$planner = new EncounterPlanner($store, $rulebooks, $observer, $handOff);

$planner->prepare('ambush', 'gm-1', 'my-system', 'The ambush on the road', [
    new Participant('ayla', Kind::Character, '3', 'player-4', initiativeBonus: 2, ref: 'sheet:12'),
    new Participant('bran', Kind::Character, '3', 'player-5', ref: 'sheet:13'),
]);
$planner->add('ambush', 'gm-1', new Participant('wolves', Kind::Adversary, '1', 'gm-1', 3, ref: 'stat:wolf'));
$planner->add('ambush', 'gm-1', new Participant('alpha', Kind::Adversary, '3', 'gm-1', hidden: true), before: 'wolves');

$assessment = $planner->assess('ambush');
// $assessment->difficulty is the band reached; $assessment->remainingTo('high'), what may still be added before it

$roster = $planner->launch('ambush', 'gm-1');   // handed to the tracker; the encounter no longer changes
```

## Requirements

PHP 8.5 or later, with `tilttek/rpg-systems`, whose game systems the rulebooks are found by.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
