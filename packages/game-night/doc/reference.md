# Reference

Every public type of the package, with its role. The types marked internal may change in any version.

## Nights

| Type | Role |
|---|---|
| `GameNights` | The service: planning, tables, publication, sign-ups, withdrawals, seats, the vote and cancellation. |
| `GameNight` | A night: when and where, its host, its tables, its sign-ups, its waiting list, its games proposed and its votes, and its version. |
| `NightStatus` | Draft, published or cancelled. |
| `Table` | A table: its seats, its game and the member who leads it. |
| `Registration` | A member signed up, with a guest or alone, confirmed or waiting, and their table. |
| `RegistrationStatus` | Confirmed or waiting. |
| `NightPlays` | The service that sends the games played to the play log. |
| `PlayedGame` | A game played at a table of a night. |

## Choosing games

| Type | Role |
|---|---|
| `Choice\GameChooser` | Suggests games for the players and the minutes. |
| `Choice\Criteria` | What a game is chosen for. |
| `Choice\Suggestion` | A game suggested, with the votes it rests on. |
| `Choice\OwnedGame` | A game a member can bring. |

## The vote

| Type | Role |
|---|---|
| `Vote\VoteResult` | The vote counted and explained. |
| `Vote\Tally` | The votes of one game and its share. |
| `Vote\VoteOutcome` | No votes, a winner or a tie. |

## Ports and stores

| Type | Role |
|---|---|
| `Contract\NightStore` | Keeps the nights at a version. |
| `Contract\NightObserver` | Told of the events of a night. |
| `Contract\ShelfReader` | Reads the games the members can bring. |
| `Contract\PlayJournal` | Receives the games played. |
| `Store\InMemoryNightStore` | The nights in memory, for tests. |

## Events

| Type | Role |
|---|---|
| `Event\NightEvent` | Every event: the identifier of the night. |
| `Event\NightPublished` | The night opened to sign-ups. |
| `Event\NightFull` | The last free seat was taken. |
| `Event\NightCancelled` | The night was cancelled. |
| `Event\SeatOffered` | A member waiting holds their places. |

## Exceptions

| Type | Role |
|---|---|
| `Exception\GameNightException` | Every exception of the library. |
| `Exception\InvalidNight` | A value out of its shape or its bounds. |
| `Exception\NightRefused` | A change the night does not allow now. |
| `Exception\NightConflict` | The night changed since it was read. |
| `Exception\NightNotFound` | No night has the identifier. |

## Internal

| Type | Role |
|---|---|
| `Internal\Identifier` | The shape of the identifiers. Internal. |
