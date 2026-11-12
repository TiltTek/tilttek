# Extension points

| Point | What an application gives | Where |
|---|---|---|
| `Contract\OpeningStore` | The storage of openings, in place of the store in memory | [Writing a store](writing-a-store.md) |
| `Psr\EventDispatcher\EventDispatcherInterface` | The dispatcher `BoxOpened` goes through, to the listener that grants the surprise and may start a celebration | [Opening boxes](opening-boxes.md#granting-the-surprise) |
| `Psr\Clock\ClockInterface` | The clock the days are read from: the system's in production, a fixed one in tests | [Testing](testing.md) |

The calendars are values the application builds with `Calendar::of()`; their surprises are references the application
resolves. Every point is passed to the constructor of `AdventCalendar`, which keeps no state of its own between two
calls.

The public types and the store contract are stable within a minor version of `0.x`; a change to them comes with a new
minor version and a line in the changelog.
