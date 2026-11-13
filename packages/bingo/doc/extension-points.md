# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `Contract\BingoStore` | Where the games and their cards are kept, at a version | [Storing](storing.md) |
| `CardDealer`'s secret | The server's secret that draws every card and every check code | [Cards and their seed](cards-and-seeds.md) |
| `WinPattern::custom()` | Patterns of its own | [Running a game](running-a-game.md#the-pool-the-grid-and-the-patterns) |
| `Trigger` | The gesture of the activity journal that ticks each square | [Running a game](running-a-game.md#ticking-by-activity) |
| `Psr\EventDispatcher\EventDispatcherInterface` | Who hears of each tick, call and win | [Celebrations](celebrations.md#the-events) |
| `Psr\Clock\ClockInterface` | The time of each win | [Installation](installation.md#wiring-the-library) |
| The variable `labels` and the block `won` | The words of the card, and its celebration | [Celebrations](celebrations.md#the-card-template) |
| The variable `labels` and the block `footer` of the printed batch | The words of the printed cards | [Printed cards](printing.md#the-template) |
| `Symfony\Component\Mercure\HubInterface` and a topic prefix | Where the moves of a game are pushed | [Live updates](live-updates.md) |

Every point is passed to a constructor; none of the library's services keeps state between two calls.
