# Reference

| Type | Role |
|---|---|
| `Bingo` | Creates games, deals and prints cards, calls squares, records and cancels gestures, claims, checks by serial or label, and closes |
| `BingoGame` | A game: pool, grid, patterns, mode, cards dealt, squares called and winners, at a version |
| `Card` | A card, a player's or printed: serial, layout, ticks and win, at a version |
| `CardDealer` | Draws a layout and a label from the server's secret; the fingerprint of a layout |
| `SerialLabel` | The label of a printed card: its serial and check code, read as typed |
| `Claim` | What the server found on a card: the cells ticked and the pattern won with |
| `TickMode` | `Activity` or `Called` |
| `Square`, `SquarePool` | A square, and the pool cards are drawn from |
| `Trigger` | The gesture that ticks a square |
| `Grid` | The size of a card and its free centre |
| `WinPattern`, `WinPatterns` | A way to win, and the ways of a game in order |
| `Activity\BingoActivityObserver` | Ticks and unticks the cards from the activity journal |
| `Contract\BingoStore` | Where the games and cards are kept |
| `Store\InMemoryBingoStore` | The store in memory |
| `Event\BingoWon`, `SquareTicked`, `SquareCalled` | See [Celebrations](celebrations.md#the-events) |
| `View\CardView`, `View\CellView` | What the card template shows |
| `View\PrintedCardView` | What the template of a printed batch shows of each card |
| `Mercure\BingoPublisher` | Pushes the calls, the wins and the ticks through Mercure |
| `Exception\BingoException`, `InvalidBingo`, `BingoRefused`, `BingoConflict` | See [Exceptions](exceptions.md) |

`Identifier` is internal.

The template `templates/card.html.twig` and the Stimulus controller `card`, in `assets/`, are described in
[Celebrations](celebrations.md); the template `templates/print.html.twig` in [Printed cards](printing.md).
