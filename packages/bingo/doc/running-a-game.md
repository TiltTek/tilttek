# Running a game

## The pool, the grid and the patterns

A `SquarePool` holds 1 to 500 squares with distinct identifiers; a `Square` has a label of 1 to 200 bytes and, in a
game ticked by activity, a `Trigger`. A `Grid` is 3 to 9 cells wide, with a free centre on an odd size by default
(`new Grid(4, freeCentre: false)` for an even one); its cells are numbered row by row from 0. The pool holds at least
as many squares as a card draws: every cell but the free centre.

`WinPatterns` lists 1 to 16 patterns, checked in order: the first matched names the win.

| Pattern | Wins with |
|---|---|
| `WinPattern::row($grid)` | Any full row |
| `WinPattern::column($grid)` | Any full column |
| `WinPattern::diagonal($grid)` | Either diagonal |
| `WinPattern::fullCard($grid)` | Every cell |
| `WinPattern::fourCorners($grid)` | The four corners |
| `WinPattern::custom('cross', $grid, [[2, 7, 10, 11, 12, 13, 14, 17, 22]])` | Any of 1 to 64 sets of cells of its own |
| `WinPatterns::line($grid)` | A row, a column or a diagonal |

The free centre counts as ticked in every pattern.

## Two ways to tick

| `TickMode` | Ticked by | Won |
|---|---|---|
| `Activity`, the default | The player's gestures, recorded in the activity journal | On its own, when a gesture completes a pattern |
| `Called` | The squares the host calls, for every card | When its player claims it and the squares called complete a pattern |

## Dealing

`Bingo::deal($game, $player)` gives a player their card: dealt on the first call, the same one after. Each card has a
serial in its game, from 1, and a layout drawn from the server's secret; a serial whose layout another card of the game
has already is passed over, so no two cards of a game are the same. A game deals up to 100 000 cards; see
[Cards and their seed](cards-and-seeds.md). In a game whose squares are called, `Bingo::print()` also deals batches of
cards held by no player, to print; see [Printed cards](printing.md).

## Ticking by activity

A `Trigger` names a verb of the activity journal — exact, `talk.*` or `*` — and, when given, the one object it must be
on. `Activity\BingoActivityObserver` hands each entry of the journal to `Bingo`: a gesture ticks, on the member's cards
of the open games ticked by activity, the squares it triggers, in one change of each card, and `SquareTicked` is
dispatched for each square; a card whose pattern it completes wins. A cancelled gesture unticks what it alone ticked,
unless the card won already: a cell stays ticked while one of its gestures stands. A gesture recorded twice ticks once,
by its idempotency key.

## Calling

`Bingo::call($game, $square)` calls a square of the pool for every card of a game whose squares are called, and
dispatches `SquareCalled` the first time. A card's ticks are the squares called that it holds, computed when it is read,
so a call costs the same whatever the number of cards.

## Claims and checks

`Bingo::claim($game, $serial, $player)` is the player's claim on their own card: refused on another player's card. The
server draws the card's layout again from its seed and refuses a card that does not match it, then reads its ticks —
the squares called, or the gestures recorded — and wins the card when they complete a pattern. `Bingo::verify()` is the
same check by serial alone, for the organiser; `Bingo::verifyLabel()` checks a printed card by its label first. A card
of a closed game is checked, never won.

The answer is a `Claim`: the cells ticked, the free centre included, and the pattern the card won with, or null. A card
wins once: `BingoWon` is dispatched once, the game keeps its serial among `winners()` in order, and the card keeps the
pattern and the moment.

## Closing

`Bingo::close($game)` closes a game: it deals, calls and ticks no more, and wins no more cards.
