# Privacy

The answers of the safety tools say what hurts a person. The library keeps the minimum, for their table only, and lets
nothing it summarises tell who gave which answer.

## What stays anonymous

- The agreement of a table, its pact, its check-in tally and the summary of its stars and wishes count and sort the
  answers; none carries a participant's reference.
- The summaries shown to people — the pact, the agreement's `shown()`, the stars and wishes — hold answers only in
  whole groups of a threshold of participants.
- An anonymous signal has no author anywhere: neither in the store nor in what the observer pushes.
- A named signal exists only at a table that chose named signals; an answer to a check-in is always anonymous.

## The agreement and the pact

A table's agreement and its pact are shown to its participants and its game master. At a table of two, a tally of
"dark" and "light" tells each who chose the other; when a fourth participant arrives, the change between two readings
tells what they answered. The summaries shown to people therefore hold answers only in whole groups of a threshold of
participants — 3 by default, chosen by the application through `TableSafety`'s `answersThreshold`, never by a table —,
taken in the order of their first answer:

- `Pact`: its tallies and its texts;
- `TableAgreement::shown()`, an `AgreementSummary`, and `TableAgreement::$respondents`.

Under the threshold they hold nothing and say only that there are not enough answers; past it, the answers of a group
that is not full wait for the group to fill, so that one more answer never adds the answers of one person alone.

### What protects the table applies every answer

A line or a veil someone set must protect the table even when few have answered. `TableAgreement::lines()`, `veils()`,
`boundary()`, `consent()`, `checklist()`, `themesAt()` and `preferences()` therefore apply every answer, whatever the
threshold. Safety comes first, and it has a cost:

- At a small table, or when one more participant answers, these tell the strictest answers of the few who answered: a
  game master who sees a new line on spiders after Ana answered knows the line is hers.
- An application keeps them where they protect: the content it filters with `preferences()`, and the game master who
  must steer play around a line. What it shows to every participant as the table's summary comes from `shown()`.

### What the threshold does not hide

- A participant who answered knows their own answers and can set them aside: with a threshold of 3, each of the first
  three learns the answers of the other two together, not which of them gave what.
- Answering again moves the participant to the end of the order. Past the threshold, when the last group is not full,
  a change can swap one participant's answers for another's between two readings; so can leaving the table or an
  erasure. A change by a participant counted in a whole group changes the summary by their change alone.
- A text of the pact can name its author or be recognised by its style, whatever the number of answers.
- The number of answers waiting is not shown, but an application that shows who answered tells it.

## The summary of stars and wishes

Stars and wishes are free text: in a small group, a summary of one or two answers would say who wrote them. The summary
of a session therefore holds answers only in whole groups of a threshold of participants — 3 by default, chosen by the
application through `TableSafety`'s `feedbackThreshold`, never by a table —, taken in the order they were given. Under
the threshold it holds nothing and says only that there are not enough answers; past it, the answers of a group that
is not full wait for the group to fill, so that one more answer never adds the answers of one person alone.

What the threshold does not hide:

- Free text can name its author or be recognised by its style, whatever the number of answers.
- A participant who gave feedback knows their own answers and can set them aside: with a threshold of 3, each of the
  first three learns the answers of the other two together, not which of them wrote what.
- Giving feedback again moves the participant to the end of the order. Past the threshold, when the last group is not
  full, an edit can swap one participant's answers for another's between two readings of the summary; so can an
  erasure. A table that wants none of this reads the summary once, after everyone has answered.
- The number of answers waiting is not shown, but an application that shows who gave feedback tells it.

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
