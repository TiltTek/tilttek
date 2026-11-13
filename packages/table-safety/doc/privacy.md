# Privacy

The answers of the safety tools say what hurts a person. The library keeps the minimum, for their table only, and lets
nothing it summarises tell who gave which answer.

## What stays anonymous

- The agreement of a table, its pact, its check-in tally and the summary of its stars and wishes count and sort the
  answers; none carries a participant's reference.
- An anonymous signal has no author anywhere: neither in the store nor in what the observer pushes.
- A named signal exists only at a table that chose named signals; an answer to a check-in is always anonymous.

## What is kept

| Kept | Where | With the participant's reference |
|---|---|---|
| Lines and veils, checklist, session zero answers | `ParticipationStore` | yes, so that they can change them |
| Stars and wishes of each session | `FeedbackStore` | yes, so that they can change them |
| Signals | `SignalStore` | only on a named signal |

A participant who leaves a table takes their answers off it with `TableSafety::leave()`. The signals serve during play:
an application purges them after a while with `SignalStore::purge()`.

## Export and erasure

`TableSafety::export()` gives everything kept about a participant: their answers at each table, the named signals they
raised, and their stars and wishes. `TableSafety::erase()` deletes their answers and their stars and wishes, and takes
their name off the signals they raised, which stay at their table as anonymous ones; running it again changes nothing.
`tilttek/table-safety-bundle` plugs both into `phil/privacy-bundle`.
