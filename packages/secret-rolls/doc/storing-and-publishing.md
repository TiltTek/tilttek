# Storing and publishing

## The store

`Contract\SecretRollStore` keeps the journals of the scenes and the sealed rolls:

| Method | Does |
|---|---|
| `entries($scene)` | The entries of the scene's journal, by position; none for a scene without any |
| `commit($entry, $roll)` | Keeps the hidden roll sealed and appends the entry of its commitment, both or neither |
| `append($entry)` | Appends an entry that reveals a roll or closes the scene |
| `hidden($roll)` | The sealed roll of this identifier, or null |

The journal only grows: an entry is appended at the position after the last one, never changed nor removed. A store
throws `JournalConflict` when the entry does not come at that position — two writers appended at once —; the caller
reads the journal again and retries. On a database, a unique key on the scene and the position gives that guarantee,
and `commit()` writes the entry and the sealed roll in one transaction.

The sealed rolls are the store's secret: whoever reads them knows the hidden results before the reveal. Keep them
apart from what the table reads, encrypted at rest when the storage is shared. `HiddenRoll::toArray()` and
`HiddenRoll::fromArray()` give the plain values to keep; `JournalEntry::toArray()` and `JournalEntry::fromArray()` those
of an entry, its hash kept as written.

`Store\InMemorySecretRollStore` keeps everything in the process, for tests and for a table that lives in one process.

## Publishing

`Contract\JournalPublisher` is told of each entry right after the store appended it: a dice tower, an overlay or a chat
bridge pushes it to the table. An entry is public — a committed roll shows its commitment, never its result —, so it
can be pushed as is; a message to a queue carries the scene and the position only, and the consumer reads the entry
from the store. `Store\NoPublisher`, the default, pushes nothing: the table reads the journal when it asks for it.
