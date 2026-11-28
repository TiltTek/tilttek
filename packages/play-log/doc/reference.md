# Reference

Every public type of the package, with its role. The types marked internal may change in any version.

## Plays

| Type | Role |
|---|---|
| `PlayLog` | The service: record, correct, delete, confirm, decline, read and forget. |
| `Play` | A game played once: its game, the member who recorded it, its seats, its result, its date, minutes, group and place. |
| `Seat` | A member or an anonymous player, with a score, whether they won, and whether the member confirmed it. |
| `Result` | Competitive, a cooperative win, a cooperative loss, or unfinished. |
| `PlayQuery` | Which plays to read: of a member, waiting for a member, recorded by a member, or of a group, by game and period. |
| `PlayQueryKind` | Whose plays a query reads. |

## Statistics

| Type | Role |
|---|---|
| `PlayStatistics` | The service: the statistics of a member or a group, computed when asked. |
| `Statistics` | What the plays of a member or a group add up to. |
| `GameStatistics` | What the plays of one game add up to. |
| `PlayerStatistics` | A member played with: their plays and wins. |

## Ports and stores

| Type | Role |
|---|---|
| `Contract\PlayStore` | Keeps the plays at a version, and reads them by query. |
| `Contract\PlayerConsent` | Whether a member agreed beforehand to be linked to another member's plays. |
| `Contract\PlayObserver` | Told of the events of a play. |
| `Store\InMemoryPlayStore` | The plays in memory, for tests. |

## Events

| Type | Role |
|---|---|
| `Event\PlayEvent` | Every event: the play and the game. |
| `Event\PlayRecorded` | A play was recorded. |
| `Event\PlayCorrected` | A play was corrected. |
| `Event\PlayDeleted` | A play was deleted. |
| `Event\SeatLinked` | A member's seat counts for them. |
| `Event\SeatPending` | A member's seat waits for their confirmation. |
| `Event\SeatUnlinked` | A member's seat no longer counts for them. |

## Exceptions

| Type | Role |
|---|---|
| `Exception\PlayLogException` | Every exception of the library. |
| `Exception\InvalidPlay` | A value out of its shape or its bounds. |
| `Exception\PlayRefused` | A change the log does not allow. |
| `Exception\PlayNotFound` | No play has the identifier. |
| `Exception\PlayConflict` | The play changed since it was read. |

## Internal

| Type | Role |
|---|---|
| `Internal\Identifier` | The shape of the identifiers. Internal. |
| `Internal\Text` | The shape of the place. Internal. |
| `Internal\Tally` | The counts of one computation of statistics. Internal. |
