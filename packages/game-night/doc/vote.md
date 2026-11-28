# The vote

Before the night, its members vote on the games to play.

## Proposing games

`GameNights::propose()` sets the games proposed to the vote, at most 30, each once, in place of those proposed before.
Each game is in the catalogue, and not hidden there. The votes for a game no longer proposed are dropped.

## Voting

`vote()` records the vote of a member signed up — confirmed or waiting — for a game proposed: one vote per member, a
new vote replacing the earlier one. `withdrawVote()` withdraws it, and a member who withdraws their sign-up withdraws
their vote with it. Votes close when the night starts.

## The result

`result()` gives a `Vote\VoteResult`:

| Value | What it is |
|---|---|
| `votes` | How many members voted |
| `tallies` | Every game proposed, with its votes and its share of the votes in percent, rounded half up; the most votes first, then in the order they were proposed |
| `leaders` | The games with the most votes, in the order they were proposed; none without a vote |
| `outcome` | `NoVotes`, `Winner` or `Tie` |

`winner()` gives the game that won, or `null` without a vote or on a tie. A tie is shown as a tie: the host chooses
between the leaders, the library does not draw.
