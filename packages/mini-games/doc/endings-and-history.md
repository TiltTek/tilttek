# Endings, records and history

## The tone of an ending

`Ending::of($outcome, $seat, $scoring, $best)` tells how a game ended for its player, in one of two tones.

| `Tone` | When | What the application does |
|---|---|---|
| `Victory` | The seat is among the winners of the outcome | It plays a celebration — the celebration recipe of its interface kit — announced in a live region and cut by a preference for reduced motion |
| `Completed` | Any other ending | A kind word and an invitation to play again another day: never a mocking message, sound or animation |

`Tone::celebrates()` is true for a victory only. The game decides what a victory is: finding the code, ten right calls
in a row.

## Records

`Ending::$score` is the score the game reported for the seat, or null. `Ending::$record` is true when that score is a
new record against `$best`, the player's best score before this game, which the application reads from its history:
the first score is always a record; an equal score never is, so an equal score never announces a record beaten.

## A history without streaks

`DailyHistory::of($scores, $order)` adds up a player's daily scores of one game: the days played, the games won, the
best score, and `distribution`, the number of games ended on each score, the best first. It refuses scores of several
games or players, or two of one day.

It counts no streak, by design: a missed day takes nothing away, and nothing in the library asks a player to come back
under threat of losing what they built. An application that shows the days played may show them over a window —
the days played this month — rather than in a row.
