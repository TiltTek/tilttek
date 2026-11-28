# tilttek/downtime

**Track what characters do between sessions: activities the game system declares, with their days, costs and
outcomes, in a log per character.**

tilttek/downtime keeps the time between two sessions of a campaign. A player chooses what their character does —
crafts an item, researches a rumour, trains a skill, carouses in town —; the game master advances the campaign by so
many days; each activity whose days are spent ends with an outcome, which the game master confirms. It carries no rule
of any game system: the rules of a system declare the activities, and the library applies them.

- **Activities declared as data.** An activity takes 1 to 365 days, costs resources once or for each day, and ends with
  one of its outcomes: always the same one, or the one of the band its roll falls in. Each outcome lists its effects
  on the resources the system names.
- **A queue per character.** Up to 8 open activities a character, worked on in the order chosen. An advance spends its
  days on each queue: the days one activity does not need go to the next.
- **Rolls from the table or from a roller.** An activity whose days are spent ends with its outcome when it is fixed,
  or when a roller rolls its dice; otherwise it waits for the total rolled at the table.
- **The game master decides.** They confirm the outcome, or choose another, and may cancel an open activity; a player
  cancels theirs while it is queued. The application debits the costs and applies the effects when it is told.
- **A log that does not change with the rules.** Each entry keeps the activity as it was declared when it was chosen.
- **Bounded.** 64 activities a system, 16 outcomes and 16 bands an activity, 8 costs and 8 effects, 1000 entries a
  read, 365 days an advance.

The service is the only way to change an entry. Each change is made on the version read, kept by the store — an advance
keeps every entry it changes, or none —, then told to an observer, by identifiers only. The service keeps no state
between calls.

It is a plain PHP library: one service, value objects, ports, a rule book and a store kept in memory.

## Status

| Part | State |
|---|---|
| `Activity`, `Cost`, `Outcome`, `Effect`, `Resolution`, `Band`, `SystemRules`: the activities a system declares, bounded and checked as a whole | Implemented and tested |
| `Entry` and `Stage`: an activity in the log of a character, from queued to confirmed or cancelled | Implemented and tested |
| `Downtime`: choose, advance a campaign, record a roll, confirm, cancel, read a log | Implemented and tested |
| `Contract\Roster`, `Contract\Roller`, `Contract\RuleBook`, `Contract\EntryStore` and `Contract\DowntimeObserver` | Implemented and tested |
| `Rules\InMemoryRuleBook` and `Store\InMemoryEntryStore` | Implemented and tested |
| A Symfony bundle: the Doctrine store, the rules read from configuration, the forms and a Stimulus log | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Downtime](doc/downtime.md) | Activities, costs, outcomes and resolutions, the queue, advances, rolls, confirmation and the log. |
| [Extension points](doc/extension-points.md) | The store, the rule book, the roster, the roller, the observer and its events. |
| [Exceptions](doc/exceptions.md) | `DowntimeException` and its exceptions. |
| [Testing](doc/testing.md) | The library's tests and benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\Downtime\Activity;
use Tilttek\Downtime\Band;
use Tilttek\Downtime\Cost;
use Tilttek\Downtime\Downtime;
use Tilttek\Downtime\Effect;
use Tilttek\Downtime\Outcome;
use Tilttek\Downtime\Resolution;
use Tilttek\Downtime\Rules\InMemoryRuleBook;
use Tilttek\Downtime\SystemRules;

$rules = new InMemoryRuleBook(new SystemRules('fantasy', [
    new Activity('crafting', 'Crafting', 5, [
        new Outcome('spoiled', 'The materials are spoiled'),
        new Outcome('made', 'The item is made', [new Effect('items', 1)]),
    ], Resolution::rolled('1d20+2', [new Band(1, 9, 'spoiled'), new Band(10, 30, 'made')]), [Cost::perDay('gp', 5)]),
    new Activity('training', 'Training', 10, [
        new Outcome('learned', 'A new skill', [new Effect('skills', 1)]),
    ], Resolution::fixed('learned'), [new Cost('gp', 25)]),
]));

$downtime = new Downtime(
    $store,      // your Contract\EntryStore
    $rules,      // the Rules\InMemoryRuleBook above, or your Contract\RuleBook
    $roster,     // your Contract\Roster: who runs the campaign, who plays each character
    null,        // a Contract\Roller, or null to record the rolls made at the table
    $observer,   // your Contract\DowntimeObserver, or null
);

$downtime->choose('entry-1', 'campaign-1', 'character-1', 'fantasy', 'crafting', 'member-2'); // the player
$downtime->advance('campaign-1', 7, 'member-1');                                               // the game master
$downtime->record('entry-1', 14, 'member-2');            // 14 falls in 10–30: "made"
$downtime->confirm('entry-1', 'member-1');               // the observer is told ActivityConfirmed

$log = $downtime->log('campaign-1', 'character-1', 'member-2');
```

## Requirements

PHP 8.5 or later, with `ext-mbstring`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
