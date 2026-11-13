# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `Contract\SecretRollStore` | The storage of the journals and of the sealed rolls | [Storing and publishing](storing-and-publishing.md#the-store) |
| `Contract\JournalPublisher` | How the table learns of each entry: Mercure, a WebSocket, a chat bridge | [Storing and publishing](storing-and-publishing.md#publishing) |
| `Tilttek\Dice\DiceRoller` | The notation, the dialects and the limits the rolls are read and checked with | [Installation](installation.md#wiring-the-library) |
| `Random\Randomizer` | The source of the seeds; the system's secure source by default | [Installation](installation.md#wiring-the-library) |

Every point is passed to the constructor of `SecretRolls` or of `RollVerifier`; neither keeps state between two calls.
