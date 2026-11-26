# Results, corrections and withdrawals

## A result

A `Result` holds how the match ended, from the side of the participant who played first, and, for the games that
score them, the mission points of each side beside the win, as miniatures games do — both sides or neither, 0 to
1 000 000 each.

| `Outcome` | Points, by `Scoring` |
|---|---|
| `FirstWins`, `SecondWins` | A win and a loss. |
| `Draw` | A draw each. |
| `FirstForfeits`, `SecondForfeits` | A forfeit loss and a forfeit win: a loss and a win unless the scoring sets them apart. |
| `BothForfeit` | A forfeit loss each. |

A forfeit is not a meeting: the two may be paired again in a Swiss stage, and a forfeit win is left out of the `wins`
tie-breaker.

## Entered once

`Tournaments::record()` takes a result for a table, with who entered it — a participant or an organiser, by an opaque
identifier:

| What was kept | Result entered | `RecordingStatus` | What happens |
|---|---|---|---|
| Nothing | Any | `Recorded` | The result counts. |
| The same result | The same | `Unchanged` | Nothing: a result entered by both players counts once. |
| Another result | Contrary | `Conflict` | The table has no result until an organiser settles it. |
| A conflict | One of those entered | `Conflict` | Nothing more. |
| A conflict | Another one | `Conflict` | It joins the submissions of the conflict. |

`resolve()` settles a conflict with the result an organiser decides. While a conflict waits, the next round is not
prepared. A bye has no result to enter.

## Corrections

`correct()` changes the result kept for a table of the current round or of a past one. The standings of its stage are
computed again, and the `Recording` returned lists each `StandingChange`: the participant, the rank and the points
before and after. The rounds paired since stay as they were published. A correction that changes the podium returns
the `TournamentFinished` event again, with the new podium.

In a bracket, the winner of a match may have played the next one already: the correction is refused until the later
match is corrected first, so that the bracket never holds a participant who should not be there.

## Withdrawals

`withdraw()` takes a participant out of the tournament. They are not paired again, and each table of theirs published
without a result becomes a forfeit: theirs, or of both when the opponent withdrew too. They stay in the standings, at
their place, marked `withdrawn`, and never qualify for a next stage. In a bracket, a participant who withdraws before
playing loses their match by walkover, which may decide the tournament.

## The end of the tournament

The tournament is over when its last stage is: every round of a Swiss stage or of the pools played, or the final of
the bracket decided. Its podium is then kept — the first three of the standings, those who withdrew left out, or, after
a bracket, the champion, the finalist and the two losers of the semi-finals, who share the third place —, and the
`Recording` of the result, the resolution, the correction or the withdrawal that ended it carries an
`Event\TournamentFinished`: the tournament, the winner and the podium, identifiers only.

The library returns the event, and the application dispatches it, to show a celebration on the page of the tournament
or to send its notices. `key()` is the same each time the same podium is told, so that a listener does each thing once.
