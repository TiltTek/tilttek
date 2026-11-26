# The example games

Two traditional games of pencil and paper, free of rights, written as models of a mini-game.

## Bulls and Cows

`Game\BullsAndCows`, named `bulls-and-cows`: find a code of different digits. Each guess tells how many of its digits
are in the right place — the bulls — and how many are in the code at another place — the cows.

| Part | What it is |
|---|---|
| Options | `length`, the digits of the code: 4, from 3 to 5; `attempts`, the guesses allowed: 10, from 6 to 12 |
| Move | `bulls-and-cows.guess`, `{guess: "0472"}`: as many different digits as the code; anything else is refused with `IllegalMove` |
| Outcome | The code found wins, with the guesses taken as `score`; running out of guesses ends the game with no score |
| Scoring | `ScoreOrder::LowerIsBetter` |
| Controls | `digit` on `0` to `9`, `erase` on `Backspace`, `guess` on `Enter` |
| View | The length, each guess with its bulls and cows, the guesses left, and the code once the game is over |
| Share | One row per guess, bulls then cows then misses; `taken/attempts`, `-` while the game goes on, `X` when the code was not found |

`Game\Code` reads a guess — `Code::read('0472', 4)` — and scores it against the code: `[bulls, cows]`.

## Higher or Lower

`Game\HigherOrLower`, named `higher-or-lower`: a number from 1 to 100 is shown; call whether the next is higher or
lower, ten times in a row. The eleven numbers are all different.

| Part | What it is |
|---|---|
| Move | `higher-or-lower.call`, `{call: "higher"}` or `{call: "lower"}` |
| Outcome | Ten right calls win; a wrong call ends the game. Either way, the right calls are reported as `score` |
| Scoring | `ScoreOrder::HigherIsBetter` |
| Controls | `higher` on `ArrowUp` and `h`, `lower` on `ArrowDown` and `l` |
| View | The numbers shown so far — never the next —, whether each call was right, and the calls left |
| Share | One row of ten cells: right, wrong, then not played; `right/10` |
