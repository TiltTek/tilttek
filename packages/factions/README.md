# tilttek/factions

**Track where the party and each character stand with the factions of a campaign: named tiers, ranks, relations
between factions, every change with its reason.**

tilttek/factions keeps the factions of a campaign as its game master runs them: the thieves' guild that likes the party
but hates one of them, the watch at war with the guild, the cult nobody at the table knows about yet. It knows no game
system: the campaign chooses its scale of standings and names its tiers, from three steps either side to a hundred.

- **Standings with the party and with each character.** A whole number on the campaign's scale, read through the tier
  that holds it. A subject no change has touched stands at the start of the scale. A shift moves a standing by steps and
  stops at the ends; a standing may also be set outright.
- **Ranks.** A faction lists its ranks from the lowest to the highest; a character joins at a rank, rises, falls or
  leaves. A rank held by a member cannot be taken from the faction.
- **Relations between factions.** Allied, friendly, neutral, rival, hostile or at war, the same both ways, with a short
  note, each pair once.
- **Every change with its reason.** A standing, a rank or a relation changes only with a reason — the game master's
  words and, when named, the session —, kept in the history in the same write as the roster.
- **Secrets.** A faction or a relation may be secret: the game master sees it, the players do not. A player sees the
  standing of the party and their own, and their own rank, never another character's.
- **Bounded.** 64 factions a roster, 512 relations, 16 ranks, 64 standings and 64 members a faction, 12 tiers a scale
  between -100 and 100.

The service is the only way to change a roster, and only its game master changes it, reads its history and sees its
secrets. Each change is made on the version read, kept by the store, then told to an observer, by identifiers only. The
service keeps no state between calls.

It is a plain PHP library: one service, value objects, ports and a store kept in memory for tests.

## Status

| Part | State |
|---|---|
| `Roster`, `Faction`, `Scale`, `Tier`, `Rank`, `Membership`, `Standing`, `Subject`, `Relation`, `Stance`: the roster and its factions, bounded and checked as a whole | Implemented and tested |
| `Factions`: create, add, change and remove a faction, change the scale, shift or set a standing, set a rank, set and end relations, hand over, delete, read the history | Implemented and tested |
| `Reason` and the history: `History\StandingEntry`, `History\RankEntry`, `History\RelationEntry`, kept with the roster | Implemented and tested |
| `View\RosterView`: what the game master sees, and what a player sees | Implemented and tested |
| `Store\InMemoryRosterStore`: the store of the tests | Implemented and tested |
| A Symfony bundle: the Doctrine store, the clock and the observer wired, Stimulus pages for the game master and the players | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Factions and standings](doc/factions.md) | The roster, the scale and its tiers, standings, ranks, relations, reasons and history, secrets and views. |
| [Extension points](doc/extension-points.md) | The store, the clock, the observer and its events. |
| [Exceptions](doc/exceptions.md) | `FactionsException` and its exceptions. |
| [Testing](doc/testing.md) | The library's tests and benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\Factions\Faction;
use Tilttek\Factions\Factions;
use Tilttek\Factions\Rank;
use Tilttek\Factions\Reason;
use Tilttek\Factions\Relation;
use Tilttek\Factions\Roster;
use Tilttek\Factions\Scale;
use Tilttek\Factions\Stance;
use Tilttek\Factions\Subject;

$factions = new Factions(
    $store,        // your Contract\RosterStore
    $clock,        // a Psr\Clock\ClockInterface
    $observer,     // your Contract\RosterObserver, or null
);

$factions->create(new Roster('campaign-1', 'member-1', Scale::standard(), [
    new Faction('guild', 'The Thieves\' Guild', 'Runs the docks.', [new Rank('cutpurse', 'Cutpurse'), new Rank('fence', 'Fence')]),
    new Faction('watch', 'The City Watch'),
    new Faction('cult', 'The Drowned Choir', secret: true),
]));

$reason = new Reason('Returned the stolen ledger.', 'session-4');
$factions->shift('campaign-1', 'guild', Subject::party(), 1, $reason, 'member-1');
$factions->setRank('campaign-1', 'guild', 'char-2', 'cutpurse', new Reason('Sworn in at the docks.'), 'member-1');
$factions->relate('campaign-1', new Relation('guild', 'watch', Stance::Hostile, 'Bribes stopped.'), $reason, 'member-1');

$view = $factions->playerView('campaign-1', 'char-2');   // no cult; the party's standing and char-2's own
// send $view->toArray() to the page; $factions->history('campaign-1', 'guild', 'member-1') lists the changes
```

## Requirements

PHP 8.5 or later, with `ext-mbstring`, and `psr/clock`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
