# Storing and publishing

## The store

`Contract\SecretRollStore` keeps the journals of the scenes and the sealed rolls:

| Method | Does |
|---|---|
| `entries($scene)` | The entries of the scene's journal, by position; none for a scene without any |
| `head($scene)` | The `JournalHead` of the scene: how many entries its journal holds, the hash of the last one, whether it is closed |
| `commit($entry, $roll)` | Keeps the hidden roll sealed and appends the entry of its commitment, both or neither |
| `append($entry)` | Appends an entry that reveals a roll or closes the scene |
| `hidden($roll)` | The sealed roll of this identifier, or null |

The journal only grows: an entry is appended at the position after the last one, never changed nor removed. A store
throws `JournalConflict` when the entry does not come at that position — two writers appended at once —; the caller
reads the journal again and retries. On a database, a unique key on the scene and the position gives that guarantee,
and `commit()` writes the entry and the sealed roll in one transaction.

A roll reads the head of the journal, never its entries, so that it costs the same at the five-thousandth roll of a
scene as at the first. The store keeps the head as it appends — on a database, a row per scene updated in the
transaction of the append, or the last row read by the unique key on the scene and the position — and
`JournalHead::after()` gives the head once an entry is appended. The head serves the next entry only: the chain of
hashes and the commitments are checked on the whole journal, by `SceneJournal::problems()`, as before.

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
