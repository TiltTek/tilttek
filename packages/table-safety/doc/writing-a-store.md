# Writing a store

`TableSafety` keeps its data through four contracts, under `Tilttek\TableSafety\Contract`. The library ships a memory
store of each, for tests; `tilttek/table-safety-bundle` ships a Doctrine DBAL store of each.

| Contract | Keeps | Memory store |
|---|---|---|
| `TableStore` | each table's `TableSettings` | `InMemoryTableStore` |
| `ParticipationStore` | each participant's `Participation` at each table | `InMemoryParticipationStore` |
| `SignalStore` | the `Signal`s of each table | `InMemorySignalStore` |
| `FeedbackStore` | each participant's `Feedback` of each session | `InMemoryFeedbackStore` |

What every implementation does:

- `save()` replaces the record of the same table and participant — and session, for feedback.
- `ofTable()` and `ofSession()` list the records in an order that does not tell who answered first, such as the order
  of the participants' references.
- `SignalStore::recent()` lists the signals in the order they were raised, the last ones within the limit, or those
  raised strictly after a moment; two signals of the same second keep their order.
- `SignalStore::acknowledge()` marks a signal once and returns it, or null.
- `eraseParticipant()` and `forgetParticipant()` return how many records they changed, and change nothing the second
  time.
- An anonymous signal is stored without an author.

The library's tests hold these behaviours in `tests/Unit/Store/StoresTestCase.php`: a store that passes them passes
`TableSafety`'s.
