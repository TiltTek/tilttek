# Writing a mini-game

A mini-game implements `Contract\MiniGame`, which extends the `Contract\Game` of `tilttek/game-engine`: everything
the engine's guide to writing a game says of a game holds — its
definition, its setup, its moves, its outcome, its view, every method pure. A mini-game adds three methods.

| Method | What it gives |
|---|---|
| `scoring()` | A `Scoring`: which way its scores go, and the seat statistic its outcome reports the score in |
| `controls()` | Its actions and the keys that perform each one, at least one |
| `share()` | The `ShareGrid` of a table: how each step went, never what it was |

## A table of one seat

A mini-game is played alone: its definition seats one player, `minimumSeats: 1` and `maximumSeats: 1`. The daily
games open a table per player and per day, with the player's identifier as the seat ([Daily games](daily-games.md)).
A game whose phase has no turns — `TurnOrder::Free`, the default — lets its one seat play at any time.

## Scoring

`ScoreOrder::HigherIsBetter` suits a game of points or right answers; `ScoreOrder::LowerIsBetter` a game of guesses,
moves or seconds. The game declares the statistic in its definition and reports it in its outcome:

```php
statistics: [Statistic::seat('score')],
// …
return Outcome::won($state->seats, 'solved', new Statistics([], [$state->seats[0] => ['score' => 4]]));
```

An ending that has no fair score — a code never found — leaves the statistic out: it is read as null, sets no record
and counts in no ranking. `ScoreOrder` compares strictly: two equal scores beat neither, and `isNewRecord()` accepts
the first score and refuses an equal one ([Endings, records and history](endings-and-history.md)).

## Controls

A `Control` names an action — `higher`, `guess`, lowercase words joined by `-` — and the 1 to 12 keys that perform it,
as values of `KeyboardEvent.key`: `ArrowUp`, `Enter`, `7`. The application binds them, through a Stimulus controller,
to the move the action stands for, and shows them next to the buttons; every action of the game has keys, so the game
is played with the keyboard alone.

## Without animation

The view of a mini-game is plain data: what the player sees now, never a step of an animation. An application may
animate a change, but nothing in the game waits for an animation, and a player who prefers reduced motion loses
nothing: the view after a move says everything the animation would.

## Sharing

`share()` turns a table into rows of `Cell` — `Hit`, `Near`, `Miss`, `Empty` — and a summary such as `4/10`. It
tells how each step went, never what it was: not a digit, not a number, not a direction that would let another player
work out the answer. [Sharing a result](sharing.md) shows how the grid is written.

## The two example games

`Game\BullsAndCows` and `Game\HigherOrLower` are written as models: read them before writing a game of your own
([The example games](example-games.md)). Each reads its data back through one private method that checks every
field, so that a cache that gives back anything else is refused with `InvalidGame` rather than misread.
