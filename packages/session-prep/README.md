# tilttek/session-prep

**Prepare a game session: scenes, secrets and their clues, NPCs, places, notes and a checklist, with what the table
reveals recorded and leftovers carried over.**

tilttek/session-prep keeps the game master's preparation of one session of a role-playing campaign. A
**preparation** holds:

- **the scenes that might happen** — a title, what the game master needs to run them, the non-player characters and
  places they feature — in the order the game master gives them;
- **secrets and their clues** — what the players may learn, tied to no scene so that it comes out wherever it fits,
  and the clues that lead to it;
- **the non-player characters and the places** of the session, each with the identifier of its entry elsewhere — a
  campaign wiki, a map — when it has one;
- **free notes**, and **a checklist** of the steps of a preparation, some of them required before it is ready. A
  standard checklist goes through the usual steps — the recap, the opening, the scenes, the secrets, the places, the
  characters, the threats, the rewards — each named by what it is for.

A preparation is linked to a session of the campaign's schedule by its identifier only: the library depends on no
scheduler. Every list and every text is bounded, and every identifier is checked.

At the table, the game master marks a scene played or skipped, a secret revealed, a clue found, an item checked; each
fact is kept and told once. A draft is marked ready once its required items are checked, then closed when the session
is over. The secrets not revealed and the scenes not played are carried into the next preparation, with the characters
and places those scenes feature.

The facts of a preparation — created, changed, linked to a session, a scene, a secret, a clue or an item marked, ready,
reopened, closed, carried over, deleted — go to an observer, each once, by identifiers only: the application sends its
messages from them, such as a secret revealed shared with the players' knowledge of the campaign.

It is a plain PHP library: one service, value objects, ports and a store kept in memory for tests.

## Status

| Part | State |
|---|---|
| `Preparation` and its parts: scenes, secrets and clues, characters, places, notes and checklist, bounded and checked | Implemented and tested |
| `ChecklistTemplate`: the standard steps of a preparation, or an application's own | Implemented and tested |
| `SessionPreparer`: create, change, link to a session, mark the facts, ready, close, reopen, carry over and delete | Implemented and tested |
| `PreparationQuery`, `Contract\PreparationStore` and `Store\InMemoryPreparationStore` | Implemented and tested |
| A Symfony bundle: the Doctrine store, Messenger, the forms and the Twig components | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Preparations](doc/preparations.md) | A preparation and its parts, their bounds, and how to change them. |
| [At the table](doc/at-the-table.md) | The service: creating, changing, linking, marking the facts, ready, closed, carried over. |
| [Extension points](doc/extension-points.md) | The store, the observer and its events, the templates. |
| [Exceptions](doc/exceptions.md) | `SessionPrepException` and its exceptions. |
| [Testing](doc/testing.md) | The library's tests and benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\SessionPrep\ChecklistTemplate;
use Tilttek\SessionPrep\Clue;
use Tilttek\SessionPrep\NonPlayerCharacter;
use Tilttek\SessionPrep\Place;
use Tilttek\SessionPrep\Preparation;
use Tilttek\SessionPrep\Scene;
use Tilttek\SessionPrep\SceneStatus;
use Tilttek\SessionPrep\Secret;
use Tilttek\SessionPrep\SessionPreparer;

$preparer = new SessionPreparer($store, $observer);       // your PreparationStore, and an observer or nothing

$preparation = ChecklistTemplate::standard()->applyTo(new Preparation(
    'prep-12',
    'campaign-7',
    'member-1',                                             // the game master
    'The drowned bell',
    session: 'session-31',                                  // the session it is for, by its identifier
    scenes: [new Scene('docks', 'The docks at night', 'Fog, a bell under the water.', ['mara'], ['pier'])],
    secrets: [new Secret('heir', 'The harbour master is the heir.', [new Clue('letter', 'A letter in her desk')])],
    characters: [new NonPlayerCharacter('mara', 'Mara', 'Wants the ledger back')],
    places: [new Place('pier', 'The old pier')],
));

$preparer->create($preparation);
$preparer->markItem('prep-12', 'opening', true, 'member-1');

// At the table:
$preparer->markScene('prep-12', 'docks', SceneStatus::Played, 'member-1');
$preparer->markClue('prep-12', 'heir', 'letter', true, 'member-1');

// After the session:
$preparer->close('prep-12', 'member-1');
$preparer->carryOver('prep-12', 'prep-13', 'member-1');   // the secret, still hidden, into the next preparation
```

## Requirements

PHP 8.5 or later, with `ext-mbstring`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
