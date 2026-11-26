# tilttek/handouts

**Handouts shared with players during play: revealed to everyone at the table or to some players — now, at a set
moment, or on a cue the game master gives —, concealed again, and what each player has seen of them.**

tilttek/handouts keeps the documents a game master prepares for a table: the letter found on a body, the map of the
mill, the wanted poster. The application creates a handout for a table — a campaign, a session, a game — under a
reference it gives, `handout:4`, with a title and a text, attachments, or both. An attachment is a file of the
application's storage named by its reference, `file:9`, with its media type and a description that stands in for it;
the library never reads, stores nor serves the file, and refuses media types the application does not allow — SVG is
left out by default.

A handout stays concealed until it is **revealed**: to everyone at the table, or to players named by their references.
A reveal can also be **staged** ahead: at a moment, which the application's scheduler releases once it has come, or on
a **cue** — `crypt.opened` — that the game master gives during play to reveal everything staged on it at once. A
handout can be concealed again, from everyone or from some players. Each edit raises its **revision**, and each player
marks what they open: the player's view lists the handouts they see with whether they have not opened them yet, saw an
earlier revision, or saw the current one; the game master's view lists who saw which revision, and when.

It is for a virtual table, a campaign manager or a game site. It is a plain PHP library whose dependencies are
`phil/resource`, the opaque reference of a handout, a table, a player or a file, and `psr/clock`. Players are
references the application gives: the library never reads a name, an e-mail or an account, and its events carry
identifiers only.

## Status

| Part | State |
|---|---|
| `Handout`, `Attachment`, `MediaTypes`: handouts of a table, their text, files and revisions, the media types allowed | Implemented and tested |
| `Handouts`: create, edit, remove, read a table's handouts | Implemented and tested |
| `Audience`, `Disclosure`: reveal to everyone or to some players, conceal, read the audience, `canSee()` | Implemented and tested |
| `Trigger`, `StagedReveal`: stage a reveal at a moment or on a cue, unstage, `releaseDue()`, `cue()` | Implemented and tested |
| `Sighting`, `Visible`, `Freshness`: what each player has seen, the player's view, what is unseen | Implemented and tested |
| `forget()`: a player erased from every table | Implemented and tested |
| `Contract\HandoutStore`, with `Store\InMemoryHandoutStore` | Implemented and tested |
| `Contract\HandoutObserver`, `HandoutChanged`: what is told, identifiers only | Implemented and tested |
| `Limits`: handouts per table, attachments per handout, audiences per handout, reveals per release | Implemented and tested |
| A Symfony bundle: Doctrine store, services, scheduler, live updates, forms and translations | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Handouts](doc/handouts.md) | Creating, editing and removing handouts, their attachments and media types. |
| [Reveals](doc/reveals.md) | Revealing to everyone or to some players, concealing, staging at a moment or on a cue. |
| [What players have seen](doc/sightings.md) | Marking what is opened, the player's view, the game master's view, erasure. |
| [Extension points](doc/extension-points.md) | The store, observers, limits, media types and the clock. |
| [Exceptions](doc/exceptions.md) | `HandoutException` and its exceptions, with the refusals and their messages. |
| [Testing](doc/testing.md) | The library's tests, and testing code that uses handouts. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require tilttek/handouts
```

```php
use Phil\Resource\ResourceReference;
use Tilttek\Handouts\Attachment;
use Tilttek\Handouts\Audience;
use Tilttek\Handouts\Handouts;
use Tilttek\Handouts\Store\InMemoryHandoutStore;
use Tilttek\Handouts\Trigger;

$handouts = new Handouts(new InMemoryHandoutStore(), $clock);

$table = ResourceReference::of('campaign', '3');
$letter = ResourceReference::of('handout', '4');
$map = ResourceReference::of('handout', '5');
$ada = ResourceReference::of('player', '12');

$handouts->create($letter, $table, 'The miller\'s letter', 'Meet me at the mill at midnight.');
$handouts->create($map, $table, 'The crypt', null, [
    new Attachment(ResourceReference::of('file', '9'), 'image/webp', 'A map of the crypt under the mill'),
]);

$handouts->reveal($letter, Audience::players($ada));          // only Ada reads the letter
$handouts->stage($map, Audience::everyone(), Trigger::cue('crypt.opened'));
$handouts->cue($table, 'crypt.opened');                        // everyone sees the map now

$handouts->markSeen($letter, $ada);
$handouts->unseen($table, $ada);                               // the map, not opened yet
```

## Requirements

PHP 8.5 or later, with `ext-mbstring`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
