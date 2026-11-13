# Reference

| Type | Role |
|---|---|
| `SecretRolls` | Rolls out of sight, reveals, closes a scene, gives the journal and the sealed rolls |
| `HiddenRoll` | A sealed roll: its opening, who rolled it and when; written and read back as plain values |
| `RollOpening` | Everything a roll is: identifier, scene, position, expression, rounding, seed, result and context |
| `RollCommitment` | The SHA-256 of an opening, compared in constant time |
| `RollVerifier` | Checks an opening against its commitment and rolls it again from its seed |
| `Journal\SceneJournal` | The public journal of a scene: its state, its withheld rolls, its check and its JSON export |
| `Journal\JournalEntry` | One entry of the journal, chained to the one before by its hash |
| `Journal\EntryKind` | `Committed`, `Revealed` or `Closed` |
| `Contract\SecretRollStore` | Where the journals and the sealed rolls are kept |
| `Contract\JournalPublisher` | Told of each entry appended |
| `Store\InMemorySecretRollStore` | The store in memory, for tests and scripts |
| `Store\NoPublisher` | The publisher that pushes nothing |
| `Exception\SecretRollsException` | The interface of every exception of the library |
| `Exception\InvalidSecretRoll` | A value or a document the library refuses |
| `Exception\SecretRollRefused` | A step the journal of a scene does not allow |
| `Exception\JournalConflict` | Two writers appended to a journal at once |

`Encoding` and `Instant` are internal: the checks, the canonical JSON and the one way the library writes a time.
