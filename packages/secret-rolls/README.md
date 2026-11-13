# tilttek/secret-rolls

**Hidden game master rolls, kept verifiable and revealed afterwards.**

A game master rolls out of sight — a perception check, a wandering monster, a trap the players have not found — and the
table sees, at that moment, only a commitment: the SHA-256 of everything the roll is, its secret seed of 32 bytes
included. The result stays sealed in the store. When the game master reveals the roll, during the scene or at its end,
the opening is published, and any player checks on their own that it hashes to the commitment shown earlier and that
rolling the expression again with its seed gives the result revealed: the game master could neither choose the result
nor change it afterwards.

The rolls of a scene form a public journal whose entries are chained by their hashes: a roll committed, a roll
revealed, the scene closed. A closed scene takes no more rolls; the game master reveals the rolls they choose, or all
of them, and the others stay withheld, their commitments kept in the journal, still revealable later. The journal is
exported as JSON, the file a player downloads and checks whole with `SceneJournal::fromJson()->problems()`, or with any
tool that computes SHA-256 and rolls `tilttek/dice` again.

It is for an application where a game master rolls for the table at a distance — a play-by-post game, a virtual
tabletop, a dice tower shown on a stream — and players want to trust the rolls they do not see. It is a plain PHP
library on `tilttek/dice`, which reads the notation and rolls from a seed; the application keeps the journals and the
sealed rolls in its own storage and pushes each entry to the table through a publisher port.

## Status

| Part | State |
|---|---|
| `SecretRolls`: hidden rolls, partial and total reveals, the closing of a scene | Implemented and tested |
| `HiddenRoll`, `RollOpening`, `RollCommitment`: the sealed roll, its opening and its SHA-256 commitment | Implemented and tested |
| `RollVerifier`: an opening checked against its commitment, and rolled again from its seed | Implemented and tested |
| `Journal\SceneJournal`, `Journal\JournalEntry`, `Journal\EntryKind`: the chained public journal, its JSON export and its check | Implemented and tested |
| `Contract\SecretRollStore`, `Contract\JournalPublisher`, `Store\InMemorySecretRollStore`, `Store\NoPublisher` | Implemented and tested |
| Exceptions under one `SecretRollsException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Hidden rolls](doc/hidden-rolls.md) | Rolling out of sight, revealing, closing a scene, what the table sees and when. |
| [Verifying](doc/verifying.md) | The commitment, the journal's hashes, the export, and how a player checks it alone. |
| [Storing and publishing](doc/storing-and-publishing.md) | The store contract, concurrent writers and the publisher port. |
| [Extension points](doc/extension-points.md) | What an application gives the library. |
| [Exceptions](doc/exceptions.md) | `SecretRollsException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, fuzz targets and benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\SecretRolls\SecretRolls;
use Tilttek\SecretRolls\Store\InMemorySecretRollStore;

$rolls = new SecretRolls(new InMemorySecretRollStore());

$hidden = $rolls->roll('scene:12', '1d20+4', roller: 'seat:gm', context: 'Do the guards notice the thief?');
$hidden->opening->totals();                       // [17], for the game master's eyes
$hidden->commitment()->hex;                       // what the table sees now

$rolls->close('scene:12', $hidden->roll());       // reveals it, then closes the scene
$export = $rolls->journal('scene:12')->toJson();  // the file a player checks
```

## Requirements

PHP 8.5 or later; `tilttek/dice` 0.1.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
