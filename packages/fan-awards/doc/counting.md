# Counting and results

Every count is made by `phil/poll` when `advance()` closes a voting phase; the library only says which nominees go on.

## The nominations and the semifinal

Their ballots are counted by the count of choices, `choices` in `phil/poll`, over the nominees of the phase. The
shortlist takes the nominees placed within the size of the next phase — the category's `semifinalists` for the
semifinal, its `finalists` for the final — that received at least one vote. Nominees tied at the edge all go on, as
`phil/poll` gives tied nominees the same place: a shortlist may be longer than its size, never shorter because of a tie.
Within a place, the standing keeps the higher score first, then the order the nominees arrived in.

## The final

The final's options are its finalists, then `no-award` when the category offers it. Its ballots are counted by the
category's ranked method: `instant_runoff`, `borda`, `schulze`, or a ranked method of the application's own.

| Outcome | Winners | `noAward` |
|---|---|---|
| Nominees alone in first place | Those nominees, several when tied | false |
| "No award" alone in first place | None | true |
| "No award" tied with nominees in first place | The tied nominees | false |
| No ballot, or only abstentions | None | false |

A phase whose category offers fewer than two options takes no ballot and counts nothing: its nominees go on as they
are, and a final with one finalist and no "no award" gives that finalist the award.

## Results

`preview($edition, $category)` reads the results once the edition is counted, for the organisers alone: the
application guards who calls it. `results()` reads the same once the edition is announced, for everyone; before, it is
refused with `Refusal::NotAnnounced`, and `preview()` before the count with `Refusal::NotCounted`.

A `Results` holds the category, the winners, `noAward`, and the `PhaseCount` of each phase in order — `count($phase)`
finds one. A `PhaseCount` holds the nominees the phase offered, the `phil/poll` `Result` — its standings, scores and
places, and the rounds of an instant runoff — and the nominees it advanced. Publishing these detailed counts after the
ceremony lets every fan check how each phase went.

## Erasure

`erase($voter)` forgets the voter on every ballot they cast: the ballots stay, anonymous, so that a count made after
the erasure gives the same result as before it.
