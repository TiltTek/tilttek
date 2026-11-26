# Reference

Every public type of the package, with its role. The types marked internal may change in any version.

## Clocks

| Type | Role |
|---|---|
| `TurnClock` | The service: create, start, pass, check, pause, resume, vacations, adjust, stop and view. |
| `TableClock` | A clock: its time control, its players in seat order, the turn in progress and its version. |
| `PlayerClock` | One player's time: budget, what is left, turns, time spent and vacations. |
| `TimeControl` | The budget, the mode and increment, and the overtime. |
| `Mode` | Fischer, Bronstein, a delay, or the whole budget per turn. |
| `Overtime` | Counted below zero, or forfeited. |
| `Vacation` | A span during which a player's clock does not run. |
| `View\ClockView` | The clock to show the table, and when the turn runs out. |
| `View\PlayerTime` | One player's time at a moment. |

## Ports and stores

| Type | Role |
|---|---|
| `Contract\ClockStore` | Keeps the clocks at a version. |
| `Contract\ClockObserver` | Told of the events of a clock. |
| `Store\InMemoryClockStore` | The clocks in memory, for tests. |

## Events

| Type | Role |
|---|---|
| `Event\ClockEvent` | Every event: the clock. |
| `Event\PlayerEvent` | The events of one player: the clock and the player. |
| `Event\ClockStarted` | A clock was started. |
| `Event\TurnStarted` | A player's turn started. |
| `Event\TurnEnded` | A player's turn ended. |
| `Event\TimeExpired` | A player's budget ran out. |
| `Event\ClockPaused` | A clock was paused. |
| `Event\ClockResumed` | A clock runs again. |
| `Event\VacationDeclared` | A player declared a vacation. |
| `Event\TimeAdjusted` | A player's time was changed. |
| `Event\ClockStopped` | A clock was stopped. |

## Exceptions

| Type | Role |
|---|---|
| `Exception\TurnClockException` | Every exception of the library. |
| `Exception\InvalidClock` | A value out of its shape or its bounds. |
| `Exception\ClockRefused` | A change the clock does not allow. |
| `Exception\ClockNotFound` | No clock or player has the identifier. |
| `Exception\ClockConflict` | The clock changed since it was read. |

## Internal

| Type | Role |
|---|---|
| `Internal\Identifier` | The shape of an identifier. |
| `Internal\Millis` | Instants as milliseconds since the epoch. |
