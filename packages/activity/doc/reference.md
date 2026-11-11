# Reference

| Type | Role |
|---|---|
| `Journal` | The entry of the members' activity: record, cancel, find, read, count, erase |
| `JournalObserver` | Told of each new entry |
| `Activity` | A fact: member, verb, moment, object, quantity, context, idempotency key |
| `Verb`, `VerbPattern`, `Verbs` | A verb, a pattern of verbs, the declared verbs |
| `IdempotencyKey` | What makes a gesture count once |
| `Context` | A few named facts about a gesture |
| `Entry`, `EntryKind` | A line of the journal, recorded or cancelled, at its position |
| `Appended` | An entry and whether this call wrote it |
| `Filter` | Activities by member, verbs, object and period |
| `Totals` | A count and a sum of quantities |
| `Erasure` | What erasing a member deleted and detached |
| `Log\ActivityLog` | Where the journal is kept |
| `Log\InMemoryActivityLog` | The journal in memory |
| `Log\Reading` | The bounds of a read |
| `Projection\Projection` | A reader that keeps its own state |
| `Projection\Projector` | Catch-up, rebuild and lag of a projection |
| `Projection\Checkpoints`, `InMemoryCheckpoints` | Where each projection stopped |
| `Projection\Projections` | The projections by name |
| `Projection\LiveProjections` | The observer that keeps projections up to date |
| `Exception\ActivityException` and its five exceptions | [Exceptions](exceptions.md) |
